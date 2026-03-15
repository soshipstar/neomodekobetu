import { create } from 'zustand';
import { User, login as apiLogin, logout as apiLogout, getUser, getDashboardPath, LoginCredentials } from '@/lib/auth';

interface AuthState {
  user: User | null;
  isAuthenticated: boolean;
  isLoading: boolean;
  error: string | null;

  login: (credentials: LoginCredentials) => Promise<void>;
  logout: () => Promise<void>;
  fetchUser: () => Promise<void>;
  clearError: () => void;
}

export const useAuthStore = create<AuthState>((set) => ({
  user: null,
  isAuthenticated: false,
  isLoading: true,
  error: null,

  login: async (credentials: LoginCredentials) => {
    set({ isLoading: true, error: null });
    try {
      const { user } = await apiLogin(credentials);
      set({
        user,
        isAuthenticated: true,
        isLoading: false,
        error: null,
      });
      // Redirect to dashboard
      if (typeof window !== 'undefined') {
        window.location.href = getDashboardPath(user.user_type);
      }
    } catch (err: unknown) {
      const message =
        (err as { response?: { data?: { message?: string } } })?.response?.data?.message ||
        'ログインに失敗しました';
      set({ isLoading: false, error: message });
      throw err;
    }
  },

  logout: async () => {
    try {
      await apiLogout();
    } finally {
      set({
        user: null,
        isAuthenticated: false,
        isLoading: false,
        error: null,
      });
      if (typeof window !== 'undefined') {
        window.location.href = '/auth/login';
      }
    }
  },

  fetchUser: async () => {
    set({ isLoading: true });
    try {
      const user = await getUser();
      set({
        user,
        isAuthenticated: true,
        isLoading: false,
      });
    } catch {
      set({
        user: null,
        isAuthenticated: false,
        isLoading: false,
      });
    }
  },

  clearError: () => set({ error: null }),
}));
