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
import { UserClassroomModal } from '@/components/admin/UserClassroomModal';

interface Classroom {
  id: number;
  classroom_name: string;
}

interface AdminAccount {
  id: number;
  username: string;
  full_name: string;
  email: string | null;
  is_master: boolean;
  is_active: boolean;
  classroom_id: number | null;
  classroom_name: string | null;
  created_at: string;
}

interface AdminFormData {
  username: string;
  password: string;
  full_name: string;
  email: string;
  is_master: boolean;
  classroom_id: string;
  is_active: boolean;
}

const emptyFormData: AdminFormData = {
  username: '',
  password: '',
  full_name: '',
  email: '',
  is_master: false,
  classroom_id: '',
  is_active: true,
};

export default function AdminAccountsPage() {
  const { isMaster, isReady } = useMasterGuard();
  const queryClient = useQueryClient();
  const toast = useToast();
  const [search, setSearch] = useState('');
  const debouncedSearch = useDebounce(search, 300);
  const [modalOpen, setModalOpen] = useState(false);
  const [deleteModalOpen, setDeleteModalOpen] = useState(false);
  const [convertModalOpen, setConvertModalOpen] = useState(false);
  const [editingAdmin, setEditingAdmin] = useState<AdminAccount | null>(null);
  const [deletingAdmin, setDeletingAdmin] = useState<AdminAccount | null>(null);
  const [convertingAdmin, setConvertingAdmin] = useState<AdminAccount | null>(null);
  const [classroomAdmin, setClassroomAdmin] = useState<AdminAccount | null>(null);
  const [formData, setFormData] = useState<AdminFormData>(emptyFormData);
  const [formErrors, setFormErrors] = useState<Partial<Record<keyof AdminFormData, string>>>({});

  const { data: admins, meta, isLoading, goToPage } = usePagination<AdminAccount>({
    endpoint: '/api/admin/admin-accounts',
    queryKey: ['admin', 'admin-accounts'],
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
    mutationFn: async (data: AdminFormData & { id?: number }) => {
      if (data.id) {
        const payload: Record<string, unknown> = {
          full_name: data.full_name,
          email: data.email || null,
          is_master: data.is_master,
          classroom_id: data.classroom_id ? Number(data.classroom_id) : null,
          is_active: data.is_active,
        };
        if (data.password) payload.password = data.password;
        return api.put(`/api/admin/admin-accounts/${data.id}`, payload);
      }
      return api.post('/api/admin/admin-accounts', {
        ...data,
        classroom_id: data.classroom_id ? Number(data.classroom_id) : null,
      });
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['admin', 'admin-accounts'] });
      toast.success(editingAdmin ? '管理者情報を更新しました' : '管理者を登録しました');
      closeModal();
    },
    onError: () => {
      toast.error('保存に失敗しました');
    },
  });

  const deleteMutation = useMutation({
    mutationFn: async (id: number) => {
      return api.delete(`/api/admin/admin-accounts/${id}`);
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['admin', 'admin-accounts'] });
      toast.success('管理者を削除しました');
      setDeleteModalOpen(false);
      setDeletingAdmin(null);
    },
    onError: () => {
      toast.error('削除に失敗しました');
    },
  });

  const convertMutation = useMutation({
    mutationFn: async (id: number) => {
      return api.post(`/api/admin/admin-accounts/${id}/convert-to-staff`);
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['admin', 'admin-accounts'] });
      queryClient.invalidateQueries({ queryKey: ['admin', 'staff-accounts'] });
      toast.success('スタッフアカウントに変換しました');
      setConvertModalOpen(false);
      setConvertingAdmin(null);
    },
    onError: () => {
      toast.error('変換に失敗しました');
    },
  });

  function openAddModal() {
    setEditingAdmin(null);
    setFormData(emptyFormData);
    setFormErrors({});
    setModalOpen(true);
  }

  function openEditModal(admin: AdminAccount) {
    setEditingAdmin(admin);
    setFormData({
      username: admin.username,
      password: '',
      full_name: admin.full_name,
      email: admin.email || '',
      is_master: admin.is_master,
      classroom_id: admin.classroom_id ? String(admin.classroom_id) : '',
      is_active: admin.is_active,
    });
    setFormErrors({});
    setModalOpen(true);
  }

  function closeModal() {
    setModalOpen(false);
    setEditingAdmin(null);
    setFormData(emptyFormData);
    setFormErrors({});
  }

  function validateForm(): boolean {
    const errors: Partial<Record<keyof AdminFormData, string>> = {};
    if (!formData.full_name.trim()) errors.full_name = '氏名は必須です';
    if (!formData.classroom_id) errors.classroom_id = '所属教室は必須です';
    if (!editingAdmin) {
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
      id: editingAdmin?.id,
    });
  }

  const columns: Column<AdminAccount>[] = [
    {
      key: 'full_name',
      label: '氏名',
      sortable: true,
      render: (a) => <span className="font-medium">{a.full_name}</span>,
    },
    { key: 'username', label: 'ユーザー名' },
    { key: 'email', label: 'メールアドレス', render: (a) => a.email || '-' },
    {
      key: 'is_master',
      label: '権限',
      render: (a) => (
        <Badge variant={a.is_master ? 'danger' : 'default'}>
          {a.is_master ? 'マスター管理者' : '通常管理者'}
        </Badge>
      ),
    },
    {
      key: 'classroom_name',
      label: '所属教室',
      render: (a) => a.classroom_name || '-',
    },
    {
      key: 'is_active',
      label: 'ステータス',
      render: (a) => (
        <Badge variant={a.is_active ? 'success' : 'danger'}>
          {a.is_active ? '有効' : '無効'}
        </Badge>
      ),
    },
    {
      key: 'created_at',
      label: '登録日',
      render: (a) => new Date(a.created_at).toLocaleDateString('ja-JP'),
    },
    {
      key: 'actions',
      label: '操作',
      render: (a) => (
        <div className="flex items-center gap-1 flex-wrap">
          <Button variant="ghost" size="sm" onClick={() => openEditModal(a)} leftIcon={<MaterialIcon name="edit" size={14} />}>
            編集
          </Button>
          <Button variant="ghost" size="sm" onClick={() => setClassroomAdmin(a)} leftIcon={<MaterialIcon name="apartment" size={14} />}>
            所属教室
          </Button>
          <Button
            variant="ghost"
            size="sm"
            onClick={() => { setConvertingAdmin(a); setConvertModalOpen(true); }}
            leftIcon={<MaterialIcon name="swap_horiz" size={14} />}
          >
            スタッフに変換
          </Button>
          <Button
            variant="ghost"
            size="sm"
            onClick={() => { setDeletingAdmin(a); setDeleteModalOpen(true); }}
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
          <h1 className="text-2xl font-bold text-[var(--neutral-foreground-1)]">管理者アカウント管理</h1>
          <p className="mt-1 text-sm text-[var(--neutral-foreground-3)]">マスター管理者専用</p>
        </div>
        <Button onClick={openAddModal} leftIcon={<MaterialIcon name="add" size={16} />}>
          新規管理者登録
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
        <SkeletonTable rows={8} cols={8} />
      ) : (
        <Table
          columns={columns}
          data={admins}
          keyExtractor={(item) => item.id}
          currentPage={meta?.current_page}
          totalPages={meta?.last_page}
          onPageChange={goToPage}
          emptyMessage="管理者アカウントが登録されていません"
        />
      )}

      {/* Add/Edit Modal */}
      <Modal
        isOpen={modalOpen}
        onClose={closeModal}
        title={editingAdmin ? '管理者情報編集' : '新規管理者登録'}
        size="md"
      >
        <form onSubmit={handleSubmit} className="space-y-4">
          <Input
            label="ユーザー名 *"
            value={formData.username}
            onChange={(e) => setFormData({ ...formData, username: e.target.value })}
            disabled={!!editingAdmin}
            error={formErrors.username}
            helperText={editingAdmin ? 'ユーザー名は変更できません' : 'ログイン時に使用します（半角英数字）'}
          />
          <Input
            label={editingAdmin ? '新しいパスワード' : 'パスワード *'}
            type="password"
            value={formData.password}
            onChange={(e) => setFormData({ ...formData, password: e.target.value })}
            error={formErrors.password}
            helperText={editingAdmin ? '変更しない場合は空欄にしてください' : '6文字以上'}
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
              権限 *
            </label>
            <select
              value={formData.is_master ? '1' : '0'}
              onChange={(e) => setFormData({ ...formData, is_master: e.target.value === '1' })}
              className="block w-full rounded-md border border-[var(--neutral-stroke-1)] bg-[var(--neutral-background-1)] px-3 py-1.5 text-sm text-[var(--neutral-foreground-1)] focus:border-[var(--brand-80)] focus:outline-none focus:ring-1 focus:ring-[var(--brand-80)]"
            >
              <option value="0">通常管理者</option>
              <option value="1">マスター管理者</option>
            </select>
          </div>
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
          {editingAdmin && (
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
              {editingAdmin ? '更新' : '登録'}
            </Button>
          </div>
        </form>
      </Modal>

      {/* Delete Confirmation Modal */}
      <Modal
        isOpen={deleteModalOpen}
        onClose={() => { setDeleteModalOpen(false); setDeletingAdmin(null); }}
        title="管理者削除の確認"
        size="sm"
      >
        <div className="space-y-4">
          <p className="text-sm text-[var(--neutral-foreground-2)]">
            本当に「<span className="font-semibold">{deletingAdmin?.full_name}</span>」を削除しますか？
          </p>
          <p className="text-xs text-[var(--status-danger-fg)]">
            この操作は取り消せません。
          </p>
          <div className="flex justify-end gap-2 pt-2">
            <Button type="button" variant="outline" onClick={() => { setDeleteModalOpen(false); setDeletingAdmin(null); }}>
              キャンセル
            </Button>
            <Button
              variant="danger"
              isLoading={deleteMutation.isPending}
              onClick={() => deletingAdmin && deleteMutation.mutate(deletingAdmin.id)}
            >
              削除する
            </Button>
          </div>
        </div>
      </Modal>

      {/* Convert to Staff Confirmation Modal */}
      <Modal
        isOpen={convertModalOpen}
        onClose={() => { setConvertModalOpen(false); setConvertingAdmin(null); }}
        title="スタッフへの変換確認"
        size="sm"
      >
        <div className="space-y-4">
          <p className="text-sm text-[var(--neutral-foreground-2)]">
            「<span className="font-semibold">{convertingAdmin?.full_name}</span>」を管理者からスタッフアカウントに変換しますか？
          </p>
          <p className="text-xs text-[var(--status-warning-fg)]">
            管理者権限がなくなり、スタッフとしてのみログイン可能になります。
          </p>
          <div className="flex justify-end gap-2 pt-2">
            <Button type="button" variant="outline" onClick={() => { setConvertModalOpen(false); setConvertingAdmin(null); }}>
              キャンセル
            </Button>
            <Button
              isLoading={convertMutation.isPending}
              onClick={() => convertingAdmin && convertMutation.mutate(convertingAdmin.id)}
            >
              変換する
            </Button>
          </div>
        </div>
      </Modal>

      {/* Classroom Assignment Modal */}
      {classroomAdmin && (
        <UserClassroomModal
          user={classroomAdmin}
          onClose={() => setClassroomAdmin(null)}
        />
      )}
    </div>
  );
}
