'use client';

import { useState, useMemo, useCallback, useRef } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import api from '@/lib/api';
import { Card, CardBody } from '@/components/ui/Card';
import { Button } from '@/components/ui/Button';
import { Skeleton } from '@/components/ui/Skeleton';
import { Badge } from '@/components/ui/Badge';
import { useToast } from '@/components/ui/Toast';
import {
  ChevronLeft,
  ChevronRight,
  Save,
  Calendar,
} from 'lucide-react';

// ---------------------------------------------------------------------------
// Types
// ---------------------------------------------------------------------------

interface Student {
  id: number;
  student_name: string;
  scheduled_monday: boolean;
  scheduled_tuesday: boolean;
  scheduled_wednesday: boolean;
  scheduled_thursday: boolean;
  scheduled_friday: boolean;
  scheduled_saturday: boolean;
  scheduled_sunday: boolean;
}

interface MonthData {
  student_name: string;
  schedule: Record<string, boolean>;
  additional_dates: string[];
  cancelled_dates: string[];
}

interface Change {
  date: string;
  action: 'add' | 'remove' | 'cancel' | 'restore';
}

const WEEKDAYS = ['日', '月', '火', '水', '木', '金', '土'];
const DAY_KEYS = ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];

// ---------------------------------------------------------------------------
// Main Component
// ---------------------------------------------------------------------------

export default function AdditionalUsagePage() {
  const queryClient = useQueryClient();
  const toast = useToast();

  const [selectedStudentId, setSelectedStudentId] = useState<number | null>(null);
  const [year, setYear] = useState(new Date().getFullYear());
  const [month, setMonth] = useState(new Date().getMonth() + 1);
  const [changes, setChanges] = useState<Record<string, Change>>({});

  // Fetch students
  const { data: students = [] } = useQuery({
    queryKey: ['staff', 'students-for-usage'],
    queryFn: async () => {
      const res = await api.get('/api/staff/students');
      const payload = res.data?.data;
      return Array.isArray(payload) ? payload as Student[] : [];
    },
  });

  // Fetch month data for selected student
  const { data: monthData, isLoading: loadingMonth } = useQuery({
    queryKey: ['staff', 'additional-usage', 'student-month', selectedStudentId, year, month],
    queryFn: async () => {
      const res = await api.get<{ data: MonthData }>('/api/staff/additional-usage/student-month', {
        params: { student_id: selectedStudentId, year, month },
      });
      return res.data.data;
    },
    enabled: !!selectedStudentId,
  });

  // Save batch mutation
  const saveMutation = useMutation({
    mutationFn: () => {
      const changeList = Object.values(changes);
      if (changeList.length === 0) return Promise.resolve({ data: { success: true } });
      return api.post('/api/staff/additional-usage/batch', {
        student_id: selectedStudentId,
        changes: changeList,
      });
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['staff', 'additional-usage'] });
      toast.success('変更を保存しました');
      setChanges({});
    },
    onError: () => toast.error('保存に失敗しました'),
  });

  // Build calendar grid
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

  // Determine day status
  const getDayStatus = useCallback((day: number): 'regular' | 'additional' | 'cancelled' | 'none' => {
    if (!monthData) return 'none';
    const dateStr = `${year}-${String(month).padStart(2, '0')}-${String(day).padStart(2, '0')}`;

    // Check pending changes first
    if (changes[dateStr]) {
      const action = changes[dateStr].action;
      if (action === 'add') return 'additional';
      if (action === 'remove') return 'none';
      if (action === 'cancel') return 'cancelled';
      if (action === 'restore') return 'regular';
    }

    // Check data
    const dayOfWeek = new Date(year, month - 1, day).getDay();
    const dayKey = DAY_KEYS[dayOfWeek];
    const isRegular = monthData.schedule[dayKey] ?? false;
    const isAdditional = monthData.additional_dates.includes(dateStr);
    const isCancelled = monthData.cancelled_dates.includes(dateStr);

    if (isCancelled) return 'cancelled';
    if (isAdditional) return 'additional';
    if (isRegular) return 'regular';
    return 'none';
  }, [monthData, changes, year, month]);

  // Toggle day checkbox
  const toggleDay = useCallback((day: number) => {
    if (!monthData) return;
    const dateStr = `${year}-${String(month).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
    const dayOfWeek = new Date(year, month - 1, day).getDay();
    const dayKey = DAY_KEYS[dayOfWeek];
    const isRegular = monthData.schedule[dayKey] ?? false;
    const isAdditional = monthData.additional_dates.includes(dateStr);
    const isCancelled = monthData.cancelled_dates.includes(dateStr);

    // Remove existing change for this date
    const newChanges = { ...changes };

    if (isRegular && !isCancelled) {
      // Regular day, currently active → cancel
      newChanges[dateStr] = { date: dateStr, action: 'cancel' };
    } else if (isRegular && isCancelled) {
      // Regular day, currently cancelled → restore
      newChanges[dateStr] = { date: dateStr, action: 'restore' };
    } else if (isAdditional) {
      // Additional day → remove
      newChanges[dateStr] = { date: dateStr, action: 'remove' };
    } else {
      // Not scheduled → add
      newChanges[dateStr] = { date: dateStr, action: 'add' };
    }

    // If change would revert to original state, remove it
    if (changes[dateStr]) {
      const prev = changes[dateStr].action;
      const next = newChanges[dateStr].action;
      if ((prev === 'cancel' && next === 'restore') ||
          (prev === 'restore' && next === 'cancel') ||
          (prev === 'add' && next === 'remove') ||
          (prev === 'remove' && next === 'add')) {
        delete newChanges[dateStr];
      }
    }

    setChanges(newChanges);
  }, [monthData, changes, year, month]);

  const goToPrevMonth = () => {
    if (month === 1) { setYear(year - 1); setMonth(12); }
    else setMonth(month - 1);
    setChanges({});
  };
  const goToNextMonth = () => {
    if (month === 12) { setYear(year + 1); setMonth(1); }
    else setMonth(month + 1);
    setChanges({});
  };
  const goToToday = () => {
    const now = new Date();
    setYear(now.getFullYear());
    setMonth(now.getMonth() + 1);
    setChanges({});
  };

  const hasChanges = Object.keys(changes).length > 0;

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold text-[var(--neutral-foreground-1)]">利用日変更</h1>
          <p className="text-sm text-[var(--neutral-foreground-3)]">利用日の追加・キャンセルを管理</p>
        </div>
      </div>

      {/* Student selector */}
      <Card>
        <CardBody>
          <label className="mb-2 block text-sm font-medium text-[var(--neutral-foreground-2)]">生徒選択</label>
          <select
            className="block w-full rounded-lg border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-1)] px-3 py-2 text-sm text-[var(--neutral-foreground-1)]"
            value={selectedStudentId ?? ''}
            onChange={(e) => {
              setSelectedStudentId(e.target.value ? Number(e.target.value) : null);
              setChanges({});
            }}
          >
            <option value="">-- 生徒を選択してください --</option>
            {students.map((s) => (
              <option key={s.id} value={s.id}>{s.student_name}</option>
            ))}
          </select>
        </CardBody>
      </Card>

      {/* Calendar */}
      {selectedStudentId && (
        <Card>
          <CardBody>
            {/* Month navigation */}
            <div className="flex items-center justify-between mb-4">
              <div className="flex items-center gap-2">
                <button onClick={goToPrevMonth}
                  className="rounded-lg p-1.5 hover:bg-[var(--neutral-background-3)] transition-colors">
                  <ChevronLeft className="h-5 w-5 text-[var(--neutral-foreground-3)]" />
                </button>
                <h2 className="text-lg font-bold text-[var(--neutral-foreground-1)] min-w-[120px] text-center">
                  {year}年{month}月
                </h2>
                <button onClick={goToNextMonth}
                  className="rounded-lg p-1.5 hover:bg-[var(--neutral-background-3)] transition-colors">
                  <ChevronRight className="h-5 w-5 text-[var(--neutral-foreground-3)]" />
                </button>
                <Button variant="ghost" size="sm" onClick={goToToday} leftIcon={<Calendar className="h-4 w-4" />}>
                  今月
                </Button>
              </div>

              {/* Save button */}
              {hasChanges && (
                <Button
                  leftIcon={<Save className="h-4 w-4" />}
                  onClick={() => saveMutation.mutate()}
                  isLoading={saveMutation.isPending}
                >
                  変更を保存（{Object.keys(changes).length}件）
                </Button>
              )}
            </div>

            {loadingMonth ? (
              <div className="space-y-2">
                {[...Array(5)].map((_, i) => <Skeleton key={i} className="h-16 w-full rounded" />)}
              </div>
            ) : (
              <>
                {/* Calendar grid */}
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

                          const status = getDayStatus(day);
                          const isChecked = status === 'regular' || status === 'additional';
                          const dateStr = `${year}-${String(month).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
                          const hasChange = !!changes[dateStr];
                          const isSunday = di === 0;
                          const isSaturday = di === 6;

                          return (
                            <td
                              key={di}
                              className={`border border-[var(--neutral-stroke-3)] p-2 align-top cursor-pointer transition-colors ${
                                hasChange ? 'bg-yellow-50 ring-2 ring-inset ring-yellow-400' :
                                status === 'cancelled' ? 'bg-red-50' :
                                status === 'additional' ? 'bg-green-50' :
                                status === 'regular' ? 'bg-blue-50' :
                                ''
                              }`}
                              onClick={() => toggleDay(day)}
                            >
                              <div className="flex flex-col items-center gap-1 min-h-[60px]">
                                <span className={`text-sm font-medium ${
                                  isSunday ? 'text-[var(--status-danger-fg)]' :
                                  isSaturday ? 'text-[var(--brand-80)]' :
                                  'text-[var(--neutral-foreground-2)]'
                                }`}>{day}</span>

                                {/* Status label */}
                                {status === 'regular' && (
                                  <Badge variant="info" className="text-[9px] px-1.5 py-0">通常</Badge>
                                )}
                                {status === 'additional' && (
                                  <Badge variant="success" className="text-[9px] px-1.5 py-0">追加</Badge>
                                )}
                                {status === 'cancelled' && (
                                  <Badge variant="danger" className="text-[9px] px-1.5 py-0">キャンセル</Badge>
                                )}

                                {/* Checkbox */}
                                <input
                                  type="checkbox"
                                  checked={isChecked}
                                  onChange={() => toggleDay(day)}
                                  onClick={(e) => e.stopPropagation()}
                                  className="rounded border-[var(--neutral-stroke-2)] mt-1"
                                />
                              </div>
                            </td>
                          );
                        })}
                      </tr>
                    ))}
                  </tbody>
                </table>

                {/* Legend */}
                <div className="mt-4 flex flex-wrap gap-4 text-xs text-[var(--neutral-foreground-3)]">
                  <span className="flex items-center gap-1">
                    <span className="inline-block h-3 w-3 rounded bg-blue-100 border border-blue-300" /> 通常利用日
                  </span>
                  <span className="flex items-center gap-1">
                    <span className="inline-block h-3 w-3 rounded bg-green-100 border border-green-300" /> 追加利用
                  </span>
                  <span className="flex items-center gap-1">
                    <span className="inline-block h-3 w-3 rounded bg-red-100 border border-red-300" /> キャンセル
                  </span>
                  <span className="flex items-center gap-1">
                    <span className="inline-block h-3 w-3 rounded bg-yellow-50 ring-2 ring-yellow-400" /> 未保存の変更
                  </span>
                </div>
              </>
            )}
          </CardBody>
        </Card>
      )}

      {/* Fixed save button */}
      {hasChanges && (
        <div className="fixed bottom-6 right-6 z-50 print:hidden">
          <Button
            size="lg"
            leftIcon={<Save className="h-5 w-5" />}
            onClick={() => saveMutation.mutate()}
            isLoading={saveMutation.isPending}
            className="shadow-lg"
          >
            変更を保存（{Object.keys(changes).length}件）
          </Button>
        </div>
      )}
    </div>
  );
}
