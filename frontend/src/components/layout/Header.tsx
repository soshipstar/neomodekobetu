'use client';

import { useAuthStore } from '@/stores/authStore';
import { useUiStore } from '@/stores/uiStore';
import { useAuth } from '@/hooks/useAuth';
import { NotificationBell } from './NotificationBell';
import { Menu, LogOut } from 'lucide-react';
import { getInitials } from '@/lib/utils';

export function Header() {
  const { user } = useAuthStore();
  const { toggleSidebar } = useUiStore();
  const { logout } = useAuth();

  if (!user) return null;

  return (
    <header className="sticky top-0 z-30 flex h-12 items-center justify-between border-b border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-1)] px-4 lg:px-6">
      {/* Left side */}
      <div className="flex items-center gap-3">
        <button
          onClick={toggleSidebar}
          className="rounded-md p-1.5 text-[var(--neutral-foreground-3)] hover:bg-[var(--neutral-background-3)] hover:text-[var(--neutral-foreground-1)] transition-colors"
          aria-label="メニュー切替"
        >
          <Menu className="h-5 w-5" />
        </button>
        {user.classroom && (
          <span className="hidden text-sm font-medium text-[var(--neutral-foreground-2)] sm:block">
            {user.classroom.classroom_name}
          </span>
        )}
      </div>

      {/* Right side */}
      <div className="flex items-center gap-2">
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
            <LogOut className="h-4 w-4" />
          </button>
        </div>
      </div>
    </header>
  );
}

export default Header;
