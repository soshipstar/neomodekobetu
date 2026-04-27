<?php

namespace App\Policies;

use App\Models\Agent;
use App\Models\User;

/**
 * 代理店データへのアクセス権を判定する。
 *
 * - master: 全代理店の閲覧・作成・更新・削除が可能
 * - agent ユーザー: 自分の所属代理店のみ閲覧可能（編集不可）
 * - その他: 不可
 */
class AgentPolicy
{
    /** マスター管理者かどうか */
    public function isMaster(User $user): bool
    {
        return $user->user_type === 'admin' && (bool) $user->is_master;
    }

    /** 代理店ユーザーかどうか */
    public function isAgentUser(User $user): bool
    {
        return $user->user_type === 'agent';
    }

    /** 代理店データの閲覧権限（マスター or 自分の代理店） */
    public function view(User $user, Agent $agent): bool
    {
        if ($this->isMaster($user)) {
            return true;
        }
        return $this->isAgentUser($user) && (int) $user->agent_id === (int) $agent->id;
    }

    /** 代理店データの編集（マスターのみ） */
    public function manage(User $user): bool
    {
        return $this->isMaster($user);
    }
}
