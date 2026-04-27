<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Agent;
use App\Models\MasterAdminAuditLog;
use App\Models\User;
use App\Policies\AgentPolicy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

/**
 * マスター管理者向け 代理店スタッフアカウント管理API。
 *
 * 代理店ユーザーは user_type='agent' + agent_id を持つ。
 * classroom_id / is_master / is_company_admin は使わない（NULL or false）。
 */
class AgentAccountController extends Controller
{
    public function __construct(private readonly AgentPolicy $policy) {}

    public function index(Request $request): JsonResponse
    {
        if ($deny = $this->requireMaster($request)) return $deny;

        $agentId = $request->integer('agent_id');
        $query = User::query()
            ->where('user_type', 'agent')
            ->with('agent:id,name,code')
            ->orderBy('agent_id')
            ->orderBy('full_name');
        if ($agentId) {
            $query->where('agent_id', $agentId);
        }

        return response()->json([
            'success' => true,
            'data' => $query->get([
                'id', 'agent_id', 'username', 'full_name', 'email',
                'is_active', 'last_login_at', 'created_at',
            ]),
        ]);
    }

    public function show(Request $request, User $user): JsonResponse
    {
        if ($deny = $this->requireMaster($request)) return $deny;
        if ($user->user_type !== 'agent') {
            return response()->json(['success' => false, 'message' => '代理店ユーザーではありません。'], 404);
        }
        $user->load('agent:id,name,code');
        return response()->json(['success' => true, 'data' => $user]);
    }

    public function store(Request $request): JsonResponse
    {
        if ($deny = $this->requireMaster($request)) return $deny;

        $validated = $request->validate([
            'agent_id' => 'required|integer|exists:agents,id',
            'username' => 'required|string|max:100|unique:users,username',
            'password' => 'required|string|min:8|max:255',
            'full_name' => 'required|string|max:200',
            'email' => 'nullable|email|max:200',
            'is_active' => 'sometimes|boolean',
        ]);

        $user = User::create([
            'agent_id' => $validated['agent_id'],
            'classroom_id' => null,
            'username' => $validated['username'],
            'password' => Hash::make($validated['password']),
            'full_name' => $validated['full_name'],
            'email' => $validated['email'] ?? null,
            'user_type' => 'agent',
            'is_master' => false,
            'is_company_admin' => false,
            'is_active' => $validated['is_active'] ?? true,
        ]);

        $this->log($request, 'create_agent_user', null, [
            'user_id' => $user->id,
            'agent_id' => $user->agent_id,
            'username' => $user->username,
        ]);

        $user->load('agent:id,name,code');
        return response()->json([
            'success' => true,
            'data' => $user->makeHidden(['password']),
            'message' => '代理店ユーザーを作成しました。',
        ], 201);
    }

    public function update(Request $request, User $user): JsonResponse
    {
        if ($deny = $this->requireMaster($request)) return $deny;
        if ($user->user_type !== 'agent') {
            return response()->json(['success' => false, 'message' => '代理店ユーザーではありません。'], 404);
        }

        $validated = $request->validate([
            'agent_id' => 'sometimes|integer|exists:agents,id',
            'username' => ['sometimes', 'string', 'max:100', Rule::unique('users', 'username')->ignore($user->id)],
            'password' => 'sometimes|nullable|string|min:8|max:255',
            'full_name' => 'sometimes|string|max:200',
            'email' => 'sometimes|nullable|email|max:200',
            'is_active' => 'sometimes|boolean',
        ]);

        $before = $user->only(['agent_id', 'username', 'full_name', 'email', 'is_active']);

        $update = collect($validated)->except(['password'])->toArray();
        if (!empty($validated['password'])) {
            $update['password'] = Hash::make($validated['password']);
        }
        $user->update($update);

        $this->log($request, 'update_agent_user', $before, $user->only(['agent_id', 'username', 'full_name', 'email', 'is_active']) + ['user_id' => $user->id]);

        $user->load('agent:id,name,code');
        return response()->json([
            'success' => true,
            'data' => $user->makeHidden(['password']),
            'message' => '代理店ユーザーを更新しました。',
        ]);
    }

    public function destroy(Request $request, User $user): JsonResponse
    {
        if ($deny = $this->requireMaster($request)) return $deny;
        if ($user->user_type !== 'agent') {
            return response()->json(['success' => false, 'message' => '代理店ユーザーではありません。'], 404);
        }

        $this->log($request, 'delete_agent_user', $user->only(['id', 'agent_id', 'username', 'full_name']), null);
        $user->delete();

        return response()->json(['success' => true, 'message' => '代理店ユーザーを削除しました。']);
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
