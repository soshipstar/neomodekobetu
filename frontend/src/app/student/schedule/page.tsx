'use client';

import { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import api from '@/lib/api';
import { nl } from '@/lib/utils';
import { Card } from '@/components/ui/Card';
import { Button } from '@/components/ui/Button';
import { Badge } from '@/components/ui/Badge';
import { SkeletonList } from '@/components/ui/Skeleton';
import { ChevronLeft, ChevronRight } from 'lucide-react';

interface ScheduleEvent {
  id: number;
  event_date: string;
  event_name: string;
  event_description: string | null;
  event_color: string | null;
}

interface ScheduleHoliday {
  id: number;
  holiday_date: string;
  holiday_name: string;
}

interface ScheduleResponse {
  events: ScheduleEvent[];
  holidays: ScheduleHoliday[];
  scheduled_days: string[];
  year: number;
  month: number;
}

const dayLabels = ['日', '月', '火', '水', '木', '金', '土'];

export default function StudentSchedulePage() {
  const now = new Date();
  const [year, setYear] = useState(now.getFullYear());
  const [month, setMonth] = useState(now.getMonth() + 1);

  const { data, isLoading } = useQuery({
    queryKey: ['student', 'schedule', year, month],
    queryFn: async () => {
      const res = await api.get<{ data: ScheduleResponse }>('/api/student/schedule', {
        params: { year, month },
      });
      return res.data.data;
    },
  });

  const goToPrevMonth = () => {
    if (month === 1) { setYear(year - 1); setMonth(12); }
    else { setMonth(month - 1); }
  };

  const goToNextMonth = () => {
    if (month === 12) { setYear(year + 1); setMonth(1); }
    else { setMonth(month + 1); }
  };

  const goToThisMonth = () => {
    setYear(now.getFullYear());
    setMonth(now.getMonth() + 1);
  };

  // Build calendar grid
  const firstDay = new Date(year, month - 1, 1);
  const daysInMonth = new Date(year, month, 0).getDate();
  const startDayOfWeek = firstDay.getDay(); // 0=Sun
  const today = now.getDate();
  const isCurrentMonth = year === now.getFullYear() && month === now.getMonth() + 1;

  // Index events and holidays by day
  const eventsByDay: Record<number, ScheduleEvent[]> = {};
  const holidaysByDay: Record<number, ScheduleHoliday[]> = {};

  data?.events?.forEach((e) => {
    const day = new Date(e.event_date).getDate();
    if (!eventsByDay[day]) eventsByDay[day] = [];
    eventsByDay[day].push(e);
  });

  data?.holidays?.forEach((h) => {
    const day = new Date(h.holiday_date).getDate();
    if (!holidaysByDay[day]) holidaysByDay[day] = [];
    holidaysByDay[day].push(h);
  });

  // scheduled_days is array of day names like ['monday', 'tuesday', ...]
  const scheduledDayNums = new Set<number>();
  const dayNameToNum: Record<string, number> = {
    sunday: 0, monday: 1, tuesday: 2, wednesday: 3,
    thursday: 4, friday: 5, saturday: 6,
  };
  data?.scheduled_days?.forEach((name) => {
    if (dayNameToNum[name] !== undefined) scheduledDayNums.add(dayNameToNum[name]);
  });

  // Check if a day is a scheduled activity day
  const isScheduledDay = (day: number): boolean => {
    const date = new Date(year, month - 1, day);
    const dow = date.getDay();
    // Not scheduled if it's a holiday
    if (holidaysByDay[day]) return false;
    return scheduledDayNums.has(dow);
  };

  // Build calendar cells
  const calendarCells: (number | null)[] = [];
  for (let i = 0; i < startDayOfWeek; i++) calendarCells.push(null);
  for (let d = 1; d <= daysInMonth; d++) calendarCells.push(d);
  while (calendarCells.length % 7 !== 0) calendarCells.push(null);

  const weeks: (number | null)[][] = [];
  for (let i = 0; i < calendarCells.length; i += 7) {
    weeks.push(calendarCells.slice(i, i + 7));
  }

  // Selected day detail
  const [selectedDay, setSelectedDay] = useState<number | null>(null);

  const selectedEvents = selectedDay ? eventsByDay[selectedDay] || [] : [];
  const selectedHolidays = selectedDay ? holidaysByDay[selectedDay] || [] : [];
  const selectedIsScheduled = selectedDay ? isScheduledDay(selectedDay) : false;

  return (
    <div className="space-y-6">
      <h1 className="text-2xl font-bold text-[var(--neutral-foreground-1)]">スケジュール</h1>

      {/* Month navigation */}
      <div className="flex items-center justify-center gap-4">
        <Button variant="ghost" size="sm" onClick={goToPrevMonth}>
          <ChevronLeft className="h-4 w-4" />
        </Button>
        <p className="text-lg font-semibold text-[var(--neutral-foreground-1)]">
          {year}年{month}月
        </p>
        <Button variant="ghost" size="sm" onClick={goToNextMonth}>
          <ChevronRight className="h-4 w-4" />
        </Button>
        <Button variant="outline" size="sm" onClick={goToThisMonth}>今月</Button>
      </div>

      {isLoading ? (
        <SkeletonList items={5} />
      ) : (
        <>
          {/* Calendar grid */}
          <Card>
            <div className="overflow-x-auto">
              <table className="w-full border-collapse text-sm">
                <thead>
                  <tr>
                    {dayLabels.map((label, i) => (
                      <th
                        key={label}
                        className={`border border-[var(--neutral-stroke-2)] p-2 text-center text-xs font-medium ${
                          i === 0 ? 'text-red-500' : i === 6 ? 'text-blue-500' : 'text-[var(--neutral-foreground-2)]'
                        }`}
                      >
                        {label}
                      </th>
                    ))}
                  </tr>
                </thead>
                <tbody>
                  {weeks.map((week, wi) => (
                    <tr key={wi}>
                      {week.map((day, di) => {
                        if (day === null) {
                          return <td key={di} className="border border-[var(--neutral-stroke-2)] p-1 h-16" />;
                        }

                        const isToday = isCurrentMonth && day === today;
                        const hasHoliday = !!holidaysByDay[day];
                        const hasEvent = !!eventsByDay[day];
                        const scheduled = isScheduledDay(day);
                        const isSun = di === 0;
                        const isSat = di === 6;
                        const isSelected = selectedDay === day;

                        return (
                          <td
                            key={di}
                            className={`border border-[var(--neutral-stroke-2)] p-1 h-16 align-top cursor-pointer transition-colors ${
                              isSelected ? 'bg-[var(--brand-80)]/10' : ''
                            } ${isToday ? 'bg-blue-50' : ''} ${hasHoliday ? 'bg-red-50/50' : ''}`}
                            onClick={() => setSelectedDay(day === selectedDay ? null : day)}
                          >
                            <div className="flex flex-col items-center gap-0.5">
                              <span className={`text-xs font-medium leading-none ${
                                isToday ? 'bg-[var(--brand-80)] text-white rounded-full w-6 h-6 flex items-center justify-center' : ''
                              } ${hasHoliday || isSun ? 'text-red-500' : isSat ? 'text-blue-500' : 'text-[var(--neutral-foreground-1)]'}`}>
                                {day}
                              </span>
                              <div className="flex flex-wrap justify-center gap-0.5 mt-0.5">
                                {scheduled && !hasHoliday && (
                                  <span className="w-1.5 h-1.5 rounded-full bg-[var(--brand-80)]" title="通所日" />
                                )}
                                {hasEvent && (
                                  <span className="w-1.5 h-1.5 rounded-full bg-green-500" title="イベント" />
                                )}
                                {hasHoliday && (
                                  <span className="w-1.5 h-1.5 rounded-full bg-red-500" title="休日" />
                                )}
                              </div>
                            </div>
                          </td>
                        );
                      })}
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>

            {/* Legend */}
            <div className="flex flex-wrap gap-4 px-4 py-3 border-t border-[var(--neutral-stroke-2)] text-xs text-[var(--neutral-foreground-3)]">
              <span className="flex items-center gap-1">
                <span className="w-2 h-2 rounded-full bg-[var(--brand-80)]" /> 通所日
              </span>
              <span className="flex items-center gap-1">
                <span className="w-2 h-2 rounded-full bg-green-500" /> イベント
              </span>
              <span className="flex items-center gap-1">
                <span className="w-2 h-2 rounded-full bg-red-500" /> 休日
              </span>
            </div>
          </Card>

          {/* Selected day detail */}
          {selectedDay && (
            <Card>
              <div className="p-4">
                <h3 className="text-sm font-semibold text-[var(--neutral-foreground-1)] mb-3">
                  {month}月{selectedDay}日の詳細
                </h3>

                {selectedHolidays.length > 0 && (
                  <div className="mb-2">
                    {selectedHolidays.map((h) => (
                      <div key={h.id} className="flex items-center gap-2 text-sm text-red-600">
                        <Badge variant="danger">{h.holiday_name}</Badge>
                      </div>
                    ))}
                  </div>
                )}

                {selectedIsScheduled && (
                  <p className="text-sm text-[var(--neutral-foreground-2)] mb-2">
                    <Badge variant="primary">通所日</Badge>
                  </p>
                )}

                {!selectedIsScheduled && selectedHolidays.length === 0 && (
                  <p className="text-sm text-[var(--neutral-foreground-3)]">通所なし</p>
                )}

                {selectedEvents.length > 0 && (
                  <div className="mt-3 space-y-2">
                    {selectedEvents.map((e) => (
                      <div
                        key={e.id}
                        className="rounded-lg border border-green-200 bg-green-50 p-3"
                      >
                        <p className="text-sm font-medium text-green-700">{e.event_name}</p>
                        {e.event_description && (
                          <p className="mt-1 text-xs text-green-600 whitespace-pre-wrap">{nl(e.event_description)}</p>
                        )}
                      </div>
                    ))}
                  </div>
                )}
              </div>
            </Card>
          )}
        </>
      )}
    </div>
  );
}
