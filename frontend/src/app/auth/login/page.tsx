'use client';

import { useState } from 'react';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { loginSchema, type LoginFormData } from '@/lib/validators';
import { useAuth } from '@/hooks/useAuth';
import { Button } from '@/components/ui/Button';
import { Input } from '@/components/ui/Input';
import { MaterialIcon } from '@/components/ui/MaterialIcon';

export default function LoginPage() {
  const { login, error, clearError } = useAuth();
  const [isSubmitting, setIsSubmitting] = useState(false);
  // 2FA: サーバが two_factor_required を返したらコード入力モードに切り替える
  const [twoFactorRequired, setTwoFactorRequired] = useState(false);
  const [code, setCode] = useState('');
  const [twoFactorError, setTwoFactorError] = useState<string | null>(null);

  const {
    register,
    handleSubmit,
    formState: { errors },
  } = useForm<LoginFormData>({
    resolver: zodResolver(loginSchema),
  });

  const onSubmit = async (data: LoginFormData) => {
    setIsSubmitting(true);
    clearError();
    setTwoFactorError(null);
    try {
      await login(data.username, data.password, twoFactorRequired ? code : undefined);
    } catch (err: unknown) {
      const e = err as { response?: { data?: { two_factor_required?: boolean; message?: string } } };
      if (e.response?.data?.two_factor_required) {
        // コード入力欄を表示 (初回) / コード誤りの再入力
        setTwoFactorRequired(true);
        if (twoFactorRequired) {
          setTwoFactorError(e.response?.data?.message || '認証コードが正しくありません。');
        }
      }
      // 通常エラーは store の error に入る
    } finally {
      setIsSubmitting(false);
    }
  };

  return (
    <div>
      <h2 className="mb-6 text-center text-lg font-semibold text-[var(--neutral-foreground-1)]">
        ログイン
      </h2>

      {error && (
        <div className="mb-4 flex items-center gap-2 rounded-md border border-[var(--status-danger-fg)]/20 bg-[var(--status-danger-bg)] px-4 py-3">
          <MaterialIcon name="error" size={16} className="text-[var(--status-danger-fg)]" />
          <p className="text-sm text-[var(--status-danger-fg)]">{error}</p>
        </div>
      )}

      <form onSubmit={handleSubmit(onSubmit)} className="space-y-4">
        <Input
          label="ログインID"
          placeholder="ログインIDを入力"
          autoComplete="username"
          error={errors.username?.message}
          {...register('username')}
        />

        <Input
          label="パスワード"
          type="password"
          placeholder="パスワードを入力"
          autoComplete="current-password"
          error={errors.password?.message}
          {...register('password')}
        />

        {twoFactorRequired && (
          <div className="rounded-md border border-[var(--brand-80)]/30 bg-[var(--brand-160)] p-3">
            <p className="mb-2 text-xs text-[var(--neutral-foreground-2)]">
              このアカウントは 2 要素認証が有効です。認証アプリの 6 桁コード
              (またはリカバリコード) を入力してください。
            </p>
            <Input
              label="認証コード"
              placeholder="123456"
              autoComplete="one-time-code"
              inputMode="numeric"
              value={code}
              onChange={(e) => setCode(e.target.value)}
              error={twoFactorError ?? undefined}
              autoFocus
            />
          </div>
        )}

        <Button
          type="submit"
          className="w-full"
          size="lg"
          isLoading={isSubmitting}
        >
          {twoFactorRequired ? '認証して続行' : 'ログイン'}
        </Button>
      </form>

      <p className="mt-6 text-center text-xs text-[var(--neutral-foreground-4)]">
        ログインID・パスワードが不明な場合は管理者にお問い合わせください
      </p>
      <p className="mt-2 text-center text-xs text-[var(--neutral-foreground-4)]">
        <a href="/terms" className="hover:underline hover:text-[var(--neutral-foreground-3)]">利用規約</a>
      </p>
    </div>
  );
}
