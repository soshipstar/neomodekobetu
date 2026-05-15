'use client';

import { useEffect, useState } from 'react';

/**
 * PWA インストールプロンプト
 *
 * 全ロール (タブレット / スタッフ / 管理者 / 保護者) で共通利用するため、
 * root layout に組み込み、ログイン状態に関わらずスマホ・タブレットで
 * インストール案内を表示する。
 *
 * 動作:
 *   - Android Chrome / Edge: `beforeinstallprompt` イベントをキャッチして
 *     ネイティブのインストールプロンプトを「インストール」ボタン経由で起動する。
 *   - iOS Safari: `beforeinstallprompt` をサポートしないため、共有ボタン →
 *     「ホーム画面に追加」の手順をモーダルで案内する。
 *   - すでにスタンドアロン起動 (PWA としてインストール済み) なら何もしない。
 *   - ユーザが「あとで」を押したら 7 日間表示しない。
 */

type BeforeInstallPromptEvent = Event & {
  prompt: () => Promise<void>;
  userChoice: Promise<{ outcome: 'accepted' | 'dismissed' }>;
};

const DISMISS_KEY = 'kiduri-pwa-install-dismissed-until';
const DISMISS_DAYS = 7;
const INITIAL_DELAY_MS = 2000;

function isStandalone(): boolean {
  if (typeof window === 'undefined') return false;
  const win = window as Window & { navigator: Navigator & { standalone?: boolean } };
  if (win.navigator.standalone === true) return true; // iOS
  if (window.matchMedia?.('(display-mode: standalone)').matches) return true;
  return false;
}

function isIOS(): boolean {
  if (typeof navigator === 'undefined' || typeof window === 'undefined') return false;
  const ua = navigator.userAgent;
  const win = window as Window & { MSStream?: unknown };
  return /iPad|iPhone|iPod/.test(ua) && !win.MSStream;
}

function isMobileOrTablet(): boolean {
  if (typeof navigator === 'undefined') return false;
  return /Mobi|Android|iPhone|iPad|iPod|Tablet/i.test(navigator.userAgent);
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
    /* localStorage 利用不可ブラウザは無視 */
  }
}

export function PwaInstallPrompt() {
  const [showAndroid, setShowAndroid] = useState(false);
  const [showIos, setShowIos] = useState(false);
  const [deferred, setDeferred] = useState<BeforeInstallPromptEvent | null>(null);

  useEffect(() => {
    if (typeof window === 'undefined') return;
    if (isStandalone()) return; // すでに PWA としてインストール済み
    if (isDismissed()) return;
    if (!isMobileOrTablet()) return; // PC は対象外

    if (isIOS()) {
      const t = setTimeout(() => setShowIos(true), INITIAL_DELAY_MS);
      return () => clearTimeout(t);
    }

    // Android Chrome / Edge / Samsung Internet 等
    const handler = (e: Event) => {
      e.preventDefault();
      setDeferred(e as BeforeInstallPromptEvent);
      setShowAndroid(true);
    };
    const installedHandler = () => {
      setShowAndroid(false);
      setShowIos(false);
    };
    window.addEventListener('beforeinstallprompt', handler);
    window.addEventListener('appinstalled', installedHandler);
    return () => {
      window.removeEventListener('beforeinstallprompt', handler);
      window.removeEventListener('appinstalled', installedHandler);
    };
  }, []);

  if (showIos) {
    return <IosInstallSheet onClose={() => { setShowIos(false); rememberDismissal(); }} />;
  }

  if (showAndroid && deferred) {
    return (
      <AndroidInstallBanner
        onInstall={async () => {
          try {
            await deferred.prompt();
            const choice = await deferred.userChoice;
            setShowAndroid(false);
            if (choice.outcome === 'dismissed') rememberDismissal();
          } catch {
            setShowAndroid(false);
          }
        }}
        onClose={() => { setShowAndroid(false); rememberDismissal(); }}
      />
    );
  }

  return null;
}

/* ---------------- Android ---------------- */

function AndroidInstallBanner({
  onInstall, onClose,
}: { onInstall: () => void; onClose: () => void }) {
  return (
    <div
      role="dialog"
      aria-label="KIDURI をホーム画面に追加"
      className="fixed inset-x-0 bottom-0 z-[9999] px-3 pb-[max(0.75rem,env(safe-area-inset-bottom))] pt-3"
    >
      <div className="mx-auto max-w-md rounded-2xl bg-white shadow-2xl ring-1 ring-black/10">
        <div className="flex items-start gap-3 p-4">
          <img
            src="/assets/icons/icon-192x192.png"
            alt="KIDURI"
            className="h-12 w-12 flex-shrink-0 rounded-xl"
          />
          <div className="flex-1 min-w-0">
            <p className="text-sm font-semibold text-gray-900">KIDURIをアプリとして追加</p>
            <p className="mt-1 text-xs text-gray-600">
              ホーム画面から1タップで開けるようになります。通知・オフライン閲覧にも対応。
            </p>
          </div>
        </div>
        <div className="flex gap-2 border-t border-gray-100 p-3">
          <button
            type="button"
            onClick={onClose}
            className="flex-1 rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
          >
            あとで
          </button>
          <button
            type="button"
            onClick={onInstall}
            className="flex-1 rounded-lg bg-[#14a898] px-4 py-2 text-sm font-semibold text-white hover:bg-[#119184]"
          >
            インストール
          </button>
        </div>
      </div>
    </div>
  );
}

/* ---------------- iOS ---------------- */

function IosInstallSheet({ onClose }: { onClose: () => void }) {
  return (
    <div
      role="dialog"
      aria-label="iPhone でホーム画面に追加する手順"
      className="fixed inset-0 z-[9999] flex items-end justify-center bg-black/40 p-3 pb-[max(0.75rem,env(safe-area-inset-bottom))]"
      onClick={(e) => {
        if (e.target === e.currentTarget) onClose();
      }}
    >
      <div className="w-full max-w-md rounded-2xl bg-white shadow-2xl">
        <div className="flex items-center gap-3 p-4">
          <img
            src="/assets/icons/icon-192x192.png"
            alt="KIDURI"
            className="h-12 w-12 flex-shrink-0 rounded-xl"
          />
          <div className="flex-1">
            <p className="text-sm font-semibold text-gray-900">KIDURIをホーム画面に追加</p>
            <p className="text-xs text-gray-500">iPhone / iPad での手順</p>
          </div>
          <button
            type="button"
            onClick={onClose}
            aria-label="閉じる"
            className="rounded-full p-2 text-gray-400 hover:bg-gray-100"
          >
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none">
              <path d="M6 6l12 12M18 6l-12 12" stroke="currentColor" strokeWidth="2" strokeLinecap="round" />
            </svg>
          </button>
        </div>
        <ol className="space-y-3 px-5 pb-5">
          <li className="flex items-start gap-3">
            <span className="flex h-6 w-6 flex-shrink-0 items-center justify-center rounded-full bg-[#14a898] text-xs font-bold text-white">1</span>
            <div className="flex-1 text-sm text-gray-700">
              画面下の
              <span className="mx-1 inline-flex items-center align-middle">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" className="text-[#14a898]">
                  <path d="M12 3v12M8 7l4-4 4 4M5 12v7a2 2 0 002 2h10a2 2 0 002-2v-7" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" />
                </svg>
              </span>
              共有ボタン をタップ
            </div>
          </li>
          <li className="flex items-start gap-3">
            <span className="flex h-6 w-6 flex-shrink-0 items-center justify-center rounded-full bg-[#14a898] text-xs font-bold text-white">2</span>
            <div className="flex-1 text-sm text-gray-700">
              メニューを下にスクロールし、
              <span className="mx-1 inline-flex items-center align-middle rounded border border-gray-300 bg-gray-50 px-1.5 py-0.5 text-xs font-medium text-gray-800">
                ＋ ホーム画面に追加
              </span>
              をタップ
            </div>
          </li>
          <li className="flex items-start gap-3">
            <span className="flex h-6 w-6 flex-shrink-0 items-center justify-center rounded-full bg-[#14a898] text-xs font-bold text-white">3</span>
            <div className="flex-1 text-sm text-gray-700">
              右上の「追加」をタップで完了。ホーム画面の KIDURI アイコンから開けます。
            </div>
          </li>
        </ol>
        <div className="border-t border-gray-100 p-3">
          <button
            type="button"
            onClick={onClose}
            className="w-full rounded-lg bg-[#14a898] px-4 py-2 text-sm font-semibold text-white hover:bg-[#119184]"
          >
            わかりました
          </button>
        </div>
        <p className="px-5 pb-4 text-center text-[11px] text-gray-400">
          ※ Safari でご利用の場合のみ追加できます。Chrome等の別ブラウザでは追加できません。
        </p>
      </div>
    </div>
  );
}
