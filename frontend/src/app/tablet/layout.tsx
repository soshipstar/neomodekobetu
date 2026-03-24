'use client';

import type { ReactNode } from 'react';
import Link from 'next/link';
import { useAuth } from '@/hooks/useAuth';

export default function TabletLayout({ children }: { children: ReactNode }) {
  const { user, logout } = useAuth();

  return (
    <div className="min-h-screen bg-[var(--neutral-background-4)]">
      <header className="flex items-center justify-between bg-white px-6 py-4 shadow-md">
        <div className="flex items-center gap-4">
          <Link href="/tablet" className="text-3xl font-bold text-[var(--neutral-foreground-1)]">
            本日の記録
          </Link>
          <span className="text-xl text-[var(--neutral-foreground-3)]">
            {user?.classroom?.classroom_name && `${user.classroom.classroom_name} | `}
            {user?.full_name}
          </span>
        </div>
        <div className="flex items-center gap-4">
          <Link
            href="/tablet"
            className="rounded-lg bg-[var(--brand-80)] px-6 py-3 text-lg font-bold text-white hover:bg-blue-700"
          >
            トップ
          </Link>
          <button
            onClick={logout}
            className="rounded-lg bg-red-500 px-6 py-3 text-lg font-bold text-white hover:bg-red-600"
          >
            ログアウト
          </button>
        </div>
      </header>
      <main className="p-4 sm:p-6">
        {children}
      </main>
    </div>
  );
}
