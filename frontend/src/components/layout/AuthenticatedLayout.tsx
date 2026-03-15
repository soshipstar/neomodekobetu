'use client';

import { useEffect, type ReactNode } from 'react';
import { useRouter } from 'next/navigation';
import { useAuthStore } from '@/stores/authStore';
import { Sidebar } from './Sidebar';
import { Header } from './Header';
import { MobileNav } from './MobileNav';
import { HelpButton } from './HelpButton';
import type { UserType } from '@/types/user';

interface AuthenticatedLayoutProps {
  children: ReactNode;
  requiredUserType?: UserType | UserType[];
}

export function AuthenticatedLayout({ children, requiredUserType }: AuthenticatedLayoutProps) {
  const router = useRouter();
  const { user, isAuthenticated, isLoading, fetchUser } = useAuthStore();

  useEffect(() => {
    fetchUser();
  }, [fetchUser]);

  useEffect(() => {
    if (!isLoading && !isAuthenticated) {
      router.push('/auth/login');
      return;
    }

    if (!isLoading && isAuthenticated && user && requiredUserType) {
      const allowed = Array.isArray(requiredUserType)
        ? requiredUserType.includes(user.user_type as UserType)
        : user.user_type === requiredUserType;

      if (!allowed) {
        router.push('/auth/login');
      }
    }
  }, [isLoading, isAuthenticated, user, requiredUserType, router]);

  if (isLoading) {
    return (
      <div className="flex min-h-screen items-center justify-center bg-[var(--neutral-background-2)]">
        <div className="flex flex-col items-center gap-3">
          <div className="h-10 w-10 rounded-lg bg-[var(--brand-80)] flex items-center justify-center">
            <span className="text-lg font-bold text-white">K</span>
          </div>
          <div className="h-5 w-5 animate-spin rounded-full border-2 border-[var(--brand-80)] border-t-transparent" />
          <p className="text-sm text-[var(--neutral-foreground-3)]">読み込み中...</p>
        </div>
      </div>
    );
  }

  if (!isAuthenticated || !user) {
    return null;
  }

  return (
    <div className="flex h-screen overflow-hidden bg-[var(--neutral-background-2)]">
      {/* Sidebar */}
      <Sidebar />

      {/* Main content area */}
      <div className="flex flex-1 flex-col overflow-hidden">
        <Header />
        <main className="flex-1 overflow-y-auto p-4 pb-20 lg:p-5 lg:pb-5">
          {children}
        </main>
      </div>

      {/* Mobile bottom navigation */}
      <MobileNav />

      {/* Floating help button */}
      <HelpButton />
    </div>
  );
}

export default AuthenticatedLayout;
