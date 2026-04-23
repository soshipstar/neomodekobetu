'use client';

import { useState } from 'react';
import { Card, CardBody } from '@/components/ui/Card';
import { Button } from '@/components/ui/Button';
import { MaterialIcon } from '@/components/ui/MaterialIcon';
import { useToast } from '@/components/ui/Toast';
import { usePushSubscription } from '@/hooks/usePushSubscription';

/**
 * Web Push 通知の ON/OFF トグルカード。
 * プロフィール画面に差し込んで使う。
 */
export function NotificationToggleCard() {
  const toast = useToast();
  const {
    permission,
    subscribed,
    isIos,
    isStandalone,
    supported,
    canPrompt,
    loading,
    enable,
    disable,
  } = usePushSubscription();
  const [busy, setBusy] = useState(false);

  const handleEnable = async () => {
    setBusy(true);
    const res = await enable();
    setBusy(false);
    if (res.ok) {
      toast.success(res.message);
    } else {
      toast.error(res.message);
    }
  };

  const handleDisable = async () => {
    setBusy(true);
    const res = await disable();
    setBusy(false);
    if (res.ok) {
      toast.success(res.message);
    } else {
      toast.error(res.message);
    }
  };

  const showIosInstallHelp = isIos && !isStandalone;

  return (
    <Card>
      <CardBody>
        <div className="flex items-start gap-3">
          <MaterialIcon
            name="notifications"
            size={24}
            className="text-[var(--brand-80)] mt-1"
          />
          <div className="flex-1">
            <h3 className="font-medium text-[var(--neutral-foreground-1)]">通知設定</h3>
            <p className="mt-1 text-xs text-[var(--neutral-foreground-3)]">
              チャット・お知らせ・連絡帳・書類依頼などを端末の通知で受け取れます。
            </p>

            {/* 非対応ブラウザ */}
            {!supported && (
              <p className="mt-3 rounded border border-[var(--status-warning-fg)]/20 bg-[var(--status-warning-bg)] p-2 text-xs text-[var(--status-warning-fg)]">
                このブラウザは Web Push 通知に対応していません。
                Chrome / Edge / Safari (iOS 16.4 以降) などの最新ブラウザでお試しください。
              </p>
            )}

            {/* iOS で非 PWA の場合 */}
            {supported && showIosInstallHelp && (
              <div className="mt-3 rounded border border-[var(--status-info-fg)]/20 bg-[var(--status-info-bg)] p-3 text-xs text-[var(--status-info-fg)]">
                <p className="font-semibold">iPhone / iPad での通知設定手順</p>
                <ol className="mt-2 ml-4 list-decimal space-y-1">
                  <li>Safari 下部の共有ボタン (<MaterialIcon name="ios_share" size={12} className="inline" />) をタップ</li>
                  <li>「ホーム画面に追加」を選択</li>
                  <li>ホーム画面に追加されたアイコンからアプリを開く</li>
                  <li>再度このページで「通知を有効にする」ボタンを押す</li>
                </ol>
                <p className="mt-2 text-[var(--neutral-foreground-3)]">
                  ※ iOS 16.4 以降で対応しています(iOS 26 などの最新バージョンも可)。
                </p>
              </div>
            )}

            {/* 拒否状態 */}
            {supported && canPrompt && permission === 'denied' && (
              <p className="mt-3 rounded border border-[var(--status-danger-fg)]/20 bg-[var(--status-danger-bg)] p-2 text-xs text-[var(--status-danger-fg)]">
                通知がブロックされています。ブラウザの設定画面から当サイトの通知を
                「許可」に変更してから、再度ページを読み込んでください。
              </p>
            )}

            {/* 状態表示 + ボタン */}
            {supported && canPrompt && permission !== 'denied' && (
              <div className="mt-3 flex items-center gap-3">
                <div className="flex-1">
                  <p className="text-sm text-[var(--neutral-foreground-2)]">
                    現在の状態:{' '}
                    <span
                      className={`font-semibold ${
                        subscribed
                          ? 'text-[var(--status-success-fg)]'
                          : 'text-[var(--neutral-foreground-3)]'
                      }`}
                    >
                      {subscribed ? '有効' : '無効'}
                    </span>
                  </p>
                </div>
                {subscribed ? (
                  <Button
                    variant="outline"
                    size="sm"
                    onClick={handleDisable}
                    isLoading={loading || busy}
                    leftIcon={<MaterialIcon name="notifications_off" size={16} />}
                  >
                    通知を無効にする
                  </Button>
                ) : (
                  <Button
                    variant="primary"
                    size="sm"
                    onClick={handleEnable}
                    isLoading={loading || busy}
                    leftIcon={<MaterialIcon name="notifications_active" size={16} />}
                  >
                    通知を有効にする
                  </Button>
                )}
              </div>
            )}
          </div>
        </div>
      </CardBody>
    </Card>
  );
}
