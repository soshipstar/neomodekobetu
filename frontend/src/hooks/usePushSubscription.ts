'use client';

import { useCallback, useEffect, useState } from 'react';
import api from '@/lib/api';

/**
 * Web Push subscription hook.
 *
 * 機能:
 * - 現在の購読状態を取得（permission + subscription）
 * - 購読の有効化 (enable): Notification.requestPermission → pushManager.subscribe → /api/push/subscribe
 * - 購読の解除 (disable): pushManager.unsubscribe → /api/push/unsubscribe
 * - iOS PWA の前提条件チェック（standalone モードかどうか）
 *
 * iOS の注意:
 * - iOS 16.4+ のみ対応
 * - ホーム画面に追加して PWA として開いている (display-mode: standalone) ときだけ
 *   Notification.requestPermission() が動作する
 */

export type PushPermission = 'default' | 'granted' | 'denied' | 'unsupported';

type PushStatus = {
  permission: PushPermission;
  subscribed: boolean;
  isIos: boolean;
  isStandalone: boolean;
  supported: boolean;
  canPrompt: boolean;
};

function urlBase64ToUint8Array(base64String: string): Uint8Array {
  const padding = '='.repeat((4 - (base64String.length % 4)) % 4);
  const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
  const rawData = window.atob(base64);
  const output = new Uint8Array(rawData.length);
  for (let i = 0; i < rawData.length; i += 1) {
    output[i] = rawData.charCodeAt(i);
  }
  return output;
}

function detectIos(): boolean {
  if (typeof navigator === 'undefined') return false;
  const ua = navigator.userAgent;
  // iPadOS 13+ は MacIntel を返すので touch 判定を併用
  return /iPad|iPhone|iPod/.test(ua) || (ua.includes('Mac') && 'ontouchend' in document);
}

function detectStandalone(): boolean {
  if (typeof window === 'undefined') return false;
  // iOS Safari
  const iosStandalone = (window.navigator as unknown as { standalone?: boolean }).standalone === true;
  // 他プラットフォーム (display-mode media query)
  const mqStandalone = window.matchMedia?.('(display-mode: standalone)').matches === true;
  return iosStandalone || mqStandalone;
}

async function getExistingSubscription(): Promise<PushSubscription | null> {
  if (typeof navigator === 'undefined' || !('serviceWorker' in navigator)) return null;
  try {
    const reg = await navigator.serviceWorker.ready;
    return await reg.pushManager.getSubscription();
  } catch {
    return null;
  }
}

export function usePushSubscription() {
  const [status, setStatus] = useState<PushStatus>({
    permission: 'default',
    subscribed: false,
    isIos: false,
    isStandalone: false,
    supported: false,
    canPrompt: false,
  });
  const [loading, setLoading] = useState(false);

  const refreshStatus = useCallback(async () => {
    if (typeof window === 'undefined') return;

    const isIos = detectIos();
    const isStandalone = detectStandalone();
    const supported =
      'serviceWorker' in navigator &&
      'PushManager' in window &&
      'Notification' in window;

    if (!supported) {
      setStatus({
        permission: 'unsupported',
        subscribed: false,
        isIos,
        isStandalone,
        supported: false,
        canPrompt: false,
      });
      return;
    }

    // iOS の場合は PWA モードでないと Notification API 自体は存在しても
    // 購読できない
    const canPrompt = !isIos || isStandalone;

    const permission: PushPermission =
      (Notification.permission as PushPermission) ?? 'default';
    const existing = await getExistingSubscription();

    setStatus({
      permission,
      subscribed: !!existing,
      isIos,
      isStandalone,
      supported: true,
      canPrompt,
    });
  }, []);

  useEffect(() => {
    refreshStatus();
  }, [refreshStatus]);

  const enable = useCallback(async () => {
    if (typeof window === 'undefined') return { ok: false, message: 'unavailable' };
    setLoading(true);
    try {
      if (!('serviceWorker' in navigator) || !('PushManager' in window)) {
        return { ok: false, message: 'お使いのブラウザは通知に対応していません。' };
      }

      // iOS の場合は PWA モードでないとユーザーが通知許可を付与できない
      if (detectIos() && !detectStandalone()) {
        return {
          ok: false,
          message: 'iPhone / iPad では、Safari 共有メニューの「ホーム画面に追加」でアプリをインストールしてから、ホーム画面のアイコンから開いて再度お試しください。',
        };
      }

      // 通知許可を要求
      const perm = await Notification.requestPermission();
      if (perm !== 'granted') {
        return {
          ok: false,
          message: '通知が許可されませんでした。ブラウザ設定から通知を許可してください。',
        };
      }

      // Service Worker
      const reg = await navigator.serviceWorker.ready;

      // 既存購読があればそれを使う
      let subscription = await reg.pushManager.getSubscription();

      if (!subscription) {
        // VAPID public key を取得
        const keyRes = await api.get<{ publicKey: string }>('/api/push/vapid-key');
        const applicationServerKey = urlBase64ToUint8Array(keyRes.data.publicKey);
        subscription = await reg.pushManager.subscribe({
          userVisibleOnly: true,
          // TypeScript の BufferSource 型との互換のため ArrayBuffer として渡す
          applicationServerKey: applicationServerKey.buffer as ArrayBuffer,
        });
      }

      // subscription を backend に送信
      const json = subscription.toJSON();
      await api.post('/api/push/subscribe', {
        endpoint: json.endpoint,
        keys: {
          p256dh: json.keys?.p256dh,
          auth: json.keys?.auth,
        },
      });

      await refreshStatus();
      return { ok: true, message: '通知を有効にしました。' };
    } catch (err: unknown) {
      const msg =
        (err as { response?: { data?: { message?: string } }; message?: string })
          ?.response?.data?.message ??
        (err as { message?: string })?.message ??
        '通知の設定に失敗しました。';
      return { ok: false, message: msg };
    } finally {
      setLoading(false);
    }
  }, [refreshStatus]);

  const disable = useCallback(async () => {
    if (typeof window === 'undefined') return { ok: false, message: 'unavailable' };
    setLoading(true);
    try {
      const reg = await navigator.serviceWorker.ready;
      const subscription = await reg.pushManager.getSubscription();
      if (subscription) {
        // backend から先に削除（購読の endpoint を渡す必要があるため）
        try {
          await api.post('/api/push/unsubscribe', { endpoint: subscription.endpoint });
        } catch {
          // backend 側の削除失敗は無視（購読は解除する）
        }
        await subscription.unsubscribe();
      }
      await refreshStatus();
      return { ok: true, message: '通知を無効にしました。' };
    } catch (err: unknown) {
      const msg =
        (err as { response?: { data?: { message?: string } }; message?: string })
          ?.response?.data?.message ??
        (err as { message?: string })?.message ??
        '通知の解除に失敗しました。';
      return { ok: false, message: msg };
    } finally {
      setLoading(false);
    }
  }, [refreshStatus]);

  return {
    ...status,
    loading,
    enable,
    disable,
    refresh: refreshStatus,
  };
}
