'use client';

import { useState, useEffect } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import api from '@/lib/api';
import { Card, CardHeader, CardTitle } from '@/components/ui/Card';
import { Button } from '@/components/ui/Button';
import { Input } from '@/components/ui/Input';
import { useToast } from '@/components/ui/Toast';
import { User, Lock, Save, Users } from 'lucide-react';

interface GuardianProfile {
  id: number;
  full_name: string;
  email: string | null;
  username: string;
  students: { id: number; student_name: string; grade_level: string }[];
  classroom?: { id: number; classroom_name: string } | null;
  last_login_at: string | null;
}

export default function GuardianProfilePage() {
  const queryClient = useQueryClient();
  const toast = useToast();
  const [profileForm, setProfileForm] = useState({ full_name: '', email: '' });
  const [passwordForm, setPasswordForm] = useState({ current_password: '', new_password: '', new_password_confirmation: '' });

  const { data: profile, isLoading } = useQuery({
    queryKey: ['guardian', 'profile'],
    queryFn: async () => {
      const res = await api.get<{ data: GuardianProfile }>('/api/guardian/profile');
      return res.data.data;
    },
  });

  useEffect(() => {
    if (profile) {
      setProfileForm({ full_name: profile.full_name, email: profile.email || '' });
    }
  }, [profile]);

  const updateProfileMutation = useMutation({
    mutationFn: (data: typeof profileForm) => api.put('/api/guardian/profile', data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['guardian', 'profile'] });
      toast.success('プロフィールを更新しました');
    },
    onError: () => toast.error('更新に失敗しました'),
  });

  const changePasswordMutation = useMutation({
    mutationFn: (data: typeof passwordForm) => api.put('/api/guardian/profile/password', data),
    onSuccess: () => {
      toast.success('パスワードを変更しました');
      setPasswordForm({ current_password: '', new_password: '', new_password_confirmation: '' });
    },
    onError: () => toast.error('パスワード変更に失敗しました'),
  });

  if (isLoading) {
    return (
      <div className="space-y-6">
        <h1 className="text-2xl font-bold text-gray-900">プロフィール</h1>
        <div className="animate-pulse space-y-4">
          <div className="h-48 rounded-xl bg-gray-200" />
          <div className="h-48 rounded-xl bg-gray-200" />
        </div>
      </div>
    );
  }

  return (
    <div className="space-y-6">
      <h1 className="text-2xl font-bold text-gray-900">プロフィール</h1>

      {/* Linked Students */}
      {profile?.students && profile.students.length > 0 && (
        <Card>
          <CardHeader>
            <CardTitle>
              <div className="flex items-center gap-2">
                <Users className="h-5 w-5" />
                お子さま
              </div>
            </CardTitle>
          </CardHeader>
          <div className="space-y-2">
            {profile.students.map((s) => (
              <div key={s.id} className="flex items-center gap-3 rounded-lg bg-gray-50 p-3">
                <div className="flex h-10 w-10 items-center justify-center rounded-full bg-blue-100">
                  <span className="text-sm font-bold text-blue-600">{s.student_name.charAt(0)}</span>
                </div>
                <div>
                  <p className="font-medium text-gray-900">{s.student_name}</p>
                  <p className="text-xs text-gray-500">{s.grade_level}</p>
                </div>
              </div>
            ))}
          </div>
        </Card>
      )}

      {/* Profile Info */}
      <Card>
        <CardHeader>
          <CardTitle>
            <div className="flex items-center gap-2">
              <User className="h-5 w-5" />
              基本情報
            </div>
          </CardTitle>
        </CardHeader>
        <form onSubmit={(e) => { e.preventDefault(); updateProfileMutation.mutate(profileForm); }} className="space-y-4">
          <div className="rounded-lg bg-gray-50 p-3">
            <p className="text-sm text-gray-500">ユーザー名</p>
            <p className="font-medium text-gray-900">{profile?.username}</p>
          </div>
          <div className="rounded-lg bg-gray-50 p-3">
            <p className="text-sm text-gray-500">所属事業所</p>
            <p className="font-medium text-gray-900">{profile?.classroom?.classroom_name ?? '未設定'}</p>
          </div>
          <Input label="氏名" value={profileForm.full_name} onChange={(e) => setProfileForm({ ...profileForm, full_name: e.target.value })} required />
          <Input label="メールアドレス" type="email" value={profileForm.email} onChange={(e) => setProfileForm({ ...profileForm, email: e.target.value })} />
          <div className="flex justify-end">
            <Button type="submit" isLoading={updateProfileMutation.isPending} leftIcon={<Save className="h-4 w-4" />}>保存</Button>
          </div>
        </form>
      </Card>

      {/* Password */}
      <Card>
        <CardHeader>
          <CardTitle>
            <div className="flex items-center gap-2">
              <Lock className="h-5 w-5" />
              パスワード変更
            </div>
          </CardTitle>
        </CardHeader>
        <form onSubmit={(e) => { e.preventDefault(); changePasswordMutation.mutate(passwordForm); }} className="space-y-4">
          <Input label="現在のパスワード" type="password" value={passwordForm.current_password} onChange={(e) => setPasswordForm({ ...passwordForm, current_password: e.target.value })} required />
          <Input label="新しいパスワード" type="password" value={passwordForm.new_password} onChange={(e) => setPasswordForm({ ...passwordForm, new_password: e.target.value })} required helperText="8文字以上で入力してください" />
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
