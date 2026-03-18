'use client';

import { useState, useMemo } from 'react';
import { useQuery } from '@tanstack/react-query';
import api from '@/lib/api';
import { Card, CardHeader, CardTitle } from '@/components/ui/Card';
import { Button } from '@/components/ui/Button';
import { Badge } from '@/components/ui/Badge';
import { SkeletonList } from '@/components/ui/Skeleton';
import { format, startOfWeek, addWeeks, subWeeks } from 'date-fns';
import { ja } from 'date-fns/locale';
import { ChevronLeft, ChevronRight, Calendar, CheckCircle } from 'lucide-react';

interface WeeklyPlanComment {
  id: number;
  commenter_type: string;
  comment: string;
  created_at: string;
  commenter?: { full_name?: string; student_name?: string };
}

interface WeeklyPlanSubmission {
  id: number;
  submission_item: string;
  due_date: string | null;
  is_completed: boolean;
  completed_at: string | null;
}

interface WeeklyPlanData {
  id: number;
  student_id: number;
  week_start_date: string;
  weekly_goal: string | null;
  shared_goal: string | null;
  must_do: string | null;
  should_do: string | null;
  want_to_do: string | null;
  plan_data: Record<string, string> | null;
  overall_comment: string | null;
  status: string;
  submissions: WeeklyPlanSubmission[];
  comments?: WeeklyPlanComment[];
}

const dayKeys = ['day_0', 'day_1', 'day_2', 'day_3', 'day_4', 'day_5', 'day_6'];
const dayNames = ['月', '火', '水', '木', '金', '土', '日'];

export default function StudentWeeklyPlansPage() {
  const [currentWeek, setCurrentWeek] = useState(new Date());

  const weekStart = format(startOfWeek(currentWeek, { weekStartsOn: 1 }), 'yyyy-MM-dd');

  const { data: plan, isLoading } = useQuery({
    queryKey: ['student', 'weekly-plans', weekStart],
    queryFn: async () => {
      const res = await api.get<{ data: WeeklyPlanData | null }>('/api/student/weekly-plans', {
        params: { week_start: weekStart },
      });
      return res.data.data;
    },
  });

  // Compute week dates for display
  const weekDates = useMemo(() => {
    const start = startOfWeek(currentWeek, { weekStartsOn: 1 });
    return dayNames.map((name, i) => {
      const date = new Date(start);
      date.setDate(date.getDate() + i);
      return {
        key: dayKeys[i],
        name,
        date: format(date, 'M/d'),
      };
    });
  }, [currentWeek]);

  const weekLabel = useMemo(() => {
    const start = startOfWeek(currentWeek, { weekStartsOn: 1 });
    const end = new Date(start);
    end.setDate(end.getDate() + 6);
    return `${format(start, 'M月d日', { locale: ja })} - ${format(end, 'M月d日', { locale: ja })}`;
  }, [currentWeek]);

  return (
    <div className="space-y-6">
      <h1 className="text-2xl font-bold text-[var(--neutral-foreground-1)]">週間計画表</h1>

      {/* Week navigation */}
      <div className="flex items-center justify-center gap-4">
        <Button variant="ghost" size="sm" onClick={() => setCurrentWeek(subWeeks(currentWeek, 1))}>
          <ChevronLeft className="h-4 w-4" />
        </Button>
        <p className="text-lg font-semibold text-[var(--neutral-foreground-1)]">{weekLabel}</p>
        <Button variant="ghost" size="sm" onClick={() => setCurrentWeek(addWeeks(currentWeek, 1))}>
          <ChevronRight className="h-4 w-4" />
        </Button>
        <Button variant="outline" size="sm" onClick={() => setCurrentWeek(new Date())}>今週</Button>
      </div>

      {isLoading ? (
        <SkeletonList items={5} />
      ) : !plan ? (
        <Card>
          <div className="py-12 text-center">
            <Calendar className="mx-auto h-12 w-12 text-[var(--neutral-foreground-4)]" />
            <p className="mt-2 text-sm text-[var(--neutral-foreground-3)]">この週の計画はまだありません</p>
          </div>
        </Card>
      ) : (
        <>
          {/* Goals section */}
          <Card>
            <CardHeader>
              <CardTitle>目標</CardTitle>
            </CardHeader>
            <div className="space-y-4">
              {/* Weekly Goal */}
              <div>
                <label className="mb-1 block text-sm font-medium text-[var(--brand-80)]">今週の個人目標</label>
                <div className="rounded-lg border-l-4 border-l-[var(--brand-80)] bg-[var(--neutral-background-2)] p-3">
                  <p className="text-sm text-[var(--neutral-foreground-1)] whitespace-pre-wrap">
                    {plan.weekly_goal || <span className="italic text-[var(--neutral-foreground-4)]">未入力</span>}
                  </p>
                </div>
              </div>

              {/* Shared Goal */}
              {plan.shared_goal && (
                <div>
                  <label className="mb-1 block text-sm font-medium text-green-600">みんなのめあて</label>
                  <div className="rounded-lg border-l-4 border-l-green-500 bg-green-50 p-3">
                    <p className="text-sm text-[var(--neutral-foreground-1)] whitespace-pre-wrap">{plan.shared_goal}</p>
                  </div>
                </div>
              )}

              {/* Must Do / Should Do / Want to Do */}
              <div className="grid gap-3 sm:grid-cols-3">
                <div>
                  <label className="mb-1 block text-xs font-medium text-red-600">やらなければならないこと</label>
                  <div className="rounded-lg border-l-4 border-l-red-400 bg-red-50/50 p-3">
                    <p className="text-sm text-[var(--neutral-foreground-1)] whitespace-pre-wrap">
                      {plan.must_do || <span className="italic text-[var(--neutral-foreground-4)]">-</span>}
                    </p>
                  </div>
                </div>
                <div>
                  <label className="mb-1 block text-xs font-medium text-yellow-600">やった方がいいこと</label>
                  <div className="rounded-lg border-l-4 border-l-yellow-400 bg-yellow-50/50 p-3">
                    <p className="text-sm text-[var(--neutral-foreground-1)] whitespace-pre-wrap">
                      {plan.should_do || <span className="italic text-[var(--neutral-foreground-4)]">-</span>}
                    </p>
                  </div>
                </div>
                <div>
                  <label className="mb-1 block text-xs font-medium text-blue-600">やりたいこと</label>
                  <div className="rounded-lg border-l-4 border-l-blue-400 bg-blue-50/50 p-3">
                    <p className="text-sm text-[var(--neutral-foreground-1)] whitespace-pre-wrap">
                      {plan.want_to_do || <span className="italic text-[var(--neutral-foreground-4)]">-</span>}
                    </p>
                  </div>
                </div>
              </div>
            </div>
          </Card>

          {/* Daily Plans */}
          <Card>
            <CardHeader>
              <CardTitle>日ごとの計画</CardTitle>
            </CardHeader>
            <div className="space-y-3">
              {weekDates.map((day) => {
                const dayContent = plan.plan_data?.[day.key] || '';
                return (
                  <div key={day.key} className="border-b border-[var(--neutral-stroke-2)] pb-3 last:border-b-0">
                    <div className="flex items-center gap-2 mb-1">
                      <span className="inline-flex h-7 w-7 items-center justify-center rounded-full bg-[var(--brand-80)] text-xs font-bold text-white">
                        {day.name}
                      </span>
                      <span className="text-sm text-[var(--neutral-foreground-2)]">{day.date}</span>
                    </div>
                    <div className="ml-9">
                      {dayContent ? (
                        <p className="text-sm text-[var(--neutral-foreground-1)] whitespace-pre-wrap">{dayContent}</p>
                      ) : (
                        <p className="text-sm italic text-[var(--neutral-foreground-4)]">予定なし</p>
                      )}
                    </div>
                  </div>
                );
              })}
            </div>
          </Card>

          {/* Submissions */}
          {plan.submissions.length > 0 && (
            <Card>
              <CardHeader>
                <CardTitle>提出物</CardTitle>
              </CardHeader>
              <div className="space-y-2">
                {plan.submissions.map((sub) => (
                  <div
                    key={sub.id}
                    className={`flex items-center gap-3 rounded-lg p-3 ${
                      sub.is_completed
                        ? 'bg-green-50 border-l-4 border-l-green-500 opacity-60 line-through'
                        : 'bg-[var(--neutral-background-2)] border-l-4 border-l-[var(--brand-80)]'
                    }`}
                  >
                    <CheckCircle className={`h-5 w-5 flex-shrink-0 ${sub.is_completed ? 'text-green-500' : 'text-[var(--neutral-foreground-4)]'}`} />
                    <div className="flex-1">
                      <p className="text-sm font-medium text-[var(--neutral-foreground-1)]">{sub.submission_item}</p>
                      {sub.due_date && (
                        <p className="text-xs text-[var(--neutral-foreground-3)]">
                          期限: {format(new Date(sub.due_date), 'M月d日', { locale: ja })}
                        </p>
                      )}
                    </div>
                    {sub.is_completed && (
                      <Badge variant="success">完了</Badge>
                    )}
                  </div>
                ))}
              </div>
            </Card>
          )}

          {/* Comments */}
          {plan.comments && plan.comments.length > 0 && (
            <Card>
              <CardHeader>
                <CardTitle>コメント</CardTitle>
              </CardHeader>
              <div className="space-y-3">
                {plan.comments.map((comment) => {
                  const borderColor = comment.commenter_type === 'staff'
                    ? 'border-l-green-500'
                    : comment.commenter_type === 'guardian'
                    ? 'border-l-orange-400'
                    : 'border-l-[var(--brand-80)]';
                  const name = comment.commenter_type === 'staff'
                    ? comment.commenter?.full_name || 'スタッフ'
                    : comment.commenter_type === 'guardian'
                    ? comment.commenter?.full_name || '保護者'
                    : '本人';
                  return (
                    <div key={comment.id} className={`rounded-lg border-l-4 ${borderColor} bg-[var(--neutral-background-2)] p-3`}>
                      <div className="flex items-center justify-between mb-1">
                        <span className="text-xs font-medium text-[var(--neutral-foreground-2)]">{name}</span>
                        <span className="text-xs text-[var(--neutral-foreground-4)]">
                          {format(new Date(comment.created_at), 'M/d H:mm', { locale: ja })}
                        </span>
                      </div>
                      <p className="text-sm text-[var(--neutral-foreground-1)] whitespace-pre-wrap">{comment.comment}</p>
                    </div>
                  );
                })}
              </div>
            </Card>
          )}

          {/* Overall comment from staff */}
          {plan.overall_comment && (
            <Card className="border-l-4 border-l-green-500">
              <div className="p-4">
                <p className="text-xs font-medium text-green-600 mb-1">先生からのコメント</p>
                <p className="text-sm text-[var(--neutral-foreground-1)] whitespace-pre-wrap">{plan.overall_comment}</p>
              </div>
            </Card>
          )}
        </>
      )}
    </div>
  );
}
