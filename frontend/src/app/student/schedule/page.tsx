'use client';

import { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import api from '@/lib/api';
import { Card, CardHeader, CardTitle } from '@/components/ui/Card';
import { Button } from '@/components/ui/Button';
import { Badge } from '@/components/ui/Badge';
import { SkeletonList } from '@/components/ui/Skeleton';
import { format, startOfWeek, endOfWeek, addWeeks, subWeeks, eachDayOfInterval, isSameDay, getDay } from 'date-fns';
import { ja } from 'date-fns/locale';
import { ChevronLeft, ChevronRight, Calendar, Clock, MapPin } from 'lucide-react';

interface ScheduleDay {
  date: string;
  is_scheduled: boolean;
  is_holiday: boolean;
  holiday_name: string | null;
  activities: ScheduleActivity[];
  arrival_time: string | null;
  departure_time: string | null;
  is_additional: boolean;
}

interface ScheduleActivity {
  id: number;
  name: string;
  start_time: string;
  end_time: string;
  location: string | null;
  description: string | null;
  type: 'routine' | 'event' | 'special';
}

export default function StudentSchedulePage() {
  const [currentWeek, setCurrentWeek] = useState(new Date());

  const weekStart = format(startOfWeek(currentWeek, { weekStartsOn: 1 }), 'yyyy-MM-dd');
  const weekEnd = format(endOfWeek(currentWeek, { weekStartsOn: 1 }), 'yyyy-MM-dd');

  const { data: schedule = [], isLoading } = useQuery({
    queryKey: ['student', 'schedule', weekStart],
    queryFn: async () => {
      const res = await api.get<{ data: ScheduleDay[] }>('/api/student/schedule', {
        params: { week_start: weekStart },
      });
      return res.data.data;
    },
  });

  const weekDays = eachDayOfInterval({
    start: startOfWeek(currentWeek, { weekStartsOn: 1 }),
    end: endOfWeek(currentWeek, { weekStartsOn: 1 }),
  });

  const getScheduleForDay = (date: Date): ScheduleDay | undefined => {
    const dateStr = format(date, 'yyyy-MM-dd');
    return schedule.find((s) => s.date === dateStr);
  };

  const typeColors: Record<string, string> = {
    routine: 'bg-blue-100 text-blue-700 border-blue-200',
    event: 'bg-green-100 text-green-700 border-green-200',
    special: 'bg-purple-100 text-purple-700 border-purple-200',
  };

  const typeLabels: Record<string, string> = {
    routine: '日課',
    event: 'イベント',
    special: '特別活動',
  };

  const scheduledDays = schedule.filter((s) => s.is_scheduled).length;

  return (
    <div className="space-y-6">
      <h1 className="text-2xl font-bold text-gray-900">スケジュール</h1>

      {/* Week navigation */}
      <div className="flex items-center justify-center gap-4">
        <Button variant="ghost" size="sm" onClick={() => setCurrentWeek(subWeeks(currentWeek, 1))}>
          <ChevronLeft className="h-4 w-4" />
        </Button>
        <p className="text-lg font-semibold">
          {format(startOfWeek(currentWeek, { weekStartsOn: 1 }), 'M月d日', { locale: ja })}
          {' - '}
          {format(endOfWeek(currentWeek, { weekStartsOn: 1 }), 'M月d日', { locale: ja })}
        </p>
        <Button variant="ghost" size="sm" onClick={() => setCurrentWeek(addWeeks(currentWeek, 1))}>
          <ChevronRight className="h-4 w-4" />
        </Button>
        <Button variant="outline" size="sm" onClick={() => setCurrentWeek(new Date())}>今週</Button>
      </div>

      {/* Summary */}
      <div className="flex justify-center gap-4">
        <Badge variant="primary" className="px-4 py-1.5 text-sm">
          今週の通所日: {scheduledDays}日
        </Badge>
      </div>

      {/* Schedule */}
      {isLoading ? (
        <SkeletonList items={5} />
      ) : (
        <div className="space-y-3">
          {weekDays.map((day) => {
            const daySchedule = getScheduleForDay(day);
            const isToday = isSameDay(day, new Date());
            const dayOfWeek = getDay(day);
            const isWeekend = dayOfWeek === 0 || dayOfWeek === 6;

            return (
              <Card
                key={format(day, 'yyyy-MM-dd')}
                className={`${isToday ? 'ring-2 ring-blue-500' : ''} ${!daySchedule?.is_scheduled && !daySchedule?.is_holiday ? 'opacity-50' : ''}`}
              >
                <div className="flex items-center gap-3 mb-3">
                  <div className={`flex h-12 w-12 items-center justify-center rounded-full ${
                    isToday ? 'bg-blue-600 text-white' : daySchedule?.is_holiday ? 'bg-red-100' : daySchedule?.is_scheduled ? 'bg-blue-100' : 'bg-gray-100'
                  }`}>
                    <div className="text-center">
                      <p className={`text-lg font-bold ${isToday ? 'text-white' : daySchedule?.is_holiday ? 'text-red-600' : ''}`}>
                        {format(day, 'd')}
                      </p>
                    </div>
                  </div>
                  <div>
                    <p className={`font-medium ${isToday ? 'text-blue-600' : 'text-gray-900'}`}>
                      {format(day, 'E', { locale: ja })}曜日
                      {isToday && <span className="ml-2 text-xs bg-blue-100 text-blue-600 rounded-full px-2 py-0.5">今日</span>}
                    </p>
                    <div className="flex gap-1">
                      {daySchedule?.is_holiday && <Badge variant="danger">{daySchedule.holiday_name || 'お休み'}</Badge>}
                      {daySchedule?.is_additional && <Badge variant="info">追加利用</Badge>}
                      {daySchedule?.is_scheduled && !daySchedule?.is_holiday && (
                        <span className="text-xs text-gray-500">
                          {daySchedule.arrival_time && `${daySchedule.arrival_time}`}
                          {daySchedule.departure_time && ` - ${daySchedule.departure_time}`}
                        </span>
                      )}
                      {!daySchedule?.is_scheduled && !daySchedule?.is_holiday && (
                        <Badge variant="default">通所なし</Badge>
                      )}
                    </div>
                  </div>
                </div>

                {daySchedule?.is_scheduled && daySchedule.activities.length > 0 && (
                  <div className="space-y-2">
                    {daySchedule.activities.map((activity) => (
                      <div
                        key={activity.id}
                        className={`flex items-start gap-3 rounded-lg border p-3 ${typeColors[activity.type] || 'bg-gray-50 border-gray-200'}`}
                      >
                        <div className="shrink-0 text-center">
                          <p className="text-xs font-medium">{activity.start_time}</p>
                          <p className="text-[10px] text-gray-400">|</p>
                          <p className="text-xs font-medium">{activity.end_time}</p>
                        </div>
                        <div className="flex-1">
                          <div className="flex items-center gap-2">
                            <p className="text-sm font-medium">{activity.name}</p>
                            <span className="text-[10px] opacity-75">{typeLabels[activity.type]}</span>
                          </div>
                          {activity.description && <p className="text-xs opacity-75">{activity.description}</p>}
                          {activity.location && (
                            <p className="flex items-center gap-1 text-xs opacity-75 mt-0.5">
                              <MapPin className="h-3 w-3" />{activity.location}
                            </p>
                          )}
                        </div>
                      </div>
                    ))}
                  </div>
                )}
              </Card>
            );
          })}
        </div>
      )}
    </div>
  );
}
