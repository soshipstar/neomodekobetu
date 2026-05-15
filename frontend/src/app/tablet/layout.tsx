'use client';

import type { ReactNode } from 'react';
import Link from 'next/link';
import { useAuth } from '@/hooks/useAuth';
import { MaterialIcon } from '@/components/ui/MaterialIcon';
import { ClassroomSwitcher } from '@/components/layout/ClassroomSwitcher';

export default function TabletLayout({ children }: { children: ReactNode }) {
  const { user, logout } = useAuth();

  return (
    <div className="min-h-screen bg-[var(--neutral-background-4)]">
      {/* メインヘッダ。右側 padding は env(safe-area-inset-right) を考慮して、
          iPhone のホームインジケータ / ノッチ向けの安全領域でクリッピングしないようにする。 */}
      <header
        className="flex items-center justify-between gap-2 bg-white shadow-md sm:gap-4"
        style={{
          // iPhone のステータスバー (時刻・電波・電池) と被らないよう
          // safe-area-inset-top を確保。最小 0.5rem (8px) は通常のヘッダ余白。
          paddingTop: 'max(0.5rem, env(safe-area-inset-top))',
          paddingBottom: '0.5rem',
          paddingLeft: 'max(1rem, env(safe-area-inset-left))',
          paddingRight: 'max(1rem, env(safe-area-inset-right))',
        }}
      >
        {/* Left: タイトル + (デスクトップでは) 教室名・ユーザー名 */}
        <div className="flex min-w-0 flex-1 items-center gap-2 sm:gap-3">
          <Link
            href="/tablet"
            className="shrink-0 text-lg font-bold text-[var(--neutral-foreground-1)] sm:text-xl lg:text-2xl"
          >
            本日の記録
          </Link>
          {/* 教室名・ユーザー名: 狭幅では非表示 (サブヘッダで表示)、md 以上で表示 */}
          {user && (
            <span className="hidden min-w-0 truncate text-sm text-[var(--neutral-foreground-3)] md:inline lg:text-base">
              {user.classroom?.classroom_name && `${user.classroom.classroom_name} | `}
              {user.full_name}
            </span>
          )}
        </div>

        {/* Right: アイコンボタン群 */}
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
          {/*
            通知設定への導線。タブレットアカウントは元々サイドバーが無いため、
            プロフィール / 通知設定の入口がヘッダにしか作れない。
            PWA インストール後にここから「通知を有効にする」までたどり着く動線。
          */}
          <Link
            href="/tablet/profile"
            className="flex items-center gap-1.5 rounded-lg bg-[var(--brand-80)] px-2.5 py-2 text-sm font-bold text-white hover:bg-blue-700 sm:px-4 sm:py-2.5 sm:text-base"
            aria-label="通知設定"
            title="通知設定"
          >
            <MaterialIcon name="notifications" size={20} />
            <span className="hidden sm:inline">通知</span>
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

      {/* サブヘッダ: 狭幅では教室名・ユーザー名 + 教室切替を表示。
          複数教室を持つタブレットアカウントは事業所をまたぐ運用が可能 (R4-bis tablet)。
          ClassroomSwitcher は 1 教室しか持たないユーザーには何も描画しない。 */}
      {user && (
        <div
          className="border-b border-[var(--neutral-stroke-2)] bg-white"
          style={{
            paddingLeft: 'max(1rem, env(safe-area-inset-left))',
            paddingRight: 'max(1rem, env(safe-area-inset-right))',
          }}
        >
          <div className="flex flex-wrap items-center justify-between gap-2 py-1.5 text-xs text-[var(--neutral-foreground-3)] md:hidden">
            <span className="truncate">
              {user.classroom?.classroom_name && `${user.classroom.classroom_name} / `}
              {user.full_name}
            </span>
            <ClassroomSwitcher variant="header" />
          </div>
          {/* md 以上ではメインヘッダに既に表示済なので、教室切替だけサブヘッダで提供 */}
          <div className="hidden md:flex md:items-center md:justify-end md:py-1.5">
            <ClassroomSwitcher variant="header" />
          </div>
        </div>
      )}

      <main className="p-4 sm:p-6">
        {children}
      </main>
    </div>
  );
}
