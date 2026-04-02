'use client';

import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import api from '@/lib/api';
import { classroomSchema, type ClassroomFormData } from '@/lib/validators';
import { Card, CardBody } from '@/components/ui/Card';
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

  const { data: classrooms, isLoading } = useQuery({
    queryKey: ['admin', 'classrooms'],
    queryFn: async () => {
      const response = await api.get<{ data: Classroom[] }>('/api/admin/classrooms');
      return response.data.data;
    },
  });

  const { register, handleSubmit, reset, formState: { errors } } = useForm<ClassroomFormData>({
    resolver: zodResolver(classroomSchema),
  });

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
      reset();
      toast.success('事業所を作成しました');
    },
    onError: () => toast.error('作成に失敗しました'),
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
  ];

  if (!isReady || !isMaster) return null;

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-bold text-[var(--neutral-foreground-1)]">事業所管理</h1>
        <Button leftIcon={<MaterialIcon name="add" size={16} />} onClick={() => setShowCreate(true)}>新規作成</Button>
      </div>

      {isLoading ? (
        <SkeletonTable rows={5} cols={4} />
      ) : (
        <Table
          columns={columns}
          data={classrooms || []}
          keyExtractor={(item) => item.id}
          emptyMessage="事業所がありません"
        />
      )}

      <Modal isOpen={showCreate} onClose={() => setShowCreate(false)} title="事業所を作成">
        <form onSubmit={handleSubmit((data) => createMutation.mutate(data))} className="space-y-4">
          <Input label="事業所名" error={errors.classroom_name?.message} {...register('classroom_name')} />
          <Input label="住所" {...register('address')} />
          <Input label="電話番号" {...register('phone')} />
          <div className="flex justify-end gap-2">
            <Button variant="ghost" type="button" onClick={() => setShowCreate(false)}>キャンセル</Button>
            <Button type="submit" isLoading={createMutation.isPending}>作成</Button>
          </div>
        </form>
      </Modal>
    </div>
  );
}
