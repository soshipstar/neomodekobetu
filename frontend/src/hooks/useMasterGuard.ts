'use client';

import { useEffect } from 'react';
import { useRouter } from 'next/navigation';
import { useAuthStore } from '@/stores/authStore';

/**
 * マスター管理者専用ページのガード
 * 通常管理者がアクセスした場合、管理者ダッシュボードにリダイレクトする
 */
export function useMasterGuard(): { isMaster: boolean; isReady: boolean } {
  const router = useRouter();
  const { user, isAuthenticated, isLoading } = useAuthStore();

  const isMaster = !!user && user.user_type === 'admin' && !!user.is_master;
  const isReady = !isLoading && isAuthenticated && !!user;

  useEffect(() => {
    if (isReady && !isMaster) {
      router.replace('/admin/dashboard');
    }
  }, [isReady, isMaster, router]);

  return { isMaster, isReady };
}
