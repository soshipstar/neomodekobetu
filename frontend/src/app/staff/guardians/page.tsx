'use client';

import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import api from '@/lib/api';
import { useDebounce } from '@/hooks/useDebounce';
import { Card } from '@/components/ui/Card';
import { Button } from '@/components/ui/Button';
import { Input } from '@/components/ui/Input';
import { Badge } from '@/components/ui/Badge';
import { Modal } from '@/components/ui/Modal';
import { Table, type Column } from '@/components/ui/Table';
import { useToast } from '@/components/ui/Toast';
import { Search, Plus, Pencil, Mail } from 'lucide-react';

interface Guardian {
  id: number;
  full_name: string;
  email: string | null;
  is_active: boolean;
  students: { id: number; student_name: string }[];
  last_login_at: string | null;
  created_at: string;
}

export default function GuardiansPage() {
  const queryClient = useQueryClient();
  const toast = useToast();
  const [search, setSearch] = useState('');
  const [modalOpen, setModalOpen] = useState(false);
  const [editingGuardian, setEditingGuardian] = useState<Guardian | null>(null);
  const [form, setForm] = useState({ full_name: '', email: '', username: '', password: '' });
  const debouncedSearch = useDebounce(search, 300);

  const { data: guardians = [], isLoading } = useQuery({
    queryKey: ['staff', 'guardians', debouncedSearch],
    queryFn: async () => {
      const res = await api.get<{ data: Guardian[] }>('/api/staff/guardians', {
        params: { search: debouncedSearch || undefined },
      });
      return res.data.data;
    },
  });

  const saveMutation = useMutation({
    mutationFn: async (data: typeof form) => {
      if (editingGuardian) {
        return api.put(`/api/staff/guardians/${editingGuardian.id}`, data);
      }
      return api.post('/api/staff/guardians', data);
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['staff', 'guardians'] });
      toast.success(editingGuardian ? '保護者情報を更新しました' : '保護者を追加しました');
      closeModal();
    },
    onError: () => toast.error('保存に失敗しました'),
  });

  const closeModal = () => {
    setModalOpen(false);
    setEditingGuardian(null);
    setForm({ full_name: '', email: '', username: '', password: '' });
  };

  const openEdit = (guardian: Guardian) => {
    setEditingGuardian(guardian);
    setForm({
      full_name: guardian.full_name,
      email: guardian.email || '',
      username: '',
      password: '',
    });
    setModalOpen(true);
  };

  const columns: Column<Guardian>[] = [
    {
      key: 'full_name',
      label: '保護者名',
      sortable: true,
      render: (g) => <span className="font-medium text-[var(--neutral-foreground-1)]">{g.full_name}</span>,
    },
    {
      key: 'contact',
      label: '連絡先',
      render: (g) => (
        <div className="space-y-0.5">
          {g.email ? (
            <div className="flex items-center gap-1 text-sm text-[var(--neutral-foreground-2)]">
              <Mail className="h-3 w-3" /> {g.email}
            </div>
          ) : (
            <span className="text-sm text-[var(--neutral-foreground-4)]">-</span>
          )}
        </div>
      ),
    },
    {
      key: 'students',
      label: 'リンク済み生徒',
      render: (g) =>
        g.students.length > 0 ? (
          <div className="flex flex-wrap gap-1">
            {g.students.map((s) => (
              <Badge key={s.id} variant="primary">{s.student_name}</Badge>
            ))}
          </div>
        ) : (
          <span className="text-sm text-[var(--neutral-foreground-4)]">なし</span>
        ),
    },
    {
      key: 'is_active',
      label: 'ステータス',
      render: (g) => (
        <Badge variant={g.is_active ? 'success' : 'danger'} dot>
          {g.is_active ? '有効' : '無効'}
        </Badge>
      ),
    },
    {
      key: 'last_login_at',
      label: '最終ログイン',
      render: (g) => g.last_login_at
        ? new Date(g.last_login_at).toLocaleDateString('ja-JP')
        : <span className="text-[var(--neutral-foreground-4)]">未ログイン</span>,
    },
    {
      key: 'actions',
      label: '操作',
      render: (g) => (
        <Button variant="ghost" size="sm" onClick={() => openEdit(g)}>
          <Pencil className="h-4 w-4" />
        </Button>
      ),
    },
  ];

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-bold text-[var(--neutral-foreground-1)]">保護者管理</h1>
        <Button onClick={() => { setEditingGuardian(null); setForm({ full_name: '', email: '', username: '', password: '' }); setModalOpen(true); }} leftIcon={<Plus className="h-4 w-4" />}>
          保護者を追加
        </Button>
      </div>

      <div className="relative">
        <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-[var(--neutral-foreground-4)]" />
        <Input
          placeholder="保護者名・メールで検索..."
          value={search}
          onChange={(e) => setSearch(e.target.value)}
          className="pl-10"
        />
      </div>

      <Table
        columns={columns}
        data={guardians}
        keyExtractor={(g) => g.id}
        isLoading={isLoading}
        emptyMessage="保護者が見つかりません"
      />

      <Modal isOpen={modalOpen} onClose={closeModal} title={editingGuardian ? '保護者を編集' : '保護者を追加'} size="lg">
        <form onSubmit={(e) => { e.preventDefault(); saveMutation.mutate(form); }} className="space-y-4">
          <Input label="氏名" value={form.full_name} onChange={(e) => setForm({ ...form, full_name: e.target.value })} required />
          <Input label="メールアドレス" type="email" value={form.email} onChange={(e) => setForm({ ...form, email: e.target.value })} />
          {!editingGuardian && (
            <>
              <Input label="ユーザー名" value={form.username} onChange={(e) => setForm({ ...form, username: e.target.value })} required />
              <Input label="パスワード" type="password" value={form.password} onChange={(e) => setForm({ ...form, password: e.target.value })} required />
            </>
          )}
          {editingGuardian && (
            <Input label="新しいパスワード（変更する場合のみ）" type="password" value={form.password} onChange={(e) => setForm({ ...form, password: e.target.value })} />
          )}
          <div className="flex justify-end gap-2 pt-2">
            <Button variant="secondary" type="button" onClick={closeModal}>キャンセル</Button>
            <Button type="submit" isLoading={saveMutation.isPending}>保存</Button>
          </div>
        </form>
      </Modal>
    </div>
  );
}
