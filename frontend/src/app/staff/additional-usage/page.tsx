'use client';

import { useState, useMemo } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import api from '@/lib/api';
import { Card, CardHeader, CardTitle } from '@/components/ui/Card';
import { Button } from '@/components/ui/Button';
import { Badge } from '@/components/ui/Badge';
import { SkeletonTable } from '@/components/ui/Skeleton';
import { useToast } from '@/components/ui/Toast';
import { format, startOfMonth, endOfMonth, eachDayOfInterval, getDay, addMonths, subMonths, isSameDay } from 'date-fns';
import { ja } from 'date-fns/locale';
import { ChevronLeft, ChevronRight, Plus, X, Users } from 'lucide-react';

interface AdditionalUsage {
  id: number;
  student_id: number;
  usage_date: string;
  notes: string | null;
  student?: { id: number; student_name: string };
  created_at: string;
}

interface StudentOption {
  id: number;
  student_name: string;
}

export default function AdditionalUsagePage() {
  const queryClient = useQueryClient();
  const toast = useToast();
  const [currentMonth, setCurrentMonth] = useState(new Date());
  const [selectedDate, setSelectedDate] = useState<string | null>(null);
  const [selectedStudent, setSelectedStudent] = useState('');
  const [reason, setReason] = useState('');

  const monthStr = format(currentMonth, 'yyyy-MM');

  const { data: usages = [], isLoading } = useQuery({
    queryKey: ['staff', 'additional-usage', monthStr],
    queryFn: async () => {
      const res = await api.get<{ data: AdditionalUsage[] }>('/api/staff/additional-usage', {
        params: { month: monthStr },
      });
      return res.data.data;
    },
  });

  const { data: students = [] } = useQuery({
    queryKey: ['staff', 'students-list'],
    queryFn: async () => {
      const res = await api.get<{ data: StudentOption[] }>('/api/staff/students', { params: { per_page: 200, status: 'active' } });
      return res.data.data;
    },
  });

  const addMutation = useMutation({
    mutationFn: async (data: { student_id: number; usage_date: string; notes: string }) => {
      return api.post('/api/staff/additional-usage', data);
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['staff', 'additional-usage'] });
      toast.success('追加利用日を登録しました');
      setSelectedStudent('');
      setReason('');
    },
    onError: () => toast.error('登録に失敗しました'),
  });

  const removeMutation = useMutation({
    mutationFn: (id: number) => api.delete(`/api/staff/additional-usage/${id}`),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['staff', 'additional-usage'] });
      toast.success('追加利用日を削除しました');
    },
    onError: () => toast.error('削除に失敗しました'),
  });

  const usagesByDate = useMemo(() => {
    const map: Record<string, AdditionalUsage[]> = {};
    usages.forEach((u) => {
      if (!map[u.usage_date]) map[u.usage_date] = [];
      map[u.usage_date].push(u);
    });
    return map;
  }, [usages]);

  const calendarDays = useMemo(() => {
    const start = startOfMonth(currentMonth);
    const end = endOfMonth(currentMonth);
    return { days: eachDayOfInterval({ start, end }), startPad: getDay(start) };
  }, [currentMonth]);

  const selectedUsages = selectedDate ? usagesByDate[selectedDate] || [] : [];

  return (
    <div className="space-y-6">
      <h1 className="text-2xl font-bold text-[var(--neutral-foreground-1)]">追加利用管理</h1>

      {/* Summary */}
      <div className="grid gap-4 sm:grid-cols-2">
        <Card>
          <div className="text-center">
            <p className="text-sm text-[var(--neutral-foreground-3)]">今月の追加利用件数</p>
            <p className="text-3xl font-bold text-[var(--brand-80)]">{usages.length}</p>
          </div>
        </Card>
        <Card>
          <div className="text-center">
            <p className="text-sm text-[var(--neutral-foreground-3)]">利用生徒数</p>
            <p className="text-3xl font-bold text-[var(--status-success-fg)]">
              {new Set(usages.map((u) => u.student_id)).size}
            </p>
          </div>
        </Card>
      </div>

      {/* Calendar */}
      <Card>
        <div className="flex items-center justify-between mb-4">
          <Button variant="ghost" size="sm" onClick={() => setCurrentMonth(subMonths(currentMonth, 1))}>
            <ChevronLeft className="h-4 w-4" />
          </Button>
          <h2 className="text-lg font-semibold">{format(currentMonth, 'yyyy年M月', { locale: ja })}</h2>
          <Button variant="ghost" size="sm" onClick={() => setCurrentMonth(addMonths(currentMonth, 1))}>
            <ChevronRight className="h-4 w-4" />
          </Button>
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
              const dayUsages = usagesByDate[dateStr] || [];
              const isSelected = selectedDate === dateStr;
              const isToday = isSameDay(day, new Date());
              return (
                <div
                  key={dateStr}
                  onClick={() => setSelectedDate(dateStr)}
                  className={`bg-[var(--neutral-background-1)] p-2 min-h-[70px] cursor-pointer hover:bg-[var(--brand-160)] transition-colors ${isSelected ? 'ring-2 ring-[var(--brand-80)] ring-inset' : ''}`}
                >
                  <span className={`text-sm ${isToday ? 'font-bold text-[var(--brand-80)]' : 'text-[var(--neutral-foreground-2)]'}`}>
                    {format(day, 'd')}
                  </span>
                  {dayUsages.length > 0 && (
                    <div className="mt-1">
                      <Badge variant="primary" className="text-[10px]">
                        <Users className="mr-0.5 inline h-3 w-3" />
                        {dayUsages.length}名
                      </Badge>
                    </div>
                  )}
                </div>
              );
            })}
          </div>
        )}
      </Card>

      {/* Selected date detail */}
      {selectedDate && (
        <Card>
          <CardHeader>
            <CardTitle>{format(new Date(selectedDate), 'M月d日(E)', { locale: ja })} の追加利用</CardTitle>
          </CardHeader>

          {/* Add form */}
          <div className="mb-4 flex flex-col gap-2 rounded-lg border border-[var(--neutral-stroke-2)] p-3 sm:flex-row sm:items-end">
            <div className="flex-1">
              <label className="mb-1 block text-xs font-medium text-[var(--neutral-foreground-2)]">生徒</label>
              <select
                value={selectedStudent}
                onChange={(e) => setSelectedStudent(e.target.value)}
                className="w-full rounded-lg border border-[var(--neutral-stroke-2)] px-3 py-2 text-sm"
              >
                <option value="">生徒を選択...</option>
                {students.map((s) => (
                  <option key={s.id} value={s.id}>{s.student_name}</option>
                ))}
              </select>
            </div>
            <div className="flex-1">
              <label className="mb-1 block text-xs font-medium text-[var(--neutral-foreground-2)]">備考（任意）</label>
              <input
                type="text"
                value={reason}
                onChange={(e) => setReason(e.target.value)}
                placeholder="追加利用の備考..."
                className="w-full rounded-lg border border-[var(--neutral-stroke-2)] px-3 py-2 text-sm"
              />
            </div>
            <Button
              size="sm"
              disabled={!selectedStudent}
              onClick={() => addMutation.mutate({ student_id: Number(selectedStudent), usage_date: selectedDate, notes: reason })}
              isLoading={addMutation.isPending}
              leftIcon={<Plus className="h-4 w-4" />}
            >
              追加
            </Button>
          </div>

          {/* List */}
          {selectedUsages.length === 0 ? (
            <p className="text-sm text-[var(--neutral-foreground-3)]">この日の追加利用はありません</p>
          ) : (
            <div className="space-y-2">
              {selectedUsages.map((usage) => (
                <div key={usage.id} className="flex items-center justify-between rounded-lg border border-[var(--neutral-stroke-2)] p-3">
                  <div>
                    <span className="font-medium text-[var(--neutral-foreground-1)]">{usage.student?.student_name || '-'}</span>
                    {usage.notes && (
                      <span className="ml-2 text-sm text-[var(--neutral-foreground-3)]">({usage.notes})</span>
                    )}
                  </div>
                  <Button
                    variant="ghost"
                    size="sm"
                    onClick={() => { if (confirm('この追加利用を削除しますか？')) removeMutation.mutate(usage.id); }}
                  >
                    <X className="h-4 w-4 text-[var(--status-danger-fg)]" />
                  </Button>
                </div>
              ))}
            </div>
          )}
        </Card>
      )}
    </div>
  );
}
