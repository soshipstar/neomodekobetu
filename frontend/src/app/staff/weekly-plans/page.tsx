'use client';

import { useState, useMemo } from 'react';
import { useQuery } from '@tanstack/react-query';
import api from '@/lib/api';
import { Card, CardBody } from '@/components/ui/Card';
import { Button } from '@/components/ui/Button';
import { Badge } from '@/components/ui/Badge';
import { Skeleton } from '@/components/ui/Skeleton';
import {
  ChevronLeft,
  ChevronRight,
  Calendar,
  CheckCircle,
  User,
} from 'lucide-react';
import { format, addWeeks, startOfWeek, addDays } from 'date-fns';
import { ja } from 'date-fns/locale';
import Link from 'next/link';

// ---------------------------------------------------------------------------
// Types
// ---------------------------------------------------------------------------

interface Student {
  id: number;
  student_name: string;
}

interface WeeklyPlan {
  id: number;
  student_id: number;
  week_start_date: string;
  weekly_goal: string | null;
  updated_at: string;
}

function getWeekStart(date: Date): Date {
  return startOfWeek(date, { weekStartsOn: 1 });
}

// ---------------------------------------------------------------------------
// Main
// ---------------------------------------------------------------------------

export default function WeeklyPlansPage() {
  const [weekOffset, setWeekOffset] = useState(0);

  const weekStart = useMemo(() => addWeeks(getWeekStart(new Date()), weekOffset), [weekOffset]);
  const weekEnd = addDays(weekStart, 6);
  const weekStartStr = format(weekStart, 'yyyy-MM-dd');

  const weekLabel = `${format(weekStart, 'M/d', { locale: ja })} 〜 ${format(weekEnd, 'M/d', { locale: ja })}`;
  const yearMonth = format(weekStart, 'yyyy年M月', { locale: ja });

  // Fetch students
  const { data: students = [], isLoading: loadingStudents } = useQuery({
    queryKey: ['staff', 'students-for-weekly'],
    queryFn: async () => {
      const res = await api.get('/api/staff/students');
      const p = res.data?.data;
      return Array.isArray(p) ? p as Student[] : [];
    },
  });

  // Fetch plans for this week
  const { data: plans = [], isLoading: loadingPlans } = useQuery({
    queryKey: ['staff', 'weekly-plans', weekStartStr],
    queryFn: async () => {
      const res = await api.get('/api/staff/weekly-plans', {
        params: { week_start_date: weekStartStr, per_page: 200 },
      });
      const p = res.data?.data;
      if (Array.isArray(p)) return p as WeeklyPlan[];
      if (p?.data && Array.isArray(p.data)) return p.data as WeeklyPlan[];
      return [] as WeeklyPlan[];
    },
  });

  // Map student_id → plan
  const planByStudent = useMemo(() => {
    const map: Record<number, WeeklyPlan> = {};
    plans.forEach((p) => { if (p.student_id) map[p.student_id] = p; });
    return map;
  }, [plans]);

  const isLoading = loadingStudents || loadingPlans;
  const isCurrentWeek = weekOffset === 0;

  return (
    <div className="space-y-4">
      {/* Header */}
      <div>
        <h1 className="text-2xl font-bold text-[var(--neutral-foreground-1)]">生徒週間計画表</h1>
        <p className="text-sm text-[var(--neutral-foreground-3)]">各生徒の週間計画を確認</p>
      </div>

      {/* Week Navigation */}
      <Card>
        <CardBody>
          <div className="flex items-center justify-between">
            <Button variant="outline" size="sm" leftIcon={<ChevronLeft className="h-4 w-4" />}
              onClick={() => setWeekOffset(weekOffset - 1)}>
              前の週
            </Button>

            <div className="text-center">
              <p className="text-xs text-[var(--neutral-foreground-3)]">{yearMonth}</p>
              <p className="text-lg font-bold text-[var(--neutral-foreground-1)]">{weekLabel}</p>
              {isCurrentWeek && (
                <Badge variant="info">今週</Badge>
              )}
            </div>

            <Button variant="outline" size="sm" disabled={weekOffset >= 1}
              onClick={() => setWeekOffset(weekOffset + 1)}>
              次の週
              <ChevronRight className="h-4 w-4 ml-1" />
            </Button>
          </div>

          {!isCurrentWeek && (
            <div className="mt-3 text-center">
              <Button variant="ghost" size="sm" leftIcon={<Calendar className="h-4 w-4" />}
                onClick={() => setWeekOffset(0)}>
                今週に戻る
              </Button>
            </div>
          )}
        </CardBody>
      </Card>

      {/* Student Grid */}
      {isLoading ? (
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
          {[...Array(6)].map((_, i) => <Skeleton key={i} className="h-28 rounded-lg" />)}
        </div>
      ) : students.length === 0 ? (
        <Card>
          <CardBody>
            <div className="py-12 text-center text-[var(--neutral-foreground-4)]">
              <User className="mx-auto mb-3 h-12 w-12" />
              <p>生徒が登録されていません</p>
            </div>
          </CardBody>
        </Card>
      ) : (
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
          {[...students].sort((a, b) => {
            const aPlan = !!planByStudent[a.id];
            const bPlan = !!planByStudent[b.id];
            if (aPlan === bPlan) return 0;
            return aPlan ? 1 : -1; // 未作成が上、作成済みが下
          }).map((student) => {
            const plan = planByStudent[student.id];
            const hasPlan = !!plan;

            return (
              <Link
                key={student.id}
                href={`/staff/weekly-plans/${student.id}?date=${weekStartStr}`}
              >
                <Card className="cursor-pointer transition-all hover:-translate-y-1 hover:shadow-lg h-full">
                  <CardBody>
                    {/* Header */}
                    <div className="flex items-center gap-3 border-b border-[var(--neutral-stroke-2)] pb-3 mb-3">
                      <div className="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-[var(--brand-150)] text-[var(--brand-70)]">
                        <User className="h-5 w-5" />
                      </div>
                      <span className="text-base font-semibold text-[var(--neutral-foreground-1)]">
                        {student.student_name}
                      </span>
                    </div>

                    {/* Status */}
                    <div>
                      {hasPlan ? (
                        <>
                          <Badge variant="success" dot>
                            <CheckCircle className="h-3 w-3 mr-1" />
                            計画あり
                          </Badge>
                          {plan.updated_at && (
                            <p className="mt-2 text-xs text-[var(--neutral-foreground-4)]">
                              最終更新: {format(new Date(plan.updated_at), 'M/d HH:mm', { locale: ja })}
                            </p>
                          )}
                          {plan.weekly_goal && (
                            <p className="mt-1 text-xs text-[var(--neutral-foreground-3)] line-clamp-2">
                              目標: {plan.weekly_goal}
                            </p>
                          )}
                        </>
                      ) : (
                        <>
                          <Badge variant="default" dot>計画なし</Badge>
                          <p className="mt-2 text-xs text-[var(--neutral-foreground-4)]">
                            この週の計画はまだ作成されていません
                          </p>
                        </>
                      )}
                    </div>
                  </CardBody>
                </Card>
              </Link>
            );
          })}
        </div>
      )}
    </div>
  );
}
