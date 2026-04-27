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
  is_company_admin: boolean;
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

export interface StudentInfo {
  id: number;
  student_name: string;
  username: string | null;
  classroom_id: number;
  classroom?: {
    id: number;
    classroom_name: string;
  };
}

export interface AuthResponse {
  success: boolean;
  data: {
    token: string;
    user: User;
    student?: StudentInfo;
    login_type?: 'student' | 'student_only';
  };
}

export interface LoginCredentials {
  username: string;
  password: string;
}

/**
 * Login with username and password
 * 生徒ログインの場合、login_typeとstudent情報をlocalStorageに保存
 */
export async function login(credentials: LoginCredentials): Promise<{ user: User; token: string; student?: StudentInfo; login_type?: string }> {
  const response = await api.post<AuthResponse>('/api/auth/login', credentials);
  const { token, user, student, login_type } = response.data.data;
  setToken(token);

  // 生徒ログインの場合、student情報とlogin_typeを保存
  if (login_type && student) {
    if (typeof window !== 'undefined') {
      localStorage.setItem('login_type', login_type);
      localStorage.setItem('student_info', JSON.stringify(student));
    }
  }

  return { user, token, student, login_type };
}

/**
 * Logout current user
 */
export async function logout(): Promise<void> {
  try {
    await api.post('/api/auth/logout');
  } finally {
    removeToken();
    if (typeof window !== 'undefined') {
      localStorage.removeItem('login_type');
      localStorage.removeItem('student_info');
    }
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
    case 'agent':
      return '/agent/dashboard';
    default:
      return '/auth/login';
  }
}
