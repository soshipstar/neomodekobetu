<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Routing\Controller as BaseController;

abstract class Controller extends BaseController
{
    /**
     * 指定した classroom_id がリクエストユーザーのアクセス可能な事業所範囲に
     * 含まれるかを検証する。含まれない場合は 403 (AuthorizationException) を投げる。
     *
     * 認可ポリシーの統一基盤 (放デイ cross-classroom データ分離):
     *  - マスター管理者 (is_master) は全事業所アクセス可
     *  - それ以外は switchableClassroomIds() に含まれる事業所のみ
     *  - classroom_id が null のユーザーは「権限なし」として扱う
     *    (旧実装では null を「全権限」と誤解釈する箇所が複数あり、
     *     cross-classroom 漏洩の温床になっていたため、ここで一律 deny にする)
     *
     * @throws AuthorizationException
     */
    protected function authorizeClassroomId(?User $user, ?int $classroomId, string $message = 'このデータへのアクセス権限がありません。'): void
    {
        if ($user === null) {
            throw new AuthorizationException('認証が必要です。');
        }

        // マスター管理者は全事業所アクセス可
        if ($user->user_type === 'admin' && $user->is_master) {
            return;
        }

        // classroom_id を持たないユーザーは権限なし (null=全権限 の誤解釈を排除)
        if (empty($user->classroom_id)) {
            throw new AuthorizationException($message);
        }

        // 対象レコードに classroom_id が無い場合も拒否 (孤児レコードへの誤アクセス防止)
        if (empty($classroomId)) {
            throw new AuthorizationException($message);
        }

        if (! in_array((int) $classroomId, $user->switchableClassroomIds(), true)) {
            throw new AuthorizationException($message);
        }
    }

    /**
     * authorizeClassroomId の boolean 版。例外を投げずに可否だけ返す。
     * 既存の if 文ベースの認可チェックを置き換えるのに使う。
     */
    protected function canAccessClassroomId(?User $user, ?int $classroomId): bool
    {
        try {
            $this->authorizeClassroomId($user, $classroomId);
            return true;
        } catch (AuthorizationException) {
            return false;
        }
    }
}
