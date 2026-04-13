'use client';

import { useState, useMemo } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import api from '@/lib/api';
import { nl } from '@/lib/utils';
import { Card, CardBody } from '@/components/ui/Card';
import { Button } from '@/components/ui/Button';
import { SkeletonList } from '@/components/ui/Skeleton';
import { useToast } from '@/components/ui/Toast';
import { format, startOfWeek, addWeeks, subWeeks, addDays } from 'date-fns';
import { ja } from 'date-fns/locale';
import { MaterialIcon } from '@/components/ui/MaterialIcon';

interface WeeklyPlan {
  id: number;
  classroom_id: number;
  student_id: number | null;
  week_start_date: string;
  plan_data: Record<string, string> | null;
  plan_content: Record<string, string> | null;
  weekly_goal: string | null;
  shared_goal: string | null;
  must_do: string | null;
  should_do: string | null;
  want_to_do: string | null;
  overall_comment: string | null;
  status: string;
  classroom?: { id: number; classroom_name: string };
  comments?: WeeklyPlanComment[];
  created_at: string;
  updated_at: string;
}

interface WeeklyPlanComment {
  id: number;
  plan_id: number;
  user_id: number;
  commenter_type?: string;
  comment: string;
  created_at: string;
  user?: { id: number; full_name: string };
}

interface StudentOption {
  id: number;
  student_name: string;
}

const DAY_LABELS = ['月曜日', '火曜日', '水曜日', '木曜日', '金曜日', '土曜日', '日曜日'];

export default function GuardianWeeklyPlansPage() {
  const queryClient = useQueryClient();
  const toast = useToast();
  const [currentWeek, setCurrentWeek] = useState(new Date());
  const [selectedStudent, setSelectedStudent] = useState('');
  const [comment, setComment] = useState('');

  const weekStartDate = format(startOfWeek(currentWeek, { weekStartsOn: 1 }), 'yyyy-MM-dd');

  const { data: students = [] } = useQuery({
    queryKey: ['guardian', 'students'],
    queryFn: async () => {
      const res = await api.get<{ data: StudentOption[] }>('/api/guardian/students');
      return res.data.data;
    },
  });

  const studentId = selectedStudent || students[0]?.id?.toString() || '';

  // Fetch weekly plans for the week
  const { data: plansResponse, isLoading } = useQuery({
    queryKey: ['guardian', 'weekly-plans', studentId, weekStartDate],
    queryFn: async () => {
      const params: Record<string, string> = { week_start_date: weekStartDate };
      // Try student-specific route first, fallback to general
      if (studentId) {
        try {
          const res = await api.get<{ data: WeeklyPlan | WeeklyPlan[] | { data: WeeklyPlan[] } }>(`/api/guardian/students/${studentId}/weekly-plans`, { params });
          return res.data.data;
        } catch {
          // fallback
        }
      }
      const res = await api.get<{ data: WeeklyPlan | WeeklyPlan[] | { data: WeeklyPlan[] } }>('/api/guardian/weekly-plans', { params });
      return res.data.data;
    },
    enabled: !!studentId,
  });

  // Normalize to single plan
  const plan: WeeklyPlan | null = useMemo(() => {
    if (!plansResponse) return null;
    if (Array.isArray(plansResponse)) return plansResponse[0] ?? null;
    if (typeof plansResponse === 'object' && 'data' in plansResponse && Array.isArray((plansResponse as { data: WeeklyPlan[] }).data)) {
      return ((plansResponse as { data: WeeklyPlan[] }).data)[0] ?? null;
    }
    return plansResponse as WeeklyPlan;
  }, [plansResponse]);

  // Fetch plan detail with comments when plan exists
  const { data: planDetail } = useQuery({
    queryKey: ['guardian', 'weekly-plans', 'detail', plan?.id],
    queryFn: async () => {
      const res = await api.get<{ data: WeeklyPlan }>(`/api/guardian/weekly-plans/${plan!.id}`);
      return res.data.data;
    },
    enabled: !!plan?.id,
  });

  const activePlan = planDetail ?? plan;
  const planData = activePlan?.plan_data ?? activePlan?.plan_content ?? {};
  const comments = activePlan?.comments ?? [];

  // Comment mutation
  const commentMutation = useMutation({
    mutationFn: async (data: { plan_id: number; comment: string }) => {
      return api.post(`/api/guardian/weekly-plans/${data.plan_id}/comments`, { comment: data.comment });
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['guardian', 'weekly-plans'] });
      toast.success('コメントを投稿しました');
      setComment('');
    },
    onError: () => toast.error('コメントの投稿に失敗しました'),
  });

  const handleSubmitComment = () => {
    if (!activePlan?.id || !comment.trim()) {
      toast.error('コメントを入力してください');
      return;
    }
    commentMutation.mutate({ plan_id: activePlan.id, comment: comment.trim() });
  };

  return (
    <div className="space-y-6">
      <h1 className="text-2xl font-bold text-[var(--neutral-foreground-1)]">週間計画表</h1>

      {/* Student selector */}
      {students.length > 1 && (
        <select
          value={selectedStudent || students[0]?.id}
          onChange={(e) => setSelectedStudent(e.target.value)}
          className="w-full rounded-lg border border-[var(--neutral-stroke-1)] bg-white px-3 py-2 text-sm shadow-sm"
        >
          {students.map((s) => (
            <option key={s.id} value={s.id}>{s.student_name}</option>
          ))}
        </select>
      )}

      {/* Week navigation */}
      <div className="flex items-center justify-between rounded-lg bg-white px-4 py-3 shadow-sm">
        <h2 className="text-sm font-semibold text-[var(--neutral-foreground-1)]">
          {format(new Date(weekStartDate), 'yyyy年M月d日', { locale: ja })}の週
        </h2>
        <div className="flex gap-2">
          <Button variant="secondary" size="sm" onClick={() => setCurrentWeek(subWeeks(currentWeek, 1))}>
            <MaterialIcon name="chevron_left" size={16} /> 前週
          </Button>
          <Button variant="outline" size="sm" onClick={() => setCurrentWeek(new Date())}>
            今週
          </Button>
          <Button variant="secondary" size="sm" onClick={() => setCurrentWeek(addWeeks(currentWeek, 1))}>
            次週 <MaterialIcon name="chevron_right" size={16} />
          </Button>
        </div>
      </div>

      {isLoading ? (
        <SkeletonList items={5} />
      ) : !activePlan ? (
        <Card>
          <div className="py-12 text-center">
            <MaterialIcon name="calendar_month" size={48} className="mx-auto text-[var(--neutral-foreground-disabled)]" />
            <p className="mt-2 text-sm text-[var(--neutral-foreground-3)]">この週の計画はまだ作成されていません</p>
          </div>
        </Card>
      ) : (
        <>
          {/* Goals section */}
          {(activePlan.weekly_goal || activePlan.shared_goal || activePlan.must_do || activePlan.should_do || activePlan.want_to_do) && (
            <Card>
              <CardBody>
                <h3 className="mb-3 text-sm font-semibold text-[var(--neutral-foreground-2)]">目標</h3>
                <div className="space-y-2">
                  {activePlan.weekly_goal && (
                    <div className="rounded-lg bg-[var(--brand-160)] p-3">
                      <p className="text-xs font-medium text-[var(--brand-70)]">週間目標</p>
                      <p className="text-sm text-[var(--neutral-foreground-1)] whitespace-pre-wrap">{nl(activePlan.weekly_goal)}</p>
                    </div>
                  )}
                  {activePlan.shared_goal && (
                    <div className="rounded-lg bg-[var(--brand-160)] p-3">
                      <p className="text-xs font-medium text-[var(--brand-80)]">共有目標</p>
                      <p className="text-sm text-[var(--neutral-foreground-1)] whitespace-pre-wrap">{nl(activePlan.shared_goal)}</p>
                    </div>
                  )}
                  {activePlan.must_do && (
                    <div className="rounded-lg bg-red-50 p-3">
                      <p className="text-xs font-medium text-red-600">やるべきこと</p>
                      <p className="text-sm text-[var(--neutral-foreground-1)] whitespace-pre-wrap">{nl(activePlan.must_do)}</p>
                    </div>
                  )}
                  {activePlan.should_do && (
                    <div className="rounded-lg bg-amber-50 p-3">
                      <p className="text-xs font-medium text-amber-600">やったほうがいいこと</p>
                      <p className="text-sm text-[var(--neutral-foreground-1)] whitespace-pre-wrap">{nl(activePlan.should_do)}</p>
                    </div>
                  )}
                  {activePlan.want_to_do && (
                    <div className="rounded-lg bg-green-50 p-3">
                      <p className="text-xs font-medium text-green-600">やりたいこと</p>
                      <p className="text-sm text-[var(--neutral-foreground-1)] whitespace-pre-wrap">{nl(activePlan.want_to_do)}</p>
                    </div>
                  )}
                </div>
              </CardBody>
            </Card>
          )}

          {/* Day-by-day plan table */}
          <Card>
            <CardBody>
              <div className="overflow-x-auto">
                <table className="w-full min-w-[500px] border-collapse">
                  <thead>
                    <tr>
                      <th className="border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-3)] px-4 py-2 text-left text-sm font-semibold text-[var(--neutral-foreground-2)]" style={{ width: 100 }}>
                        曜日
                      </th>
                      <th className="border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-3)] px-4 py-2 text-left text-sm font-semibold text-[var(--neutral-foreground-2)]">
                        計画・目標
                      </th>
                    </tr>
                  </thead>
                  <tbody>
                    {DAY_LABELS.map((dayLabel, index) => {
                      const dayKey = `day_${index}`;
                      const content = planData[dayKey] ?? '';
                      const date = format(addDays(new Date(weekStartDate), index), 'M/d');
                      return (
                        <tr key={dayKey}>
                          <td className="border border-[var(--neutral-stroke-2)] px-4 py-3 align-top">
                            <p className="text-sm font-semibold text-[var(--brand-70)]">{dayLabel}</p>
                            <p className="text-xs text-[var(--neutral-foreground-4)]">{date}</p>
                          </td>
                          <td className="border border-[var(--neutral-stroke-2)] px-4 py-3 align-top">
                            {content ? (
                              <p className="whitespace-pre-wrap text-sm text-[var(--neutral-foreground-1)]">{nl(content)}</p>
                            ) : (
                              <p className="text-sm italic text-[var(--neutral-foreground-4)]">計画なし</p>
                            )}
                          </td>
                        </tr>
                      );
                    })}
                  </tbody>
                </table>
              </div>
            </CardBody>
          </Card>

          {/* Overall comment */}
          {activePlan.overall_comment && (
            <Card>
              <CardBody>
                <h3 className="mb-2 text-sm font-semibold text-[var(--neutral-foreground-2)]">総合コメント</h3>
                <p className="whitespace-pre-wrap text-sm text-[var(--neutral-foreground-1)]">{nl(activePlan.overall_comment)}</p>
              </CardBody>
            </Card>
          )}

          {/* Comments section */}
          <Card>
            <CardBody>
              <h3 className="mb-4 text-sm font-semibold text-[var(--neutral-foreground-2)]">コメント</h3>

              {comments.length === 0 ? (
                <p className="py-4 text-center text-sm text-[var(--neutral-foreground-4)]">まだコメントはありません</p>
              ) : (
                <div className="mb-4 space-y-3">
                  {comments.map((c) => {
                    const borderColor = c.commenter_type === 'guardian' ? 'border-orange-400' : c.commenter_type === 'student' ? 'border-purple-400' : 'border-green-500';
                    const typeLabel = c.commenter_type === 'guardian' ? '保護者' : c.commenter_type === 'student' ? '本人' : 'スタッフ';
                    return (
                    <div key={c.id} className={`rounded-lg border-l-4 ${borderColor} bg-[var(--neutral-background-3)] p-3`}>
                      <div className="mb-1 flex items-center justify-between">
                        <span className="text-sm font-semibold text-[var(--brand-70)]">
                          {c.user?.full_name ?? '不明'}
                          <span className="ml-2 text-xs font-normal text-[var(--neutral-foreground-3)]">({typeLabel})</span>
                        </span>
                        <span className="text-xs text-[var(--neutral-foreground-4)]">
                          {format(new Date(c.created_at), 'yyyy/M/d HH:mm', { locale: ja })}
                        </span>
                      </div>
                      <p className="whitespace-pre-wrap text-sm text-[var(--neutral-foreground-2)]">{nl(c.comment)}</p>
                    </div>
                    );
                  })}
                </div>
              )}

              {/* Comment form */}
              <div className="border-t border-[var(--neutral-stroke-2)] pt-4">
                <textarea
                  value={comment}
                  onChange={(e) => setComment(e.target.value)}
                  placeholder="コメントを入力..."
                  className="w-full rounded-lg border border-[var(--neutral-stroke-1)] px-3 py-2 text-sm"
                  rows={3}
                  required
                />
                <div className="mt-2 flex justify-end">
                  <Button
                    size="sm"
                    onClick={handleSubmitComment}
                    isLoading={commentMutation.isPending}
                    disabled={!comment.trim()}
                    leftIcon={<MaterialIcon name="send" size={16} />}
                  >
                    コメントを投稿
                  </Button>
                </div>
              </div>
            </CardBody>
          </Card>
        </>
      )}
    </div>
  );
}
