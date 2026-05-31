'use client';

/**
 * /admin/two-factor (マスター管理者専用)
 *
 * 2 要素認証 (TOTP) の有効化・確認・無効化。
 * 認証アプリ (Google Authenticator 等) に手動キーを登録 → 6 桁コードで確認。
 * リカバリコードは有効化時に一度だけ表示。
 */

import { useState, useEffect } from 'react';
import api, { formatApiError } from '@/lib/api';
import { Card, CardHeader, CardTitle, CardBody } from '@/components/ui/Card';
import { Button } from '@/components/ui/Button';
import { Input } from '@/components/ui/Input';
import { Badge } from '@/components/ui/Badge';
import { useToast } from '@/components/ui/Toast';
import { MaterialIcon } from '@/components/ui/MaterialIcon';
import { useMasterGuard } from '@/hooks/useMasterGuard';

interface Status {
  enabled: boolean;
  pending: boolean;
  recovery_count: number;
}

export default function TwoFactorPage() {
  const { isMaster, isReady } = useMasterGuard();
  const { toast } = useToast();

  const [status, setStatus] = useState<Status | null>(null);
  const [loading, setLoading] = useState(true);

  // enable フロー
  const [secret, setSecret] = useState<string | null>(null);
  const [otpauthUri, setOtpauthUri] = useState<string | null>(null);
  const [confirmCode, setConfirmCode] = useState('');
  const [busy, setBusy] = useState(false);

  // 結果
  const [recoveryCodes, setRecoveryCodes] = useState<string[] | null>(null);

  // disable フロー
  const [disablePassword, setDisablePassword] = useState('');

  const loadStatus = async () => {
    setLoading(true);
    try {
      const res = await api.get('/api/auth/two-factor/status');
      setStatus(res.data.data);
    } catch (err) {
      toast(formatApiError(err, '状態の取得に失敗しました'), 'error');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    if (isReady && isMaster) loadStatus();
  }, [isReady, isMaster]);

  const handleEnable = async () => {
    setBusy(true);
    try {
      const res = await api.post('/api/auth/two-factor/enable');
      setSecret(res.data.data.secret);
      setOtpauthUri(res.data.data.otpauth_uri);
      setRecoveryCodes(null);
      await loadStatus();
    } catch (err) {
      toast(formatApiError(err, '開始に失敗しました'), 'error');
    } finally {
      setBusy(false);
    }
  };

  const handleConfirm = async () => {
    setBusy(true);
    try {
      const res = await api.post('/api/auth/two-factor/confirm', { code: confirmCode });
      setRecoveryCodes(res.data.data.recovery_codes);
      setSecret(null);
      setOtpauthUri(null);
      setConfirmCode('');
      toast('2 要素認証を有効にしました', 'success');
      await loadStatus();
    } catch (err) {
      toast(formatApiError(err, 'コードの確認に失敗しました'), 'error');
    } finally {
      setBusy(false);
    }
  };

  const handleDisable = async () => {
    if (!disablePassword) {
      toast('パスワードを入力してください', 'warning');
      return;
    }
    if (!confirm('2 要素認証を無効にします。よろしいですか？')) return;
    setBusy(true);
    try {
      await api.post('/api/auth/two-factor/disable', { password: disablePassword });
      setDisablePassword('');
      setRecoveryCodes(null);
      toast('2 要素認証を無効にしました', 'success');
      await loadStatus();
    } catch (err) {
      toast(formatApiError(err, '無効化に失敗しました'), 'error');
    } finally {
      setBusy(false);
    }
  };

  const handleRegenRecovery = async () => {
    if (!confirm('リカバリコードを再生成します。古いコードは使えなくなります。よろしいですか？')) return;
    setBusy(true);
    try {
      const res = await api.post('/api/auth/two-factor/recovery-codes');
      setRecoveryCodes(res.data.data.recovery_codes);
      toast('リカバリコードを再生成しました', 'success');
      await loadStatus();
    } catch (err) {
      toast(formatApiError(err, '再生成に失敗しました'), 'error');
    } finally {
      setBusy(false);
    }
  };

  if (!isReady || !isMaster) return null;

  return (
    <div className="space-y-6 max-w-2xl">
      <div>
        <h1 className="text-2xl font-bold text-[var(--neutral-foreground-1)]">2 要素認証 (2FA)</h1>
        <p className="mt-1 text-sm text-[var(--neutral-foreground-3)]">
          マスター管理者アカウントのログインに、認証アプリの 6 桁コードを追加できます。
          不正ログインのリスクを大きく下げられます。
        </p>
      </div>

      {loading ? (
        <Card><CardBody><p className="py-6 text-center text-sm text-[var(--neutral-foreground-3)]">読み込み中...</p></CardBody></Card>
      ) : (
        <>
          {/* 状態 */}
          <Card>
            <CardBody>
              <div className="flex items-center justify-between">
                <span className="text-sm font-medium text-[var(--neutral-foreground-2)]">現在の状態</span>
                {status?.enabled ? (
                  <Badge variant="success">有効</Badge>
                ) : status?.pending ? (
                  <Badge variant="warning">設定途中</Badge>
                ) : (
                  <Badge variant="default">無効</Badge>
                )}
              </div>
              {status?.enabled && (
                <p className="mt-2 text-xs text-[var(--neutral-foreground-3)]">
                  残りリカバリコード: {status.recovery_count} 個
                </p>
              )}
            </CardBody>
          </Card>

          {/* リカバリコード表示 (一度だけ) */}
          {recoveryCodes && (
            <Card>
              <CardHeader><CardTitle>リカバリコード (必ず控えてください)</CardTitle></CardHeader>
              <CardBody>
                <div className="rounded-md bg-[var(--status-warning-bg)] p-3 text-xs text-[var(--status-warning-fg)]">
                  ⚠ このコードは二度と表示されません。認証アプリを失くした際にログインするための
                  最後の手段です。安全な場所に保管してください。各コードは 1 回のみ使用できます。
                </div>
                <div className="mt-3 grid grid-cols-2 gap-2 font-mono text-sm">
                  {recoveryCodes.map((c) => (
                    <div key={c} className="rounded bg-[var(--neutral-background-3)] px-3 py-1.5 text-center">{c}</div>
                  ))}
                </div>
              </CardBody>
            </Card>
          )}

          {/* 無効時: 有効化フロー */}
          {!status?.enabled && (
            <Card>
              <CardHeader><CardTitle>2 要素認証を有効にする</CardTitle></CardHeader>
              <CardBody>
                {!secret ? (
                  <div>
                    <p className="mb-3 text-sm text-[var(--neutral-foreground-2)]">
                      まず認証アプリ (Google Authenticator、Microsoft Authenticator、Authy 等) を
                      スマートフォンに用意してください。
                    </p>
                    <Button onClick={handleEnable} isLoading={busy} leftIcon={<MaterialIcon name="shield" size={16} />}>
                      設定を開始する
                    </Button>
                  </div>
                ) : (
                  <div className="space-y-4">
                    <div>
                      <p className="mb-1 text-sm font-medium text-[var(--neutral-foreground-2)]">
                        手順 1: 認証アプリに次のキーを「手動で追加」してください
                      </p>
                      <div className="rounded-md bg-[var(--neutral-background-3)] p-3 font-mono text-sm break-all select-all">
                        {secret}
                      </div>
                      <p className="mt-1 text-xs text-[var(--neutral-foreground-4)]">
                        アカウント名: kiduri / 種類: 時間ベース (TOTP) / 桁数: 6 / 期間: 30 秒
                      </p>
                      {otpauthUri && (
                        <p className="mt-1 break-all text-[10px] text-[var(--neutral-foreground-4)]">
                          QR 対応アプリ用 URI: {otpauthUri}
                        </p>
                      )}
                    </div>
                    <div>
                      <p className="mb-1 text-sm font-medium text-[var(--neutral-foreground-2)]">
                        手順 2: アプリに表示された 6 桁コードを入力
                      </p>
                      <Input
                        placeholder="123456"
                        inputMode="numeric"
                        value={confirmCode}
                        onChange={(e) => setConfirmCode(e.target.value)}
                      />
                    </div>
                    <Button onClick={handleConfirm} isLoading={busy} disabled={!confirmCode}>
                      確認して有効化
                    </Button>
                  </div>
                )}
              </CardBody>
            </Card>
          )}

          {/* 有効時: リカバリ再生成 + 無効化 */}
          {status?.enabled && (
            <>
              <Card>
                <CardHeader><CardTitle>リカバリコードの再生成</CardTitle></CardHeader>
                <CardBody>
                  <p className="mb-3 text-sm text-[var(--neutral-foreground-2)]">
                    リカバリコードを使い切った、または漏えいした可能性がある場合に再生成します。
                  </p>
                  <Button variant="outline" onClick={handleRegenRecovery} isLoading={busy}>
                    リカバリコードを再生成
                  </Button>
                </CardBody>
              </Card>

              <Card>
                <CardHeader><CardTitle>2 要素認証を無効にする</CardTitle></CardHeader>
                <CardBody>
                  <p className="mb-3 text-sm text-[var(--neutral-foreground-2)]">
                    無効にするには現在のパスワードを入力してください。
                  </p>
                  <div className="flex items-end gap-2">
                    <div className="flex-1">
                      <Input
                        label="パスワード"
                        type="password"
                        value={disablePassword}
                        onChange={(e) => setDisablePassword(e.target.value)}
                      />
                    </div>
                    <Button
                      onClick={handleDisable}
                      isLoading={busy}
                      className="bg-[var(--status-danger-fg)] hover:bg-[var(--status-danger-fg)]/90"
                    >
                      無効にする
                    </Button>
                  </div>
                </CardBody>
              </Card>
            </>
          )}
        </>
      )}
    </div>
  );
}
