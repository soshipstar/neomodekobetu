'use client';

import { useState, useMemo } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import api from '@/lib/api';
import { Card } from '@/components/ui/Card';
import { Button } from '@/components/ui/Button';
import { Input } from '@/components/ui/Input';
import { Badge } from '@/components/ui/Badge';
import { Modal } from '@/components/ui/Modal';
import { Table, type Column } from '@/components/ui/Table';
import { SkeletonTable } from '@/components/ui/Skeleton';
import { useToast } from '@/components/ui/Toast';
import { format, startOfMonth, endOfMonth, eachDayOfInterval, getDay, addMonths, subMonths, isSameDay } from 'date-fns';
import { ja } from 'date-fns/locale';
import { Plus, Pencil, Trash2, ChevronLeft, ChevronRight, Calendar, List } from 'lucide-react';

interface AdminEvent {
  id: number;
  title: string;
  description: string;
  date: string;
  start_time: string | null;
  end_time: string | null;
  location: string | null;
  classroom_id: number | null;
  classroom_name: string | null;
  is_published: boolean;
  created_at: string;
}

export default function AdminEventsPage() {
  const queryClient = useQueryClient();
  const toast = useToast();
  const [viewMode, setViewMode] = useState<'list' | 'calendar'>('list');
  const [currentMonth, setCurrentMonth] = useState(new Date());
  const [modalOpen, setModalOpen] = useState(false);
  const [editingEvent, setEditingEvent] = useState<AdminEvent | null>(null);
  const [form, setForm] = useState({ title: '', description: '', date: '', start_time: '', end_time: '', location: '', classroom_id: '', is_published: true });

  const monthStr = format(currentMonth, 'yyyy-MM');

  const { data: events = [], isLoading } = useQuery({
    queryKey: ['admin', 'events', monthStr],
    queryFn: async () => {
      const res = await api.get<{ data: AdminEvent[] }>('/api/admin/events', { params: { month: monthStr } });
      return res.data.data;
    },
  });

  const saveMutation = useMutation({
    mutationFn: async (data: typeof form) => {
      if (editingEvent) return api.put(`/api/admin/events/${editingEvent.id}`, data);
      return api.post('/api/admin/events', data);
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['admin', 'events'] });
      toast.success(editingEvent ? '更新しました' : '作成しました');
      closeModal();
    },
    onError: () => toast.error('保存に失敗しました'),
  });

  const deleteMutation = useMutation({
    mutationFn: (id: number) => api.delete(`/api/admin/events/${id}`),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['admin', 'events'] });
      toast.success('削除しました');
    },
    onError: () => toast.error('削除に失敗しました'),
  });

  const closeModal = () => { setModalOpen(false); setEditingEvent(null); setForm({ title: '', description: '', date: '', start_time: '', end_time: '', location: '', classroom_id: '', is_published: true }); };

  const openEdit = (event: AdminEvent) => {
    setEditingEvent(event);
    setForm({ title: event.title, description: event.description, date: event.date, start_time: event.start_time || '', end_time: event.end_time || '', location: event.location || '', classroom_id: event.classroom_id?.toString() || '', is_published: event.is_published });
    setModalOpen(true);
  };

  const eventsByDate = useMemo(() => {
    const map: Record<string, AdminEvent[]> = {};
    events.forEach((e) => { if (!map[e.date]) map[e.date] = []; map[e.date].push(e); });
    return map;
  }, [events]);

  const calendarDays = useMemo(() => {
    const start = startOfMonth(currentMonth);
    const end = endOfMonth(currentMonth);
    return { days: eachDayOfInterval({ start, end }), startPad: getDay(start) };
  }, [currentMonth]);

  const columns: Column<AdminEvent>[] = [
    { key: 'title', label: 'イベント名', render: (e) => <span className="font-medium">{e.title}</span> },
    { key: 'date', label: '日付', render: (e) => format(new Date(e.date), 'yyyy/MM/dd(E)', { locale: ja }) },
    { key: 'classroom_name', label: '事業所', render: (e) => e.classroom_name || '全体' },
    { key: 'is_published', label: '公開', render: (e) => <Badge variant={e.is_published ? 'success' : 'default'}>{e.is_published ? '公開' : '下書き'}</Badge> },
    {
      key: 'actions', label: '操作', render: (e) => (
        <div className="flex gap-1">
          <Button variant="ghost" size="sm" onClick={() => openEdit(e)}><Pencil className="h-4 w-4" /></Button>
          <Button variant="ghost" size="sm" onClick={() => { if (confirm('削除しますか？')) deleteMutation.mutate(e.id); }}><Trash2 className="h-4 w-4 text-red-500" /></Button>
        </div>
      ),
    },
  ];

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-bold text-gray-900">イベント管理</h1>
        <div className="flex gap-2">
          <div className="flex rounded-lg border border-gray-300">
            <button onClick={() => setViewMode('list')} className={`px-3 py-1.5 text-sm rounded-l-lg ${viewMode === 'list' ? 'bg-blue-600 text-white' : 'text-gray-600'}`}><List className="h-4 w-4" /></button>
            <button onClick={() => setViewMode('calendar')} className={`px-3 py-1.5 text-sm rounded-r-lg ${viewMode === 'calendar' ? 'bg-blue-600 text-white' : 'text-gray-600'}`}><Calendar className="h-4 w-4" /></button>
          </div>
          <Button onClick={() => { setEditingEvent(null); setModalOpen(true); }} leftIcon={<Plus className="h-4 w-4" />}>作成</Button>
        </div>
      </div>

      <div className="flex items-center justify-center gap-4">
        <Button variant="ghost" size="sm" onClick={() => setCurrentMonth(subMonths(currentMonth, 1))}><ChevronLeft className="h-4 w-4" /></Button>
        <span className="text-lg font-semibold">{format(currentMonth, 'yyyy年M月', { locale: ja })}</span>
        <Button variant="ghost" size="sm" onClick={() => setCurrentMonth(addMonths(currentMonth, 1))}><ChevronRight className="h-4 w-4" /></Button>
      </div>

      {viewMode === 'list' ? (
        isLoading ? <SkeletonTable rows={5} cols={5} /> : <Table columns={columns} data={events} keyExtractor={(e) => e.id} emptyMessage="イベントがありません" />
      ) : (
        <Card>
          {isLoading ? <SkeletonTable rows={5} cols={7} /> : (
            <div className="grid grid-cols-7 gap-px bg-gray-200 rounded-lg overflow-hidden">
              {['日', '月', '火', '水', '木', '金', '土'].map((d) => <div key={d} className="bg-gray-50 py-2 text-center text-xs font-semibold text-gray-500">{d}</div>)}
              {Array.from({ length: calendarDays.startPad }).map((_, i) => <div key={`p-${i}`} className="bg-white p-2 min-h-[80px]" />)}
              {calendarDays.days.map((day) => {
                const dateStr = format(day, 'yyyy-MM-dd');
                const dayEvents = eventsByDate[dateStr] || [];
                return (
                  <div key={dateStr} className="bg-white p-2 min-h-[80px]">
                    <span className={`text-sm ${isSameDay(day, new Date()) ? 'font-bold text-blue-600' : 'text-gray-700'}`}>{format(day, 'd')}</span>
                    {dayEvents.map((ev) => (
                      <div key={ev.id} onClick={() => openEdit(ev)} className="mt-1 truncate rounded bg-green-100 px-1 py-0.5 text-xs text-green-700 cursor-pointer hover:bg-green-200">{ev.title}</div>
                    ))}
                  </div>
                );
              })}
            </div>
          )}
        </Card>
      )}

      <Modal isOpen={modalOpen} onClose={closeModal} title={editingEvent ? 'イベント編集' : 'イベント作成'} size="lg">
        <form onSubmit={(e) => { e.preventDefault(); saveMutation.mutate(form); }} className="space-y-4">
          <Input label="イベント名" value={form.title} onChange={(e) => setForm({ ...form, title: e.target.value })} required />
          <div><label className="mb-1 block text-sm font-medium text-gray-700">説明</label><textarea value={form.description} onChange={(e) => setForm({ ...form, description: e.target.value })} className="block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm" rows={3} /></div>
          <Input label="日付" type="date" value={form.date} onChange={(e) => setForm({ ...form, date: e.target.value })} required />
          <div className="grid grid-cols-2 gap-4">
            <Input label="開始時間" type="time" value={form.start_time} onChange={(e) => setForm({ ...form, start_time: e.target.value })} />
            <Input label="終了時間" type="time" value={form.end_time} onChange={(e) => setForm({ ...form, end_time: e.target.value })} />
          </div>
          <Input label="場所" value={form.location} onChange={(e) => setForm({ ...form, location: e.target.value })} />
          <label className="flex items-center gap-2"><input type="checkbox" checked={form.is_published} onChange={(e) => setForm({ ...form, is_published: e.target.checked })} className="rounded border-gray-300" /><span className="text-sm text-gray-700">公開する</span></label>
          <div className="flex justify-end gap-2 pt-2">
            <Button variant="secondary" type="button" onClick={closeModal}>キャンセル</Button>
            <Button type="submit" isLoading={saveMutation.isPending}>保存</Button>
          </div>
        </form>
      </Modal>
    </div>
  );
}
