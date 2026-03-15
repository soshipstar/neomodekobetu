import api, { setToken, removeToken } from './api';

export type UserType = 'admin' | 'staff' | 'guardian' | 'student' | 'tablet';

export interface User {
  id: number;
  classroom_id: number;
  username: string;
  full_name: string;
  email: string | null;
  user_type: UserType;
  is_master: boolean;
  is_active: boolean;
  last_login_at: string | null;
  created_at: string;
  updated_at: string;
  classroom?: {
    id: number;
    classroom_name: string;
    logo_path: string | null;
  };
}

export interface AuthResponse {
  success: boolean;
  data: {
    token: string;
    user: User;
  };
}

export interface LoginCredentials {
  username: string;
  password: string;
}

/**
 * Login with username and password
 */
export async function login(credentials: LoginCredentials): Promise<{ user: User; token: string }> {
  const response = await api.post<AuthResponse>('/api/auth/login', credentials);
  const { token, user } = response.data.data;
  setToken(token);
  return { user, token };
}

/**
 * Logout current user
 */
export async function logout(): Promise<void> {
  try {
    await api.post('/api/auth/logout');
  } finally {
    removeToken();
  }
}

/**
 * Get current authenticated user
 */
export async function getUser(): Promise<User> {
  const response = await api.get<{ success: boolean; data: User }>('/api/auth/me');
  return response.data.data;
}

/**
 * Get the dashboard path for a given user type
 */
export function getDashboardPath(userType: UserType): string {
  switch (userType) {
    case 'admin':
      return '/admin/dashboard';
    case 'staff':
      return '/staff/dashboard';
    case 'guardian':
      return '/guardian/dashboard';
    case 'student':
      return '/student/dashboard';
    case 'tablet':
      return '/tablet';
    default:
      return '/auth/login';
  }
}
