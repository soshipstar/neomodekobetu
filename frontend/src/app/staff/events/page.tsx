'use client';

import { useState, useMemo } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import api from '@/lib/api';
import { Card, CardHeader, CardTitle } from '@/components/ui/Card';
import { Button } from '@/components/ui/Button';
import { Input } from '@/components/ui/Input';
import { Badge } from '@/components/ui/Badge';
import { Modal } from '@/components/ui/Modal';
import { Tabs } from '@/components/ui/Tabs';
import { Table, type Column } from '@/components/ui/Table';
import { SkeletonTable } from '@/components/ui/Skeleton';
import { useToast } from '@/components/ui/Toast';
import { format, startOfMonth, endOfMonth, eachDayOfInterval, getDay, addMonths, subMonths, isSameDay } from 'date-fns';
import { ja } from 'date-fns/locale';
import { Plus, Pencil, Trash2, ChevronLeft, ChevronRight, Calendar, List, Users } from 'lucide-react';

interface Event {
  id: number;
  title: string;
  description: string;
  date: string;
  start_time: string | null;
  end_time: string | null;
  location: string | null;
  capacity: number | null;
  registration_count: number;
  is_published: boolean;
  created_at: string;
}

interface Registration {
  id: number;
  student_id: number;
  student_name: string;
  guardian_name: string;
  registered_at: string;
  status: 'registered' | 'cancelled';
}

export default function EventsPage() {
  const queryClient = useQueryClient();
  const toast = useToast();
  const [viewMode, setViewMode] = useState<'calendar' | 'list'>('list');
  const [currentMonth, setCurrentMonth] = useState(new Date());
  const [modalOpen, setModalOpen] = useState(false);
  const [registrationModal, setRegistrationModal] = useState(false);
  const [editingEvent, setEditingEvent] = useState<Event | null>(null);
  const [form, setForm] = useState({ title: '', description: '', date: '', start_time: '', end_time: '', location: '', capacity: '', is_published: true });

  const monthStr = format(currentMonth, 'yyyy-MM');

  const { data: events = [], isLoading } = useQuery({
    queryKey: ['staff', 'events', monthStr],
    queryFn: async () => {
      const res = await api.get<{ data: Event[] }>('/api/staff/events', { params: { month: monthStr } });
      return res.data.data;
    },
  });

  const { data: registrations = [] } = useQuery({
    queryKey: ['staff', 'event-registrations', editingEvent?.id],
    queryFn: async () => {
      const res = await api.get<{ data: Registration[] }>(`/api/staff/events/${editingEvent!.id}/registrations`);
      return res.data.data;
    },
    enabled: !!editingEvent && registrationModal,
  });

  const saveMutation = useMutation({
    mutationFn: async (data: typeof form) => {
      if (editingEvent) return api.put(`/api/staff/events/${editingEvent.id}`, data);
      return api.post('/api/staff/events', data);
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['staff', 'events'] });
      toast.success(editingEvent ? 'イベントを更新しました' : 'イベントを作成しました');
      closeModal();
    },
    onError: () => toast.error('保存に失敗しました'),
  });

  const deleteMutation = useMutation({
    mutationFn: (id: number) => api.delete(`/api/staff/events/${id}`),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['staff', 'events'] });
      toast.success('イベントを削除しました');
    },
    onError: () => toast.error('削除に失敗しました'),
  });

  const closeModal = () => {
    setModalOpen(false);
    setEditingEvent(null);
    setForm({ title: '', description: '', date: '', start_time: '', end_time: '', location: '', capacity: '', is_published: true });
  };

  const openEdit = (event: Event) => {
    setEditingEvent(event);
    setForm({
      title: event.title,
      description: event.description,
      date: event.date,
      start_time: event.start_time || '',
      end_time: event.end_time || '',
      location: event.location || '',
      capacity: event.capacity?.toString() || '',
      is_published: event.is_published,
    });
    setModalOpen(true);
  };

  const eventsByDate = useMemo(() => {
    const map: Record<string, Event[]> = {};
    events.forEach((e) => { if (!map[e.date]) map[e.date] = []; map[e.date].push(e); });
    return map;
  }, [events]);

  const calendarDays = useMemo(() => {
    const start = startOfMonth(currentMonth);
    const end = endOfMonth(currentMonth);
    return { days: eachDayOfInterval({ start, end }), startPad: getDay(start) };
  }, [currentMonth]);

  const columns: Column<Event>[] = [
    { key: 'title', label: 'イベント名', render: (e) => <span className="font-medium text-[var(--neutral-foreground-1)]">{e.title}</span> },
    { key: 'date', label: '日付', render: (e) => format(new Date(e.date), 'yyyy/MM/dd(E)', { locale: ja }) },
    { key: 'time', label: '時間', render: (e) => e.start_time ? `${e.start_time}${e.end_time ? ` - ${e.end_time}` : ''}` : '-' },
    { key: 'location', label: '場所', render: (e) => e.location || '-' },
    {
      key: 'registrations',
      label: '参加者',
      render: (e) => (
        <button onClick={() => { setEditingEvent(e); setRegistrationModal(true); }} className="flex items-center gap-1 text-sm text-[var(--brand-80)] hover:underline">
          <Users className="h-3 w-3" /> {e.registration_count}名
          {e.capacity && <span className="text-[var(--neutral-foreground-4)]">/ {e.capacity}</span>}
        </button>
      ),
    },
    {
      key: 'is_published',
      label: '公開',
      render: (e) => <Badge variant={e.is_published ? 'success' : 'default'}>{e.is_published ? '公開中' : '下書き'}</Badge>,
    },
    {
      key: 'actions',
      label: '操作',
      render: (e) => (
        <div className="flex gap-1">
          <Button variant="ghost" size="sm" onClick={() => openEdit(e)}><Pencil className="h-4 w-4" /></Button>
          <Button variant="ghost" size="sm" onClick={() => { if (confirm('削除しますか？')) deleteMutation.mutate(e.id); }}>
            <Trash2 className="h-4 w-4 text-[var(--status-danger-fg)]" />
          </Button>
        </div>
      ),
    },
  ];

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-bold text-[var(--neutral-foreground-1)]">イベント管理</h1>
        <div className="flex gap-2">
          <div className="flex rounded-lg border border-[var(--neutral-stroke-2)]">
            <button onClick={() => setViewMode('list')} className={`px-3 py-1.5 text-sm ${viewMode === 'list' ? 'bg-[var(--brand-80)] text-white' : 'text-[var(--neutral-foreground-2)]'} rounded-l-lg`}>
              <List className="h-4 w-4" />
            </button>
            <button onClick={() => setViewMode('calendar')} className={`px-3 py-1.5 text-sm ${viewMode === 'calendar' ? 'bg-[var(--brand-80)] text-white' : 'text-[var(--neutral-foreground-2)]'} rounded-r-lg`}>
              <Calendar className="h-4 w-4" />
            </button>
          </div>
          <Button onClick={() => { setEditingEvent(null); setForm({ title: '', description: '', date: '', start_time: '', end_time: '', location: '', capacity: '', is_published: true }); setModalOpen(true); }} leftIcon={<Plus className="h-4 w-4" />}>
            イベント作成
          </Button>
        </div>
      </div>

      {/* Month navigation */}
      <div className="flex items-center justify-center gap-4">
        <Button variant="ghost" size="sm" onClick={() => setCurrentMonth(subMonths(currentMonth, 1))}><ChevronLeft className="h-4 w-4" /></Button>
        <span className="text-lg font-semibold">{format(currentMonth, 'yyyy年M月', { locale: ja })}</span>
        <Button variant="ghost" size="sm" onClick={() => setCurrentMonth(addMonths(currentMonth, 1))}><ChevronRight className="h-4 w-4" /></Button>
      </div>

      {viewMode === 'list' ? (
        isLoading ? <SkeletonTable rows={5} cols={7} /> : (
          <Table columns={columns} data={events} keyExtractor={(e) => e.id} emptyMessage="イベントがありません" />
        )
      ) : (
        <Card>
          {isLoading ? <SkeletonTable rows={5} cols={7} /> : (
            <div className="grid grid-cols-7 gap-px bg-[var(--neutral-stroke-2)] rounded-lg overflow-hidden">
              {['日', '月', '火', '水', '木', '金', '土'].map((d) => (
                <div key={d} className="bg-[var(--neutral-background-2)] py-2 text-center text-xs font-semibold text-[var(--neutral-foreground-3)]">{d}</div>
              ))}
              {Array.from({ length: calendarDays.startPad }).map((_, i) => <div key={`p-${i}`} className="bg-[var(--neutral-background-1)] p-2 min-h-[80px]" />)}
              {calendarDays.days.map((day) => {
                const dateStr = format(day, 'yyyy-MM-dd');
                const dayEvents = eventsByDate[dateStr] || [];
                return (
                  <div key={dateStr} className="bg-[var(--neutral-background-1)] p-2 min-h-[80px]">
                    <span className={`text-sm ${isSameDay(day, new Date()) ? 'font-bold text-[var(--brand-80)]' : 'text-[var(--neutral-foreground-2)]'}`}>{format(day, 'd')}</span>
                    {dayEvents.map((ev) => (
                      <div key={ev.id} onClick={() => openEdit(ev)} className="mt-1 truncate rounded bg-[var(--status-success-bg)] px-1 py-0.5 text-xs text-[var(--status-success-fg)] cursor-pointer hover:bg-[var(--status-success-bg)]">
                        {ev.title}
                      </div>
                    ))}
                  </div>
                );
              })}
            </div>
          )}
        </Card>
      )}

      {/* Event Form Modal */}
      <Modal isOpen={modalOpen} onClose={closeModal} title={editingEvent ? 'イベントを編集' : 'イベントを作成'} size="lg">
        <form onSubmit={(e) => { e.preventDefault(); saveMutation.mutate(form); }} className="space-y-4">
          <Input label="イベント名" value={form.title} onChange={(e) => setForm({ ...form, title: e.target.value })} required />
          <div>
            <label className="mb-1 block text-sm font-medium text-[var(--neutral-foreground-2)]">説明</label>
            <textarea value={form.description} onChange={(e) => setForm({ ...form, description: e.target.value })} className="block w-full rounded-lg border border-[var(--neutral-stroke-2)] px-3 py-2 text-sm" rows={3} />
          </div>
          <Input label="日付" type="date" value={form.date} onChange={(e) => setForm({ ...form, date: e.target.value })} required />
          <div className="grid grid-cols-2 gap-4">
            <Input label="開始時間" type="time" value={form.start_time} onChange={(e) => setForm({ ...form, start_time: e.target.value })} />
            <Input label="終了時間" type="time" value={form.end_time} onChange={(e) => setForm({ ...form, end_time: e.target.value })} />
          </div>
          <Input label="場所" value={form.location} onChange={(e) => setForm({ ...form, location: e.target.value })} />
          <Input label="定員（任意）" type="number" value={form.capacity} onChange={(e) => setForm({ ...form, capacity: e.target.value })} />
          <label className="flex items-center gap-2">
            <input type="checkbox" checked={form.is_published} onChange={(e) => setForm({ ...form, is_published: e.target.checked })} className="rounded border-[var(--neutral-stroke-2)]" />
            <span className="text-sm text-[var(--neutral-foreground-2)]">公開する</span>
          </label>
          <div className="flex justify-end gap-2 pt-2">
            <Button variant="secondary" type="button" onClick={closeModal}>キャンセル</Button>
            <Button type="submit" isLoading={saveMutation.isPending}>保存</Button>
          </div>
        </form>
      </Modal>

      {/* Registrations Modal */}
      <Modal isOpen={registrationModal} onClose={() => { setRegistrationModal(false); setEditingEvent(null); }} title={`${editingEvent?.title || ''} - 参加者一覧`} size="lg">
        {registrations.length === 0 ? (
          <p className="py-8 text-center text-sm text-[var(--neutral-foreground-3)]">参加者はいません</p>
        ) : (
          <div className="space-y-2">
            {registrations.map((reg) => (
              <div key={reg.id} className="flex items-center justify-between rounded-lg border border-[var(--neutral-stroke-2)] p-3">
                <div>
                  <span className="font-medium text-[var(--neutral-foreground-1)]">{reg.student_name}</span>
                  <span className="ml-2 text-sm text-[var(--neutral-foreground-3)]">({reg.guardian_name})</span>
                </div>
                <div className="flex items-center gap-2">
                  <Badge variant={reg.status === 'registered' ? 'success' : 'danger'}>
                    {reg.status === 'registered' ? '参加' : 'キャンセル'}
                  </Badge>
                  <span className="text-xs text-[var(--neutral-foreground-4)]">{format(new Date(reg.registered_at), 'M/d HH:mm')}</span>
                </div>
              </div>
            ))}
          </div>
        )}
      </Modal>
    </div>
  );
}
