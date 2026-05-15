'use client';

import Link from 'next/link';
import { useEffect, useState } from 'react';
import { useAuthStore } from '@/stores/authStore';

/**
 * PWA インストール完了後の通知許可誘導バナー。
 *
 * PWA としてホーム画面から起動した直後 (display-mode: standalone) で、
 * かつ通知許可がまだ「default」状態 (未設定) の場合に、ユーザに通知設定
 * 画面へ誘導するためのバナーを表示する。
 *
 * 表示条件 (AND):
 *   - 端末がスタンドアロン (PWA インストール済み) で起動している
 *   - Notification.permission === 'default' (まだ可否を聞かれていない)
 *   - 認証済み (auth store に user がいる)
 *   - 30日以内にユーザが「あとで」を押していない
 *   - すでに通知許可済み (granted) または拒否済み (denied) なら出さない
 *
 * 誘導先:
 *   役割に応じたプロフィール画面 (/{role}/profile) を開いてもらい、
 *   既存の NotificationToggleCard から購読してもらう動線。
 */

const DISMISS_KEY = 'kiduri-pwa-notification-followup-dismissed-until';
const DISMISS_DAYS = 30;
const INITIAL_DELAY_MS = 1500;

function isStandalone(): boolean {
  if (typeof window === 'undefined') return false;
  const win = window as Window & { navigator: Navigator & { standalone?: boolean } };
  if (win.navigator.standalone === true) return true; // iOS
  if (window.matchMedia?.('(display-mode: standalone)').matches) return true;
  return false;
}

function isDismissed(): boolean {
  if (typeof localStorage === 'undefined') return false;
  try {
    const until = localStorage.getItem(DISMISS_KEY);
    if (!until) return false;
    return Number(until) > Date.now();
  } catch {
    return false;
  }
}

function rememberDismissal() {
  if (typeof localStorage === 'undefined') return;
  try {
    localStorage.setItem(DISMISS_KEY, String(Date.now() + DISMISS_DAYS * 86400 * 1000));
  } catch {
    /* localStorage 不可ブラウザは無視 */
  }
}

function profilePathForUserType(userType: string | null | undefined): string {
  switch (userType) {
    case 'guardian':
      return '/guardian/profile';
    case 'staff':
      return '/staff/profile';
    case 'student':
      return '/student/profile';
    case 'admin':
      // 管理者ダッシュボードに通知トグルが無い場合もあるためスタッフ画面で代替
      return '/staff/profile';
    case 'tablet':
      // タブレットアカウント専用の通知設定画面 (/tablet/profile を別途用意)
      return '/tablet/profile';
    default:
      return '/';
  }
}

export function NotificationFollowUpPrompt() {
  const { user, isAuthenticated } = useAuthStore();
  const [show, setShow] = useState(false);

  useEffect(() => {
    if (typeof window === 'undefined') return;
    if (!isAuthenticated || !user) return;
    if (!isStandalone()) return; // PWA としてインストール起動していない
    if (isDismissed()) return;
    if (!('Notification' in window)) return; // Web Notification 非対応 (古い iOS Safari 等)
    if (Notification.permission !== 'default') return; // 既に granted / denied

    const t = setTimeout(() => setShow(true), INITIAL_DELAY_MS);
    return () => clearTimeout(t);
  }, [user, isAuthenticated]);

  if (!show || !user) return null;

  const profilePath = profilePathForUserType(user.user_type);

  return (
    <div
      role="dialog"
      aria-label="通知を有効にする"
      className="fixed inset-x-0 bottom-0 z-[9998] px-3 pb-[max(0.75rem,env(safe-area-inset-bottom))] pt-3"
    >
      <div className="mx-auto max-w-md rounded-2xl bg-white shadow-2xl ring-1 ring-black/10">
        <div className="flex items-start gap-3 p-4">
          <div className="flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-xl bg-[#14a898]/10">
            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" className="text-[#14a898]">
              <path d="M15 17h5l-1.4-1.4A2 2 0 0118 14.2V11a6 6 0 10-12 0v3.2a2 2 0 01-.6 1.4L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"
                stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" />
            </svg>
          </div>
          <div className="flex-1 min-w-0">
            <p className="text-sm font-semibold text-gray-900">通知も有効にしますか?</p>
            <p className="mt-1 text-xs text-gray-600">
              連絡帳・チャット・面談など、お知らせを通知センターで受け取れます。
              プロフィール画面の「通知を有効にする」を押してください。
            </p>
          </div>
        </div>
        <div className="flex gap-2 border-t border-gray-100 p-3">
          <button
            type="button"
            onClick={() => { setShow(false); rememberDismissal(); }}
            className="flex-1 rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
          >
            あとで
          </button>
          <Link
            href={profilePath}
            onClick={() => { setShow(false); rememberDismissal(); }}
            className="flex-1 rounded-lg bg-[#14a898] px-4 py-2 text-center text-sm font-semibold text-white hover:bg-[#119184]"
          >
            設定を開く
          </Link>
        </div>
      </div>
    </div>
  );
}
