'use client';

import { useState, useMemo } from 'react';
import Link from 'next/link';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import api from '@/lib/api';
import { useToast } from '@/components/ui/Toast';
import { format, startOfMonth, endOfMonth, getDay, getDaysInMonth, addMonths, subMonths, isSameDay, isToday } from 'date-fns';
import { ja } from 'date-fns/locale';

interface ActivityRecord {
  id: number;
  activity_name: string;
  common_activity: string | null;
  record_date: string;
  participant_count: number;
  staff: { id: number; full_name: string } | null;
  student_records: Array<{
    id: number;
    student_id: number;
    student: { id: number; student_name: string };
  }>;
}

const DAY_HEADERS = ['日', '月', '火', '水', '木', '金', '土'];

export default function TabletHomePage() {
  const queryClient = useQueryClient();
  const toast = useToast();
  const [selectedDate, setSelectedDate] = useState(new Date());
  const [calendarMonth, setCalendarMonth] = useState(new Date());

  const selectedDateStr = format(selectedDate, 'yyyy-MM-dd');
  const calYear = calendarMonth.getFullYear();
  const calMonth = calendarMonth.getMonth() + 1;

  // 活動一覧
  const { data: activities = [], isLoading } = useQuery({
    queryKey: ['tablet', 'activities', selectedDateStr],
    queryFn: async () => {
      const res = await api.get<{ data: ActivityRecord[] }>(`/api/tablet/activities/${selectedDateStr}`);
      return res.data.data;
    },
  });

  // 活動がある日付一覧
  const { data: activeDates = [] } = useQuery({
    queryKey: ['tablet', 'active-dates', calYear, calMonth],
    queryFn: async () => {
      const res = await api.get<{ data: string[] }>('/api/tablet/active-dates', {
        params: { year: calYear, month: calMonth },
      });
      return res.data.data;
    },
  });

  // 削除
  const deleteMutation = useMutation({
    mutationFn: (id: number) => api.delete(`/api/tablet/activities/${id}`),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['tablet', 'activities', selectedDateStr] });
      queryClient.invalidateQueries({ queryKey: ['tablet', 'active-dates'] });
      toast.success('活動を削除しました');
    },
    onError: () => toast.error('削除に失敗しました'),
  });

  const handleDelete = (id: number) => {
    if (confirm('この活動を削除してもよろしいですか？')) {
      deleteMutation.mutate(id);
    }
  };

  // カレンダー生成
  const calendarDays = useMemo(() => {
    const first = startOfMonth(calendarMonth);
    const firstDow = getDay(first);
    const daysInMonth = getDaysInMonth(calendarMonth);
    const days: Array<{ day: number; date: Date; dateStr: string } | null> = [];

    for (let i = 0; i < firstDow; i++) {
      days.push(null);
    }
    for (let d = 1; d <= daysInMonth; d++) {
      const date = new Date(calYear, calMonth - 1, d);
      days.push({ day: d, date, dateStr: format(date, 'yyyy-MM-dd') });
    }
    return days;
  }, [calendarMonth, calYear, calMonth]);

  return (
    <div className="space-y-6">
      {/* カレンダー */}
      <div className="rounded-xl bg-white p-6 shadow-md">
        <div className="mb-6 flex items-center justify-between">
          <div className="flex items-center gap-4">
            <button
              onClick={() => setCalendarMonth(subMonths(calendarMonth, 1))}
              className="rounded-lg bg-[var(--brand-80)] px-6 py-3 text-2xl font-bold text-white hover:bg-blue-700"
            >
              ◀
            </button>
            <span className="text-2xl font-bold">{calYear}年{calMonth}月</span>
            <button
              onClick={() => setCalendarMonth(addMonths(calendarMonth, 1))}
              className="rounded-lg bg-[var(--brand-80)] px-6 py-3 text-2xl font-bold text-white hover:bg-blue-700"
            >
              ▶
            </button>
          </div>
          <Link
            href={`/tablet/activity/edit?date=${selectedDateStr}`}
            className="rounded-lg bg-green-600 px-6 py-3 text-xl font-bold text-white hover:bg-green-700"
          >
            + 新しい活動を追加
          </Link>
        </div>

        <div className="grid grid-cols-7 gap-2">
          {DAY_HEADERS.map((d) => (
            <div key={d} className="rounded-md bg-[var(--neutral-background-4)] py-3 text-center text-xl font-bold">
              {d}
            </div>
          ))}
          {calendarDays.map((cell, i) => {
            if (!cell) {
              return <div key={`e-${i}`} className="aspect-square" />;
            }
            const hasActivity = activeDates.includes(cell.dateStr);
            const isSelected = isSameDay(cell.date, selectedDate);
            const today = isToday(cell.date);
            return (
              <button
                key={cell.dateStr}
                onClick={() => setSelectedDate(cell.date)}
                className={`flex aspect-square items-center justify-center rounded-md text-xl font-medium transition-all
                  ${hasActivity ? 'bg-green-100 font-bold' : 'bg-[var(--neutral-background-3)]'}
                  ${isSelected ? 'bg-[var(--brand-80)] text-white' : ''}
                  ${today && !isSelected ? 'ring-2 ring-blue-500' : ''}
                  hover:bg-[var(--neutral-background-5)]`}
              >
                {cell.day}
              </button>
            );
          })}
        </div>
      </div>

      {/* 活動一覧 */}
      <div className="rounded-xl bg-white p-6 shadow-md">
        <h2 className="mb-6 text-2xl font-bold">
          {format(selectedDate, 'yyyy年M月d日', { locale: ja })}の活動
        </h2>

        {isLoading ? (
          <div className="py-12 text-center text-xl text-[var(--neutral-foreground-4)]">読み込み中...</div>
        ) : activities.length === 0 ? (
          <div className="py-12 text-center text-xl text-[var(--neutral-foreground-4)]">
            この日の活動はまだ登録されていません。<br />
            「新しい活動を追加」ボタンから登録してください。
          </div>
        ) : (
          <div className="space-y-4">
            {activities.map((activity) => (
              <div
                key={activity.id}
                className="flex flex-wrap items-center justify-between gap-4 rounded-lg border-2 border-[var(--neutral-stroke-2)] p-5"
              >
                <div className="flex-1">
                  <div className="text-xl font-bold">
                    {activity.activity_name || activity.common_activity}
                  </div>
                  <div className="mt-1 text-base text-[var(--neutral-foreground-3)]">
                    {activity.staff?.full_name} | {activity.participant_count ?? activity.student_records?.length ?? 0}名参加
                  </div>
                </div>
                <div className="flex flex-wrap gap-3">
                  <Link
                    href={`/tablet/activity/edit?id=${activity.id}&date=${selectedDateStr}`}
                    className="rounded-lg bg-[var(--brand-80)] px-5 py-3 text-lg font-bold text-white hover:bg-blue-700"
                  >
                    編集
                  </Link>
                  <Link
                    href={`/tablet/renrakucho?activity_id=${activity.id}`}
                    className="rounded-lg bg-green-600 px-5 py-3 text-lg font-bold text-white hover:bg-green-700"
                  >
                    連絡帳入力
                  </Link>
                  <Link
                    href={`/tablet/integrate?id=${activity.id}&date=${selectedDateStr}`}
                    className="rounded-lg bg-gray-600 px-5 py-3 text-lg font-bold text-white hover:bg-gray-700"
                  >
                    統合
                  </Link>
                  <button
                    onClick={() => handleDelete(activity.id)}
                    className="rounded-lg bg-red-500 px-5 py-3 text-lg font-bold text-white hover:bg-red-600"
                  >
                    削除
                  </button>
                </div>
              </div>
            ))}
          </div>
        )}
      </div>
    </div>
  );
}
