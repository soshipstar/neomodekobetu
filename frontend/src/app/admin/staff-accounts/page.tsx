'use client';

import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import api from '@/lib/api';
import { Table, type Column } from '@/components/ui/Table';
import { Button } from '@/components/ui/Button';
import { Badge } from '@/components/ui/Badge';
import { Input } from '@/components/ui/Input';
import { Modal } from '@/components/ui/Modal';
import { SkeletonTable } from '@/components/ui/Skeleton';
import { useToast } from '@/components/ui/Toast';
import { useDebounce } from '@/hooks/useDebounce';
import { usePagination } from '@/hooks/usePagination';
import { useMasterGuard } from '@/hooks/useMasterGuard';
import { MaterialIcon } from '@/components/ui/MaterialIcon';

interface Classroom {
  id: number;
  classroom_name: string;
}

interface StaffAccount {
  id: number;
  username: string;
  full_name: string;
  email: string | null;
  is_active: boolean;
  classroom_id: number | null;
  classroom_name: string | null;
  created_at: string;
}

interface StaffAccountFormData {
  username: string;
  password: string;
  full_name: string;
  email: string;
  classroom_id: string;
  is_active: boolean;
}

const emptyFormData: StaffAccountFormData = {
  username: '',
  password: '',
  full_name: '',
  email: '',
  classroom_id: '',
  is_active: true,
};

export default function StaffAccountsPage() {
  const { isMaster, isReady } = useMasterGuard();
  const queryClient = useQueryClient();
  const toast = useToast();
  const [search, setSearch] = useState('');
  const debouncedSearch = useDebounce(search, 300);
  const [modalOpen, setModalOpen] = useState(false);
  const [deleteModalOpen, setDeleteModalOpen] = useState(false);
  const [convertModalOpen, setConvertModalOpen] = useState(false);
  const [editingStaff, setEditingStaff] = useState<StaffAccount | null>(null);
  const [deletingStaff, setDeletingStaff] = useState<StaffAccount | null>(null);
  const [convertingStaff, setConvertingStaff] = useState<StaffAccount | null>(null);
  const [formData, setFormData] = useState<StaffAccountFormData>(emptyFormData);
  const [formErrors, setFormErrors] = useState<Partial<Record<keyof StaffAccountFormData, string>>>({});

  const { data: staffAccounts, meta, isLoading, goToPage } = usePagination<StaffAccount>({
    endpoint: '/api/admin/staff-accounts',
    queryKey: ['admin', 'staff-accounts'],
    params: { search: debouncedSearch || undefined },
  });

  const { data: classroomsData } = useQuery({
    queryKey: ['admin', 'classrooms-list'],
    queryFn: async () => {
      const response = await api.get<{ data: Classroom[] }>('/api/admin/classrooms', {
        params: { per_page: 100 },
      });
      return response.data.data;
    },
  });

  const classrooms = classroomsData ?? [];

  const saveMutation = useMutation({
    mutationFn: async (data: StaffAccountFormData & { id?: number }) => {
      if (data.id) {
        const payload: Record<string, unknown> = {
          full_name: data.full_name,
          email: data.email || null,
          classroom_id: data.classroom_id ? Number(data.classroom_id) : null,
          is_active: data.is_active,
        };
        if (data.password) payload.password = data.password;
        return api.put(`/api/admin/staff-accounts/${data.id}`, payload);
      }
      return api.post('/api/admin/staff-accounts', {
        ...data,
        classroom_id: data.classroom_id ? Number(data.classroom_id) : null,
      });
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['admin', 'staff-accounts'] });
      toast.success(editingStaff ? 'スタッフ情報を更新しました' : 'スタッフを登録しました');
      closeModal();
    },
    onError: () => {
      toast.error('保存に失敗しました');
    },
  });

  const deleteMutation = useMutation({
    mutationFn: async (id: number) => {
      return api.delete(`/api/admin/staff-accounts/${id}`);
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['admin', 'staff-accounts'] });
      toast.success('スタッフを削除しました');
      setDeleteModalOpen(false);
      setDeletingStaff(null);
    },
    onError: () => {
      toast.error('削除に失敗しました');
    },
  });

  const convertMutation = useMutation({
    mutationFn: async (id: number) => {
      return api.post(`/api/admin/staff-accounts/${id}/convert-to-admin`);
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['admin', 'staff-accounts'] });
      queryClient.invalidateQueries({ queryKey: ['admin', 'admin-accounts'] });
      toast.success('管理者アカウントに変換しました');
      setConvertModalOpen(false);
      setConvertingStaff(null);
    },
    onError: () => {
      toast.error('変換に失敗しました');
    },
  });

  function openAddModal() {
    setEditingStaff(null);
    setFormData(emptyFormData);
    setFormErrors({});
    setModalOpen(true);
  }

  function openEditModal(staff: StaffAccount) {
    setEditingStaff(staff);
    setFormData({
      username: staff.username,
      password: '',
      full_name: staff.full_name,
      email: staff.email || '',
      classroom_id: staff.classroom_id ? String(staff.classroom_id) : '',
      is_active: staff.is_active,
    });
    setFormErrors({});
    setModalOpen(true);
  }

  function closeModal() {
    setModalOpen(false);
    setEditingStaff(null);
    setFormData(emptyFormData);
    setFormErrors({});
  }

  function validateForm(): boolean {
    const errors: Partial<Record<keyof StaffAccountFormData, string>> = {};
    if (!formData.full_name.trim()) errors.full_name = '氏名は必須です';
    if (!formData.classroom_id) errors.classroom_id = '所属教室は必須です';
    if (!editingStaff) {
      if (!formData.username.trim()) errors.username = 'ユーザー名は必須です';
      if (!formData.password) errors.password = 'パスワードは必須です';
      else if (formData.password.length < 6) errors.password = 'パスワードは6文字以上です';
    } else if (formData.password && formData.password.length < 6) {
      errors.password = 'パスワードは6文字以上です';
    }
    setFormErrors(errors);
    return Object.keys(errors).length === 0;
  }

  function handleSubmit(e: React.FormEvent) {
    e.preventDefault();
    if (!validateForm()) return;
    saveMutation.mutate({
      ...formData,
      id: editingStaff?.id,
    });
  }

  const columns: Column<StaffAccount>[] = [
    {
      key: 'full_name',
      label: '氏名',
      sortable: true,
      render: (s) => <span className="font-medium">{s.full_name}</span>,
    },
    { key: 'username', label: 'ユーザー名' },
    { key: 'email', label: 'メールアドレス', render: (s) => s.email || '-' },
    {
      key: 'classroom_name',
      label: '所属教室',
      render: (s) => s.classroom_name || '-',
    },
    {
      key: 'is_active',
      label: 'ステータス',
      render: (s) => (
        <Badge variant={s.is_active ? 'success' : 'danger'}>
          {s.is_active ? '有効' : '無効'}
        </Badge>
      ),
    },
    {
      key: 'created_at',
      label: '登録日',
      render: (s) => new Date(s.created_at).toLocaleDateString('ja-JP'),
    },
    {
      key: 'actions',
      label: '操作',
      render: (s) => (
        <div className="flex items-center gap-1 flex-wrap">
          <Button variant="ghost" size="sm" onClick={() => openEditModal(s)} leftIcon={<MaterialIcon name="edit" size={14} />}>
            編集
          </Button>
          <Button
            variant="ghost"
            size="sm"
            onClick={() => { setConvertingStaff(s); setConvertModalOpen(true); }}
            leftIcon={<MaterialIcon name="swap_horiz" size={14} />}
          >
            管理者に変換
          </Button>
          <Button
            variant="ghost"
            size="sm"
            onClick={() => { setDeletingStaff(s); setDeleteModalOpen(true); }}
            leftIcon={<MaterialIcon name="delete" size={14} />}
            className="text-[var(--status-danger-fg)]"
          >
            削除
          </Button>
        </div>
      ),
    },
  ];

  if (!isReady || !isMaster) return null;

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold text-[var(--neutral-foreground-1)]">スタッフアカウント管理</h1>
          <p className="mt-1 text-sm text-[var(--neutral-foreground-3)]">マスター管理者専用</p>
        </div>
        <Button onClick={openAddModal} leftIcon={<MaterialIcon name="add" size={16} />}>
          新規スタッフ登録
        </Button>
      </div>

      <div className="relative">
        <MaterialIcon name="search" size={16} className="absolute left-3 top-1/2 -translate-y-1/2 text-[var(--neutral-foreground-4)]" />
        <Input
          placeholder="氏名・ユーザー名で検索..."
          value={search}
          onChange={(e) => setSearch(e.target.value)}
          className="pl-10"
        />
      </div>

      {isLoading ? (
        <SkeletonTable rows={8} cols={7} />
      ) : (
        <Table
          columns={columns}
          data={staffAccounts}
          keyExtractor={(item) => item.id}
          currentPage={meta?.current_page}
          totalPages={meta?.last_page}
          onPageChange={goToPage}
          emptyMessage="スタッフアカウントが登録されていません"
        />
      )}

      {/* Add/Edit Modal */}
      <Modal
        isOpen={modalOpen}
        onClose={closeModal}
        title={editingStaff ? 'スタッフ情報編集' : '新規スタッフ登録'}
        size="md"
      >
        <form onSubmit={handleSubmit} className="space-y-4">
          <Input
            label="ユーザー名 *"
            value={formData.username}
            onChange={(e) => setFormData({ ...formData, username: e.target.value })}
            disabled={!!editingStaff}
            error={formErrors.username}
            helperText={editingStaff ? 'ユーザー名は変更できません' : 'ログイン時に使用します（半角英数字）'}
          />
          <Input
            label={editingStaff ? '新しいパスワード' : 'パスワード *'}
            type="password"
            value={formData.password}
            onChange={(e) => setFormData({ ...formData, password: e.target.value })}
            error={formErrors.password}
            helperText={editingStaff ? '変更しない場合は空欄にしてください' : '6文字以上'}
          />
          <Input
            label="氏名 *"
            value={formData.full_name}
            onChange={(e) => setFormData({ ...formData, full_name: e.target.value })}
            error={formErrors.full_name}
          />
          <Input
            label="メールアドレス"
            type="email"
            value={formData.email}
            onChange={(e) => setFormData({ ...formData, email: e.target.value })}
          />
          <div className="w-full">
            <label className="mb-1 block text-sm font-medium text-[var(--neutral-foreground-1)]">
              所属教室 *
            </label>
            <select
              value={formData.classroom_id}
              onChange={(e) => setFormData({ ...formData, classroom_id: e.target.value })}
              className="block w-full rounded-md border border-[var(--neutral-stroke-1)] bg-[var(--neutral-background-1)] px-3 py-1.5 text-sm text-[var(--neutral-foreground-1)] focus:border-[var(--brand-80)] focus:outline-none focus:ring-1 focus:ring-[var(--brand-80)]"
            >
              <option value="">選択してください</option>
              {classrooms.map((c) => (
                <option key={c.id} value={c.id}>
                  {c.classroom_name}
                </option>
              ))}
            </select>
            {formErrors.classroom_id && (
              <p className="mt-1 text-xs text-[var(--status-danger-fg)]">{formErrors.classroom_id}</p>
            )}
          </div>
          {editingStaff && (
            <div className="w-full">
              <label className="mb-1 block text-sm font-medium text-[var(--neutral-foreground-1)]">
                ステータス *
              </label>
              <select
                value={formData.is_active ? '1' : '0'}
                onChange={(e) => setFormData({ ...formData, is_active: e.target.value === '1' })}
                className="block w-full rounded-md border border-[var(--neutral-stroke-1)] bg-[var(--neutral-background-1)] px-3 py-1.5 text-sm text-[var(--neutral-foreground-1)] focus:border-[var(--brand-80)] focus:outline-none focus:ring-1 focus:ring-[var(--brand-80)]"
              >
                <option value="1">有効</option>
                <option value="0">無効</option>
              </select>
            </div>
          )}
          <div className="flex justify-end gap-2 pt-2">
            <Button type="button" variant="outline" onClick={closeModal}>
              キャンセル
            </Button>
            <Button type="submit" isLoading={saveMutation.isPending}>
              {editingStaff ? '更新' : '登録'}
            </Button>
          </div>
        </form>
      </Modal>

      {/* Delete Confirmation Modal */}
      <Modal
        isOpen={deleteModalOpen}
        onClose={() => { setDeleteModalOpen(false); setDeletingStaff(null); }}
        title="スタッフ削除の確認"
        size="sm"
      >
        <div className="space-y-4">
          <p className="text-sm text-[var(--neutral-foreground-2)]">
            本当に「<span className="font-semibold">{deletingStaff?.full_name}</span>」を削除しますか？
          </p>
          <p className="text-xs text-[var(--status-danger-fg)]">
            この操作は取り消せません。
          </p>
          <div className="flex justify-end gap-2 pt-2">
            <Button type="button" variant="outline" onClick={() => { setDeleteModalOpen(false); setDeletingStaff(null); }}>
              キャンセル
            </Button>
            <Button
              variant="danger"
              isLoading={deleteMutation.isPending}
              onClick={() => deletingStaff && deleteMutation.mutate(deletingStaff.id)}
            >
              削除する
            </Button>
          </div>
        </div>
      </Modal>

      {/* Convert to Admin Confirmation Modal */}
      <Modal
        isOpen={convertModalOpen}
        onClose={() => { setConvertModalOpen(false); setConvertingStaff(null); }}
        title="管理者への変換確認"
        size="sm"
      >
        <div className="space-y-4">
          <p className="text-sm text-[var(--neutral-foreground-2)]">
            「<span className="font-semibold">{convertingStaff?.full_name}</span>」をスタッフから管理者アカウントに変換しますか？
          </p>
          <p className="text-xs text-[var(--status-warning-fg)]">
            管理者権限が付与され、管理者としてログイン可能になります。
          </p>
          <div className="flex justify-end gap-2 pt-2">
            <Button type="button" variant="outline" onClick={() => { setConvertModalOpen(false); setConvertingStaff(null); }}>
              キャンセル
            </Button>
            <Button
              isLoading={convertMutation.isPending}
              onClick={() => convertingStaff && convertMutation.mutate(convertingStaff.id)}
            >
              変換する
            </Button>
          </div>
        </div>
      </Modal>
    </div>
  );
}
