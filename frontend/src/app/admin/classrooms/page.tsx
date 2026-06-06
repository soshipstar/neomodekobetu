'use client';

import { useState, useEffect } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import api from '@/lib/api';
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
import { SERVICE_TYPE_OPTIONS, serviceTypeShort } from '@/lib/serviceType';

export default function ClassroomsPage() {
  const { isMaster, isReady } = useMasterGuard();
  const queryClient = useQueryClient();
  const toast = useToast();
  const [showCreate, setShowCreate] = useState(false);
  const [editingClassroom, setEditingClassroom] = useState<Classroom | null>(null);

  const { data: classrooms, isLoading } = useQuery({
    queryKey: ['admin', 'classrooms'],
    queryFn: async () => {
      const response = await api.get<{ data: Classroom[] }>('/api/admin/classrooms');
      return response.data.data;
    },
  });

  const createForm = useForm<ClassroomFormData>({
    resolver: zodResolver(classroomSchema),
    defaultValues: { service_type: 'after_school' },
  });

  const editForm = useForm<ClassroomFormData>({
    resolver: zodResolver(classroomSchema),
  });

  useEffect(() => {
    if (editingClassroom) {
      const st = editingClassroom.service_type;
      editForm.reset({
        classroom_name: editingClassroom.classroom_name,
        service_type: (st === 'after_school' || st === 'employment_a' || st === 'employment_b' || st === 'transition')
          ? st
          : 'after_school',
        address: editingClassroom.address || '',
        phone: editingClassroom.phone || '',
        capacity: editingClassroom.capacity != null ? String(editingClassroom.capacity) : '',
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
    onError: () => toast.error('変更に失敗しました'),
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
    onError: () => toast.error('作成に失敗しました'),
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
    onError: () => toast.error('更新に失敗しました'),
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
    {
      key: 'service_type',
      label: 'サービス種別',
      render: (c) => <Badge variant="info">{serviceTypeShort(c.service_type)}</Badge>,
    },
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
        <Button
          variant="ghost"
          size="sm"
          onClick={() => setEditingClassroom(c)}
          leftIcon={<MaterialIcon name="edit" size={14} />}
        >
          編集
        </Button>
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
          <div>
            <label className="mb-1 block text-sm font-medium text-[var(--neutral-foreground-2)]">サービス種別</label>
            <select
              className="block w-full rounded-md border border-[var(--neutral-stroke-1)] bg-[var(--neutral-background-1)] px-3 py-2 text-sm focus:border-[var(--brand-80)] focus:outline-none focus:ring-1 focus:ring-[var(--brand-80)]"
              defaultValue="after_school"
              {...createForm.register('service_type')}
            >
              {SERVICE_TYPE_OPTIONS.map((opt) => (
                <option key={opt.value} value={opt.value}>{opt.label}</option>
              ))}
            </select>
            <p className="mt-1 text-xs text-[var(--neutral-foreground-3)]">
              一度設定したサービス種別の変更は強み・領域・集計に影響します。
            </p>
          </div>
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
          <div>
            <label className="mb-1 block text-sm font-medium text-[var(--neutral-foreground-2)]">サービス種別</label>
            <select
              className="block w-full rounded-md border border-[var(--neutral-stroke-1)] bg-[var(--neutral-background-1)] px-3 py-2 text-sm focus:border-[var(--brand-80)] focus:outline-none focus:ring-1 focus:ring-[var(--brand-80)]"
              {...editForm.register('service_type')}
            >
              {SERVICE_TYPE_OPTIONS.map((opt) => (
                <option key={opt.value} value={opt.value}>{opt.label}</option>
              ))}
            </select>
            <p className="mt-1 text-xs text-[var(--neutral-foreground-3)]">
              既存事業所のサービス種別を変更すると、過去データの集計表示が変わります。慎重に変更してください。
            </p>
          </div>
          <Input label="住所" {...editForm.register('address')} />
          <Input label="電話番号" {...editForm.register('phone')} />
          <Input
            label="定員 (名)"
            type="number"
            error={editForm.formState.errors.capacity?.message}
            {...editForm.register('capacity')}
            placeholder="例: 20"
          />

          <div className="flex justify-end gap-2">
            <Button variant="ghost" type="button" onClick={() => setEditingClassroom(null)}>キャンセル</Button>
            <Button type="submit" isLoading={updateMutation.isPending}>更新</Button>
          </div>
        </form>
      </Modal>
    </div>
  );
}
