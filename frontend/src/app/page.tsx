'use client';

import { useEffect } from 'react';
import { useRouter } from 'next/navigation';
import { useAuthStore } from '@/stores/authStore';

export default function Home() {
  const router = useRouter();
  const { isAuthenticated, isLoading, fetchUser } = useAuthStore();

  useEffect(() => {
    fetchUser();
  }, [fetchUser]);

  useEffect(() => {
    if (!isLoading) {
      if (isAuthenticated) {
        const user = useAuthStore.getState().user;
        const prefix = user?.user_type === 'guardian' ? '/guardian' : user?.user_type === 'admin' ? '/admin' : user?.user_type === 'student' ? '/student' : '/staff';
        router.replace(`${prefix}/dashboard`);
      } else {
        router.replace('/auth/login');
      }
    }
  }, [isLoading, isAuthenticated, router]);

  return (
    <div className="flex min-h-screen items-center justify-center">
      <div className="flex flex-col items-center gap-4">
        <div className="h-12 w-12 rounded-xl bg-[var(--brand-80)] flex items-center justify-center">
          <span className="text-xl font-bold text-white">K</span>
        </div>
        <div className="h-6 w-6 animate-spin rounded-full border-2 border-blue-600 border-t-transparent" />
      </div>
    </div>
  );
}
