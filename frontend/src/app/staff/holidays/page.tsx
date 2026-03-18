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
import { ChevronLeft, ChevronRight, Plus, Trash2, Search } from 'lucide-react';

interface Holiday {
  id: number;
  date: string;
  name: string;
  holiday_type: string;
  is_recurring: boolean;
  created_by_name: string | null;
  created_at: string;
}

export default function HolidaysPage() {
  const queryClient = useQueryClient();
  const toast = useToast();
  const [currentMonth, setCurrentMonth] = useState(new Date());
  const [modalOpen, setModalOpen] = useState(false);
  const [form, setForm] = useState({ date: '', name: '', holiday_type: 'regular' as 'regular' | 'special' });

  // 検索フォーム
  const [searchKeyword, setSearchKeyword] = useState('');
  const [searchStartDate, setSearchStartDate] = useState('');
  const [searchEndDate, setSearchEndDate] = useState('');
  const [activeSearch, setActiveSearch] = useState<{ keyword: string; start_date: string; end_date: string } | null>(null);

  const year = format(currentMonth, 'yyyy');

  // カレンダー用クエリ（年単位で取得）
  const { data: holidays = [], isLoading } = useQuery({
    queryKey: ['staff', 'holidays', year],
    queryFn: async () => {
      const res = await api.get<{ data: Holiday[] }>('/api/staff/holidays', { params: { year } });
      return res.data.data;
    },
  });

  // 検索用クエリ（検索が実行されたときのみ）
  const { data: searchResults, isLoading: isSearching } = useQuery({
    queryKey: ['staff', 'holidays', 'search', activeSearch],
    queryFn: async () => {
      const params: Record<string, string> = {};
      if (activeSearch?.keyword) params.keyword = activeSearch.keyword;
      if (activeSearch?.start_date) params.start_date = activeSearch.start_date;
      if (activeSearch?.end_date) params.end_date = activeSearch.end_date;
      const res = await api.get<{ data: Holiday[] }>('/api/staff/holidays', { params });
      return res.data.data;
    },
    enabled: activeSearch !== null,
  });

  const addMutation = useMutation({
    mutationFn: (data: typeof form) =>
      api.post('/api/staff/holidays', {
        date: data.date,
        name: data.name,
        holiday_type: data.holiday_type,
      }),
    onSuccess: (res) => {
      queryClient.invalidateQueries({ queryKey: ['staff', 'holidays'] });
      const msg = res.data?.message || '休日を追加しました';
      toast.success(msg);
      setModalOpen(false);
      setForm({ date: '', name: '', holiday_type: 'regular' });
    },
    onError: (err: any) => {
      const msg = err?.response?.data?.message || '追加に失敗しました';
      toast.error(msg);
    },
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

  const handleSearch = (e: React.FormEvent) => {
    e.preventDefault();
    setActiveSearch({ keyword: searchKeyword, start_date: searchStartDate, end_date: searchEndDate });
  };

  const clearSearch = () => {
    setSearchKeyword('');
    setSearchStartDate('');
    setSearchEndDate('');
    setActiveSearch(null);
  };

  // 表示するリスト（検索結果 or 月別）
  const displayList = activeSearch !== null ? searchResults ?? [] : monthHolidays;
  const isSearchMode = activeSearch !== null;

  const dayNames = ['日', '月', '火', '水', '木', '金', '土'];
  const formatDateWithDay = (dateStr: string) => {
    const d = new Date(dateStr);
    const dayOfWeek = dayNames[d.getDay()];
    return `${format(d, 'yyyy年M月d日')}（${dayOfWeek}）`;
  };

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold text-[var(--neutral-foreground-1)]">休日管理</h1>
          <p className="text-sm text-[var(--neutral-foreground-3)]">休日・祝日の登録と管理</p>
        </div>
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
            {dayNames.map((d, i) => (
              <div key={d} className={`bg-[var(--neutral-background-2)] py-2 text-center text-xs font-semibold ${i === 0 ? 'text-[var(--status-danger-fg)]' : i === 6 ? 'text-[var(--brand-80)]' : 'text-[var(--neutral-foreground-3)]'}`}>{d}</div>
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

      {/* Search Form */}
      <Card>
        <CardHeader>
          <CardTitle>休日検索</CardTitle>
        </CardHeader>
        <form onSubmit={handleSearch} className="space-y-4">
          <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <Input
              label="期間（開始日）"
              type="date"
              value={searchStartDate}
              onChange={(e) => setSearchStartDate(e.target.value)}
            />
            <Input
              label="期間（終了日）"
              type="date"
              value={searchEndDate}
              onChange={(e) => setSearchEndDate(e.target.value)}
            />
          </div>
          <Input
            label="キーワード"
            value={searchKeyword}
            onChange={(e) => setSearchKeyword(e.target.value)}
            placeholder="休日名で検索"
          />
          <div className="flex justify-end gap-2">
            <Button variant="secondary" type="button" onClick={clearSearch}>クリア</Button>
            <Button type="submit" leftIcon={<Search className="h-4 w-4" />} isLoading={isSearching}>検索</Button>
          </div>
        </form>
      </Card>

      {/* Holiday List */}
      <Card>
        <CardHeader>
          <CardTitle>
            {isSearchMode
              ? `検索結果 (${displayList.length}件)`
              : `この月の休日一覧 (${monthHolidays.length}件)`
            }
          </CardTitle>
        </CardHeader>

        {isSearchMode && (
          <div className="mb-4 rounded-lg bg-[var(--status-info-bg)] p-3 text-sm text-[var(--status-info-fg)]">
            検索結果: {displayList.length}件の休日が見つかりました
          </div>
        )}

        {displayList.length === 0 ? (
          <p className="text-sm text-[var(--neutral-foreground-3)]">
            {isSearchMode ? '検索条件に一致する休日が見つかりませんでした' : 'この月の休日はありません'}
          </p>
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full text-sm">
              <thead>
                <tr className="border-b border-[var(--neutral-stroke-2)]">
                  <th className="text-left py-2 px-3 font-semibold text-[var(--neutral-foreground-3)]">日付</th>
                  <th className="text-left py-2 px-3 font-semibold text-[var(--neutral-foreground-3)]">休日名</th>
                  <th className="text-left py-2 px-3 font-semibold text-[var(--neutral-foreground-3)]">タイプ</th>
                  <th className="text-left py-2 px-3 font-semibold text-[var(--neutral-foreground-3)]">登録者</th>
                  <th className="text-left py-2 px-3 font-semibold text-[var(--neutral-foreground-3)]">登録日</th>
                  <th className="text-left py-2 px-3 font-semibold text-[var(--neutral-foreground-3)]">操作</th>
                </tr>
              </thead>
              <tbody>
                {displayList.map((holiday) => (
                  <tr key={holiday.id} className="border-b border-[var(--neutral-stroke-2)] hover:bg-[var(--neutral-background-2)]">
                    <td className="py-2 px-3 text-[var(--neutral-foreground-1)]">{formatDateWithDay(holiday.date)}</td>
                    <td className="py-2 px-3 text-[var(--neutral-foreground-1)]">{holiday.name}</td>
                    <td className="py-2 px-3">
                      <span className={`inline-block rounded-md px-2.5 py-1 text-xs font-bold ${
                        holiday.holiday_type === 'regular'
                          ? 'bg-blue-500/15 text-blue-600'
                          : 'bg-orange-500/15 text-orange-600'
                      }`}>
                        {holiday.holiday_type === 'regular' ? '定期休日' : '特別休日'}
                      </span>
                    </td>
                    <td className="py-2 px-3 text-[var(--neutral-foreground-2)]">{holiday.created_by_name ?? '-'}</td>
                    <td className="py-2 px-3 text-[var(--neutral-foreground-2)]">
                      {holiday.created_at ? format(new Date(holiday.created_at), 'yyyy/MM/dd') : '-'}
                    </td>
                    <td className="py-2 px-3">
                      <Button
                        variant="danger"
                        size="sm"
                        onClick={() => { if (confirm('この休日を削除しますか？')) deleteMutation.mutate(holiday.id); }}
                      >
                        削除
                      </Button>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </Card>

      {/* Add Modal */}
      <Modal isOpen={modalOpen} onClose={() => setModalOpen(false)} title="新規休日登録">
        <form onSubmit={(e) => { e.preventDefault(); addMutation.mutate(form); }} className="space-y-4">
          <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <Input label="日付 *" type="date" value={form.date} onChange={(e) => setForm({ ...form, date: e.target.value })} required />
            <div>
              <label className="block text-sm font-medium text-[var(--neutral-foreground-2)] mb-1.5">休日タイプ *</label>
              <select
                value={form.holiday_type}
                onChange={(e) => setForm({ ...form, holiday_type: e.target.value as 'regular' | 'special' })}
                className="w-full rounded-lg border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-1)] px-3 py-2 text-sm text-[var(--neutral-foreground-1)]"
                required
              >
                <option value="regular">定期休日（毎週の休み）</option>
                <option value="special">特別休日（イベント・祝日など）</option>
              </select>
            </div>
          </div>
          <div>
            <Input label="休日名 *" value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} required placeholder="例: 夏季休業、年末年始、祝日名など" />
            <p className="mt-1 text-xs text-[var(--neutral-foreground-3)]">
              {form.holiday_type === 'regular'
                ? '定期休日を選択すると、年度内（4月〜3月）の同じ曜日すべてに一括登録されます'
                : 'カレンダーに表示される名前です'}
            </p>
          </div>
          <div className="flex justify-end gap-2 pt-2">
            <Button variant="secondary" type="button" onClick={() => setModalOpen(false)}>キャンセル</Button>
            <Button type="submit" isLoading={addMutation.isPending}>登録する</Button>
          </div>
        </form>
      </Modal>
    </div>
  );
}
