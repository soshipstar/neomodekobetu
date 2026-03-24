'use client';

import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import api from '@/lib/api';
import { Card, CardHeader, CardTitle } from '@/components/ui/Card';
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
  display_name: string;
  classroom_id: number;
  classroom_name: string;
  is_active: boolean;
  last_login_at: string | null;
  created_at: string;
}

export default function AdminTabletAccountsPage() {
  const queryClient = useQueryClient();
  const toast = useToast();
  const [modalOpen, setModalOpen] = useState(false);
  const [form, setForm] = useState({ username: '', display_name: '', password: '', classroom_id: '' });

  const { data: accounts = [], isLoading } = useQuery({
    queryKey: ['admin', 'tablet-accounts'],
    queryFn: async () => {
      const res = await api.get<{ data: TabletAccount[] }>('/api/admin/tablet-accounts');
      return res.data.data;
    },
  });

  const createMutation = useMutation({
    mutationFn: (data: typeof form) => api.post('/api/admin/tablet-accounts', data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['admin', 'tablet-accounts'] });
      toast.success('タブレットアカウントを作成しました');
      setModalOpen(false);
      setForm({ username: '', display_name: '', password: '', classroom_id: '' });
    },
    onError: () => toast.error('作成に失敗しました'),
  });

  const toggleMutation = useMutation({
    mutationFn: ({ id, is_active }: { id: number; is_active: boolean }) =>
      api.put(`/api/admin/tablet-accounts/${id}`, { is_active }),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['admin', 'tablet-accounts'] });
      toast.success('ステータスを更新しました');
    },
    onError: () => toast.error('更新に失敗しました'),
  });

  const columns: Column<TabletAccount>[] = [
    {
      key: 'display_name',
      label: 'アカウント名',
      render: (a) => (
        <div className="flex items-center gap-2">
          <MaterialIcon name="tablet" size={16} className="text-[var(--neutral-foreground-4)]" />
          <span className="font-medium text-[var(--neutral-foreground-1)]">{a.display_name}</span>
        </div>
      ),
    },
    { key: 'username', label: 'ユーザー名' },
    { key: 'classroom_name', label: '事業所' },
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
        <Button
          variant={a.is_active ? 'outline' : 'primary'}
          size="sm"
          onClick={() => toggleMutation.mutate({ id: a.id, is_active: !a.is_active })}
          leftIcon={a.is_active ? <MaterialIcon name="power_off" size={16} /> : <MaterialIcon name="power_settings_new" size={16} />}
        >
          {a.is_active ? '無効にする' : '有効にする'}
        </Button>
      ),
    },
  ];

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-bold text-[var(--neutral-foreground-1)]">タブレットアカウント管理</h1>
        <Button onClick={() => setModalOpen(true)} leftIcon={<MaterialIcon name="add" size={16} />}>
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
        <form onSubmit={(e) => { e.preventDefault(); createMutation.mutate(form); }} className="space-y-4">
          <Input label="表示名" value={form.display_name} onChange={(e) => setForm({ ...form, display_name: e.target.value })} required placeholder="例: 1F タブレット" />
          <Input label="ユーザー名" value={form.username} onChange={(e) => setForm({ ...form, username: e.target.value })} required placeholder="例: tablet_1f" />
          <Input label="パスワード" type="password" value={form.password} onChange={(e) => setForm({ ...form, password: e.target.value })} required />
          <div className="flex justify-end gap-2 pt-2">
            <Button variant="secondary" type="button" onClick={() => setModalOpen(false)}>キャンセル</Button>
            <Button type="submit" isLoading={createMutation.isPending}>作成</Button>
          </div>
        </form>
      </Modal>
    </div>
  );
}
