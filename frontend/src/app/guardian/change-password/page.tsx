'use client';

import { useState } from 'react';
import { useMutation } from '@tanstack/react-query';
import { useRouter } from 'next/navigation';
import api from '@/lib/api';
import { Card, CardHeader, CardTitle } from '@/components/ui/Card';
import { Button } from '@/components/ui/Button';
import { Input } from '@/components/ui/Input';
import { useToast } from '@/components/ui/Toast';
import { MaterialIcon } from '@/components/ui/MaterialIcon';

export default function GuardianChangePasswordPage() {
  const router = useRouter();
  const toast = useToast();

  const [form, setForm] = useState({
    current_password: '',
    new_password: '',
    new_password_confirmation: '',
  });
  const [errors, setErrors] = useState<Record<string, string>>({});
  const [showCurrentPassword, setShowCurrentPassword] = useState(false);
  const [showNewPassword, setShowNewPassword] = useState(false);
  const [showConfirmPassword, setShowConfirmPassword] = useState(false);

  const validate = (): boolean => {
    const newErrors: Record<string, string> = {};

    if (!form.current_password) {
      newErrors.current_password = '現在のパスワードを入力してください';
    }
    if (!form.new_password) {
      newErrors.new_password = '新しいパスワードを入力してください';
    } else if (form.new_password.length < 8) {
      newErrors.new_password = 'パスワードは8文字以上で設定してください';
    }
    if (!form.new_password_confirmation) {
      newErrors.new_password_confirmation = '確認用パスワードを入力してください';
    } else if (form.new_password !== form.new_password_confirmation) {
      newErrors.new_password_confirmation = '新しいパスワードが一致しません';
    }

    setErrors(newErrors);
    return Object.keys(newErrors).length === 0;
  };

  const mutation = useMutation({
    mutationFn: (data: typeof form) => api.put('/api/guardian/profile/password', data),
    onSuccess: () => {
      toast.success('パスワードを変更しました');
      setForm({ current_password: '', new_password: '', new_password_confirmation: '' });
      setErrors({});
      // Redirect after short delay
      setTimeout(() => router.push('/guardian/dashboard'), 1500);
    },
    onError: (error: any) => {
      const message =
        error?.response?.data?.message || 'パスワード変更に失敗しました';
      if (message.includes('現在のパスワード') || message.includes('current')) {
        setErrors({ current_password: '現在のパスワードが正しくありません' });
      } else {
        toast.error(message);
      }
    },
  });

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    if (validate()) {
      mutation.mutate(form);
    }
  };

  const updateField = (field: keyof typeof form, value: string) => {
    setForm((prev) => ({ ...prev, [field]: value }));
    // Clear field error on change
    if (errors[field]) {
      setErrors((prev) => {
        const next = { ...prev };
        delete next[field];
        return next;
      });
    }
  };

  return (
    <div className="space-y-6">
      {/* Page Header */}
      <div>
        <h1 className="text-2xl font-bold text-[var(--neutral-foreground-1)]">パスワード変更</h1>
        <p className="mt-1 text-sm text-[var(--neutral-foreground-3)]">
          新しいパスワードを設定してください
        </p>
      </div>

      <div className="mx-auto max-w-md">
        <Card>
          <CardHeader>
            <CardTitle>
              <span className="flex items-center gap-2">
                <MaterialIcon name="lock" size={16} className="text-[var(--brand-80)]" />
                パスワード変更
              </span>
            </CardTitle>
          </CardHeader>

          <form onSubmit={handleSubmit} className="space-y-4">
            {/* Current password */}
            <div className="relative">
              <Input
                label="現在のパスワード *"
                type={showCurrentPassword ? 'text' : 'password'}
                value={form.current_password}
                onChange={(e) => updateField('current_password', e.target.value)}
                error={errors.current_password}
                placeholder="現在のパスワードを入力"
              />
              <button
                type="button"
                className="absolute right-3 top-[34px] text-[var(--neutral-foreground-4)] hover:text-[var(--neutral-foreground-2)]"
                onClick={() => setShowCurrentPassword(!showCurrentPassword)}
              >
                {showCurrentPassword ? <MaterialIcon name="visibility_off" size={16} /> : <MaterialIcon name="visibility" size={16} />}
              </button>
            </div>

            {/* New password */}
            <div className="relative">
              <Input
                label="新しいパスワード *"
                type={showNewPassword ? 'text' : 'password'}
                value={form.new_password}
                onChange={(e) => updateField('new_password', e.target.value)}
                error={errors.new_password}
                helperText="8文字以上で設定してください"
                placeholder="新しいパスワードを入力"
              />
              <button
                type="button"
                className="absolute right-3 top-[34px] text-[var(--neutral-foreground-4)] hover:text-[var(--neutral-foreground-2)]"
                onClick={() => setShowNewPassword(!showNewPassword)}
              >
                {showNewPassword ? <MaterialIcon name="visibility_off" size={16} /> : <MaterialIcon name="visibility" size={16} />}
              </button>
            </div>

            {/* Confirm password */}
            <div className="relative">
              <Input
                label="新しいパスワード（確認） *"
                type={showConfirmPassword ? 'text' : 'password'}
                value={form.new_password_confirmation}
                onChange={(e) => updateField('new_password_confirmation', e.target.value)}
                error={errors.new_password_confirmation}
                placeholder="もう一度入力してください"
              />
              <button
                type="button"
                className="absolute right-3 top-[34px] text-[var(--neutral-foreground-4)] hover:text-[var(--neutral-foreground-2)]"
                onClick={() => setShowConfirmPassword(!showConfirmPassword)}
              >
                {showConfirmPassword ? <MaterialIcon name="visibility_off" size={16} /> : <MaterialIcon name="visibility" size={16} />}
              </button>
            </div>

            {/* Buttons */}
            <div className="flex gap-3 pt-2">
              <Button
                type="button"
                variant="secondary"
                className="flex-1"
                leftIcon={<MaterialIcon name="arrow_back" size={16} />}
                onClick={() => router.push('/guardian/dashboard')}
              >
                キャンセル
              </Button>
              <Button
                type="submit"
                variant="primary"
                className="flex-1"
                isLoading={mutation.isPending}
                leftIcon={<MaterialIcon name="lock" size={16} />}
              >
                変更する
              </Button>
            </div>
          </form>
        </Card>
      </div>
    </div>
  );
}
