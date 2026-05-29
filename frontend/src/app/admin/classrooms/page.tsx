'use client';

import { useState, useEffect } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import api, { formatApiError } from '@/lib/api';
import { classroomSchema, type ClassroomFormData } from '@/lib/validators';
import { Button } from '@/components/ui/Button';
import { Badge } from '@/components/ui/Badge';
import { Modal } from '@/components/ui/Modal';
import { Input } from '@/components/ui/Input';
import { Table, type Column } from '@/components/ui/Table';
import { SkeletonTable } from '@/components/ui/Skeleton';
import { useToast } from '@/components/ui/Toast';
import type { Classroom } from '@/types/user';
import { useMasterGuard } from '@/hooks/useMasterGuard';
import { MaterialIcon } from '@/components/ui/MaterialIcon';

export default function ClassroomsPage() {
  const { isMaster, isReady } = useMasterGuard();
  const queryClient = useQueryClient();
  const toast = useToast();
  const [showCreate, setShowCreate] = useState(false);
  const [editingClassroom, setEditingClassroom] = useState<Classroom | null>(null);
  // 削除モーダル: 確認 + 「無効化」or「完全削除」を選ばせる
  const [deletingClassroom, setDeletingClassroom] = useState<Classroom | null>(null);

  const { data: classrooms, isLoading } = useQuery({
    queryKey: ['admin', 'classrooms'],
    queryFn: async () => {
      const response = await api.get<{ data: Classroom[] }>('/api/admin/classrooms');
      return response.data.data;
    },
  });

  const createForm = useForm<ClassroomFormData>({
    resolver: zodResolver(classroomSchema),
  });

  const editForm = useForm<ClassroomFormData>({
    resolver: zodResolver(classroomSchema),
  });

  useEffect(() => {
    if (editingClassroom) {
      editForm.reset({
        classroom_name: editingClassroom.classroom_name,
        address: editingClassroom.address || '',
        phone: editingClassroom.phone || '',
      });
    }
  }, [editingClassroom, editForm]);

  const toggleActiveMutation = useMutation({
    mutationFn: async ({ id, is_active }: { id: number; is_active: boolean }) => {
      await api.put(`/api/admin/classrooms/${id}`, { is_active });
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['admin', 'classrooms'] });
      toast.success('ステータスを変更しました');
    },
    onError: (err: unknown) => toast.error(formatApiError(err, '変更に失敗しました')),
  });

  const createMutation = useMutation({
    mutationFn: async (data: ClassroomFormData) => {
      await api.post('/api/admin/classrooms', data);
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['admin', 'classrooms'] });
      setShowCreate(false);
      createForm.reset();
      toast.success('事業所を作成しました');
    },
    onError: (err: unknown) => toast.error(formatApiError(err, '作成に失敗しました')),
  });

  const updateMutation = useMutation({
    mutationFn: async ({ id, data }: { id: number; data: ClassroomFormData }) => {
      await api.put(`/api/admin/classrooms/${id}`, data);
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['admin', 'classrooms'] });
      setEditingClassroom(null);
      editForm.reset();
      toast.success('事業所を更新しました');
    },
    onError: (err: unknown) => toast.error(formatApiError(err, '更新に失敗しました')),
  });

  // 教室の削除 (mode = 'soft' で無効化、'hard' で完全削除)。
  // BE 側で関連テーブルの件数をチェックし、hard の場合は
  // すべて 0 件でないと 422 で拒否される。失敗時のレスポンスから
  // 各テーブルの残件数を取り出してトーストに表示する。
  const deleteMutation = useMutation({
    mutationFn: async ({ id, mode }: { id: number; mode: 'soft' | 'hard' }) => {
      await api.delete(`/api/admin/classrooms/${id}`, { params: { mode } });
    },
    onSuccess: (_data, variables) => {
      queryClient.invalidateQueries({ queryKey: ['admin', 'classrooms'] });
      toast.success(variables.mode === 'hard' ? '事業所を完全に削除しました' : '事業所を無効にしました');
      setDeletingClassroom(null);
    },
    onError: (err: unknown) => toast.error(formatApiError(err, '削除に失敗しました')),
  });

  const columns: Column<Classroom>[] = [
    {
      key: 'classroom_name',
      label: '事業所名',
      sortable: true,
      render: (c) => (
        <div className="flex items-center gap-2">
          <MaterialIcon name="apartment" size={16} className="text-[var(--neutral-foreground-4)]" />
          <span className="font-medium">{c.classroom_name}</span>
        </div>
      ),
    },
    { key: 'company_name', label: '所属企業', render: (c) => c.company_name || '-' },
    { key: 'address', label: '住所', render: (c) => c.address || '-' },
    { key: 'phone', label: '電話番号', render: (c) => c.phone || '-' },
    {
      key: 'is_active',
      label: 'ステータス',
      render: (c) => (
        <button
          onClick={() => {
            if (confirm(`${c.classroom_name}を${c.is_active ? '無効' : '有効'}にしますか？`))
              toggleActiveMutation.mutate({ id: c.id, is_active: !c.is_active });
          }}
          className="cursor-pointer"
        >
          <Badge variant={c.is_active ? 'success' : 'default'}>
            {c.is_active ? '有効' : '無効'}
          </Badge>
        </button>
      ),
    },
    {
      key: 'actions',
      label: '操作',
      render: (c) => (
        <div className="flex items-center gap-1">
          <Button
            variant="ghost"
            size="sm"
            onClick={() => setEditingClassroom(c)}
            leftIcon={<MaterialIcon name="edit" size={14} />}
          >
            編集
          </Button>
          <Button
            variant="ghost"
            size="sm"
            onClick={() => setDeletingClassroom(c)}
            leftIcon={<MaterialIcon name="delete" size={14} />}
            className="text-[var(--status-danger-fg)] hover:bg-[var(--status-danger-bg)]"
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
        <h1 className="text-2xl font-bold text-[var(--neutral-foreground-1)]">事業所管理</h1>
        <Button leftIcon={<MaterialIcon name="add" size={16} />} onClick={() => setShowCreate(true)}>新規作成</Button>
      </div>

      {isLoading ? (
        <SkeletonTable rows={5} cols={5} />
      ) : (
        <Table
          columns={columns}
          data={classrooms || []}
          keyExtractor={(item) => item.id}
          emptyMessage="事業所がありません"
        />
      )}

      {/* Create Modal */}
      <Modal isOpen={showCreate} onClose={() => setShowCreate(false)} title="事業所を作成">
        <form onSubmit={createForm.handleSubmit((data) => createMutation.mutate(data))} className="space-y-4">
          <Input label="事業所名" error={createForm.formState.errors.classroom_name?.message} {...createForm.register('classroom_name')} />
          <Input label="住所" {...createForm.register('address')} />
          <Input label="電話番号" {...createForm.register('phone')} />
          <div className="flex justify-end gap-2">
            <Button variant="ghost" type="button" onClick={() => setShowCreate(false)}>キャンセル</Button>
            <Button type="submit" isLoading={createMutation.isPending}>作成</Button>
          </div>
        </form>
      </Modal>

      {/* Edit Modal */}
      <Modal isOpen={!!editingClassroom} onClose={() => setEditingClassroom(null)} title={`事業所を編集: ${editingClassroom?.classroom_name ?? ''}`}>
        <form
          onSubmit={editForm.handleSubmit((data) => {
            if (editingClassroom) updateMutation.mutate({ id: editingClassroom.id, data });
          })}
          className="space-y-4"
        >
          <Input label="事業所名" error={editForm.formState.errors.classroom_name?.message} {...editForm.register('classroom_name')} />
          <Input label="住所" {...editForm.register('address')} />
          <Input label="電話番号" {...editForm.register('phone')} />
          <div className="flex justify-end gap-2">
            <Button variant="ghost" type="button" onClick={() => setEditingClassroom(null)}>キャンセル</Button>
            <Button type="submit" isLoading={updateMutation.isPending}>更新</Button>
          </div>
        </form>
      </Modal>

      {/* Delete Modal: 無効化 (soft) と 完全削除 (hard) の 2 つを提示。
          BE 側は hard delete の場合、関連テーブル (students/users/daily_records/photos 等)
          に 1 件でも参照が残っていれば 422 で拒否する。 */}
      <Modal
        isOpen={!!deletingClassroom}
        onClose={() => setDeletingClassroom(null)}
        title={`事業所を削除: ${deletingClassroom?.classroom_name ?? ''}`}
      >
        <div className="space-y-4">
          <div className="rounded-md bg-[var(--status-warning-bg)] p-3 text-sm text-[var(--status-warning-fg)]">
            <p className="font-semibold">⚠ 操作の選択</p>
            <ul className="mt-2 list-disc pl-5 space-y-1 text-xs">
              <li>
                <strong>無効化</strong>: ステータスを「無効」に変更します (元に戻せます)。
                生徒が在籍中だと拒否されます。
              </li>
              <li>
                <strong>完全削除</strong>: DB レコードを物理削除します
                (<strong className="text-[var(--status-danger-fg)]">取り消し不可</strong>)。
                生徒・スタッフ・記録・写真などが 1 件でも残っていれば拒否されます。
                テスト/誤作成の事業所を整理する場合のみ使ってください。
              </li>
            </ul>
          </div>
          <div className="flex justify-end gap-2 pt-2">
            <Button variant="ghost" type="button" onClick={() => setDeletingClassroom(null)}>
              キャンセル
            </Button>
            <Button
              variant="outline"
              onClick={() => {
                if (!deletingClassroom) return;
                if (!confirm(`「${deletingClassroom.classroom_name}」を無効化します。よろしいですか？`)) return;
                deleteMutation.mutate({ id: deletingClassroom.id, mode: 'soft' });
              }}
              isLoading={deleteMutation.isPending && deleteMutation.variables?.mode === 'soft'}
            >
              無効化
            </Button>
            <Button
              onClick={() => {
                if (!deletingClassroom) return;
                if (!confirm(
                  `「${deletingClassroom.classroom_name}」を完全削除します。\n\n` +
                  `この操作は取り消せません。本当に実行しますか？\n` +
                  `(関連データが残っている場合は自動で拒否されます)`,
                )) return;
                deleteMutation.mutate({ id: deletingClassroom.id, mode: 'hard' });
              }}
              isLoading={deleteMutation.isPending && deleteMutation.variables?.mode === 'hard'}
              className="bg-[var(--status-danger-fg)] hover:bg-[var(--status-danger-fg)]/90"
            >
              完全削除
            </Button>
          </div>
        </div>
      </Modal>
    </div>
  );
}
