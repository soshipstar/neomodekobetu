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
  date: string;
  name: string;
  is_recurring: boolean;
  created_at: string;
}

export default function HolidaysPage() {
  const queryClient = useQueryClient();
  const toast = useToast();
  const [currentMonth, setCurrentMonth] = useState(new Date());
  const [modalOpen, setModalOpen] = useState(false);
  const [form, setForm] = useState({ date: '', name: '', is_recurring: false });

  const year = format(currentMonth, 'yyyy');

  const { data: holidays = [], isLoading } = useQuery({
    queryKey: ['staff', 'holidays', year],
    queryFn: async () => {
      const res = await api.get<{ data: Holiday[] }>('/api/staff/holidays', { params: { year } });
      return res.data.data;
    },
  });

  const addMutation = useMutation({
    mutationFn: (data: typeof form) => api.post('/api/staff/holidays', data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['staff', 'holidays'] });
      toast.success('休日を追加しました');
      setModalOpen(false);
      setForm({ date: '', name: '', is_recurring: false });
    },
    onError: () => toast.error('追加に失敗しました'),
  });

  const deleteMutation = useMutation({
    mutationFn: (id: number) => api.delete(`/api/staff/holidays/${id}`),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['staff', 'holidays'] });
      toast.success('休日を削除しました');
    },
    onError: () => toast.error('削除に失敗しました'),
  });

  const holidaysByDate = useMemo(() => {
    const map: Record<string, Holiday> = {};
    holidays.forEach((h) => { map[h.date] = h; });
    return map;
  }, [holidays]);

  const calendarDays = useMemo(() => {
    const start = startOfMonth(currentMonth);
    const end = endOfMonth(currentMonth);
    return { days: eachDayOfInterval({ start, end }), startPad: getDay(start) };
  }, [currentMonth]);

  const monthHolidays = holidays.filter((h) => h.date.startsWith(format(currentMonth, 'yyyy-MM')));

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-bold text-[var(--neutral-foreground-1)]">休日管理</h1>
        <Button onClick={() => setModalOpen(true)} leftIcon={<Plus className="h-4 w-4" />}>
          休日を追加
        </Button>
      </div>

      {/* Calendar */}
      <Card>
        <div className="flex items-center justify-between mb-4">
          <Button variant="ghost" size="sm" onClick={() => setCurrentMonth(subMonths(currentMonth, 1))}><ChevronLeft className="h-4 w-4" /></Button>
          <h2 className="text-lg font-semibold">{format(currentMonth, 'yyyy年M月', { locale: ja })}</h2>
          <Button variant="ghost" size="sm" onClick={() => setCurrentMonth(addMonths(currentMonth, 1))}><ChevronRight className="h-4 w-4" /></Button>
        </div>

        {isLoading ? (
          <SkeletonTable rows={5} cols={7} />
        ) : (
          <div className="grid grid-cols-7 gap-px bg-[var(--neutral-stroke-2)] rounded-lg overflow-hidden">
            {['日', '月', '火', '水', '木', '金', '土'].map((d) => (
              <div key={d} className="bg-[var(--neutral-background-2)] py-2 text-center text-xs font-semibold text-[var(--neutral-foreground-3)]">{d}</div>
            ))}
            {Array.from({ length: calendarDays.startPad }).map((_, i) => (
              <div key={`pad-${i}`} className="bg-[var(--neutral-background-1)] p-2 min-h-[70px]" />
            ))}
            {calendarDays.days.map((day) => {
              const dateStr = format(day, 'yyyy-MM-dd');
              const holiday = holidaysByDate[dateStr];
              const isToday = isSameDay(day, new Date());
              const dayOfWeek = getDay(day);
              const isWeekend = dayOfWeek === 0 || dayOfWeek === 6;
              return (
                <div
                  key={dateStr}
                  className={`p-2 min-h-[70px] ${holiday ? 'bg-[var(--status-danger-bg)]' : isWeekend ? 'bg-[var(--neutral-background-2)]' : 'bg-[var(--neutral-background-1)]'}`}
                >
                  <span className={`text-sm ${isToday ? 'font-bold text-[var(--brand-80)]' : holiday || dayOfWeek === 0 ? 'text-[var(--status-danger-fg)]' : dayOfWeek === 6 ? 'text-[var(--brand-80)]' : 'text-[var(--neutral-foreground-2)]'}`}>
                    {format(day, 'd')}
                  </span>
                  {holiday && (
                    <div className="mt-1 truncate rounded bg-[var(--status-danger-bg)] px-1 py-0.5 text-xs text-[var(--status-danger-fg)]">
                      {holiday.name}
                    </div>
                  )}
                </div>
              );
            })}
          </div>
        )}
      </Card>

      {/* Holiday List */}
      <Card>
        <CardHeader>
          <CardTitle>この月の休日一覧 ({monthHolidays.length}件)</CardTitle>
        </CardHeader>
        {monthHolidays.length === 0 ? (
          <p className="text-sm text-[var(--neutral-foreground-3)]">この月の休日はありません</p>
        ) : (
          <div className="space-y-2">
            {monthHolidays.map((holiday) => (
              <div key={holiday.id} className="flex items-center justify-between rounded-lg border border-[var(--neutral-stroke-2)] p-3">
                <div className="flex items-center gap-3">
                  <div className="text-center">
                    <p className="text-lg font-bold text-[var(--status-danger-fg)]">{format(new Date(holiday.date), 'd')}</p>
                    <p className="text-xs text-[var(--neutral-foreground-3)]">{format(new Date(holiday.date), 'E', { locale: ja })}</p>
                  </div>
                  <div>
                    <p className="font-medium text-[var(--neutral-foreground-1)]">{holiday.name}</p>
                    {holiday.is_recurring && <p className="text-xs text-[var(--neutral-foreground-3)]">毎年繰り返し</p>}
                  </div>
                </div>
                <Button
                  variant="ghost"
                  size="sm"
                  onClick={() => { if (confirm(`「${holiday.name}」を削除しますか？`)) deleteMutation.mutate(holiday.id); }}
                >
                  <Trash2 className="h-4 w-4 text-[var(--status-danger-fg)]" />
                </Button>
              </div>
            ))}
          </div>
        )}
      </Card>

      {/* Add Modal */}
      <Modal isOpen={modalOpen} onClose={() => setModalOpen(false)} title="休日を追加">
        <form onSubmit={(e) => { e.preventDefault(); addMutation.mutate(form); }} className="space-y-4">
          <Input label="日付" type="date" value={form.date} onChange={(e) => setForm({ ...form, date: e.target.value })} required />
          <Input label="名称" value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} required placeholder="例: 元旦、お盆休み" />
          <label className="flex items-center gap-2">
            <input type="checkbox" checked={form.is_recurring} onChange={(e) => setForm({ ...form, is_recurring: e.target.checked })} className="rounded border-[var(--neutral-stroke-2)]" />
            <span className="text-sm text-[var(--neutral-foreground-2)]">毎年繰り返す</span>
          </label>
          <div className="flex justify-end gap-2 pt-2">
            <Button variant="secondary" type="button" onClick={() => setModalOpen(false)}>キャンセル</Button>
            <Button type="submit" isLoading={addMutation.isPending}>追加</Button>
          </div>
        </form>
      </Modal>
    </div>
  );
}
