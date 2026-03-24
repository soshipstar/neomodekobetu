'use client';

import { useState, useEffect } from 'react';
import { useQuery, useMutation } from '@tanstack/react-query';
import api from '@/lib/api';
import { Card, CardHeader, CardTitle } from '@/components/ui/Card';
import { Button } from '@/components/ui/Button';
import { Input } from '@/components/ui/Input';
import { Badge } from '@/components/ui/Badge';
import { useToast } from '@/components/ui/Toast';
import { MaterialIcon } from '@/components/ui/MaterialIcon';

interface StudentProfile {
  id: number;
  student_name: string;
  username: string;
  birth_date: string | null;
  grade_level: string;
  classroom_name: string;
  guardian_name: string | null;
  scheduled_days: string[];
}

const gradeLabels: Record<string, string> = {
  preschool: '未就学',
  elementary: '小学生',
  middle: '中学生',
  high: '高校生',
  other: 'その他',
};

const dayLabels: Record<string, string> = {
  monday: '月',
  tuesday: '火',
  wednesday: '水',
  thursday: '木',
  friday: '金',
  saturday: '土',
  sunday: '日',
};

export default function StudentProfilePage() {
  const toast = useToast();
  const [passwordForm, setPasswordForm] = useState({ current_password: '', new_password: '', new_password_confirmation: '' });

  const { data: profile, isLoading } = useQuery({
    queryKey: ['student', 'profile'],
    queryFn: async () => {
      const res = await api.get<{ data: StudentProfile }>('/api/student/profile');
      return res.data.data;
    },
  });

  const changePasswordMutation = useMutation({
    mutationFn: (data: typeof passwordForm) => api.put('/api/student/profile/password', data),
    onSuccess: () => {
      toast.success('パスワードを変更しました');
      setPasswordForm({ current_password: '', new_password: '', new_password_confirmation: '' });
    },
    onError: () => toast.error('パスワード変更に失敗しました'),
  });

  if (isLoading) {
    return (
      <div className="space-y-6">
        <h1 className="text-2xl font-bold text-[var(--neutral-foreground-1)]">プロフィール</h1>
        <div className="animate-pulse space-y-4">
          <div className="h-48 rounded-xl bg-[var(--neutral-background-5)]" />
          <div className="h-48 rounded-xl bg-[var(--neutral-background-5)]" />
        </div>
      </div>
    );
  }

  return (
    <div className="space-y-6">
      <h1 className="text-2xl font-bold text-[var(--neutral-foreground-1)]">プロフィール</h1>

      {/* Profile Card */}
      <Card>
        <div className="flex flex-col items-center text-center sm:flex-row sm:text-left sm:items-start gap-6">
          <div className="flex h-20 w-20 items-center justify-center rounded-full bg-[var(--brand-160)] shrink-0">
            <span className="text-2xl font-bold text-[var(--brand-80)]">
              {profile?.student_name?.charAt(0) || '?'}
            </span>
          </div>
          <div className="flex-1">
            <h2 className="text-xl font-bold text-[var(--neutral-foreground-1)]">{profile?.student_name}</h2>
            <div className="mt-2 flex flex-wrap justify-center gap-2 sm:justify-start">
              <Badge variant="primary">{gradeLabels[profile?.grade_level || ''] || profile?.grade_level}</Badge>
              <Badge variant="info">{profile?.classroom_name}</Badge>
            </div>
          </div>
        </div>
      </Card>

      {/* Info */}
      <Card>
        <CardHeader>
          <CardTitle>
            <div className="flex items-center gap-2">
              <MaterialIcon name="person" size={20} />
              基本情報
            </div>
          </CardTitle>
        </CardHeader>
        <div className="space-y-3">
          <div className="flex items-center justify-between rounded-lg bg-[var(--neutral-background-3)] p-3">
            <span className="text-sm text-[var(--neutral-foreground-3)]">ユーザー名</span>
            <span className="font-medium text-[var(--neutral-foreground-1)]">{profile?.username}</span>
          </div>
          {profile?.birth_date && (
            <div className="flex items-center justify-between rounded-lg bg-[var(--neutral-background-3)] p-3">
              <span className="text-sm text-[var(--neutral-foreground-3)] flex items-center gap-1"><MaterialIcon name="calendar_month" size={12} />生年月日</span>
              <span className="font-medium text-[var(--neutral-foreground-1)]">{new Date(profile.birth_date).toLocaleDateString('ja-JP')}</span>
            </div>
          )}
          <div className="flex items-center justify-between rounded-lg bg-[var(--neutral-background-3)] p-3">
            <span className="text-sm text-[var(--neutral-foreground-3)] flex items-center gap-1"><MaterialIcon name="school" size={12} />事業所</span>
            <span className="font-medium text-[var(--neutral-foreground-1)]">{profile?.classroom_name}</span>
          </div>
          {profile?.guardian_name && (
            <div className="flex items-center justify-between rounded-lg bg-[var(--neutral-background-3)] p-3">
              <span className="text-sm text-[var(--neutral-foreground-3)]">保護者</span>
              <span className="font-medium text-[var(--neutral-foreground-1)]">{profile.guardian_name}</span>
            </div>
          )}
          <div className="rounded-lg bg-[var(--neutral-background-3)] p-3">
            <span className="text-sm text-[var(--neutral-foreground-3)] block mb-2">通所曜日</span>
            <div className="flex gap-2">
              {Object.entries(dayLabels).map(([key, label]) => (
                <div
                  key={key}
                  className={`flex h-10 w-10 items-center justify-center rounded-full text-sm font-medium ${
                    profile?.scheduled_days?.includes(key)
                      ? 'bg-[var(--brand-80)] text-white'
                      : 'bg-[var(--neutral-background-5)] text-[var(--neutral-foreground-4)]'
                  }`}
                >
                  {label}
                </div>
              ))}
            </div>
          </div>
        </div>
      </Card>

      {/* Password */}
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
          <Input label="現在のパスワード" type="password" value={passwordForm.current_password} onChange={(e) => setPasswordForm({ ...passwordForm, current_password: e.target.value })} required />
          <Input label="新しいパスワード" type="password" value={passwordForm.new_password} onChange={(e) => setPasswordForm({ ...passwordForm, new_password: e.target.value })} required helperText="6文字以上で入力してください" />
          <Input
            label="新しいパスワード（確認）"
            type="password"
            value={passwordForm.new_password_confirmation}
            onChange={(e) => setPasswordForm({ ...passwordForm, new_password_confirmation: e.target.value })}
            required
            error={passwordForm.new_password_confirmation && passwordForm.new_password !== passwordForm.new_password_confirmation ? '新しいパスワードが一致しません' : undefined}
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
