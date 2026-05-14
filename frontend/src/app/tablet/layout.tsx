'use client';

import type { ReactNode } from 'react';
import Link from 'next/link';
import { useAuth } from '@/hooks/useAuth';
import { MaterialIcon } from '@/components/ui/MaterialIcon';

export default function TabletLayout({ children }: { children: ReactNode }) {
  const { user, logout } = useAuth();

  return (
    <div className="min-h-screen bg-[var(--neutral-background-4)]">
      <header className="flex items-center justify-between gap-2 bg-white px-3 py-2 shadow-md sm:gap-4 sm:px-4 sm:py-3 lg:px-6">
        {/* Left: タイトル + (デスクトップでは) 教室名・ユーザー名 */}
        <div className="flex min-w-0 flex-1 items-center gap-2 sm:gap-3">
          <Link
            href="/tablet"
            className="shrink-0 text-lg font-bold text-[var(--neutral-foreground-1)] sm:text-xl lg:text-2xl"
          >
            本日の記録
          </Link>
          {/* 教室名・ユーザー名: 狭い画面では非表示。中サイズ以上で truncate 表示 */}
          {user && (
            <span className="hidden min-w-0 truncate text-sm text-[var(--neutral-foreground-3)] md:inline lg:text-base">
              {user.classroom?.classroom_name && `${user.classroom.classroom_name} | `}
              {user.full_name}
            </span>
          )}
        </div>

        {/* Right: アイコンボタン群 (狭い画面はアイコンのみ、広い画面はアイコン + ラベル) */}
        <nav className="flex shrink-0 items-center gap-1.5 sm:gap-2">
          <Link
            href="/tablet"
            className="flex items-center gap-1.5 rounded-lg bg-[var(--brand-80)] px-2.5 py-2 text-sm font-bold text-white hover:bg-blue-700 sm:px-4 sm:py-2.5 sm:text-base"
            aria-label="トップ"
            title="トップ"
          >
            <MaterialIcon name="home" size={20} />
            <span className="hidden sm:inline">トップ</span>
          </Link>
          <Link
            href="/tablet/photos"
            className="flex items-center gap-1.5 rounded-lg bg-[var(--brand-80)] px-2.5 py-2 text-sm font-bold text-white hover:bg-blue-700 sm:px-4 sm:py-2.5 sm:text-base"
            aria-label="写真"
            title="写真"
          >
            <MaterialIcon name="photo_library" size={20} />
            <span className="hidden sm:inline">写真</span>
          </Link>
          <button
            onClick={logout}
            className="flex items-center gap-1.5 rounded-lg bg-red-500 px-2.5 py-2 text-sm font-bold text-white hover:bg-red-600 sm:px-4 sm:py-2.5 sm:text-base"
            aria-label="ログアウト"
            title="ログアウト"
          >
            <MaterialIcon name="logout" size={20} />
            <span className="hidden sm:inline">ログアウト</span>
          </button>
        </nav>
      </header>

      {/* スマホ画面でヘッダに収まらない教室名・ユーザー名をサブヘッダで表示 */}
      {user && (
        <div className="border-b border-[var(--neutral-stroke-2)] bg-white px-3 py-1.5 text-xs text-[var(--neutral-foreground-3)] md:hidden">
          <span className="truncate">
            {user.classroom?.classroom_name && `${user.classroom.classroom_name} / `}
            {user.full_name}
          </span>
        </div>
      )}

      <main className="p-4 sm:p-6">
        {children}
      </main>
    </div>
  );
}
