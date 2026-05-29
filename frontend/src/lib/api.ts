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
    } else if (status && status < 500 && status !== 429) {
      // 4xx以下(429除く)のみカウントリセット
      // 5xx, 502, 503, 504等のサーバーエラーやネットワークエラーはカウントに影響しない（誤ログアウト防止）
      consecutive401Count = 0;
    }
    return Promise.reject(error);
  }
);

export default api;

/**
 * API レスポンスのエラーを人間が読みやすいトースト用文字列に整形する。
 *
 * 旧実装: 各コンポーネントで `onError: () => toast.error('保存に失敗しました')`
 *   のように一律メッセージを出していて、422 のバリデーション内容や 502 の
 *   接続断などが画面に出ず原因が分からない問題があった。
 *
 * 優先順位:
 *  1. Laravel の 422 `errors` (フィールド別エラー) を最大 3 行で展開
 *  2. レスポンス body の `message` をそのまま表示
 *  3. HTTP ステータスから一般的な原因を推測
 *      - 502 / 503: 「サーバーに接続できません」
 *      - 401     : 「認証が切れました」
 *      - 403     : 「権限がありません」
 *      - その他 : fallback メッセージ
 *
 * @param err   useMutation の onError などで渡される未知エラー
 * @param fallback ステータス不明 / 解釈不能時の既定メッセージ
 */
export function formatApiError(err: unknown, fallback = '操作に失敗しました'): string {
  if (axios.isAxiosError(err)) {
    const status = err.response?.status;
    const data = err.response?.data as
      | { message?: string; errors?: Record<string, string | string[]> }
      | undefined;

    // 422 のフィールド別バリデーションエラー
    if (data?.errors && typeof data.errors === 'object') {
      const lines: string[] = [];
      for (const [field, msgs] of Object.entries(data.errors)) {
        const firstMsg = Array.isArray(msgs) ? msgs[0] : msgs;
        if (firstMsg) lines.push(`${field}: ${firstMsg}`);
        if (lines.length >= 3) break;
      }
      const head = data.message ? `${data.message}\n` : '';
      return `${head}${lines.join('\n')}`.trim();
    }

    // 単純な message
    if (data?.message) return data.message;

    // ステータス別の一般メッセージ
    if (status === 401) return '認証が切れました。再度ログインしてください。';
    if (status === 403) return 'この操作の権限がありません。';
    if (status === 404) return '対象が見つかりませんでした。';
    if (status === 429) return 'リクエストが多すぎます。少し時間を空けてから再度お試しください。';
    if (status === 502 || status === 503 || status === 504) {
      return 'サーバーに接続できません。少し待ってから再試行してください。';
    }
    if (!err.response) return 'ネットワークエラー: サーバーに接続できませんでした。';
  }
  if (err instanceof Error && err.message) return err.message;
  return fallback;
}
