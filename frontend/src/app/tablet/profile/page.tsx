'use client';

import Link from 'next/link';
import { useAuth } from '@/hooks/useAuth';
import { Card, CardBody, CardHeader, CardTitle } from '@/components/ui/Card';
import { MaterialIcon } from '@/components/ui/MaterialIcon';
import { NotificationToggleCard } from '@/components/notifications/NotificationToggleCard';
import { NotificationPreferencesCard } from '@/components/notifications/NotificationPreferencesCard';

/**
 * タブレットアカウント用 プロフィール / 通知設定画面。
 *
 * タブレットアカウントは現場の共有端末用に作成された軽量ユーザで、
 * これまで通知設定 UI にたどり着く動線が存在していなかった
 * (サイドバーが無く、staff/guardian の /profile API は呼べないため)。
 *
 * この画面は:
 *   - アカウント情報を読み取り専用で表示 (authStore から)
 *   - NotificationToggleCard で Web Push の購読を管理
 *   - NotificationPreferencesCard で通知カテゴリ ON/OFF を管理
 *
 * バックエンドの新規 API は不要で、既存の /api/push/* と
 * /api/notification-preferences で完結する。
 */
export default function TabletProfilePage() {
  const { user, isLoading } = useAuth('tablet');

  if (isLoading) {
    return (
      <div className="flex items-center justify-center py-12 text-sm text-[var(--neutral-foreground-3)]">
        読み込み中...
      </div>
    );
  }
  if (!user) return null;

  return (
    <div className="mx-auto max-w-3xl space-y-4">
      <div className="flex items-center justify-between">
        <h1 className="text-lg font-bold text-[var(--neutral-foreground-1)] sm:text-xl">
          プロフィール・通知設定
        </h1>
        <Link
          href="/tablet"
          className="flex items-center gap-1 text-sm text-[var(--brand-80)] hover:underline"
        >
          <MaterialIcon name="arrow_back" size={16} />
          トップへ戻る
        </Link>
      </div>

      {/* アカウント情報 (読み取り専用) */}
      <Card>
        <CardHeader>
          <CardTitle>アカウント情報</CardTitle>
        </CardHeader>
        <CardBody>
          <dl className="grid grid-cols-1 gap-3 sm:grid-cols-2">
            <div>
              <dt className="text-xs text-[var(--neutral-foreground-3)]">ユーザー名</dt>
              <dd className="mt-0.5 text-sm font-medium text-[var(--neutral-foreground-1)]">
                {user.full_name}
              </dd>
            </div>
            <div>
              <dt className="text-xs text-[var(--neutral-foreground-3)]">ログインID</dt>
              <dd className="mt-0.5 text-sm font-medium text-[var(--neutral-foreground-1)]">
                {user.username}
              </dd>
            </div>
            {user.classroom?.classroom_name && (
              <div>
                <dt className="text-xs text-[var(--neutral-foreground-3)]">教室</dt>
                <dd className="mt-0.5 text-sm font-medium text-[var(--neutral-foreground-1)]">
                  {user.classroom.classroom_name}
                </dd>
              </div>
            )}
            <div>
              <dt className="text-xs text-[var(--neutral-foreground-3)]">権限</dt>
              <dd className="mt-0.5 text-sm font-medium text-[var(--neutral-foreground-1)]">
                タブレット
              </dd>
            </div>
          </dl>
          <p className="mt-3 text-xs text-[var(--neutral-foreground-3)]">
            アカウント情報の変更は管理者にお問い合わせください。
          </p>
        </CardBody>
      </Card>

      {/* Web Push 購読カード (端末で通知を受け取れるようにする) */}
      <NotificationToggleCard />

      {/* カテゴリ別 ON/OFF (チャット・連絡帳・お知らせなど) */}
      <NotificationPreferencesCard />
    </div>
  );
}
