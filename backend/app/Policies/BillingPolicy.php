<?php

namespace App\Policies;

use App\Models\Company;
use App\Models\User;

/**
 * 課金・契約・請求情報へのアクセス権を判定する。
 *
 * - master: 全企業の閲覧・編集が可能
 * - company_admin: 自企業のみ閲覧・支払い操作（カード変更・解約予約）
 * - admin/staff/その他: 不可
 */
class BillingPolicy
{
    /**
     * 自社の請求情報を閲覧できるか（企業管理者 or マスター）
     */
    public function view(User $user, Company $company): bool
    {
        if ($this->isMaster($user)) {
            return true;
        }
        return $this->isCompanyAdminOf($user, $company);
    }

    /**
     * 自社の支払い方法・解約予約など、企業管理者が許される操作
     */
    public function manageOwn(User $user, Company $company): bool
    {
        return $this->isCompanyAdminOf($user, $company) || $this->isMaster($user);
    }

    /**
     * マスター管理者専用の操作（価格設定・表示制御・スポット請求・強制解約）
     */
    public function manageAsMaster(User $user): bool
    {
        return $this->isMaster($user);
    }

    private function isMaster(User $user): bool
    {
        return $user->user_type === 'admin' && (bool) $user->is_master;
    }

    private function isCompanyAdminOf(User $user, Company $company): bool
    {
        // User モデルの isCompanyAdmin() / company_id アクセサ（classroom 経由で導出）を使う
        return $user->isCompanyAdmin() && $user->company_id === $company->id;
    }
}
