<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Agent;
use App\Models\AgentPayout;
use App\Models\MasterAdminAuditLog;
use App\Policies\AgentPolicy;
use App\Services\Billing\AgentPayoutCalculator;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Stripe\StripeClient;

/**
 * マスター管理者向け 代理店手数料の集計と支払い管理API。
 */
class AgentPayoutController extends Controller
{
    public function __construct(private readonly AgentPolicy $policy) {}

    /**
     * 代理店手数料の一覧（フィルタ可：?status= / ?agent_id= / ?period=YYYY-MM）。
     */
    public function index(Request $request): JsonResponse
    {
        if ($deny = $this->requireMaster($request)) return $deny;

        $query = AgentPayout::query()->with('agent:id,name');

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }
        if ($agentId = $request->integer('agent_id')) {
            $query->where('agent_id', $agentId);
        }
        if ($period = $request->input('period')) {
            // YYYY-MM 形式 → period_start = その月の1日
            try {
                $start = CarbonImmutable::createFromFormat('Y-m', $period)->startOfMonth()->toDateString();
                $query->where('period_start', $start);
            } catch (\Throwable) {
                // ignore
            }
        }

        $payouts = $query->orderByDesc('period_start')
            ->orderBy('agent_id')
            ->limit(200)
            ->get();

        return response()->json(['success' => true, 'data' => $payouts]);
    }

    /**
     * 月次集計を実行（または再計算）して draft レコードを作成する。
     * - period: YYYY-MM 形式
     * - agent_id: 省略時は全代理店を集計
     */
    public function calculate(Request $request): JsonResponse
    {
        if ($deny = $this->requireMaster($request)) return $deny;

        $validated = $request->validate([
            'period' => 'required|date_format:Y-m',
            'agent_id' => 'nullable|integer|exists:agents,id',
        ]);

        $month = CarbonImmutable::createFromFormat('Y-m', $validated['period'])->startOfMonth();
        $calc = new AgentPayoutCalculator($this->stripe());

        $agents = $validated['agent_id'] ?? null
            ? Agent::where('id', $validated['agent_id'])->get()
            : Agent::where('is_active', true)->get();

        $results = [];
        foreach ($agents as $agent) {
            $payout = $calc->calculateMonth($agent, $month);
            if ($payout) {
                $results[] = $payout;
            }
        }

        $this->log($request, 'calculate_payouts', null, [
            'period' => $validated['period'],
            'agent_count' => count($results),
        ]);

        return response()->json([
            'success' => true,
            'data' => $results,
            'message' => count($results).'件の集計を実行しました。',
        ]);
    }

    /**
     * 集計結果を確定（draft → finalized）。
     */
    public function finalize(Request $request, AgentPayout $payout): JsonResponse
    {
        if ($deny = $this->requireMaster($request)) return $deny;

        if ($payout->status !== AgentPayout::STATUS_DRAFT) {
            return response()->json(['success' => false, 'message' => 'draft 状態のレコードのみ確定できます。'], 422);
        }

        $payout->update(['status' => AgentPayout::STATUS_FINALIZED]);
        $this->log($request, 'finalize_payout', ['payout_id' => $payout->id, 'status' => 'draft'], ['status' => 'finalized']);

        return response()->json(['success' => true, 'data' => $payout, 'message' => '集計を確定しました。']);
    }

    /**
     * 支払い済みマーク（finalized → paid）。
     */
    public function markPaid(Request $request, AgentPayout $payout): JsonResponse
    {
        if ($deny = $this->requireMaster($request)) return $deny;

        $validated = $request->validate([
            'paid_at' => 'sometimes|date',
            'transaction_ref' => 'nullable|string|max:200',
            'notes' => 'nullable|string|max:1000',
        ]);

        if ($payout->status !== AgentPayout::STATUS_FINALIZED) {
            return response()->json(['success' => false, 'message' => 'finalized 状態のレコードのみ支払い済みにできます。'], 422);
        }

        $payout->update([
            'status' => AgentPayout::STATUS_PAID,
            'paid_at' => $validated['paid_at'] ?? now(),
            'transaction_ref' => $validated['transaction_ref'] ?? null,
            'notes' => $validated['notes'] ?? $payout->notes,
        ]);

        $this->log($request, 'mark_payout_paid', ['payout_id' => $payout->id], $payout->only(['paid_at', 'transaction_ref']));

        return response()->json(['success' => true, 'data' => $payout, 'message' => '支払い済みとしてマークしました。']);
    }

    /**
     * 集計の取消（誤集計のリセット）。
     */
    public function cancel(Request $request, AgentPayout $payout): JsonResponse
    {
        if ($deny = $this->requireMaster($request)) return $deny;

        if ($payout->status === AgentPayout::STATUS_PAID) {
            return response()->json(['success' => false, 'message' => '支払い済みの集計は取消できません。'], 422);
        }

        $before = ['status' => $payout->status];
        $payout->update(['status' => AgentPayout::STATUS_CANCELED]);
        $this->log($request, 'cancel_payout', $before, ['status' => 'canceled', 'payout_id' => $payout->id]);

        return response()->json(['success' => true, 'data' => $payout, 'message' => '集計を取消しました。']);
    }

    /**
     * 手数料一覧を CSV でダウンロード（経理取込み用）。
     * フィルタ: ?status= / ?agent_id= / ?period=YYYY-MM
     * UTF-8 BOM付きで Excel での文字化けを防ぐ。
     */
    public function export(Request $request): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        if ($deny = $this->requireMaster($request)) {
            // CSV エンドポイントだが権限エラー時も JSON を返さず適切なステータスで戻す
            return response()->stream(
                function () { echo '権限がありません'; },
                403,
                ['Content-Type' => 'text/plain; charset=UTF-8']
            );
        }

        $query = AgentPayout::query()->with('agent:id,name,code');

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }
        if ($agentId = $request->integer('agent_id')) {
            $query->where('agent_id', $agentId);
        }
        if ($period = $request->input('period')) {
            try {
                $start = CarbonImmutable::createFromFormat('Y-m', $period)->startOfMonth()->toDateString();
                $query->where('period_start', $start);
            } catch (\Throwable) {
                // ignore
            }
        }

        $payouts = $query->orderByDesc('period_start')->orderBy('agent_id')->get();

        $filename = 'agent-payouts_'.now()->format('Ymd_His').'.csv';

        return response()->streamDownload(function () use ($payouts) {
            $out = fopen('php://output', 'w');
            // UTF-8 BOM (Excelで日本語が文字化けしないよう)
            fwrite($out, "\xEF\xBB\xBF");

            fputcsv($out, [
                '代理店ID', '代理店名', '代理店コード',
                '対象期間開始', '対象期間終了', '支払期日',
                '売上総額', 'Stripe手数料', '利益',
                '手数料率', '手数料額',
                '状態', '支払日', '振込番号', 'メモ',
                '集計確定日(updated_at)',
            ]);

            $statusLabel = [
                AgentPayout::STATUS_DRAFT => '集計中(draft)',
                AgentPayout::STATUS_FINALIZED => '確定(未払)',
                AgentPayout::STATUS_PAID => '支払済',
                AgentPayout::STATUS_CANCELED => '取消',
            ];

            foreach ($payouts as $p) {
                fputcsv($out, [
                    $p->agent_id,
                    $p->agent?->name ?? '',
                    $p->agent?->code ?? '',
                    $p->period_start?->format('Y-m-d') ?? '',
                    $p->period_end?->format('Y-m-d') ?? '',
                    $p->due_date?->format('Y-m-d') ?? '',
                    $p->gross_revenue,
                    $p->stripe_fees,
                    $p->net_profit,
                    number_format((float) $p->commission_rate, 4, '.', ''),
                    $p->commission_amount,
                    $statusLabel[$p->status] ?? $p->status,
                    $p->paid_at?->format('Y-m-d') ?? '',
                    $p->transaction_ref ?? '',
                    $p->notes ?? '',
                    $p->updated_at?->format('Y-m-d H:i:s') ?? '',
                ]);
            }

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function requireMaster(Request $request): ?JsonResponse
    {
        $user = $request->user();
        if (!$user || !$this->policy->isMaster($user)) {
            return response()->json([
                'success' => false,
                'message' => 'マスター管理者権限が必要です。',
            ], 403);
        }
        return null;
    }

    private function stripe(): StripeClient
    {
        return new StripeClient(config('cashier.secret'));
    }

    private function log(Request $request, string $action, ?array $before, ?array $after): void
    {
        MasterAdminAuditLog::create([
            'master_user_id' => $request->user()->id,
            'company_id' => null,
            'action' => $action,
            'before' => $before,
            'after' => $after,
            'context' => [
                'ip' => $request->ip(),
                'user_agent' => mb_substr($request->userAgent() ?? '', 0, 500),
            ],
        ]);
    }
}
