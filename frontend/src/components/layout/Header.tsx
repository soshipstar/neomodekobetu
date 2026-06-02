'use client';

import { useAuthStore } from '@/stores/authStore';
import { useUiStore } from '@/stores/uiStore';
import { useAuth } from '@/hooks/useAuth';
import { NotificationBell } from './NotificationBell';
import { BillingSystemLink } from './BillingSystemLink';
import { ClassroomSwitcher } from './ClassroomSwitcher';
import { MaterialIcon } from '@/components/ui/MaterialIcon';
import { getInitials } from '@/lib/utils';

export function Header() {
  const { user } = useAuthStore();
  const { toggleSidebar } = useUiStore();
  const { logout } = useAuth();

  if (!user) return null;

  return (
    // iOS の viewport-fit=cover (layout.tsx で指定) によりページは
    // iPhone のステータスバー / ノッチの背後まで描画される。
    // 何もしないとヘッダがステータスバーに重なって h-12 (48px) の大半が
    // 隠れ、メニューボタン・通知ベル・ユーザー名が見えなくなる
    // (バグ報告 #63 の根本原因)。
    // paddingTop に env(safe-area-inset-top) を加算し、ヘッダ全体を
    // 安全領域の下に押し下げる。height は固定の代わりに minHeight に切替え。
    <header
      className="sticky top-0 z-30 flex min-h-12 items-center justify-between border-b border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-1)] px-4 lg:px-6"
      style={{
        paddingTop: 'env(safe-area-inset-top, 0)',
        paddingLeft: 'max(1rem, env(safe-area-inset-left, 0))',
        paddingRight: 'max(1rem, env(safe-area-inset-right, 0))',
      }}
    >
      {/* Left side */}
      <div className="flex items-center gap-3">
        <button
          onClick={toggleSidebar}
          className="rounded-md p-1.5 text-[var(--neutral-foreground-3)] hover:bg-[var(--neutral-background-3)] hover:text-[var(--neutral-foreground-1)] transition-colors"
          aria-label="メニュー切替"
        >
          <MaterialIcon name="menu" size={20} />
        </button>

        {/* 教室切替: lg 以上のみヘッダに表示。スマホ (< lg) ではサイドバーに移動
            (R4-bis: スマホヘッダ内ではメニューボタン等とタップ領域が被って
            押せなかったため、ドロワーメニュー内に集約する) */}
        <div className="hidden lg:block">
          <ClassroomSwitcher variant="header" />
        </div>

        {/* 1教室ユーザー向けの教室名表示 (デスクトップのみ、ClassroomSwitcher が null を返すケース) */}
        {user.classroom && (
          <span className="hidden text-sm font-medium text-[var(--neutral-foreground-2)] lg:hidden xl:block">
            {user.classroom.classroom_name}
          </span>
        )}
      </div>

      {/* Right side */}
      <div className="flex items-center gap-2">
        {/* 国保連請求システム (account.kiduri.xyz) へ SSO 遷移。職員・管理者のみ表示 */}
        <BillingSystemLink />
        <NotificationBell />

        {/* User menu */}
        <div className="flex items-center gap-2">
          <div className="hidden text-right sm:block">
            <p className="text-sm font-medium text-[var(--neutral-foreground-1)]">{user.full_name}</p>
          </div>
          <div className="flex h-8 w-8 items-center justify-center rounded-full bg-[var(--brand-160)] text-xs font-semibold text-[var(--brand-60)]">
            {getInitials(user.full_name)}
          </div>
          <button
            onClick={logout}
            className="rounded-md p-1.5 text-[var(--neutral-foreground-4)] hover:bg-[var(--neutral-background-3)] hover:text-[var(--status-danger-fg)] transition-colors"
            title="ログアウト"
          >
            <MaterialIcon name="logout" size={18} />
          </button>
        </div>
      </div>
    </header>
  );
}

export default Header;
