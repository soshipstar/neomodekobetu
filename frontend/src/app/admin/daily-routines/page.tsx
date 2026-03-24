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
import { Plus, Pencil, Trash2, Clock } from 'lucide-react';

interface DailyRoutine {
  id: number;
  name: string;
  start_time: string;
  end_time: string;
  description: string | null;
  classroom_id: number | null;
  classroom_name: string | null;
  sort_order: number;
  is_active: boolean;
}

export default function AdminDailyRoutinesPage() {
  const queryClient = useQueryClient();
  const toast = useToast();
  const [modalOpen, setModalOpen] = useState(false);
  const [editing, setEditing] = useState<DailyRoutine | null>(null);
  const [form, setForm] = useState({ name: '', start_time: '09:00', end_time: '09:30', description: '', classroom_id: '', is_active: true });

  const { data: routines = [], isLoading } = useQuery({
    queryKey: ['admin', 'daily-routines'],
    queryFn: async () => {
      const res = await api.get<{ data: DailyRoutine[] }>('/api/admin/daily-routines');
      return res.data.data;
    },
  });

  const saveMutation = useMutation({
    mutationFn: async (data: typeof form) => {
      if (editing) return api.put(`/api/admin/daily-routines/${editing.id}`, data);
      return api.post('/api/admin/daily-routines', data);
    },
    onSuccess: () => { queryClient.invalidateQueries({ queryKey: ['admin', 'daily-routines'] }); toast.success('保存しました'); closeModal(); },
    onError: () => toast.error('保存に失敗しました'),
  });

  const deleteMutation = useMutation({
    mutationFn: (id: number) => api.delete(`/api/admin/daily-routines/${id}`),
    onSuccess: () => { queryClient.invalidateQueries({ queryKey: ['admin', 'daily-routines'] }); toast.success('削除しました'); },
    onError: () => toast.error('削除に失敗しました'),
  });

  const closeModal = () => { setModalOpen(false); setEditing(null); setForm({ name: '', start_time: '09:00', end_time: '09:30', description: '', classroom_id: '', is_active: true }); };

  const openEdit = (r: DailyRoutine) => {
    setEditing(r);
    setForm({ name: r.name, start_time: r.start_time, end_time: r.end_time, description: r.description || '', classroom_id: r.classroom_id?.toString() || '', is_active: r.is_active });
    setModalOpen(true);
  };

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-bold text-[var(--neutral-foreground-1)]">日課管理</h1>
        <Button onClick={() => { setEditing(null); setModalOpen(true); }} leftIcon={<Plus className="h-4 w-4" />}>追加</Button>
      </div>

      <Card>
        <CardHeader><CardTitle>日課一覧</CardTitle></CardHeader>
        {isLoading ? <SkeletonList items={6} /> : routines.length === 0 ? (
          <p className="py-8 text-center text-sm text-[var(--neutral-foreground-3)]">日課がありません</p>
        ) : (
          <div className="space-y-0">
            {routines.map((routine) => (
              <div key={routine.id} className={`flex items-stretch gap-4 ${!routine.is_active ? 'opacity-50' : ''}`}>
                <div className="flex flex-col items-center w-16 shrink-0">
                  <span className="text-xs font-medium text-[var(--neutral-foreground-3)]">{routine.start_time}</span>
                  <div className="flex-1 w-px bg-blue-200 my-1" />
                  <span className="text-xs font-medium text-[var(--neutral-foreground-3)]">{routine.end_time}</span>
                </div>
                <div className="flex-1 flex items-center justify-between rounded-lg border border-[var(--neutral-stroke-2)] p-4 mb-2">
                  <div className="flex items-center gap-3">
                    <Clock className="h-5 w-5 text-[var(--brand-80)]" />
                    <div>
                      <h3 className="font-medium text-[var(--neutral-foreground-1)]">{routine.name}</h3>
                      <div className="flex gap-1 mt-0.5">
                        {routine.classroom_name && <Badge variant="info">{routine.classroom_name}</Badge>}
                        {!routine.is_active && <Badge variant="default">無効</Badge>}
                      </div>
                    </div>
                  </div>
                  <div className="flex gap-1">
                    <Button variant="ghost" size="sm" onClick={() => openEdit(routine)}><Pencil className="h-4 w-4" /></Button>
                    <Button variant="ghost" size="sm" onClick={() => { if (confirm('削除しますか？')) deleteMutation.mutate(routine.id); }}><Trash2 className="h-4 w-4 text-red-500" /></Button>
                  </div>
                </div>
              </div>
            ))}
          </div>
        )}
      </Card>

      <Modal isOpen={modalOpen} onClose={closeModal} title={editing ? '日課を編集' : '日課を追加'}>
        <form onSubmit={(e) => { e.preventDefault(); saveMutation.mutate(form); }} className="space-y-4">
          <Input label="日課名" value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} required />
          <div className="grid grid-cols-2 gap-4">
            <Input label="開始時間" type="time" value={form.start_time} onChange={(e) => setForm({ ...form, start_time: e.target.value })} required />
            <Input label="終了時間" type="time" value={form.end_time} onChange={(e) => setForm({ ...form, end_time: e.target.value })} required />
          </div>
          <div><label className="mb-1 block text-sm font-medium text-[var(--neutral-foreground-2)]">説明</label><textarea value={form.description} onChange={(e) => setForm({ ...form, description: e.target.value })} className="block w-full rounded-lg border border-[var(--neutral-stroke-1)] px-3 py-2 text-sm" rows={2} /></div>
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
