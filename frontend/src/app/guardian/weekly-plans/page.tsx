'use client';

import { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import api from '@/lib/api';
import { Card, CardHeader, CardTitle } from '@/components/ui/Card';
import { Button } from '@/components/ui/Button';
import { Badge } from '@/components/ui/Badge';
import { SkeletonList } from '@/components/ui/Skeleton';
import { format, startOfWeek, endOfWeek, addWeeks, subWeeks } from 'date-fns';
import { ja } from 'date-fns/locale';
import { ChevronLeft, ChevronRight, Calendar, Clock } from 'lucide-react';

interface WeeklyPlan {
  id: number;
  week_start: string;
  week_end: string;
  student_name: string;
  status: 'draft' | 'published';
  days: DayPlan[];
  staff_comment: string | null;
}

interface DayPlan {
  date: string;
  day_label: string;
  activities: Activity[];
  is_scheduled: boolean;
}

interface Activity {
  time: string;
  name: string;
  description: string | null;
}

interface StudentOption {
  id: number;
  student_name: string;
}

export default function GuardianWeeklyPlansPage() {
  const [currentWeek, setCurrentWeek] = useState(new Date());
  const [selectedStudent, setSelectedStudent] = useState('');

  const weekStart = format(startOfWeek(currentWeek, { weekStartsOn: 1 }), 'yyyy-MM-dd');

  const { data: students = [] } = useQuery({
    queryKey: ['guardian', 'students'],
    queryFn: async () => {
      const res = await api.get<{ data: StudentOption[] }>('/api/guardian/students');
      return res.data.data;
    },
  });

  const studentId = selectedStudent || students[0]?.id?.toString() || '';

  const { data: plan, isLoading } = useQuery({
    queryKey: ['guardian', 'weekly-plans', studentId, weekStart],
    queryFn: async () => {
      const res = await api.get<{ data: WeeklyPlan }>(`/api/guardian/students/${studentId}/weekly-plans`, {
        params: { week_start: weekStart },
      });
      return res.data.data;
    },
    enabled: !!studentId,
  });

  return (
    <div className="space-y-6">
      <h1 className="text-2xl font-bold text-gray-900">週間計画</h1>

      {/* Student selector */}
      {students.length > 1 && (
        <select
          value={selectedStudent || students[0]?.id}
          onChange={(e) => setSelectedStudent(e.target.value)}
          className="rounded-lg border border-gray-300 px-3 py-2 text-sm"
        >
          {students.map((s) => (
            <option key={s.id} value={s.id}>{s.student_name}</option>
          ))}
        </select>
      )}

      {/* Week navigation */}
      <div className="flex items-center justify-center gap-4">
        <Button variant="ghost" size="sm" onClick={() => setCurrentWeek(subWeeks(currentWeek, 1))}>
          <ChevronLeft className="h-4 w-4" />
        </Button>
        <div className="text-center">
          <p className="text-lg font-semibold">
            {format(startOfWeek(currentWeek, { weekStartsOn: 1 }), 'M月d日', { locale: ja })}
            {' - '}
            {format(endOfWeek(currentWeek, { weekStartsOn: 1 }), 'M月d日', { locale: ja })}
          </p>
        </div>
        <Button variant="ghost" size="sm" onClick={() => setCurrentWeek(addWeeks(currentWeek, 1))}>
          <ChevronRight className="h-4 w-4" />
        </Button>
        <Button variant="outline" size="sm" onClick={() => setCurrentWeek(new Date())}>
          今週
        </Button>
      </div>

      {isLoading ? (
        <SkeletonList items={5} />
      ) : !plan ? (
        <Card>
          <div className="py-12 text-center">
            <Calendar className="mx-auto h-12 w-12 text-gray-300" />
            <p className="mt-2 text-sm text-gray-500">この週の計画はまだ作成されていません</p>
          </div>
        </Card>
      ) : (
        <>
          {plan.status === 'draft' && (
            <div className="rounded-lg bg-yellow-50 border border-yellow-200 p-3 text-sm text-yellow-700">
              この週の計画はまだ作成中です。確定後に公開されます。
            </div>
          )}

          {plan.staff_comment && (
            <Card>
              <div className="flex items-start gap-3">
                <div className="flex h-8 w-8 items-center justify-center rounded-full bg-blue-100 shrink-0">
                  <span className="text-xs font-bold text-blue-600">S</span>
                </div>
                <div>
                  <p className="text-sm font-medium text-gray-700">スタッフからのコメント</p>
                  <p className="mt-1 text-sm text-gray-600 whitespace-pre-wrap">{plan.staff_comment}</p>
                </div>
              </div>
            </Card>
          )}

          {/* Day plans */}
          <div className="space-y-3">
            {plan.days.map((day) => (
              <Card key={day.date} className={!day.is_scheduled ? 'opacity-50' : ''}>
                <div className="flex items-center gap-3 mb-3">
                  <div className={`flex h-10 w-10 items-center justify-center rounded-full ${day.is_scheduled ? 'bg-blue-100' : 'bg-gray-100'}`}>
                    <span className={`text-sm font-bold ${day.is_scheduled ? 'text-blue-600' : 'text-gray-400'}`}>
                      {day.day_label}
                    </span>
                  </div>
                  <div>
                    <p className="font-medium text-gray-900">
                      {format(new Date(day.date), 'M月d日(E)', { locale: ja })}
                    </p>
                    {!day.is_scheduled && <Badge variant="default">通所なし</Badge>}
                  </div>
                </div>

                {day.is_scheduled && day.activities.length > 0 && (
                  <div className="space-y-2 ml-13">
                    {day.activities.map((activity, i) => (
                      <div key={i} className="flex items-start gap-3 rounded-lg bg-gray-50 p-3">
                        <div className="flex items-center gap-1 shrink-0 text-sm text-gray-500">
                          <Clock className="h-3 w-3" />
                          {activity.time}
                        </div>
                        <div>
                          <p className="text-sm font-medium text-gray-900">{activity.name}</p>
                          {activity.description && (
                            <p className="text-sm text-gray-500">{activity.description}</p>
                          )}
                        </div>
                      </div>
                    ))}
                  </div>
                )}

                {day.is_scheduled && day.activities.length === 0 && (
                  <p className="text-sm text-gray-500 ml-13">活動の詳細はまだありません</p>
                )}
              </Card>
            ))}
          </div>
        </>
      )}
    </div>
  );
}
