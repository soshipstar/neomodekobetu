'use client';

import { useState, useEffect } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import api from '@/lib/api';
import { Card, CardHeader, CardTitle } from '@/components/ui/Card';
import { Button } from '@/components/ui/Button';
import { Input } from '@/components/ui/Input';
import { useToast } from '@/components/ui/Toast';
import { MaterialIcon } from '@/components/ui/MaterialIcon';
import { NotificationToggleCard } from '@/components/notifications/NotificationToggleCard';

interface Profile {
  id: number;
  full_name: string;
  email: string | null;
  username: string;
  classroom: { id: number; classroom_name: string } | null;
  last_login_at: string | null;
}

export default function StaffProfilePage() {
  const queryClient = useQueryClient();
  const toast = useToast();
  const [profileForm, setProfileForm] = useState({ full_name: '', email: '' });
  const [passwordForm, setPasswordForm] = useState({ current_password: '', new_password: '', new_password_confirmation: '' });

  const { data: profile, isLoading } = useQuery({
    queryKey: ['staff', 'profile'],
    queryFn: async () => {
      const res = await api.get<{ data: Profile }>('/api/staff/profile');
      return res.data.data;
    },
  });

  useEffect(() => {
    if (profile) {
      setProfileForm({ full_name: profile.full_name, email: profile.email || '' });
    }
  }, [profile]);

  const updateProfileMutation = useMutation({
    mutationFn: (data: typeof profileForm) => api.put('/api/staff/profile', data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['staff', 'profile'] });
      toast.success('プロフィールを更新しました');
    },
    onError: () => toast.error('更新に失敗しました'),
  });

  const changePasswordMutation = useMutation({
    mutationFn: (data: typeof passwordForm) => api.put('/api/staff/profile/password', data),
    onSuccess: () => {
      toast.success('パスワードを変更しました');
      setPasswordForm({ current_password: '', new_password: '', new_password_confirmation: '' });
    },
    onError: () => toast.error('パスワード変更に失敗しました。現在のパスワードを確認してください。'),
  });

  if (isLoading) {
    return (
      <div className="space-y-6">
        <h1 className="text-2xl font-bold text-[var(--neutral-foreground-1)]">プロフィール</h1>
        <div className="animate-pulse space-y-4">
          <div className="h-48 rounded-xl bg-[var(--neutral-background-4)]" />
          <div className="h-48 rounded-xl bg-[var(--neutral-background-4)]" />
        </div>
      </div>
    );
  }

  return (
    <div className="space-y-6">
      <h1 className="text-2xl font-bold text-[var(--neutral-foreground-1)]">プロフィール</h1>

      {/* Web Push 通知設定 */}
      <NotificationToggleCard />

      {/* Profile Info */}
      <Card>
        <CardHeader>
          <CardTitle>
            <div className="flex items-center gap-2">
              <MaterialIcon name="person" size={20} />
              基本情報
            </div>
          </CardTitle>
        </CardHeader>
        <form onSubmit={(e) => { e.preventDefault(); updateProfileMutation.mutate(profileForm); }} className="space-y-4">
          <div className="rounded-lg bg-[var(--neutral-background-2)] p-3">
            <p className="text-sm text-[var(--neutral-foreground-3)]">ユーザー名</p>
            <p className="font-medium text-[var(--neutral-foreground-1)]">{profile?.username}</p>
          </div>
          <div className="rounded-lg bg-[var(--neutral-background-2)] p-3">
            <p className="text-sm text-[var(--neutral-foreground-3)]">所属事業所</p>
            <p className="font-medium text-[var(--neutral-foreground-1)]">{profile?.classroom?.classroom_name || '-'}</p>
          </div>
          <Input
            label="氏名"
            value={profileForm.full_name}
            onChange={(e) => setProfileForm({ ...profileForm, full_name: e.target.value })}
            required
          />
          <Input
            label="メールアドレス"
            type="email"
            value={profileForm.email}
            onChange={(e) => setProfileForm({ ...profileForm, email: e.target.value })}
          />
          {profile?.last_login_at && (
            <p className="text-sm text-[var(--neutral-foreground-3)]">
              最終ログイン: {new Date(profile.last_login_at).toLocaleString('ja-JP')}
            </p>
          )}
          <div className="flex justify-end">
            <Button type="submit" isLoading={updateProfileMutation.isPending} leftIcon={<MaterialIcon name="save" size={16} />}>
              保存
            </Button>
          </div>
        </form>
      </Card>

      {/* Password Change */}
      <Card>
        <CardHeader>
          <CardTitle>
            <div className="flex items-center gap-2">
              <MaterialIcon name="lock" size={20} />
              パスワード変更
            </div>
          </CardTitle>
        </CardHeader>
        <form onSubmit={(e) => { e.preventDefault(); changePasswordMutation.mutate(passwordForm); }} className="space-y-4">
          <Input
            label="現在のパスワード"
            type="password"
            value={passwordForm.current_password}
            onChange={(e) => setPasswordForm({ ...passwordForm, current_password: e.target.value })}
            required
          />
          <Input
            label="新しいパスワード"
            type="password"
            value={passwordForm.new_password}
            onChange={(e) => setPasswordForm({ ...passwordForm, new_password: e.target.value })}
            required
            helperText="8文字以上で入力してください"
          />
          <Input
            label="新しいパスワード（確認）"
            type="password"
            value={passwordForm.new_password_confirmation}
            onChange={(e) => setPasswordForm({ ...passwordForm, new_password_confirmation: e.target.value })}
            required
            error={passwordForm.new_password_confirmation && passwordForm.new_password !== passwordForm.new_password_confirmation ? 'パスワードが一致しません' : undefined}
          />
          <div className="flex justify-end">
            <Button
              type="submit"
              variant="danger"
              isLoading={changePasswordMutation.isPending}
              disabled={!passwordForm.current_password || !passwordForm.new_password || passwordForm.new_password !== passwordForm.new_password_confirmation}
            >
              パスワードを変更
            </Button>
          </div>
        </form>
      </Card>
    </div>
  );
}
