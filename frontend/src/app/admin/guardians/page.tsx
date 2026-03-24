'use client';

import { useState } from 'react';
import { useMutation, useQueryClient } from '@tanstack/react-query';
import api from '@/lib/api';
import { usePagination } from '@/hooks/usePagination';
import { useDebounce } from '@/hooks/useDebounce';
import { Button } from '@/components/ui/Button';
import { Input } from '@/components/ui/Input';
import { Badge } from '@/components/ui/Badge';
import { Modal } from '@/components/ui/Modal';
import { Table, type Column } from '@/components/ui/Table';
import { useToast } from '@/components/ui/Toast';
import { Search, Plus, Pencil, Trash2, Mail, Phone } from 'lucide-react';

interface Guardian {
  id: number;
  full_name: string;
  username: string;
  email: string | null;
  phone: string | null;
  is_active: boolean;
  classroom_name: string;
  students: { id: number; student_name: string }[];
  last_login_at: string | null;
}

export default function AdminGuardiansPage() {
  const queryClient = useQueryClient();
  const toast = useToast();
  const [search, setSearch] = useState('');
  const [modalOpen, setModalOpen] = useState(false);
  const [editing, setEditing] = useState<Guardian | null>(null);
  const [form, setForm] = useState({ full_name: '', username: '', email: '', phone: '', password: '', classroom_id: '', is_active: true });
  const debouncedSearch = useDebounce(search, 300);

  const { data: guardians, meta, isLoading, goToPage } = usePagination<Guardian>({
    endpoint: '/api/admin/guardians',
    queryKey: ['admin', 'guardians'],
    params: { search: debouncedSearch || undefined },
  });

  const saveMutation = useMutation({
    mutationFn: async (data: typeof form) => {
      if (editing) return api.put(`/api/admin/guardians/${editing.id}`, data);
      return api.post('/api/admin/guardians', data);
    },
    onSuccess: () => { queryClient.invalidateQueries({ queryKey: ['admin', 'guardians'] }); toast.success('保存しました'); closeModal(); },
    onError: () => toast.error('保存に失敗しました'),
  });

  const deleteMutation = useMutation({
    mutationFn: (id: number) => api.delete(`/api/admin/guardians/${id}`),
    onSuccess: () => { queryClient.invalidateQueries({ queryKey: ['admin', 'guardians'] }); toast.success('削除しました'); },
    onError: () => toast.error('削除に失敗しました'),
  });

  const closeModal = () => { setModalOpen(false); setEditing(null); setForm({ full_name: '', username: '', email: '', phone: '', password: '', classroom_id: '', is_active: true }); };

  const openEdit = (g: Guardian) => {
    setEditing(g);
    setForm({ full_name: g.full_name, username: g.username, email: g.email || '', phone: g.phone || '', password: '', classroom_id: '', is_active: g.is_active });
    setModalOpen(true);
  };

  const columns: Column<Guardian>[] = [
    { key: 'full_name', label: '氏名', render: (g) => <span className="font-medium">{g.full_name}</span> },
    {
      key: 'contact', label: '連絡先', render: (g) => (
        <div className="space-y-0.5">
          {g.email && <div className="flex items-center gap-1 text-sm text-[var(--neutral-foreground-3)]"><Mail className="h-3 w-3" />{g.email}</div>}
          {g.phone && <div className="flex items-center gap-1 text-sm text-[var(--neutral-foreground-3)]"><Phone className="h-3 w-3" />{g.phone}</div>}
        </div>
      ),
    },
    { key: 'classroom_name', label: '事業所' },
    {
      key: 'students', label: '生徒', render: (g) => g.students.length > 0 ? (
        <div className="flex flex-wrap gap-1">{g.students.map((s) => <Badge key={s.id} variant="primary">{s.student_name}</Badge>)}</div>
      ) : <span className="text-[var(--neutral-foreground-4)]">-</span>,
    },
    { key: 'is_active', label: 'ステータス', render: (g) => <Badge variant={g.is_active ? 'success' : 'danger'} dot>{g.is_active ? '有効' : '無効'}</Badge> },
    {
      key: 'actions', label: '操作', render: (g) => (
        <div className="flex gap-1">
          <Button variant="ghost" size="sm" onClick={() => openEdit(g)}><Pencil className="h-4 w-4" /></Button>
          <Button variant="ghost" size="sm" onClick={() => { if (confirm('削除しますか？')) deleteMutation.mutate(g.id); }}><Trash2 className="h-4 w-4 text-red-500" /></Button>
        </div>
      ),
    },
  ];

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-bold text-[var(--neutral-foreground-1)]">保護者管理</h1>
        <Button onClick={() => { setEditing(null); setModalOpen(true); }} leftIcon={<Plus className="h-4 w-4" />}>追加</Button>
      </div>

      <div className="relative">
        <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-[var(--neutral-foreground-4)]" />
        <Input placeholder="名前・メールで検索..." value={search} onChange={(e) => setSearch(e.target.value)} className="pl-10" />
      </div>

      <Table columns={columns} data={guardians} keyExtractor={(g) => g.id} isLoading={isLoading} currentPage={meta?.current_page} totalPages={meta?.last_page} onPageChange={goToPage} emptyMessage="保護者がいません" />

      <Modal isOpen={modalOpen} onClose={closeModal} title={editing ? '保護者を編集' : '保護者を追加'} size="lg">
        <form onSubmit={(e) => { e.preventDefault(); saveMutation.mutate(form); }} className="space-y-4">
          <Input label="氏名" value={form.full_name} onChange={(e) => setForm({ ...form, full_name: e.target.value })} required />
          <Input label="ユーザー名" value={form.username} onChange={(e) => setForm({ ...form, username: e.target.value })} required={!editing} disabled={!!editing} />
          <Input label="メールアドレス" type="email" value={form.email} onChange={(e) => setForm({ ...form, email: e.target.value })} />
          <Input label="電話番号" value={form.phone} onChange={(e) => setForm({ ...form, phone: e.target.value })} />
          <Input label={editing ? '新しいパスワード（変更時のみ）' : 'パスワード'} type="password" value={form.password} onChange={(e) => setForm({ ...form, password: e.target.value })} required={!editing} />
          <label className="flex items-center gap-2"><input type="checkbox" checked={form.is_active} onChange={(e) => setForm({ ...form, is_active: e.target.checked })} className="rounded border-[var(--neutral-stroke-1)]" /><span className="text-sm text-[var(--neutral-foreground-2)]">有効</span></label>
          <div className="flex justify-end gap-2 pt-2">
            <Button variant="secondary" type="button" onClick={closeModal}>キャンセル</Button>
            <Button type="submit" isLoading={saveMutation.isPending}>保存</Button>
          </div>
        </form>
      </Modal>
    </div>
  );
}
