'use client';

import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import api from '@/lib/api';
import { Card } from '@/components/ui/Card';
import { Button } from '@/components/ui/Button';
import { Input } from '@/components/ui/Input';
import { Badge } from '@/components/ui/Badge';
import { Modal } from '@/components/ui/Modal';
import { Table, type Column } from '@/components/ui/Table';
import { SkeletonTable } from '@/components/ui/Skeleton';
import { useToast } from '@/components/ui/Toast';
import { MaterialIcon } from '@/components/ui/MaterialIcon';

interface TabletAccount {
  id: number;
  username: string;
  full_name: string;
  password_plain: string | null;
  classroom_id: number;
  classroom?: { id: number; classroom_name: string } | null;
  is_active: boolean;
  last_login_at: string | null;
  created_at: string;
}

interface ClassroomOption {
  id: number;
  classroom_name: string;
}

export default function AdminTabletAccountsPage() {
  const queryClient = useQueryClient();
  const toast = useToast();
  const [modalOpen, setModalOpen] = useState(false);
  const [form, setForm] = useState({ username: '', full_name: '', password: '', classroom_id: '' });

  // タブレットアカウント一覧
  const { data: accounts = [], isLoading } = useQuery({
    queryKey: ['admin', 'tablet-accounts'],
    queryFn: async () => {
      const res = await api.get<{ data: TabletAccount[] }>('/api/admin/tablet-accounts');
      return res.data.data;
    },
  });

  // 教室一覧 (作成モーダル用 - 自社全教室を企業管理者にも見せる)
  const { data: classrooms = [] } = useQuery({
    queryKey: ['admin', 'classrooms'],
    queryFn: async () => {
      const res = await api.get<{ data: ClassroomOption[] }>('/api/admin/classrooms');
      return res.data.data;
    },
  });

  // モーダル open 時、デフォルトで先頭教室を選択
  const openCreateModal = () => {
    setForm({
      username: '',
      full_name: '',
      password: '',
      classroom_id: classrooms[0] ? String(classrooms[0].id) : '',
    });
    setModalOpen(true);
  };

  const createMutation = useMutation({
    mutationFn: (data: typeof form) =>
      api.post('/api/admin/tablet-accounts', {
        ...data,
        classroom_id: Number(data.classroom_id),
      }),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['admin', 'tablet-accounts'] });
      toast.success('タブレットアカウントを作成しました');
      setModalOpen(false);
      setForm({ username: '', full_name: '', password: '', classroom_id: '' });
    },
    onError: (err: unknown) => {
      const e = err as { response?: { data?: { message?: string; errors?: Record<string, string[]> } } };
      const errors = e?.response?.data?.errors;
      if (errors) {
        const first = Object.values(errors)[0];
        toast.error(Array.isArray(first) ? first[0] : String(first));
      } else {
        toast.error(e?.response?.data?.message || '作成に失敗しました');
      }
    },
  });

  const toggleMutation = useMutation({
    mutationFn: ({ id }: { id: number }) => api.post(`/api/admin/tablet-accounts/${id}/toggle`),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['admin', 'tablet-accounts'] });
      toast.success('ステータスを更新しました');
    },
    onError: (err: unknown) => {
      const e = err as { response?: { data?: { message?: string } } };
      toast.error(e?.response?.data?.message || '更新に失敗しました');
    },
  });

  // パスワードリセット (既存アカウントに新しい平文パスワードを生成 → 保存)
  const resetPasswordMutation = useMutation({
    mutationFn: async ({ id }: { id: number }) => {
      const chars = 'abcdefghijkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789';
      let pw = '';
      for (let i = 0; i < 10; i++) pw += chars[Math.floor(Math.random() * chars.length)];
      const res = await api.put(`/api/admin/tablet-accounts/${id}`, { password: pw });
      return { pw, res };
    },
    onSuccess: ({ pw }) => {
      queryClient.invalidateQueries({ queryKey: ['admin', 'tablet-accounts'] });
      toast.success(`新しいパスワード: ${pw}`);
      navigator.clipboard.writeText(pw).catch(() => undefined);
    },
    onError: (err: unknown) => {
      const e = err as { response?: { data?: { message?: string } } };
      toast.error(e?.response?.data?.message || 'パスワード再発行に失敗しました');
    },
  });

  const columns: Column<TabletAccount>[] = [
    {
      key: 'full_name',
      label: 'アカウント名',
      render: (a) => (
        <div className="flex items-center gap-2">
          <MaterialIcon name="tablet" size={16} className="text-[var(--neutral-foreground-4)]" />
          <span className="font-medium text-[var(--neutral-foreground-1)]">{a.full_name}</span>
        </div>
      ),
    },
    { key: 'username', label: 'ユーザー名' },
    {
      key: 'password_plain',
      label: 'パスワード',
      render: (a) => (
        a.password_plain ? (
          <div className="flex items-center gap-1.5">
            <code className="rounded bg-[var(--neutral-background-3)] px-1.5 py-0.5 font-mono text-xs text-[var(--neutral-foreground-1)]">
              {a.password_plain}
            </code>
            <button
              type="button"
              onClick={() => {
                navigator.clipboard.writeText(a.password_plain!);
                toast.success('パスワードをコピーしました');
              }}
              className="rounded p-1 text-[var(--neutral-foreground-4)] hover:bg-[var(--neutral-background-3)] hover:text-[var(--neutral-foreground-1)]"
              title="パスワードをコピー"
            >
              <MaterialIcon name="content_copy" size={14} />
            </button>
          </div>
        ) : (
          <span className="text-xs text-[var(--neutral-foreground-4)]">（再設定が必要）</span>
        )
      ),
    },
    { key: 'classroom_name', label: '事業所', render: (a) => a.classroom?.classroom_name || '-' },
    {
      key: 'is_active',
      label: 'ステータス',
      render: (a) => (
        <Badge variant={a.is_active ? 'success' : 'danger'} dot>
          {a.is_active ? '有効' : '無効'}
        </Badge>
      ),
    },
    {
      key: 'last_login_at',
      label: '最終利用',
      render: (a) => a.last_login_at ? new Date(a.last_login_at).toLocaleString('ja-JP') : '未使用',
    },
    {
      key: 'actions',
      label: '操作',
      render: (a) => (
        <div className="flex flex-wrap gap-1">
          <Button
            variant="outline"
            size="sm"
            onClick={() => {
              if (confirm(`「${a.full_name}」のパスワードを再発行します。よろしいですか？\n\n新しいパスワードは画面に表示され、自動でクリップボードにコピーされます。`)) {
                resetPasswordMutation.mutate({ id: a.id });
              }
            }}
            isLoading={resetPasswordMutation.isPending}
            leftIcon={<MaterialIcon name="key" size={14} />}
          >
            パスワード再発行
          </Button>
          <Button
            variant={a.is_active ? 'outline' : 'primary'}
            size="sm"
            onClick={() => toggleMutation.mutate({ id: a.id })}
            leftIcon={a.is_active ? <MaterialIcon name="power_off" size={14} /> : <MaterialIcon name="power_settings_new" size={14} />}
          >
            {a.is_active ? '無効にする' : '有効にする'}
          </Button>
        </div>
      ),
    },
  ];

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-bold text-[var(--neutral-foreground-1)]">タブレットアカウント管理</h1>
        <Button onClick={openCreateModal} leftIcon={<MaterialIcon name="add" size={16} />}>
          アカウント作成
        </Button>
      </div>

      <div className="grid gap-4 sm:grid-cols-3">
        <Card>
          <div className="text-center">
            <p className="text-sm text-[var(--neutral-foreground-3)]">全アカウント</p>
            <p className="text-3xl font-bold text-[var(--neutral-foreground-1)]">{accounts.length}</p>
          </div>
        </Card>
        <Card>
          <div className="text-center">
            <p className="text-sm text-[var(--neutral-foreground-3)]">有効</p>
            <p className="text-3xl font-bold text-green-600">{accounts.filter((a) => a.is_active).length}</p>
          </div>
        </Card>
        <Card>
          <div className="text-center">
            <p className="text-sm text-[var(--neutral-foreground-3)]">無効</p>
            <p className="text-3xl font-bold text-red-600">{accounts.filter((a) => !a.is_active).length}</p>
          </div>
        </Card>
      </div>

      {isLoading ? (
        <SkeletonTable rows={5} cols={6} />
      ) : (
        <Table columns={columns} data={accounts} keyExtractor={(a) => a.id} emptyMessage="タブレットアカウントがありません" />
      )}

      <Modal isOpen={modalOpen} onClose={() => setModalOpen(false)} title="タブレットアカウント作成">
        <form
          onSubmit={(e) => {
            e.preventDefault();
            if (!form.classroom_id) {
              toast.error('事業所を選択してください');
              return;
            }
            createMutation.mutate(form);
          }}
          className="space-y-4"
        >
          {/* 事業所セレクタ (主因の修正): これが無いと BE で classroom_id 必須エラーになる */}
          <div>
            <label className="mb-1 block text-sm font-medium text-[var(--neutral-foreground-2)]">事業所 *</label>
            <select
              value={form.classroom_id}
              onChange={(e) => setForm({ ...form, classroom_id: e.target.value })}
              className="block w-full rounded-md border border-[var(--neutral-stroke-1)] bg-[var(--neutral-background-1)] px-3 py-1.5 text-sm text-[var(--neutral-foreground-1)] focus:border-[var(--brand-80)] focus:outline-none focus:ring-1 focus:ring-[var(--brand-80)]"
              required
            >
              <option value="">選択してください</option>
              {classrooms.map((c) => (
                <option key={c.id} value={c.id}>
                  {c.classroom_name}
                </option>
              ))}
            </select>
            {classrooms.length === 0 && (
              <p className="mt-1 text-xs text-[var(--status-warning-fg)]">
                管理可能な事業所がありません。
              </p>
            )}
          </div>
          <Input
            label="表示名"
            value={form.full_name}
            onChange={(e) => setForm({ ...form, full_name: e.target.value })}
            required
            placeholder="例: 1F タブレット"
          />
          <Input
            label="ユーザー名"
            value={form.username}
            onChange={(e) => setForm({ ...form, username: e.target.value })}
            required
            placeholder="例: tablet_1f"
          />
          <Input
            label="パスワード"
            type="password"
            value={form.password}
            onChange={(e) => setForm({ ...form, password: e.target.value })}
            required
          />
          <div className="flex justify-end gap-2 pt-2">
            <Button variant="secondary" type="button" onClick={() => setModalOpen(false)}>キャンセル</Button>
            <Button type="submit" isLoading={createMutation.isPending}>作成</Button>
          </div>
        </form>
      </Modal>
    </div>
  );
}
