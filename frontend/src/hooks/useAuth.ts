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

  const handleLogin = async (username: string, password: string) => {
    await login({ username, password });
    const loggedUser = useAuthStore.getState().user;
    if (loggedUser) {
      const prefix = loggedUser.user_type === 'guardian' ? '/guardian' : loggedUser.user_type === 'admin' ? '/admin' : loggedUser.user_type === 'student' ? '/student' : '/staff';
      router.push(`${prefix}/dashboard`);
    }
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
