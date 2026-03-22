'use client';

import { useState, useMemo } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import api from '@/lib/api';
import { Card, CardHeader, CardTitle } from '@/components/ui/Card';
import { Button } from '@/components/ui/Button';
import { Input } from '@/components/ui/Input';
import { Modal } from '@/components/ui/Modal';
import { SkeletonTable } from '@/components/ui/Skeleton';
import { useToast } from '@/components/ui/Toast';
import { format, startOfMonth, endOfMonth, eachDayOfInterval, getDay, addMonths, subMonths, isSameDay } from 'date-fns';
import { ja } from 'date-fns/locale';
import { ChevronLeft, ChevronRight, Plus, Trash2 } from 'lucide-react';

interface Holiday {
  id: number;
  holiday_date: string;
  holiday_name: string;
  classroom_id: number | null;
  classroom?: { id: number; classroom_name: string } | null;
}

export default function AdminHolidaysPage() {
  const queryClient = useQueryClient();
  const toast = useToast();
  const [currentMonth, setCurrentMonth] = useState(new Date());
  const [modalOpen, setModalOpen] = useState(false);
  const [form, setForm] = useState({ holiday_date: '', holiday_name: '', classroom_id: '' });

  const year = format(currentMonth, 'yyyy');

  const { data: holidays = [], isLoading } = useQuery({
    queryKey: ['admin', 'holidays', year],
    queryFn: async () => {
      const res = await api.get<{ data: Holiday[] }>('/api/admin/holidays', { params: { year } });
      const data = res.data.data;
      return Array.isArray(data) ? data : [];
    },
  });

  const addMutation = useMutation({
    mutationFn: (data: typeof form) => api.post('/api/admin/holidays', {
      ...data,
      classroom_id: data.classroom_id ? Number(data.classroom_id) : null,
    }),
    onSuccess: () => { queryClient.invalidateQueries({ queryKey: ['admin', 'holidays'] }); toast.success('追加しました'); setModalOpen(false); setForm({ holiday_date: '', holiday_name: '', classroom_id: '' }); },
    onError: () => toast.error('追加に失敗しました'),
  });

  const deleteMutation = useMutation({
    mutationFn: (id: number) => api.delete(`/api/admin/holidays/${id}`),
    onSuccess: () => { queryClient.invalidateQueries({ queryKey: ['admin', 'holidays'] }); toast.success('削除しました'); },
    onError: () => toast.error('削除に失敗しました'),
  });

  const holidaysByDate = useMemo(() => {
    const map: Record<string, Holiday> = {};
    holidays.forEach((h) => {
      const dateStr = typeof h.holiday_date === 'string' ? h.holiday_date.slice(0, 10) : '';
      if (dateStr) map[dateStr] = h;
    });
    return map;
  }, [holidays]);

  const calendarDays = useMemo(() => {
    const start = startOfMonth(currentMonth);
    const end = endOfMonth(currentMonth);
    return { days: eachDayOfInterval({ start, end }), startPad: getDay(start) };
  }, [currentMonth]);

  const monthPrefix = format(currentMonth, 'yyyy-MM');
  const monthHolidays = holidays.filter((h) => {
    const dateStr = typeof h.holiday_date === 'string' ? h.holiday_date.slice(0, 10) : '';
    return dateStr.startsWith(monthPrefix);
  });

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-bold text-gray-900">休日管理</h1>
        <Button onClick={() => setModalOpen(true)} leftIcon={<Plus className="h-4 w-4" />}>追加</Button>
      </div>

      <Card>
        <div className="flex items-center justify-between mb-4">
          <Button variant="ghost" size="sm" onClick={() => setCurrentMonth(subMonths(currentMonth, 1))}><ChevronLeft className="h-4 w-4" /></Button>
          <h2 className="text-lg font-semibold">{format(currentMonth, 'yyyy年M月', { locale: ja })}</h2>
          <Button variant="ghost" size="sm" onClick={() => setCurrentMonth(addMonths(currentMonth, 1))}><ChevronRight className="h-4 w-4" /></Button>
        </div>
        {isLoading ? <SkeletonTable rows={5} cols={7} /> : (
          <div className="grid grid-cols-7 gap-px bg-gray-200 rounded-lg overflow-hidden">
            {['日', '月', '火', '水', '木', '金', '土'].map((d) => <div key={d} className="bg-gray-50 py-2 text-center text-xs font-semibold text-gray-500">{d}</div>)}
            {Array.from({ length: calendarDays.startPad }).map((_, i) => <div key={`p-${i}`} className="bg-white p-2 min-h-[70px]" />)}
            {calendarDays.days.map((day) => {
              const dateStr = format(day, 'yyyy-MM-dd');
              const holiday = holidaysByDate[dateStr];
              return (
                <div key={dateStr} className={`p-2 min-h-[70px] ${holiday ? 'bg-red-50' : 'bg-white'}`}>
                  <span className={`text-sm ${isSameDay(day, new Date()) ? 'font-bold text-blue-600' : holiday ? 'text-red-600' : 'text-gray-700'}`}>{format(day, 'd')}</span>
                  {holiday && <div className="mt-1 truncate rounded bg-red-100 px-1 py-0.5 text-xs text-red-700">{holiday.holiday_name}</div>}
                </div>
              );
            })}
          </div>
        )}
      </Card>

      <Card>
        <CardHeader><CardTitle>休日一覧 ({monthHolidays.length}件)</CardTitle></CardHeader>
        {monthHolidays.length === 0 ? <p className="text-sm text-gray-500">この月の休日はありません</p> : (
          <div className="space-y-2">
            {monthHolidays.map((h) => (
              <div key={h.id} className="flex items-center justify-between rounded-lg border border-gray-200 p-3">
                <div>
                  <span className="font-medium text-gray-900">{format(new Date(h.holiday_date), 'M/d(E)', { locale: ja })}</span>
                  <span className="ml-2 text-gray-700">{h.holiday_name}</span>
                  {h.classroom?.classroom_name && <span className="ml-2 text-sm text-gray-500">({h.classroom.classroom_name})</span>}
                </div>
                <Button variant="ghost" size="sm" onClick={() => { if (confirm('削除しますか？')) deleteMutation.mutate(h.id); }}><Trash2 className="h-4 w-4 text-red-500" /></Button>
              </div>
            ))}
          </div>
        )}
      </Card>

      <Modal isOpen={modalOpen} onClose={() => setModalOpen(false)} title="休日を追加">
        <form onSubmit={(e) => { e.preventDefault(); addMutation.mutate(form); }} className="space-y-4">
          <Input label="日付" type="date" value={form.holiday_date} onChange={(e) => setForm({ ...form, holiday_date: e.target.value })} required />
          <Input label="名称" value={form.holiday_name} onChange={(e) => setForm({ ...form, holiday_name: e.target.value })} required />
          <div className="flex justify-end gap-2 pt-2">
            <Button variant="secondary" type="button" onClick={() => setModalOpen(false)}>キャンセル</Button>
            <Button type="submit" isLoading={addMutation.isPending}>追加</Button>
          </div>
        </form>
      </Modal>
    </div>
  );
}
