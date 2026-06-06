'use client';

import { useEffect, useState, useCallback } from 'react';
import { usePushSubscription } from '@/hooks/usePushSubscription';

/**
 * PWA インストール誘導 + 通知許可案内コンポーネント。
 *
 * 動作:
 *  - Android/Chrome: beforeinstallprompt イベントを捕捉し、画面下部にバナー表示
 *  - iOS Safari: beforeinstallprompt は来ないので「共有 → ホーム画面に追加」を案内
 *  - インストール検出後 (appinstalled イベント) または既に standalone なら、
 *    通知権限が未設定であれば「通知も有効にしますか?」バナーを連続表示
 *  - 通知有効化は usePushSubscription().enable() に委譲 (購読登録まで一発)
 *
 * 表示判定:
 *  - すでに standalone モード (PWA として起動) → インストールバナーは出さない
 *  - 24時間以内に dismiss された localStorage フラグがある → 全バナー非表示
 */

interface BeforeInstallPromptEvent extends Event {
  prompt: () => Promise<void>;
  userChoice: Promise<{ outcome: 'accepted' | 'dismissed' }>;
}

const DISMISS_KEY = 'pwa_install_dismissed_at';
const DISMISS_WINDOW_MS = 24 * 60 * 60 * 1000; // 24 時間

function isStandalone(): boolean {
  if (typeof window === 'undefined') return false;
  const mq = window.matchMedia('(display-mode: standalone)').matches;
  const ios = (window.navigator as Navigator & { standalone?: boolean }).standalone === true;
  return mq || ios;
}

function isIosSafari(): boolean {
  if (typeof window === 'undefined') return false;
  const ua = window.navigator.userAgent;
  const isIOS = /iPad|iPhone|iPod/.test(ua) && !(window as Window & { MSStream?: unknown }).MSStream;
  const isSafari = /Safari/i.test(ua) && !/CriOS|FxiOS|EdgiOS/i.test(ua);
  return isIOS && isSafari;
}

function isRecentlyDismissed(): boolean {
  if (typeof window === 'undefined') return false;
  const v = window.localStorage.getItem(DISMISS_KEY);
  if (!v) return false;
  const t = Number(v);
  if (!Number.isFinite(t)) return false;
  return Date.now() - t < DISMISS_WINDOW_MS;
}

export function PwaInstallPrompt() {
  const { permission, subscribed, supported, canPrompt, enable } = usePushSubscription();
  const [deferred, setDeferred] = useState<BeforeInstallPromptEvent | null>(null);
  const [showIosBanner, setShowIosBanner] = useState(false);
  const [showAndroidBanner, setShowAndroidBanner] = useState(false);
  const [showNotificationPrompt, setShowNotificationPrompt] = useState(false);
  const [enabling, setEnabling] = useState(false);

  // Standalone 起動時に通知未許可なら案内
  useEffect(() => {
    if (typeof window === 'undefined') return;
    if (isRecentlyDismissed()) return;
    if (isStandalone() && supported && canPrompt && permission === 'default' && !subscribed) {
      setShowNotificationPrompt(true);
    }
  }, [supported, canPrompt, permission, subscribed]);

  // Android/Chrome beforeinstallprompt + appinstalled
  useEffect(() => {
    if (typeof window === 'undefined') return;
    if (isStandalone() || isRecentlyDismissed()) return;

    const handler = (e: Event) => {
      e.preventDefault();
      setDeferred(e as BeforeInstallPromptEvent);
      setShowAndroidBanner(true);
    };
    window.addEventListener('beforeinstallprompt', handler as EventListener);

    const installedHandler = () => {
      setShowAndroidBanner(false);
      setShowIosBanner(false);
      setDeferred(null);
      setTimeout(() => {
        if (supported && canPrompt && permission === 'default') {
          setShowNotificationPrompt(true);
        }
      }, 800);
    };
    window.addEventListener('appinstalled', installedHandler);

    // iOS Safari の手動誘導 (2秒後)
    let timeoutId: number | undefined;
    if (isIosSafari()) {
      timeoutId = window.setTimeout(() => setShowIosBanner(true), 2000);
    }

    return () => {
      window.removeEventListener('beforeinstallprompt', handler as EventListener);
      window.removeEventListener('appinstalled', installedHandler);
      if (timeoutId) window.clearTimeout(timeoutId);
    };
  }, [supported, canPrompt, permission]);

  const dismiss = useCallback(() => {
    setShowAndroidBanner(false);
    setShowIosBanner(false);
    window.localStorage.setItem(DISMISS_KEY, String(Date.now()));
  }, []);

  const dismissNotification = useCallback(() => {
    setShowNotificationPrompt(false);
    window.localStorage.setItem(DISMISS_KEY, String(Date.now()));
  }, []);

  const handleAndroidInstall = useCallback(async () => {
    if (!deferred) return;
    await deferred.prompt();
    const choice = await deferred.userChoice;
    setDeferred(null);
    if (choice.outcome === 'accepted') {
      setShowAndroidBanner(false);
      // appinstalled イベントで通知案内が起動
    } else {
      dismiss();
    }
  }, [deferred, dismiss]);

  const handleEnableNotifications = useCallback(async () => {
    setEnabling(true);
    try {
      await enable();
    } finally {
      setEnabling(false);
      setShowNotificationPrompt(false);
    }
  }, [enable]);

  if (showAndroidBanner && deferred) {
    return (
      <Banner
        icon="install_mobile"
        title="ホーム画面に追加できます"
        body="アプリ感覚で素早く起動できるようになります。"
        primaryLabel="インストール"
        onPrimary={handleAndroidInstall}
        onDismiss={dismiss}
      />
    );
  }

  if (showIosBanner) {
    return (
      <Banner
        icon="ios_share"
        title="iPhone でアプリとして使う方法"
        body="画面下の 共有ボタン → 「ホーム画面に追加」 を選択してください。"
        primaryLabel="閉じる"
        onPrimary={dismiss}
        onDismiss={dismiss}
      />
    );
  }

  if (showNotificationPrompt) {
    return (
      <Banner
        icon="notifications_active"
        title="通知も有効にしますか?"
        body="新着の連絡帳・チャット・面談調整をすぐに受け取れます。"
        primaryLabel="有効にする"
        primaryLoading={enabling}
        onPrimary={handleEnableNotifications}
        onDismiss={dismissNotification}
      />
    );
  }

  return null;
}

// ---------------------------------------------------------------------------

function Banner({
  icon,
  title,
  body,
  primaryLabel,
  primaryLoading,
  onPrimary,
  onDismiss,
}: {
  icon: string;
  title: string;
  body: string;
  primaryLabel: string;
  primaryLoading?: boolean;
  onPrimary: () => void;
  onDismiss: () => void;
}) {
  return (
    <div className="fixed inset-x-0 bottom-0 z-50 flex justify-center px-4 pb-4 pointer-events-none">
      <div className="pointer-events-auto w-full max-w-md rounded-2xl border border-[var(--neutral-stroke-2)] bg-white p-4 shadow-2xl ring-1 ring-black/5">
        <div className="flex items-start gap-3">
          <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-[var(--brand-160)]">
            <span className="material-symbols-outlined text-[var(--brand-60)]" style={{ fontSize: 22 }}>
              {icon}
            </span>
          </div>
          <div className="flex-1 min-w-0">
            <p className="text-sm font-bold text-[var(--neutral-foreground-1)]">{title}</p>
            <p className="mt-1 text-xs text-[var(--neutral-foreground-3)] leading-relaxed">{body}</p>
            <div className="mt-3 flex items-center gap-2">
              <button
                onClick={onPrimary}
                disabled={primaryLoading}
                className="rounded-lg bg-[var(--brand-80)] px-4 py-2 text-xs font-bold text-white hover:opacity-90 disabled:opacity-50"
              >
                {primaryLoading ? '処理中…' : primaryLabel}
              </button>
              <button
                onClick={onDismiss}
                className="rounded-lg px-3 py-2 text-xs text-[var(--neutral-foreground-3)] hover:bg-[var(--neutral-background-3)]"
              >
                あとで
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}
