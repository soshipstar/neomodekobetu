<?php

namespace App\Http\Controllers\Agent;

use App\Http\Controllers\Controller;
use App\Models\Agent;
use App\Models\AgentPayout;
use App\Models\Company;
use App\Models\Invoice;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * 代理店ユーザー向けの閲覧API。
 *
 * すべて自代理店スコープ（user.agent_id）に閉じる。マスターは全代理店を見る用に
 * クエリ ?agent_id= を許容する。
 */
class AgentDashboardController extends Controller
{
    /**
     * ダッシュボード: 紹介企業数、当月の見込み手数料、未払い手数料、最新の支払い。
     */
    public function dashboard(Request $request): JsonResponse
    {
        $agent = $this->resolveAgent($request);
        if ($agent instanceof JsonResponse) return $agent;

        $now = CarbonImmutable::now();
        $start = $now->startOfMonth();
        $end = $now->endOfMonth();

        $companyIds = Company::where('agent_id', $agent->id)->pluck('id');
        $companyCount = $companyIds->count();

        // 当月支払い済みの売上合計（見込み手数料の元になる）
        $mtdInvoices = Invoice::whereIn('company_id', $companyIds)
            ->where('status', 'paid')
            ->whereBetween('paid_at', [$start, $end])
            ->get(['id', 'company_id', 'amount_paid', 'paid_at']);

        $companies = Company::whereIn('id', $companyIds)
            ->get(['id', 'agent_assigned_at', 'commission_rate_override'])
            ->keyBy('id');

        // 簡易見込み手数料（Stripe手数料は集計時にしか分からないので gross * rate）
        $estimatedCommission = 0;
        $applicableRevenue = 0;
        foreach ($mtdInvoices as $inv) {
            $c = $companies->get($inv->company_id);
            if (!$c || !$c->agent_assigned_at || $inv->paid_at < $c->agent_assigned_at) {
                continue;
            }
            $rate = $c->commission_rate_override !== null
                ? (float) $c->commission_rate_override
                : (float) $agent->default_commission_rate;
            $applicableRevenue += (int) $inv->amount_paid;
            $estimatedCommission += (int) round($inv->amount_paid * $rate);
        }

        // 未払い手数料（finalized で未paid）
        $unpaid = AgentPayout::where('agent_id', $agent->id)
            ->where('status', AgentPayout::STATUS_FINALIZED)
            ->sum('commission_amount');

        // 直近の支払い実績
        $latest = AgentPayout::where('agent_id', $agent->id)
            ->where('status', AgentPayout::STATUS_PAID)
            ->orderByDesc('paid_at')
            ->limit(3)
            ->get(['id', 'period_start', 'period_end', 'commission_amount', 'paid_at']);

        return response()->json([
            'success' => true,
            'data' => [
                'agent' => $agent->only(['id', 'name', 'code', 'default_commission_rate']),
                'company_count' => $companyCount,
                'current_month' => [
                    'period_label' => $now->format('Y年n月'),
                    'applicable_revenue' => $applicableRevenue,
                    'estimated_commission' => $estimatedCommission,
                    'invoice_count' => $mtdInvoices->count(),
                ],
                'unpaid_total' => (int) $unpaid,
                'recent_paid_payouts' => $latest,
            ],
        ]);
    }

    /**
     * 紹介企業の一覧（自代理店分のみ）。
     * 7. 企業の住所・連絡先も閲覧可（問題発生時は代理店経由で連絡）。
     */
    public function companies(Request $request): JsonResponse
    {
        $agent = $this->resolveAgent($request);
        if ($agent instanceof JsonResponse) return $agent;

        $companies = Company::where('agent_id', $agent->id)
            ->select([
                'id', 'name', 'code', 'agent_id', 'agent_assigned_at',
                'commission_rate_override',
                'subscription_status', 'custom_amount', 'tax_inclusive',
                'current_period_end', 'cancel_at_period_end',
                'stripe_id', 'is_active',
            ])
            ->orderBy('name')
            ->get();

        // Stripe Customer 情報（住所・連絡先）も含めて返す
        $stripe = new \Stripe\StripeClient(config('cashier.secret'));
        $companies = $companies->map(function (Company $c) use ($agent, $stripe) {
            $row = $c->toArray();
            $row['effective_commission_rate'] = $c->effectiveCommissionRate();
            $row['default_commission_rate'] = (float) $agent->default_commission_rate;
            if ($c->stripe_id) {
                try {
                    $cust = $stripe->customers->retrieve($c->stripe_id);
                    $row['contact'] = [
                        'name' => $cust->name,
                        'email' => $cust->email,
                        'phone' => $cust->phone,
                        'address' => $cust->address ? $cust->address->toArray() : null,
                    ];
                } catch (\Throwable) {
                    $row['contact'] = null;
                }
            } else {
                $row['contact'] = null;
            }
            return $row;
        });

        return response()->json(['success' => true, 'data' => $companies]);
    }

    /**
     * 自分への支払い履歴（payouts）。
     */
    public function payouts(Request $request): JsonResponse
    {
        $agent = $this->resolveAgent($request);
        if ($agent instanceof JsonResponse) return $agent;

        $payouts = AgentPayout::where('agent_id', $agent->id)
            ->orderByDesc('period_start')
            ->limit(60)
            ->get();

        return response()->json(['success' => true, 'data' => $payouts]);
    }

    /**
     * 自代理店契約書PDFをダウンロード（代理店ユーザーは自分のみ、マスターは ?agent_id= で他社）。
     * 中身はそのままバイナリ配信、ファイル名はASCIIサニタイズ済みなので文字化けしない。
     */
    public function downloadContract(Request $request): \Symfony\Component\HttpFoundation\StreamedResponse|JsonResponse
    {
        $agent = $this->resolveAgent($request);
        if ($agent instanceof JsonResponse) return $agent;

        if (!$agent->contract_document_path) {
            return response()->json(['success' => false, 'message' => '契約書が登録されていません。'], 404);
        }
        if (!\Illuminate\Support\Facades\Storage::disk('local')->exists($agent->contract_document_path)) {
            return response()->json(['success' => false, 'message' => 'ファイルが見つかりません。'], 404);
        }

        $downloadName = sprintf('agent-contract_%s.pdf', preg_replace('/[^A-Za-z0-9_\-]/u', '_', $agent->name ?? 'agent'));
        return \Illuminate\Support\Facades\Storage::disk('local')->download(
            $agent->contract_document_path,
            $downloadName,
            ['Content-Type' => 'application/pdf']
        );
    }

    /**
     * 自代理店プロフィール（連絡先・契約条件など）。
     */
    public function profile(Request $request): JsonResponse
    {
        $agent = $this->resolveAgent($request);
        if ($agent instanceof JsonResponse) return $agent;

        return response()->json([
            'success' => true,
            'data' => $agent->only([
                'id', 'name', 'code', 'contact_name', 'contact_email', 'contact_phone',
                'address', 'default_commission_rate', 'contract_terms',
                'contract_document_path', 'is_active',
            ]),
        ]);
    }

    /**
     * リクエストユーザーから対象の Agent を解決する。
     * - agent ユーザー: 自分の agent
     * - master ユーザー: ?agent_id= で任意の agent
     */
    private function resolveAgent(Request $request): Agent|JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['success' => false, 'message' => '認証が必要です。'], 401);
        }

        $isMaster = $user->user_type === 'admin' && (bool) $user->is_master;
        $isAgent = $user->user_type === 'agent';

        if (!$isMaster && !$isAgent) {
            return response()->json(['success' => false, 'message' => '代理店リソースへのアクセス権がありません。'], 403);
        }

        if ($isMaster && $request->filled('agent_id')) {
            $agent = Agent::find($request->integer('agent_id'));
            return $agent ?: response()->json(['success' => false, 'message' => '代理店が見つかりません。'], 404);
        }

        if (!$user->agent_id) {
            return response()->json(['success' => false, 'message' => '所属代理店が設定されていません。'], 422);
        }

        $agent = Agent::find($user->agent_id);
        return $agent ?: response()->json(['success' => false, 'message' => '所属代理店が見つかりません。'], 404);
    }
}
