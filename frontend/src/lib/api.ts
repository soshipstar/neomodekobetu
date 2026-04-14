import axios, { AxiosError, InternalAxiosRequestConfig } from 'axios';

// Backend base URL
const BACKEND_URL = process.env.NEXT_PUBLIC_BACKEND_URL || process.env.NEXT_PUBLIC_API_URL?.replace(/\/api$/, '') || 'http://localhost:8000';

const api = axios.create({
  baseURL: BACKEND_URL,
  headers: {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
  },
});

/**
 * Get stored auth token
 */
function getToken(): string | null {
  if (typeof window === 'undefined') return null;
  return localStorage.getItem('auth_token');
}

/**
 * Save auth token
 */
export function setToken(token: string): void {
  if (typeof window !== 'undefined') {
    localStorage.setItem('auth_token', token);
  }
}

/**
 * Remove auth token
 */
export function removeToken(): void {
  if (typeof window !== 'undefined') {
    localStorage.removeItem('auth_token');
  }
}

// Request interceptor: attach Bearer token & fix Content-Type for FormData
api.interceptors.request.use((config: InternalAxiosRequestConfig) => {
  const token = getToken();
  if (token) {
    config.headers['Authorization'] = `Bearer ${token}`;
  }
  // FormData の場合は Content-Type を削除して axios に自動設定させる
  if (config.data instanceof FormData) {
    delete config.headers['Content-Type'];
  }
  return config;
});

// Response interceptor: handle 401 (redirect to login)
// 一時的なサーバーエラー(503等)やレート制限(429)ではログアウトしない
let consecutive401Count = 0;
api.interceptors.response.use(
  (response) => {
    consecutive401Count = 0; // 成功したらカウントリセット
    return response;
  },
  async (error: AxiosError) => {
    const status = error.response?.status;
    if (status === 401) {
      consecutive401Count++;
      // 連続2回以上の401で初めてログアウト（一時的な503→401を除外）
      if (consecutive401Count >= 2) {
        removeToken();
        if (typeof window !== 'undefined') {
          localStorage.removeItem('login_type');
          localStorage.removeItem('student_info');
          if (!window.location.pathname.includes('/auth/login')) {
            window.location.href = '/auth/login';
          }
        }
      }
    } else if (status !== 429 && status !== 503) {
      consecutive401Count = 0;
    }
    // 429, 503 は consecutive401Count をリセットしない（誤ログアウト防止）
    return Promise.reject(error);
  }
);

export default api;
