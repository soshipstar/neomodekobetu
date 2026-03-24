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
  event_name: string;
  event_description: string | null;
  event_date: string;
  event_color: string;
  target_audience: string;
  staff_comment: string | null;
  guardian_message: string | null;
  classroom_id: number | null;
  classroom?: { id: number; classroom_name: string } | null;
  created_at: string;
}

const TARGET_LABELS: Record<string, string> = {
  all: '全員',
  elementary: '小学生',
  junior_high_school: '中学生',
  guardian: '保護者',
  other: 'その他',
};

export default function AdminEventsPage() {
  const queryClient = useQueryClient();
  const toast = useToast();
  const [viewMode, setViewMode] = useState<'list' | 'calendar'>('list');
  const [currentMonth, setCurrentMonth] = useState(new Date());
  const [modalOpen, setModalOpen] = useState(false);
  const [editingEvent, setEditingEvent] = useState<AdminEvent | null>(null);
  const [form, setForm] = useState({
    event_name: '', event_description: '', event_date: '',
    target_audience: 'all', event_color: '#28a745',
    staff_comment: '', guardian_message: '', classroom_id: '',
  });

  const monthStr = format(currentMonth, 'yyyy-MM');

  const { data: events = [], isLoading } = useQuery({
    queryKey: ['admin', 'events', monthStr],
    queryFn: async () => {
      const res = await api.get<{ data: AdminEvent[] }>('/api/admin/events', { params: { month: monthStr } });
      const data = res.data.data;
      return Array.isArray(data) ? data : [];
    },
  });

  const saveMutation = useMutation({
    mutationFn: async (data: typeof form) => {
      const payload = {
        ...data,
        classroom_id: data.classroom_id ? Number(data.classroom_id) : null,
      };
      if (editingEvent) return api.put(`/api/admin/events/${editingEvent.id}`, payload);
      return api.post('/api/admin/events', payload);
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

  const emptyForm = {
    event_name: '', event_description: '', event_date: '',
    target_audience: 'all', event_color: '#28a745',
    staff_comment: '', guardian_message: '', classroom_id: '',
  };

  const closeModal = () => { setModalOpen(false); setEditingEvent(null); setForm(emptyForm); };

  const openEdit = (event: AdminEvent) => {
    setEditingEvent(event);
    setForm({
      event_name: event.event_name,
      event_description: event.event_description || '',
      event_date: typeof event.event_date === 'string' ? event.event_date.slice(0, 10) : '',
      target_audience: event.target_audience || 'all',
      event_color: event.event_color || '#28a745',
      staff_comment: event.staff_comment || '',
      guardian_message: event.guardian_message || '',
      classroom_id: event.classroom_id?.toString() || '',
    });
    setModalOpen(true);
  };

  const eventsByDate = useMemo(() => {
    const map: Record<string, AdminEvent[]> = {};
    events.forEach((e) => {
      const dateStr = typeof e.event_date === 'string' ? e.event_date.slice(0, 10) : '';
      if (dateStr) { if (!map[dateStr]) map[dateStr] = []; map[dateStr].push(e); }
    });
    return map;
  }, [events]);

  const calendarDays = useMemo(() => {
    const start = startOfMonth(currentMonth);
    const end = endOfMonth(currentMonth);
    return { days: eachDayOfInterval({ start, end }), startPad: getDay(start) };
  }, [currentMonth]);

  const columns: Column<AdminEvent>[] = [
    { key: 'event_name', label: 'イベント名', render: (e) => <span className="font-medium">{e.event_name}</span> },
    { key: 'event_date', label: '日付', render: (e) => format(new Date(e.event_date), 'yyyy/MM/dd(E)', { locale: ja }) },
    { key: 'target_audience', label: '対象者', render: (e) => <Badge variant="default">{TARGET_LABELS[e.target_audience] || e.target_audience}</Badge> },
    { key: 'event_color', label: '色', render: (e) => <div className="h-5 w-5 rounded" style={{ backgroundColor: e.event_color }} /> },
    { key: 'classroom', label: '事業所', render: (e) => e.classroom?.classroom_name || '全体' },
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
        <h1 className="text-2xl font-bold text-[var(--neutral-foreground-1)]">イベント管理</h1>
        <div className="flex gap-2">
          <div className="flex rounded-lg border border-[var(--neutral-stroke-1)]">
            <button onClick={() => setViewMode('list')} className={`px-3 py-1.5 text-sm rounded-l-lg ${viewMode === 'list' ? 'bg-[var(--brand-80)] text-white' : 'text-[var(--neutral-foreground-3)]'}`}><List className="h-4 w-4" /></button>
            <button onClick={() => setViewMode('calendar')} className={`px-3 py-1.5 text-sm rounded-r-lg ${viewMode === 'calendar' ? 'bg-[var(--brand-80)] text-white' : 'text-[var(--neutral-foreground-3)]'}`}><Calendar className="h-4 w-4" /></button>
          </div>
          <Button onClick={() => { setEditingEvent(null); setForm(emptyForm); setModalOpen(true); }} leftIcon={<Plus className="h-4 w-4" />}>作成</Button>
        </div>
      </div>

      <div className="flex items-center justify-center gap-4">
        <Button variant="ghost" size="sm" onClick={() => setCurrentMonth(subMonths(currentMonth, 1))}><ChevronLeft className="h-4 w-4" /></Button>
        <span className="text-lg font-semibold">{format(currentMonth, 'yyyy年M月', { locale: ja })}</span>
        <Button variant="ghost" size="sm" onClick={() => setCurrentMonth(addMonths(currentMonth, 1))}><ChevronRight className="h-4 w-4" /></Button>
      </div>

      {viewMode === 'list' ? (
        isLoading ? <SkeletonTable rows={5} cols={6} /> : <Table columns={columns} data={events} keyExtractor={(e) => e.id} emptyMessage="イベントがありません" />
      ) : (
        <Card>
          {isLoading ? <SkeletonTable rows={5} cols={7} /> : (
            <div className="grid grid-cols-7 gap-px bg-[var(--neutral-background-5)] rounded-lg overflow-hidden">
              {['日', '月', '火', '水', '木', '金', '土'].map((d) => <div key={d} className="bg-[var(--neutral-background-3)] py-2 text-center text-xs font-semibold text-[var(--neutral-foreground-3)]">{d}</div>)}
              {Array.from({ length: calendarDays.startPad }).map((_, i) => <div key={`p-${i}`} className="bg-white p-2 min-h-[80px]" />)}
              {calendarDays.days.map((day) => {
                const dateStr = format(day, 'yyyy-MM-dd');
                const dayEvents = eventsByDate[dateStr] || [];
                return (
                  <div key={dateStr} className="bg-white p-2 min-h-[80px]">
                    <span className={`text-sm ${isSameDay(day, new Date()) ? 'font-bold text-[var(--brand-80)]' : 'text-[var(--neutral-foreground-2)]'}`}>{format(day, 'd')}</span>
                    {dayEvents.map((ev) => (
                      <div key={ev.id} onClick={() => openEdit(ev)} className="mt-1 truncate rounded px-1 py-0.5 text-xs cursor-pointer hover:opacity-80" style={{ backgroundColor: ev.event_color + '33', color: ev.event_color }}>{ev.event_name}</div>
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
          <Input label="イベント名 *" value={form.event_name} onChange={(e) => setForm({ ...form, event_name: e.target.value })} required />
          <div>
            <label className="mb-1 block text-sm font-medium text-[var(--neutral-foreground-2)]">説明</label>
            <textarea value={form.event_description} onChange={(e) => setForm({ ...form, event_description: e.target.value })} className="block w-full rounded-lg border border-[var(--neutral-stroke-1)] px-3 py-2 text-sm" rows={3} />
          </div>
          <Input label="日付 *" type="date" value={form.event_date} onChange={(e) => setForm({ ...form, event_date: e.target.value })} required />
          <div className="grid grid-cols-2 gap-4">
            <div>
              <label className="mb-1 block text-sm font-medium text-[var(--neutral-foreground-2)]">対象者</label>
              <select value={form.target_audience} onChange={(e) => setForm({ ...form, target_audience: e.target.value })} className="block w-full rounded-md border border-[var(--neutral-stroke-1)] bg-white px-3 py-1.5 text-sm focus:border-[var(--brand-80)] focus:outline-none focus:ring-1 focus:ring-[var(--brand-80)]">
                <option value="all">全員</option>
                <option value="elementary">小学生</option>
                <option value="junior_high_school">中学生</option>
                <option value="guardian">保護者</option>
                <option value="other">その他</option>
              </select>
            </div>
            <div>
              <label className="mb-1 block text-sm font-medium text-[var(--neutral-foreground-2)]">カレンダー色</label>
              <input type="color" value={form.event_color} onChange={(e) => setForm({ ...form, event_color: e.target.value })} className="h-9 w-full rounded-md border border-[var(--neutral-stroke-1)] p-1" />
            </div>
          </div>
          <div>
            <label className="mb-1 block text-sm font-medium text-[var(--neutral-foreground-2)]">スタッフ向けコメント</label>
            <textarea value={form.staff_comment} onChange={(e) => setForm({ ...form, staff_comment: e.target.value })} className="block w-full rounded-lg border border-[var(--neutral-stroke-1)] px-3 py-2 text-sm" rows={2} />
          </div>
          <div>
            <label className="mb-1 block text-sm font-medium text-[var(--neutral-foreground-2)]">保護者・生徒連絡用メッセージ</label>
            <textarea value={form.guardian_message} onChange={(e) => setForm({ ...form, guardian_message: e.target.value })} className="block w-full rounded-lg border border-[var(--neutral-stroke-1)] px-3 py-2 text-sm" rows={2} />
          </div>
          <div className="flex justify-end gap-2 pt-2">
            <Button variant="secondary" type="button" onClick={closeModal}>キャンセル</Button>
            <Button type="submit" isLoading={saveMutation.isPending}>保存</Button>
          </div>
        </form>
      </Modal>
    </div>
  );
}
