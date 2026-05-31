'use client';

import { useEffect } from 'react';
import { useRouter } from 'next/navigation';
import { useAuthStore } from '@/stores/authStore';
import type { UserType } from '@/types/user';

/**
 * Auth hook that wraps authStore with routing logic
 */
export function useAuth(requiredUserType?: UserType | UserType[]) {
  const router = useRouter();
  const { user, isAuthenticated, isLoading, error, login, logout, fetchUser, clearError } =
    useAuthStore();

  useEffect(() => {
    if (!isAuthenticated) {
      fetchUser();
    }
  }, [isAuthenticated, fetchUser]);

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

  const handleLogin = async (username: string, password: string, code?: string) => {
    await login({ username, password, ...(code ? { code } : {}) });
    // authStore.login() 内で既に window.location.href = getDashboardPath(...)
    // による全画面リロードが発火している。ここで重ねて router.push を呼ぶと
    // ダブルナビゲーションが発生し、iPhone Safari 等では SPA 遷移 (router.push) が
    // 全画面リロードを先取りして、user_type に合わないダッシュボード (例: tablet が
    // staff/dashboard へ) が一瞬表示された後に AuthenticatedLayout の認可で
    // /auth/login に戻されるレースが起きていた。
    //
    // 修正: authStore に redirect を一元化し、ここでは何もしない。
    // (もし authStore 側が将来 redirect しない設計に戻したら、getDashboardPath を
    //  ここで再度呼ぶ形で復活させればよい)
  };

  const handleLogout = async () => {
    await logout();
    router.push('/auth/login');
  };

  return {
    user,
    isAuthenticated,
    isLoading,
    error,
    login: handleLogin,
    logout: handleLogout,
    clearError,
  };
}
