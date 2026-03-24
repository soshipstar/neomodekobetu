'use client';

import { useState } from 'react';
import { useMutation } from '@tanstack/react-query';
import api from '@/lib/api';
import { Card, CardHeader, CardTitle } from '@/components/ui/Card';
import { Button } from '@/components/ui/Button';
import { Input } from '@/components/ui/Input';
import { useToast } from '@/components/ui/Toast';
import Link from 'next/link';
import { MaterialIcon } from '@/components/ui/MaterialIcon';

interface PasswordForm {
  current_password: string;
  new_password: string;
  new_password_confirmation: string;
}

export default function StudentChangePasswordPage() {
  const toast = useToast();
  const [form, setForm] = useState<PasswordForm>({
    current_password: '',
    new_password: '',
    new_password_confirmation: '',
  });

  const mutation = useMutation({
    mutationFn: (data: PasswordForm) => api.put('/api/student/profile/password', data),
    onSuccess: () => {
      toast.success('パスワードを変更しました');
      setForm({ current_password: '', new_password: '', new_password_confirmation: '' });
    },
    onError: () => toast.error('パスワード変更に失敗しました'),
  });

  const isNewPasswordTooShort = form.new_password.length > 0 && form.new_password.length < 8;
  const isConfirmMismatch =
    form.new_password_confirmation.length > 0 &&
    form.new_password !== form.new_password_confirmation;
  const isValid =
    form.current_password.length > 0 &&
    form.new_password.length >= 8 &&
    form.new_password === form.new_password_confirmation;

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    if (isValid) {
      mutation.mutate(form);
    }
  };

  return (
    <div className="space-y-6">
      <div className="flex items-center gap-3">
        <Link
          href="/student/profile"
          className="flex h-8 w-8 items-center justify-center rounded-md text-[var(--neutral-foreground-2)] hover:bg-[var(--neutral-background-3)] transition-colors"
        >
          <MaterialIcon name="arrow_back" size={20} />
        </Link>
        <h1 className="text-2xl font-bold text-[var(--neutral-foreground-1)]">パスワード変更</h1>
      </div>

      <div className="mx-auto max-w-md">
        <Card>
          <CardHeader>
            <CardTitle>
              <div className="flex items-center gap-2">
                <MaterialIcon name="lock" size={20} />
                あたらしいパスワードをせっていしてね
              </div>
            </CardTitle>
          </CardHeader>
          <form onSubmit={handleSubmit} className="space-y-4">
            <Input
              label="いまのパスワード"
              type="password"
              value={form.current_password}
              onChange={(e) => setForm({ ...form, current_password: e.target.value })}
              required
            />
            <Input
              label="あたらしいパスワード"
              type="password"
              value={form.new_password}
              onChange={(e) => setForm({ ...form, new_password: e.target.value })}
              required
              helperText="8もじいじょうでにゅうりょくしてね"
              error={isNewPasswordTooShort ? 'パスワードは8もじいじょうにしてね' : undefined}
            />
            <Input
              label="あたらしいパスワード（もういちど）"
              type="password"
              value={form.new_password_confirmation}
              onChange={(e) => setForm({ ...form, new_password_confirmation: e.target.value })}
              required
              error={isConfirmMismatch ? 'パスワードがあっていません' : undefined}
            />
            <div className="flex gap-3 pt-2">
              <Link href="/student/profile" className="flex-1">
                <Button type="button" variant="secondary" className="w-full" size="lg">
                  もどる
                </Button>
              </Link>
              <Button
                type="submit"
                variant="primary"
                size="lg"
                className="flex-1"
                isLoading={mutation.isPending}
                disabled={!isValid}
              >
                パスワードをかえる
              </Button>
            </div>
          </form>
        </Card>
      </div>
    </div>
  );
}
