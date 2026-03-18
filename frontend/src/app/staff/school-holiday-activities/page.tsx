'use client';

import { useState, useMemo, useCallback, useEffect } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import api from '@/lib/api';
import { Card, CardBody } from '@/components/ui/Card';
import { Button } from '@/components/ui/Button';
import { Badge } from '@/components/ui/Badge';
import { Skeleton } from '@/components/ui/Skeleton';
import { useToast } from '@/components/ui/Toast';
import {
  ChevronLeft,
  ChevronRight,
  Save,
  Calendar,
  Info,
} from 'lucide-react';

// ---------------------------------------------------------------------------
// Types & Constants
// ---------------------------------------------------------------------------

interface Holiday {
  id: number;
  holiday_date: string;
  holiday_name: string;
}

interface SchoolHolidayActivity {
  id: number;
  activity_date: string;
}

const WEEKDAYS = ['日', '月', '火', '水', '木', '金', '土'];

// ---------------------------------------------------------------------------
// Main Component
// ---------------------------------------------------------------------------

export default function SchoolHolidayActivitiesPage() {
  const queryClient = useQueryClient();
  const toast = useToast();

  const [year, setYear] = useState(new Date().getFullYear());
  const [month, setMonth] = useState(new Date().getMonth() + 1);
  const [checkedDates, setCheckedDates] = useState<Set<string>>(new Set());
  const [initialized, setInitialized] = useState(false);

  // Fetch holidays (facility closed days)
  const { data: holidays = [] } = useQuery({
    queryKey: ['staff', 'holidays', year, month],
    queryFn: async () => {
      const res = await api.get('/api/staff/holidays', { params: { year, month } });
      const payload = res.data?.data;
      return Array.isArray(payload) ? payload as Holiday[] : [];
    },
  });

  // Fetch existing school holiday activities for this month
  const { data: activities = [], isLoading } = useQuery({
    queryKey: ['staff', 'school-holiday-activities', year, month],
    queryFn: async () => {
      const res = await api.get('/api/staff/school-holiday-activities', { params: { year, month } });
      const payload = res.data?.data;
      return Array.isArray(payload) ? payload as SchoolHolidayActivity[] : [];
    },
  });

  // Initialize checked dates from fetched data
  useEffect(() => {
    const dates = new Set(activities.map((a) => {
      const d = a.activity_date;
      return typeof d === 'string' ? d.split('T')[0] : d;
    }));
    setCheckedDates(dates);
    setInitialized(true);
  }, [activities]);

  // Holiday date set for quick lookup
  const holidayDateSet = useMemo(() => {
    const set = new Set<string>();
    holidays.forEach((h) => {
      const d = h.holiday_date?.split('T')[0];
      if (d) set.add(d);
    });
    return set;
  }, [holidays]);

  // Save mutation
  const saveMutation = useMutation({
    mutationFn: async () => {
      await api.post('/api/staff/school-holiday-activities/batch', {
        year,
        month,
        activity_dates: Array.from(checkedDates),
      });
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['staff', 'school-holiday-activities'] });
      toast.success('この月の設定を保存しました');
    },
    onError: () => toast.error('保存に失敗しました'),
  });

  // Calendar grid
  const calendarGrid = useMemo(() => {
    const firstDay = new Date(year, month - 1, 1).getDay();
    const daysInMonth = new Date(year, month, 0).getDate();
    const rows: (number | null)[][] = [];
    let week: (number | null)[] = Array(firstDay).fill(null);
    for (let d = 1; d <= daysInMonth; d++) {
      week.push(d);
      if (week.length === 7) { rows.push(week); week = []; }
    }
    if (week.length > 0) {
      while (week.length < 7) week.push(null);
      rows.push(week);
    }
    return rows;
  }, [year, month]);

  const toggleDate = useCallback((dateStr: string) => {
    if (holidayDateSet.has(dateStr)) return;
    setCheckedDates((prev) => {
      const next = new Set(prev);
      if (next.has(dateStr)) next.delete(dateStr);
      else next.add(dateStr);
      return next;
    });
  }, [holidayDateSet]);

  const goToPrevMonth = () => {
    if (month === 1) { setYear(year - 1); setMonth(12); }
    else setMonth(month - 1);
    setInitialized(false);
  };
  const goToNextMonth = () => {
    if (month === 12) { setYear(year + 1); setMonth(1); }
    else setMonth(month + 1);
    setInitialized(false);
  };
  const goToToday = () => {
    setYear(new Date().getFullYear());
    setMonth(new Date().getMonth() + 1);
    setInitialized(false);
  };

  return (
    <div className="space-y-4">
      <div>
        <h1 className="text-2xl font-bold text-[var(--neutral-foreground-1)]">学校休業日活動設定</h1>
        <p className="text-sm text-[var(--neutral-foreground-3)]">学校が休みの日（夏休み・春休み等）に活動する日を設定</p>
      </div>

      {/* Info box */}
      <Card>
        <CardBody>
          <div className="flex items-start gap-3">
            <Info className="h-5 w-5 shrink-0 text-[var(--brand-80)] mt-0.5" />
            <div className="text-sm text-[var(--neutral-foreground-2)] space-y-1">
              <p className="font-semibold">学校休業日活動とは？</p>
              <p>学校が休みの日（夏休み、春休み、冬休み等）に施設で活動する日です。</p>
              <p>チェックを入れた日は保護者カレンダーに「学校休業日活動」と表示されます。</p>
              <p>チェックがない日は「平日活動」として表示されます。</p>
              <p className="text-[var(--neutral-foreground-4)]">※ 休日として登録されている日は選択できません。</p>
            </div>
          </div>
          <div className="mt-3 flex flex-wrap gap-4 text-xs">
            <span className="flex items-center gap-1.5">
              <span className="inline-block h-3 w-3 rounded bg-blue-100 border border-blue-400" /> 学校休業日活動
            </span>
            <span className="flex items-center gap-1.5">
              <span className="inline-block h-3 w-3 rounded bg-red-100 border border-red-400" /> 休日（選択不可）
            </span>
            <span className="flex items-center gap-1.5">
              <span className="inline-block h-3 w-3 rounded bg-white border border-gray-300" /> 平日活動
            </span>
          </div>
        </CardBody>
      </Card>

      {/* Calendar */}
      <Card>
        <CardBody>
          {/* Month navigation */}
          <div className="flex items-center justify-between mb-4">
            <div className="flex items-center gap-2">
              <button onClick={goToPrevMonth} className="rounded-lg p-1.5 hover:bg-[var(--neutral-background-3)]">
                <ChevronLeft className="h-5 w-5 text-[var(--neutral-foreground-3)]" />
              </button>
              <h2 className="text-lg font-bold text-[var(--neutral-foreground-1)] min-w-[120px] text-center">
                {year}年{month}月
              </h2>
              <button onClick={goToNextMonth} className="rounded-lg p-1.5 hover:bg-[var(--neutral-background-3)]">
                <ChevronRight className="h-5 w-5 text-[var(--neutral-foreground-3)]" />
              </button>
              <Button variant="ghost" size="sm" onClick={goToToday} leftIcon={<Calendar className="h-4 w-4" />}>
                今月
              </Button>
            </div>
          </div>

          {isLoading ? (
            <div className="space-y-2">{[...Array(5)].map((_, i) => <Skeleton key={i} className="h-20 w-full rounded" />)}</div>
          ) : (
            <table className="w-full table-fixed border-collapse">
              <thead>
                <tr>
                  {WEEKDAYS.map((wd, i) => (
                    <th key={wd} className={`py-2 text-center text-xs font-medium ${
                      i === 0 ? 'text-[var(--status-danger-fg)]' : i === 6 ? 'text-[var(--brand-80)]' : 'text-[var(--neutral-foreground-3)]'
                    }`}>{wd}</th>
                  ))}
                </tr>
              </thead>
              <tbody>
                {calendarGrid.map((week, wi) => (
                  <tr key={wi}>
                    {week.map((day, di) => {
                      if (day === null) return <td key={di} className="border border-[var(--neutral-stroke-3)] p-1" />;

                      const dateStr = `${year}-${String(month).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
                      const isHoliday = holidayDateSet.has(dateStr);
                      const isChecked = checkedDates.has(dateStr);
                      const holidayInfo = holidays.find((h) => h.holiday_date?.split('T')[0] === dateStr);
                      const isSunday = di === 0;
                      const isSaturday = di === 6;

                      return (
                        <td
                          key={di}
                          className={`border border-[var(--neutral-stroke-3)] p-2 align-top transition-colors min-h-[80px] ${
                            isHoliday
                              ? 'bg-red-50 cursor-not-allowed'
                              : isChecked
                                ? 'bg-blue-50 cursor-pointer hover:bg-blue-100'
                                : 'bg-white cursor-pointer hover:bg-gray-50'
                          }`}
                          onClick={() => !isHoliday && toggleDate(dateStr)}
                        >
                          <div className="flex flex-col items-center gap-1 min-h-[70px]">
                            <span className={`text-sm font-medium ${
                              isSunday ? 'text-[var(--status-danger-fg)]' :
                              isSaturday ? 'text-[var(--brand-80)]' :
                              'text-[var(--neutral-foreground-2)]'
                            }`}>{day}</span>

                            {isHoliday ? (
                              <>
                                <Badge variant="danger" className="text-[8px] px-1 py-0">休日</Badge>
                                <span className="text-[8px] text-[var(--neutral-foreground-4)] text-center">
                                  {holidayInfo?.holiday_name || '定期休日'}
                                </span>
                              </>
                            ) : (
                              <>
                                <div className="flex items-center gap-1">
                                  <input
                                    type="checkbox"
                                    checked={isChecked}
                                    readOnly
                                    className="rounded border-[var(--neutral-stroke-2)] pointer-events-none"
                                  />
                                  <span className="text-[10px] text-[var(--neutral-foreground-3)] hidden sm:inline">休業日活動</span>
                                </div>
                                <span className={`text-[10px] font-medium px-1.5 py-0.5 rounded ${
                                  isChecked ? 'bg-blue-100 text-blue-800' : 'bg-green-100 text-green-800'
                                }`}>
                                  {isChecked ? '休業日活動' : '平日活動'}
                                </span>
                              </>
                            )}
                          </div>
                        </td>
                      );
                    })}
                  </tr>
                ))}
              </tbody>
            </table>
          )}

          {/* Save button */}
          <div className="mt-4 flex justify-end">
            <Button
              leftIcon={<Save className="h-4 w-4" />}
              onClick={() => saveMutation.mutate()}
              isLoading={saveMutation.isPending}
            >
              この月の設定を保存
            </Button>
          </div>
        </CardBody>
      </Card>
    </div>
  );
}
