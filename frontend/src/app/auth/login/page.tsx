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
    try {
      await login(data.username, data.password);
    } catch {
      // Error is handled by the store
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
          label="ユーザー名"
          placeholder="ユーザー名を入力"
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

        <Button
          type="submit"
          className="w-full"
          size="lg"
          isLoading={isSubmitting}
        >
          ログイン
        </Button>
      </form>

      <p className="mt-6 text-center text-xs text-[var(--neutral-foreground-4)]">
        ユーザー名・パスワードが不明な場合は管理者にお問い合わせください
      </p>
    </div>
  );
}
