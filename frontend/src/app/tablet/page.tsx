'use client';

import { useState, useMemo } from 'react';
import Link from 'next/link';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import api from '@/lib/api';
import { useToast } from '@/components/ui/Toast';
import { format, startOfMonth, endOfMonth, getDay, getDaysInMonth, addMonths, subMonths, isSameDay, isToday } from 'date-fns';
import { ja } from 'date-fns/locale';
import { MaterialIcon } from '@/components/ui/MaterialIcon';

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
      <div className="rounded-xl bg-white p-3 shadow-md sm:p-5 lg:p-6">
        {/* カレンダー上部コントロール: 月ナビ + 新規追加ボタン
            狭幅では縦並び、sm 以上で横並びにして折り返し回避 */}
        <div className="mb-4 flex flex-col gap-2 sm:mb-6 sm:flex-row sm:items-center sm:justify-between sm:gap-3">
          <div className="flex items-center justify-between gap-2 sm:justify-start sm:gap-3 lg:gap-4">
            <button
              onClick={() => setCalendarMonth(subMonths(calendarMonth, 1))}
              className="flex items-center justify-center rounded-lg bg-[var(--brand-80)] p-2 text-white hover:bg-blue-700 sm:p-2.5 lg:px-4"
              aria-label="前の月"
              title="前の月"
            >
              <MaterialIcon name="chevron_left" size={24} />
            </button>
            <span className="whitespace-nowrap text-lg font-bold sm:text-xl lg:text-2xl">
              {calYear}年{calMonth}月
            </span>
            <button
              onClick={() => setCalendarMonth(addMonths(calendarMonth, 1))}
              className="flex items-center justify-center rounded-lg bg-[var(--brand-80)] p-2 text-white hover:bg-blue-700 sm:p-2.5 lg:px-4"
              aria-label="次の月"
              title="次の月"
            >
              <MaterialIcon name="chevron_right" size={24} />
            </button>
          </div>
          <Link
            href={`/tablet/activity/edit?date=${selectedDateStr}`}
            className="flex items-center justify-center gap-1.5 rounded-lg bg-green-600 px-3 py-2 text-sm font-bold text-white hover:bg-green-700 sm:px-4 sm:text-base lg:px-5 lg:py-2.5 lg:text-lg"
            title="新しい活動を追加"
          >
            <MaterialIcon name="add" size={20} />
            <span>新しい活動を追加</span>
          </Link>
        </div>

        <div className="grid grid-cols-7 gap-1 sm:gap-2">
          {DAY_HEADERS.map((d) => (
            <div key={d} className="rounded-md bg-[var(--neutral-background-4)] py-2 text-center text-sm font-bold sm:py-3 sm:text-base lg:text-xl">
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
                className={`flex aspect-square items-center justify-center rounded-md text-sm font-medium transition-all sm:text-base lg:text-xl
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
      <div className="rounded-xl bg-white p-3 shadow-md sm:p-5 lg:p-6">
        <h2 className="mb-4 text-lg font-bold sm:mb-6 sm:text-xl lg:text-2xl">
          {format(selectedDate, 'yyyy年M月d日', { locale: ja })}の活動
        </h2>

        {isLoading ? (
          <div className="py-12 text-center text-base text-[var(--neutral-foreground-4)] sm:text-xl">読み込み中...</div>
        ) : activities.length === 0 ? (
          <div className="py-12 text-center text-base text-[var(--neutral-foreground-4)] sm:text-xl">
            この日の活動はまだ登録されていません。<br />
            「新しい活動を追加」ボタンから登録してください。
          </div>
        ) : (
          <div className="space-y-3 sm:space-y-4">
            {activities.map((activity) => (
              <div
                key={activity.id}
                className="flex flex-col gap-3 rounded-lg border-2 border-[var(--neutral-stroke-2)] p-3 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between sm:gap-4 sm:p-5"
              >
                <div className="min-w-0 flex-1">
                  <div className="text-base font-bold sm:text-lg lg:text-xl">
                    {activity.activity_name || activity.common_activity}
                  </div>
                  <div className="mt-1 text-xs text-[var(--neutral-foreground-3)] sm:text-sm lg:text-base">
                    {activity.staff?.full_name} | {activity.participant_count ?? activity.student_records?.length ?? 0}名参加
                  </div>
                </div>
                <div className="flex flex-wrap gap-2 sm:gap-3">
                  <Link
                    href={`/tablet/activity/edit?id=${activity.id}&date=${selectedDateStr}`}
                    className="flex items-center gap-1 rounded-lg bg-[var(--brand-80)] px-3 py-2 text-sm font-bold text-white hover:bg-blue-700 sm:px-4 sm:text-base lg:px-5 lg:py-2.5 lg:text-lg"
                    title="編集"
                  >
                    <MaterialIcon name="edit" size={18} />
                    <span>編集</span>
                  </Link>
                  <Link
                    href={`/tablet/renrakucho?activity_id=${activity.id}`}
                    className="flex items-center gap-1 rounded-lg bg-green-600 px-3 py-2 text-sm font-bold text-white hover:bg-green-700 sm:px-4 sm:text-base lg:px-5 lg:py-2.5 lg:text-lg"
                    title="連絡帳入力"
                  >
                    <MaterialIcon name="edit_note" size={18} />
                    <span>連絡帳</span>
                  </Link>
                  <Link
                    href={`/tablet/integrate?id=${activity.id}&date=${selectedDateStr}`}
                    className="flex items-center gap-1 rounded-lg bg-gray-600 px-3 py-2 text-sm font-bold text-white hover:bg-gray-700 sm:px-4 sm:text-base lg:px-5 lg:py-2.5 lg:text-lg"
                    title="統合"
                  >
                    <MaterialIcon name="merge_type" size={18} />
                    <span>統合</span>
                  </Link>
                  <button
                    onClick={() => handleDelete(activity.id)}
                    className="flex items-center gap-1 rounded-lg bg-red-500 px-3 py-2 text-sm font-bold text-white hover:bg-red-600 sm:px-4 sm:text-base lg:px-5 lg:py-2.5 lg:text-lg"
                    title="削除"
                  >
                    <MaterialIcon name="delete" size={18} />
                    <span>削除</span>
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
