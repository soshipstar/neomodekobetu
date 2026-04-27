<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Agent;
use App\Models\Company;
use App\Models\MasterAdminAuditLog;
use App\Policies\AgentPolicy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * マスター管理者向け 代理店マスタ管理API。
 */
class AgentController extends Controller
{
    public function __construct(private readonly AgentPolicy $policy) {}

    public function index(Request $request): JsonResponse
    {
        if ($deny = $this->requireMaster($request)) return $deny;

        $agents = Agent::query()
            ->withCount('companies')
            ->orderBy('is_active', 'desc')
            ->orderBy('name')
            ->get();

        return response()->json(['success' => true, 'data' => $agents]);
    }

    public function store(Request $request): JsonResponse
    {
        if ($deny = $this->requireMaster($request)) return $deny;

        $validated = $this->validateAgent($request);
        $agent = Agent::create($validated);

        $this->log($request, null, 'create_agent', null, ['agent_id' => $agent->id, 'name' => $agent->name]);

        return response()->json(['success' => true, 'data' => $agent, 'message' => '代理店を作成しました。'], 201);
    }

    public function show(Request $request, Agent $agent): JsonResponse
    {
        $user = $request->user();
        if (!$user || !$this->policy->view($user, $agent)) {
            return response()->json(['success' => false, 'message' => '閲覧権限がありません。'], 403);
        }

        $agent->load(['companies:id,name,code,agent_id,commission_rate_override,subscription_status,custom_amount,agent_assigned_at']);
        $agent->loadCount('companies');

        return response()->json(['success' => true, 'data' => $agent]);
    }

    public function update(Request $request, Agent $agent): JsonResponse
    {
        if ($deny = $this->requireMaster($request)) return $deny;

        $validated = $this->validateAgent($request, $agent->id);
        $before = $agent->only(array_keys($validated));
        $agent->update($validated);

        $this->log($request, null, 'update_agent', $before, $agent->only(array_keys($validated)) + ['agent_id' => $agent->id]);

        return response()->json(['success' => true, 'data' => $agent, 'message' => '代理店を更新しました。']);
    }

    public function destroy(Request $request, Agent $agent): JsonResponse
    {
        if ($deny = $this->requireMaster($request)) return $deny;

        if ($agent->companies()->exists()) {
            return response()->json([
                'success' => false,
                'message' => '紐付く企業が存在するため削除できません。先に販売チャネルを直販に戻してください。',
            ], 422);
        }
        if ($agent->users()->exists()) {
            return response()->json([
                'success' => false,
                'message' => '所属する代理店ユーザーが存在するため削除できません。先に削除または非活性化してください。',
            ], 422);
        }

        $this->log($request, null, 'delete_agent', ['agent_id' => $agent->id, 'name' => $agent->name], null);
        $agent->delete();

        return response()->json(['success' => true, 'message' => '代理店を削除しました。']);
    }

    /**
     * 企業の販売チャネル（直販/代理店）を更新する。
     * agent_id=null で直販に戻す。agent_id=N でその代理店に紐付ける。
     * 紐付け時に agent_assigned_at を当日に更新する（手数料計算開始日）。
     */
    public function assignChannel(Request $request, Company $company): JsonResponse
    {
        if ($deny = $this->requireMaster($request)) return $deny;

        $validated = $request->validate([
            'agent_id' => 'nullable|integer|exists:agents,id',
            'commission_rate_override' => 'nullable|numeric|min:0|max:1',
            'agent_assigned_at' => 'nullable|date',
        ]);

        $before = $company->only(['agent_id', 'commission_rate_override', 'agent_assigned_at']);

        $update = [
            'agent_id' => $validated['agent_id'] ?? null,
            'commission_rate_override' => $validated['commission_rate_override'] ?? null,
        ];
        if (array_key_exists('agent_id', $validated) && $validated['agent_id'] !== null) {
            // 代理店紐付け開始日（明示指定がなければ今日）
            $update['agent_assigned_at'] = $validated['agent_assigned_at'] ?? now();
        } else {
            // 直販に戻す
            $update['agent_assigned_at'] = null;
        }

        $company->update($update);

        $this->log($request, $company, 'update_sales_channel', $before, $update);

        $company->refresh()->load('agent:id,name,default_commission_rate');

        return response()->json([
            'success' => true,
            'data' => [
                'agent_id' => $company->agent_id,
                'agent' => $company->agent,
                'commission_rate_override' => $company->commission_rate_override,
                'agent_assigned_at' => $company->agent_assigned_at,
                'effective_commission_rate' => $company->effectiveCommissionRate(),
            ],
            'message' => '販売チャネルを更新しました。',
        ]);
    }

    private function requireMaster(Request $request): ?JsonResponse
    {
        $user = $request->user();
        if (!$user || !$this->policy->manage($user)) {
            return response()->json([
                'success' => false,
                'message' => 'マスター管理者権限が必要です。',
            ], 403);
        }
        return null;
    }

    /**
     * Agent の入力バリデーション
     */
    private function validateAgent(Request $request, ?int $excludeId = null): array
    {
        $codeRule = 'nullable|string|max:50|unique:agents,code';
        if ($excludeId) {
            $codeRule .= ','.$excludeId;
        }

        return $request->validate([
            'name' => 'required|string|max:200',
            'code' => $codeRule,
            'contact_name' => 'nullable|string|max:200',
            'contact_email' => 'nullable|email|max:200',
            'contact_phone' => 'nullable|string|max:50',
            'address' => 'nullable|string|max:500',
            'default_commission_rate' => 'sometimes|numeric|min:0|max:1',
            'bank_info' => 'nullable|array',
            'bank_info.bank_name' => 'nullable|string|max:100',
            'bank_info.branch' => 'nullable|string|max:100',
            'bank_info.account_type' => 'nullable|string|in:普通,当座',
            'bank_info.account_number' => 'nullable|string|max:50',
            'bank_info.account_holder' => 'nullable|string|max:200',
            'contract_document_path' => 'nullable|string|max:500',
            'contract_terms' => 'nullable|string|max:5000',
            'is_active' => 'sometimes|boolean',
            'notes' => 'nullable|string|max:5000',
        ]);
    }

    private function log(Request $request, ?Company $company, string $action, ?array $before, ?array $after): void
    {
        MasterAdminAuditLog::create([
            'master_user_id' => $request->user()->id,
            'company_id' => $company?->id,
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
