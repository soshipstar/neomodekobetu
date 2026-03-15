'use client';

import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import api from '@/lib/api';
import { Card, CardHeader, CardTitle } from '@/components/ui/Card';
import { Button } from '@/components/ui/Button';
import { Input } from '@/components/ui/Input';
import { Badge } from '@/components/ui/Badge';
import { Modal } from '@/components/ui/Modal';
import { SkeletonList } from '@/components/ui/Skeleton';
import { useToast } from '@/components/ui/Toast';
import { Plus, Pencil, Trash2, Clock, GripVertical } from 'lucide-react';

interface DailyRoutine {
  id: number;
  name: string;
  start_time: string;
  end_time: string;
  description: string | null;
  sort_order: number;
  is_active: boolean;
}

export default function DailyRoutinesPage() {
  const queryClient = useQueryClient();
  const toast = useToast();
  const [modalOpen, setModalOpen] = useState(false);
  const [editingRoutine, setEditingRoutine] = useState<DailyRoutine | null>(null);
  const [form, setForm] = useState({ name: '', start_time: '09:00', end_time: '09:30', description: '', is_active: true });

  const { data: routines = [], isLoading } = useQuery({
    queryKey: ['staff', 'daily-routines'],
    queryFn: async () => {
      const res = await api.get<{ data: DailyRoutine[] }>('/api/staff/daily-routines');
      return res.data.data;
    },
  });

  const saveMutation = useMutation({
    mutationFn: async (data: typeof form) => {
      if (editingRoutine) return api.put(`/api/staff/daily-routines/${editingRoutine.id}`, data);
      return api.post('/api/staff/daily-routines', data);
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['staff', 'daily-routines'] });
      toast.success(editingRoutine ? 'ルーティンを更新しました' : 'ルーティンを追加しました');
      closeModal();
    },
    onError: () => toast.error('保存に失敗しました'),
  });

  const deleteMutation = useMutation({
    mutationFn: (id: number) => api.delete(`/api/staff/daily-routines/${id}`),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['staff', 'daily-routines'] });
      toast.success('ルーティンを削除しました');
    },
    onError: () => toast.error('削除に失敗しました'),
  });

  const reorderMutation = useMutation({
    mutationFn: (ids: number[]) => api.post('/api/staff/daily-routines/reorder', { routine_ids: ids }),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['staff', 'daily-routines'] }),
    onError: () => toast.error('並び替えに失敗しました'),
  });

  const closeModal = () => {
    setModalOpen(false);
    setEditingRoutine(null);
    setForm({ name: '', start_time: '09:00', end_time: '09:30', description: '', is_active: true });
  };

  const openEdit = (routine: DailyRoutine) => {
    setEditingRoutine(routine);
    setForm({
      name: routine.name,
      start_time: routine.start_time,
      end_time: routine.end_time,
      description: routine.description || '',
      is_active: routine.is_active,
    });
    setModalOpen(true);
  };

  const handleDragEnd = (fromIndex: number, toIndex: number) => {
    const reordered = [...routines];
    const [removed] = reordered.splice(fromIndex, 1);
    reordered.splice(toIndex, 0, removed);
    reorderMutation.mutate(reordered.map((r) => r.id));
  };

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-bold text-[var(--neutral-foreground-1)]">日課設定</h1>
        <Button onClick={() => { setEditingRoutine(null); setForm({ name: '', start_time: '09:00', end_time: '09:30', description: '', is_active: true }); setModalOpen(true); }} leftIcon={<Plus className="h-4 w-4" />}>
          日課を追加
        </Button>
      </div>

      {/* Timeline View */}
      <Card>
        <CardHeader>
          <CardTitle>1日のスケジュール</CardTitle>
          <p className="text-sm text-[var(--neutral-foreground-3)]">{routines.length}件の日課</p>
        </CardHeader>

        {isLoading ? (
          <SkeletonList items={6} />
        ) : routines.length === 0 ? (
          <p className="py-8 text-center text-sm text-[var(--neutral-foreground-3)]">日課が設定されていません</p>
        ) : (
          <div className="relative space-y-0">
            {routines.map((routine, index) => (
              <div
                key={routine.id}
                className={`flex items-stretch gap-4 ${!routine.is_active ? 'opacity-50' : ''}`}
              >
                {/* Timeline line */}
                <div className="flex flex-col items-center w-16 shrink-0">
                  <span className="text-xs font-medium text-[var(--neutral-foreground-3)] whitespace-nowrap">{routine.start_time}</span>
                  <div className="flex-1 w-px bg-[var(--brand-160)] my-1" />
                  <span className="text-xs font-medium text-[var(--neutral-foreground-3)] whitespace-nowrap">{routine.end_time}</span>
                </div>

                {/* Content */}
                <div className="flex-1 flex items-center justify-between rounded-lg border border-[var(--neutral-stroke-2)] p-4 mb-2 hover:shadow-[var(--shadow-4)] transition-shadow">
                  <div className="flex items-center gap-3">
                    <div className="flex h-10 w-10 items-center justify-center rounded-full bg-[var(--brand-160)]">
                      <Clock className="h-5 w-5 text-[var(--brand-80)]" />
                    </div>
                    <div>
                      <h3 className="font-medium text-[var(--neutral-foreground-1)]">{routine.name}</h3>
                      {routine.description && <p className="text-sm text-[var(--neutral-foreground-3)]">{routine.description}</p>}
                    </div>
                  </div>
                  <div className="flex items-center gap-2">
                    {!routine.is_active && <Badge variant="default">無効</Badge>}
                    <Button variant="ghost" size="sm" onClick={() => openEdit(routine)}>
                      <Pencil className="h-4 w-4" />
                    </Button>
                    <Button variant="ghost" size="sm" onClick={() => { if (confirm('削除しますか？')) deleteMutation.mutate(routine.id); }}>
                      <Trash2 className="h-4 w-4 text-[var(--status-danger-fg)]" />
                    </Button>
                  </div>
                </div>
              </div>
            ))}
          </div>
        )}
      </Card>

      {/* Add/Edit Modal */}
      <Modal isOpen={modalOpen} onClose={closeModal} title={editingRoutine ? '日課を編集' : '日課を追加'}>
        <form onSubmit={(e) => { e.preventDefault(); saveMutation.mutate(form); }} className="space-y-4">
          <Input label="日課名" value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} required placeholder="例: 朝の会、昼食" />
          <div className="grid grid-cols-2 gap-4">
            <Input label="開始時間" type="time" value={form.start_time} onChange={(e) => setForm({ ...form, start_time: e.target.value })} required />
            <Input label="終了時間" type="time" value={form.end_time} onChange={(e) => setForm({ ...form, end_time: e.target.value })} required />
          </div>
          <div>
            <label className="mb-1 block text-sm font-medium text-[var(--neutral-foreground-2)]">説明（任意）</label>
            <textarea value={form.description} onChange={(e) => setForm({ ...form, description: e.target.value })} className="block w-full rounded-lg border border-[var(--neutral-stroke-2)] px-3 py-2 text-sm" rows={2} />
          </div>
          <label className="flex items-center gap-2">
            <input type="checkbox" checked={form.is_active} onChange={(e) => setForm({ ...form, is_active: e.target.checked })} className="rounded border-[var(--neutral-stroke-2)]" />
            <span className="text-sm text-[var(--neutral-foreground-2)]">有効にする</span>
          </label>
          <div className="flex justify-end gap-2 pt-2">
            <Button variant="secondary" type="button" onClick={closeModal}>キャンセル</Button>
            <Button type="submit" isLoading={saveMutation.isPending}>保存</Button>
          </div>
        </form>
      </Modal>
    </div>
  );
}
