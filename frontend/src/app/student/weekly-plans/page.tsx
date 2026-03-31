'use client';

import { useState, useMemo, useCallback } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import api from '@/lib/api';
import { nl } from '@/lib/utils';
import { Card, CardHeader, CardTitle } from '@/components/ui/Card';
import { Button } from '@/components/ui/Button';
import { Badge } from '@/components/ui/Badge';
import { SkeletonList } from '@/components/ui/Skeleton';
import { useToast } from '@/components/ui/Toast';
import { MaterialIcon } from '@/components/ui/MaterialIcon';
import { format, startOfWeek, addWeeks, subWeeks, addDays } from 'date-fns';
import { ja } from 'date-fns/locale';

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
  submissions: { id: number; submission_item: string; due_date: string | null; is_completed: boolean; completed_at: string | null }[];
  comments?: { id: number; commenter_type: string; comment: string; created_at: string; commenter?: { full_name?: string } }[];
}

const dayKeys = ['day_0', 'day_1', 'day_2', 'day_3', 'day_4', 'day_5', 'day_6'];
const dayNames = ['月', '火', '水', '木', '金', '土', '日'];

export default function StudentWeeklyPlansPage() {
  const queryClient = useQueryClient();
  const toast = useToast();
  const [currentWeek, setCurrentWeek] = useState(new Date());
  const [isEditing, setIsEditing] = useState(false);
  const [form, setForm] = useState({
    weekly_goal: '', must_do: '', should_do: '', want_to_do: '',
    plan_data: {} as Record<string, string>,
  });

  const weekStart = format(startOfWeek(currentWeek, { weekStartsOn: 1 }), 'yyyy-MM-dd');

  const { data: plan, isLoading, refetch } = useQuery({
    queryKey: ['student', 'weekly-plans', weekStart],
    queryFn: async () => {
      const res = await api.get<{ data: WeeklyPlanData | null }>('/api/student/weekly-plans', {
        params: { week_start: weekStart },
      });
      return res.data.data;
    },
  });

  const weekDates = useMemo(() => {
    const start = startOfWeek(currentWeek, { weekStartsOn: 1 });
    return dayNames.map((name, i) => ({
      key: dayKeys[i],
      name,
      date: format(addDays(start, i), 'M/d'),
    }));
  }, [currentWeek]);

  const weekLabel = useMemo(() => {
    const start = startOfWeek(currentWeek, { weekStartsOn: 1 });
    const end = addDays(start, 6);
    return `${format(start, 'M月d日', { locale: ja })} - ${format(end, 'M月d日', { locale: ja })}`;
  }, [currentWeek]);

  const enterEdit = useCallback(() => {
    setForm({
      weekly_goal: plan?.weekly_goal ?? '',
      must_do: plan?.must_do ?? '',
      should_do: plan?.should_do ?? '',
      want_to_do: plan?.want_to_do ?? '',
      plan_data: {
        day_0: plan?.plan_data?.day_0 ?? '',
        day_1: plan?.plan_data?.day_1 ?? '',
        day_2: plan?.plan_data?.day_2 ?? '',
        day_3: plan?.plan_data?.day_3 ?? '',
        day_4: plan?.plan_data?.day_4 ?? '',
        day_5: plan?.plan_data?.day_5 ?? '',
        day_6: plan?.plan_data?.day_6 ?? '',
      },
    });
    setIsEditing(true);
  }, [plan]);

  const saveMutation = useMutation({
    mutationFn: async () => {
      const payload = {
        weekly_goal: form.weekly_goal || null,
        must_do: form.must_do || null,
        should_do: form.should_do || null,
        want_to_do: form.want_to_do || null,
        plan_data: form.plan_data,
      };
      if (plan?.id) {
        return api.put(`/api/student/weekly-plans/${plan.id}`, payload);
      } else {
        return api.post('/api/student/weekly-plans', {
          ...payload,
          week_start_date: weekStart,
        });
      }
    },
    onSuccess: () => {
      toast.success('保存しました');
      setIsEditing(false);
      refetch();
    },
    onError: () => toast.error('保存に失敗しました'),
  });

  const inputCls = 'block w-full rounded-lg border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-1)] px-3 py-2 text-sm focus:border-[var(--brand-80)] focus:outline-none focus:ring-1 focus:ring-[var(--brand-80)]';

  return (
    <div className="space-y-6">
      <h1 className="text-2xl font-bold text-[var(--neutral-foreground-1)]">週間計画表</h1>

      {/* Week navigation */}
      <div className="flex items-center justify-center gap-4">
        <Button variant="ghost" size="sm" onClick={() => { setCurrentWeek(subWeeks(currentWeek, 1)); setIsEditing(false); }}>
          <MaterialIcon name="chevron_left" size={20} />
        </Button>
        <p className="text-lg font-semibold text-[var(--neutral-foreground-1)]">{weekLabel}</p>
        <Button variant="ghost" size="sm" onClick={() => { setCurrentWeek(addWeeks(currentWeek, 1)); setIsEditing(false); }}>
          <MaterialIcon name="chevron_right" size={20} />
        </Button>
        <Button variant="outline" size="sm" onClick={() => { setCurrentWeek(new Date()); setIsEditing(false); }}>今週</Button>
      </div>

      {isLoading ? (
        <SkeletonList items={5} />
      ) : !plan && !isEditing ? (
        <Card>
          <div className="py-12 text-center">
            <MaterialIcon name="calendar_month" size={48} className="mx-auto mb-2 text-[var(--neutral-foreground-4)]" />
            <p className="mb-4 text-sm text-[var(--neutral-foreground-3)]">この週の計画はまだありません</p>
            <Button variant="primary" size="sm" leftIcon={<MaterialIcon name="add" size={16} />} onClick={() => { setForm({ weekly_goal: '', must_do: '', should_do: '', want_to_do: '', plan_data: { day_0: '', day_1: '', day_2: '', day_3: '', day_4: '', day_5: '', day_6: '' } }); setIsEditing(true); }}>
              計画を作成する
            </Button>
          </div>
        </Card>
      ) : isEditing ? (
        /* ===== Edit mode ===== */
        <div className="space-y-4">
          <Card>
            <CardHeader>
              <div className="flex items-center justify-between">
                <CardTitle>目標を編集</CardTitle>
                <div className="flex gap-2">
                  <Button variant="ghost" size="sm" onClick={() => setIsEditing(false)}>キャンセル</Button>
                  <Button size="sm" leftIcon={<MaterialIcon name="save" size={16} />} onClick={() => saveMutation.mutate()} isLoading={saveMutation.isPending}>保存</Button>
                </div>
              </div>
            </CardHeader>
            <div className="space-y-3 p-4">
              <div>
                <label className="mb-1 block text-sm font-medium text-[var(--brand-80)]">今週の個人目標</label>
                <textarea className={inputCls} rows={2} value={form.weekly_goal} onChange={(e) => setForm({ ...form, weekly_goal: e.target.value })} placeholder="今週の目標..." />
              </div>
              <div className="grid gap-3 sm:grid-cols-3">
                <div>
                  <label className="mb-1 block text-xs font-medium text-red-600">やらなければならないこと</label>
                  <textarea className={inputCls} rows={2} value={form.must_do} onChange={(e) => setForm({ ...form, must_do: e.target.value })} />
                </div>
                <div>
                  <label className="mb-1 block text-xs font-medium text-yellow-600">やった方がいいこと</label>
                  <textarea className={inputCls} rows={2} value={form.should_do} onChange={(e) => setForm({ ...form, should_do: e.target.value })} />
                </div>
                <div>
                  <label className="mb-1 block text-xs font-medium text-[var(--brand-80)]">やりたいこと</label>
                  <textarea className={inputCls} rows={2} value={form.want_to_do} onChange={(e) => setForm({ ...form, want_to_do: e.target.value })} />
                </div>
              </div>
            </div>
          </Card>

          <Card>
            <CardHeader><CardTitle>日ごとの計画</CardTitle></CardHeader>
            <div className="space-y-3 p-4">
              {weekDates.map((day) => (
                <div key={day.key} className="grid grid-cols-[60px_1fr] gap-2 items-start">
                  <div className="pt-2">
                    <span className="inline-flex h-7 w-7 items-center justify-center rounded-full bg-[var(--brand-80)] text-xs font-bold text-white">{day.name}</span>
                    <span className="ml-1 text-xs text-[var(--neutral-foreground-3)]">{day.date}</span>
                  </div>
                  <textarea
                    className={inputCls}
                    rows={2}
                    value={form.plan_data[day.key] ?? ''}
                    onChange={(e) => setForm({ ...form, plan_data: { ...form.plan_data, [day.key]: e.target.value } })}
                    placeholder="この日の計画..."
                  />
                </div>
              ))}
            </div>
          </Card>

          <div className="flex justify-end gap-2">
            <Button variant="ghost" onClick={() => setIsEditing(false)}>キャンセル</Button>
            <Button leftIcon={<MaterialIcon name="save" size={16} />} onClick={() => saveMutation.mutate()} isLoading={saveMutation.isPending}>保存する</Button>
          </div>
        </div>
      ) : plan ? (
        /* ===== View mode ===== */
        <>
          {/* Edit button */}
          <div className="flex justify-end">
            <Button variant="primary" size="sm" leftIcon={<MaterialIcon name="edit" size={16} />} onClick={enterEdit}>
              編集する
            </Button>
          </div>

          {/* Goals */}
          <Card>
            <CardHeader><CardTitle>目標</CardTitle></CardHeader>
            <div className="space-y-4 p-4">
              <div>
                <label className="mb-1 block text-sm font-medium text-[var(--brand-80)]">今週の個人目標</label>
                <div className="rounded-lg border-l-4 border-l-[var(--brand-80)] bg-[var(--neutral-background-2)] p-3">
                  <p className="text-sm text-[var(--neutral-foreground-1)] whitespace-pre-wrap">
                    {nl(plan.weekly_goal) || <span className="italic text-[var(--neutral-foreground-4)]">未入力</span>}
                  </p>
                </div>
              </div>
              {plan.shared_goal && (
                <div>
                  <label className="mb-1 block text-sm font-medium text-green-600">みんなのめあて</label>
                  <div className="rounded-lg border-l-4 border-l-green-500 bg-green-50 p-3">
                    <p className="text-sm whitespace-pre-wrap">{nl(plan.shared_goal)}</p>
                  </div>
                </div>
              )}
              <div className="grid gap-3 sm:grid-cols-3">
                <div>
                  <label className="mb-1 block text-xs font-medium text-red-600">やらなければならないこと</label>
                  <div className="rounded-lg border-l-4 border-l-red-400 bg-red-50/50 p-3">
                    <p className="text-sm whitespace-pre-wrap">{nl(plan.must_do) || '-'}</p>
                  </div>
                </div>
                <div>
                  <label className="mb-1 block text-xs font-medium text-yellow-600">やった方がいいこと</label>
                  <div className="rounded-lg border-l-4 border-l-yellow-400 bg-yellow-50/50 p-3">
                    <p className="text-sm whitespace-pre-wrap">{nl(plan.should_do) || '-'}</p>
                  </div>
                </div>
                <div>
                  <label className="mb-1 block text-xs font-medium text-[var(--brand-80)]">やりたいこと</label>
                  <div className="rounded-lg border-l-4 border-l-blue-400 bg-[var(--brand-160)]/50 p-3">
                    <p className="text-sm whitespace-pre-wrap">{nl(plan.want_to_do) || '-'}</p>
                  </div>
                </div>
              </div>
            </div>
          </Card>

          {/* Daily Plans */}
          <Card>
            <CardHeader><CardTitle>日ごとの計画</CardTitle></CardHeader>
            <div className="space-y-3 p-4">
              {weekDates.map((day) => {
                const content = plan.plan_data?.[day.key] || '';
                return (
                  <div key={day.key} className="border-b border-[var(--neutral-stroke-2)] pb-3 last:border-b-0">
                    <div className="flex items-center gap-2 mb-1">
                      <span className="inline-flex h-7 w-7 items-center justify-center rounded-full bg-[var(--brand-80)] text-xs font-bold text-white">{day.name}</span>
                      <span className="text-sm text-[var(--neutral-foreground-2)]">{day.date}</span>
                    </div>
                    <div className="ml-9">
                      <p className="text-sm whitespace-pre-wrap">{content ? nl(content) : <span className="italic text-[var(--neutral-foreground-4)]">予定なし</span>}</p>
                    </div>
                  </div>
                );
              })}
            </div>
          </Card>

          {/* Overall comment */}
          {plan.overall_comment && (
            <Card className="border-l-4 border-l-green-500">
              <div className="p-4">
                <p className="text-xs font-medium text-green-600 mb-1">先生からのコメント</p>
                <p className="text-sm whitespace-pre-wrap">{nl(plan.overall_comment)}</p>
              </div>
            </Card>
          )}
        </>
      ) : null}
    </div>
  );
}
