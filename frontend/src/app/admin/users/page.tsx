'use client';

import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import api from '@/lib/api';
import { userSchema, type UserFormData } from '@/lib/validators';
import { Button } from '@/components/ui/Button';
import { Badge } from '@/components/ui/Badge';
import { Modal } from '@/components/ui/Modal';
import { Input } from '@/components/ui/Input';
import { Table, type Column } from '@/components/ui/Table';
import { SkeletonTable } from '@/components/ui/Skeleton';
import { useToast } from '@/components/ui/Toast';
import { usePagination } from '@/hooks/usePagination';
import { useDebounce } from '@/hooks/useDebounce';
import { Plus, Search } from 'lucide-react';
import type { User } from '@/types/user';

const userTypeLabels: Record<string, string> = {
  admin: '管理者', staff: 'スタッフ', guardian: '保護者', tablet: 'タブレット',
};

export default function UsersPage() {
  const queryClient = useQueryClient();
  const toast = useToast();
  const [showCreate, setShowCreate] = useState(false);
  const [search, setSearch] = useState('');
  const debouncedSearch = useDebounce(search, 300);

  const { data: users, meta, isLoading, goToPage } = usePagination<User>({
    endpoint: '/api/admin/users',
    queryKey: ['admin', 'users'],
    params: { search: debouncedSearch || undefined },
  });

  const { register, handleSubmit, reset, formState: { errors } } = useForm<UserFormData>({
    resolver: zodResolver(userSchema),
  });

  const createMutation = useMutation({
    mutationFn: async (data: UserFormData) => {
      await api.post('/api/admin/users', data);
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['admin', 'users'] });
      setShowCreate(false);
      reset();
      toast.success('ユーザーを作成しました');
    },
    onError: () => toast.error('作成に失敗しました'),
  });

  const columns: Column<User>[] = [
    { key: 'full_name', label: '氏名', sortable: true, render: (u) => <span className="font-medium">{u.full_name}</span> },
    { key: 'username', label: 'ユーザー名' },
    { key: 'user_type', label: '種別', render: (u) => <Badge variant="primary">{userTypeLabels[u.user_type]}</Badge> },
    { key: 'is_active', label: 'ステータス', render: (u) => <Badge variant={u.is_active ? 'success' : 'default'}>{u.is_active ? '有効' : '無効'}</Badge> },
  ];

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-bold text-[var(--neutral-foreground-1)]">ユーザー管理</h1>
        <Button leftIcon={<Plus className="h-4 w-4" />} onClick={() => setShowCreate(true)}>新規作成</Button>
      </div>

      <div className="relative">
        <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-[var(--neutral-foreground-4)]" />
        <Input placeholder="氏名・ユーザー名で検索..." value={search} onChange={(e) => setSearch(e.target.value)} className="pl-10" />
      </div>

      {isLoading ? (
        <SkeletonTable rows={8} cols={4} />
      ) : (
        <Table
          columns={columns}
          data={users}
          keyExtractor={(item) => item.id}
          currentPage={meta?.current_page}
          totalPages={meta?.last_page}
          onPageChange={goToPage}
          emptyMessage="ユーザーが見つかりません"
        />
      )}

      <Modal isOpen={showCreate} onClose={() => setShowCreate(false)} title="ユーザーを作成" size="lg">
        <form onSubmit={handleSubmit((data) => createMutation.mutate(data))} className="space-y-4">
          <div className="grid gap-4 sm:grid-cols-2">
            <Input label="ユーザー名" error={errors.username?.message} {...register('username')} />
            <Input label="パスワード" type="password" error={errors.password?.message} {...register('password')} />
          </div>
          <Input label="氏名" error={errors.full_name?.message} {...register('full_name')} />
          <Input label="メールアドレス" type="email" error={errors.email?.message} {...register('email')} />
          <div>
            <label className="mb-1 block text-sm font-medium text-[var(--neutral-foreground-2)]">種別</label>
            <select {...register('user_type')} className="block w-full rounded-lg border border-[var(--neutral-stroke-1)] bg-white px-3 py-2 text-sm">
              <option value="staff">スタッフ</option>
              <option value="guardian">保護者</option>
              <option value="admin">管理者</option>
              <option value="tablet">タブレット</option>
            </select>
          </div>
          <div className="flex justify-end gap-2">
            <Button variant="ghost" type="button" onClick={() => setShowCreate(false)}>キャンセル</Button>
            <Button type="submit" isLoading={createMutation.isPending}>作成</Button>
          </div>
        </form>
      </Modal>
    </div>
  );
}
