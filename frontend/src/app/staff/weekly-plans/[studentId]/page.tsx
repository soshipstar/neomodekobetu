'use client';

import { useState, useMemo, useCallback } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { useParams, useSearchParams } from 'next/navigation';
import api from '@/lib/api';
import { nl } from '@/lib/utils';
import { Card, CardBody } from '@/components/ui/Card';
import { Button } from '@/components/ui/Button';
import { Badge } from '@/components/ui/Badge';
import { Skeleton } from '@/components/ui/Skeleton';
import { format, addDays, startOfWeek, addWeeks } from 'date-fns';
import { ja } from 'date-fns/locale';
import Link from 'next/link';
import { MaterialIcon } from '@/components/ui/MaterialIcon';

// ---------------------------------------------------------------------------
// Types
// ---------------------------------------------------------------------------

interface Student {
  id: number;
  student_name: string;
}

interface WeeklyPlanData {
  id: number;
  student_id: number;
  classroom_id: number;
  week_start_date: string;
  weekly_goal: string | null;
  shared_goal: string | null;
  must_do: string | null;
  should_do: string | null;
  want_to_do: string | null;
  plan_data: Record<string, string> | null;
  plan_content: Record<string, unknown> | null;
  weekly_goal_achievement: number | null;
  weekly_goal_comment: string | null;
  shared_goal_achievement: number | null;
  shared_goal_comment: string | null;
  must_do_achievement: number | null;
  must_do_comment: string | null;
  should_do_achievement: number | null;
  should_do_comment: string | null;
  want_to_do_achievement: number | null;
  want_to_do_comment: string | null;
  daily_achievement: Record<string, { achievement: number; comment?: string }> | null;
  overall_comment: string | null;
  evaluated_at: string | null;
  status: string | null;
  created_by: number | null;
  created_at: string;
  updated_at: string;
  creator?: { id: number; full_name: string };
  comments?: Array<{
    id: number;
    comment: string;
    created_at: string;
    user?: { id: number; full_name: string };
  }>;
  submissions?: Array<{
    id: number;
    submission_item: string;
    due_date: string;
    is_completed: boolean;
    completed_at: string | null;
  }>;
}

interface FormState {
  weekly_goal: string;
  shared_goal: string;
  must_do: string;
  should_do: string;
  want_to_do: string;
  plan_data: Record<string, string>;
}

// ---------------------------------------------------------------------------
// Constants
// ---------------------------------------------------------------------------

const DAYS = ['月曜日', '火曜日', '水曜日', '木曜日', '金曜日', '土曜日', '日曜日'] as const;

const ACHIEVEMENT_LABELS: Record<number, string> = {
  0: '未評価',
  1: '1 - できなかった',
  2: '2',
  3: '3',
  4: '4',
  5: '5 - よくできた',
};

const ACHIEVEMENT_COLORS: Record<number, string> = {
  0: 'bg-gray-400',
  1: 'bg-red-500',
  2: 'bg-yellow-500',
  3: 'bg-[var(--brand-80)]',
  4: 'bg-green-400',
  5: 'bg-green-600',
};

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function getWeekStart(date: Date): Date {
  return startOfWeek(date, { weekStartsOn: 1 });
}

function computeWeekStartFromDate(dateStr: string): Date {
  const d = new Date(dateStr + 'T00:00:00');
  return getWeekStart(d);
}

// ---------------------------------------------------------------------------
// Components
// ---------------------------------------------------------------------------

/** 5-scale rating selector */
function RatingScale({
  value,
  onChange,
  name,
}: {
  value: number;
  onChange: (v: number) => void;
  name: string;
}) {
  return (
    <div className="flex items-center gap-2">
      {[1, 2, 3, 4, 5].map((n) => (
        <button
          key={n}
          type="button"
          onClick={() => onChange(n)}
          className={`flex h-9 w-9 items-center justify-center rounded-md border-2 text-sm font-bold transition-all ${
            value === n
              ? 'border-purple-600 bg-purple-600 text-white'
              : 'border-[var(--neutral-stroke-1)] bg-white text-[var(--neutral-foreground-4)] hover:border-[var(--brand-110)]'
          }`}
          aria-label={`${name} ${n}`}
        >
          {n}
        </button>
      ))}
    </div>
  );
}

/** Display a section in view mode */
function ViewSection({
  icon,
  label,
  content,
}: {
  icon: React.ReactNode;
  label: string;
  content: string | null | undefined;
}) {
  const hasContent = !!content?.trim();
  return (
    <div className="mb-5">
      <h3 className="mb-2 flex items-center gap-2 text-sm font-semibold text-[var(--brand-60)]">
        {icon}
        {label}
      </h3>
      <div
        className={`rounded-md border-l-4 border-[var(--brand-90)] bg-[var(--neutral-background-3)] p-3 text-sm leading-relaxed whitespace-pre-wrap ${
          !hasContent ? 'italic text-[var(--neutral-foreground-4)]' : 'text-[var(--neutral-foreground-1)]'
        }`}
      >
        {hasContent ? nl(content) : '未記入'}
      </div>
    </div>
  );
}

/** Achievement display for a goal item */
function AchievementDisplay({
  icon,
  label,
  goalText,
  achievement,
  comment,
}: {
  icon: React.ReactNode;
  label: string;
  goalText: string | null;
  achievement: number | null;
  comment: string | null;
}) {
  if (!goalText?.trim()) return null;
  const a = achievement ?? 0;
  const colorClass = ACHIEVEMENT_COLORS[a] || 'bg-gray-400';
  const achLabel = ACHIEVEMENT_LABELS[a] || '未評価';

  return (
    <div className="mb-4 rounded-lg bg-white p-4">
      <div className="mb-1 flex items-center gap-2 text-sm font-semibold text-[var(--neutral-foreground-1)]">
        {icon}
        {label}
      </div>
      <div className="mb-2 rounded bg-[var(--neutral-background-3)] p-2 text-sm leading-relaxed whitespace-pre-wrap">
        {nl(goalText)}
      </div>
      <span
        className={`inline-block rounded px-3 py-1 text-xs font-bold text-white ${colorClass}`}
      >
        {achLabel}
      </span>
      {comment?.trim() && (
        <div className="mt-2 rounded border-l-3 border-yellow-500 bg-yellow-50 p-2 text-xs text-[var(--neutral-foreground-2)]">
          <MaterialIcon name="forum" size={12} className="mr-1 inline-block" />
          {comment}
        </div>
      )}
    </div>
  );
}

// ---------------------------------------------------------------------------
// Main Page
// ---------------------------------------------------------------------------

export default function WeeklyPlanStudentDetailPage() {
  const params = useParams();
  const searchParams = useSearchParams();
  const queryClient = useQueryClient();

  const studentId = Number(params.studentId);
  const dateParam = searchParams.get('date') || format(new Date(), 'yyyy-MM-dd');

  // Week navigation
  const baseWeekStart = useMemo(() => computeWeekStartFromDate(dateParam), [dateParam]);
  const [weekOffset, setWeekOffset] = useState(0);
  const weekStart = useMemo(() => addWeeks(baseWeekStart, weekOffset), [baseWeekStart, weekOffset]);
  const weekEnd = addDays(weekStart, 6);
  const weekStartStr = format(weekStart, 'yyyy-MM-dd');

  const weekLabel = `${format(weekStart, 'M/d', { locale: ja })} 〜 ${format(weekEnd, 'M/d', { locale: ja })}`;
  const yearMonth = format(weekStart, 'yyyy年M月', { locale: ja });

  // Edit mode
  const [isEditMode, setIsEditMode] = useState(false);
  const [form, setForm] = useState<FormState>({
    weekly_goal: '',
    shared_goal: '',
    must_do: '',
    should_do: '',
    want_to_do: '',
    plan_data: {},
  });

  // Success / error messages
  const [message, setMessage] = useState<{ type: 'success' | 'error'; text: string } | null>(null);

  // Fetch student info
  const { data: student, isLoading: loadingStudent } = useQuery({
    queryKey: ['staff', 'student', studentId],
    queryFn: async () => {
      const res = await api.get(`/api/staff/students/${studentId}`);
      return (res.data?.data ?? res.data) as Student;
    },
    enabled: !!studentId,
  });

  // Fetch weekly plan for this student and week
  const {
    data: plan,
    isLoading: loadingPlan,
    refetch: refetchPlan,
  } = useQuery({
    queryKey: ['staff', 'weekly-plan-detail', studentId, weekStartStr],
    queryFn: async () => {
      const res = await api.get(`/api/staff/weekly-plans/${studentId}`, {
        params: { week_start_date: weekStartStr },
      });
      return (res.data?.data ?? null) as WeeklyPlanData | null;
    },
    enabled: !!studentId,
  });

  // Populate form when entering edit mode
  const enterEditMode = useCallback(() => {
    const pc = (plan?.plan_content ?? {}) as Record<string, unknown>;
    const pd = plan?.plan_data && Object.keys(plan.plan_data).length > 0
      ? plan.plan_data
      : ((pc.plan_data ?? {}) as Record<string, string>);

    setForm({
      weekly_goal: plan?.weekly_goal ?? (pc.weekly_goal as string) ?? '',
      shared_goal: plan?.shared_goal ?? (pc.shared_goal as string) ?? '',
      must_do: plan?.must_do ?? (pc.must_do as string) ?? '',
      should_do: plan?.should_do ?? (pc.should_do as string) ?? '',
      want_to_do: plan?.want_to_do ?? (pc.want_to_do as string) ?? '',
      plan_data: {
        day_0: pd.day_0 ?? '',
        day_1: pd.day_1 ?? '',
        day_2: pd.day_2 ?? '',
        day_3: pd.day_3 ?? '',
        day_4: pd.day_4 ?? '',
        day_5: pd.day_5 ?? '',
        day_6: pd.day_6 ?? '',
      },
    });
    setIsEditMode(true);
    setMessage(null);
  }, [plan]);

  const cancelEdit = useCallback(() => {
    setIsEditMode(false);
    setMessage(null);
  }, []);

  // Save mutation
  const saveMutation = useMutation({
    mutationFn: async () => {
      const payload = {
        week_start_date: weekStartStr,
        student_id: studentId,
        weekly_goal: form.weekly_goal || null,
        shared_goal: form.shared_goal || null,
        must_do: form.must_do || null,
        should_do: form.should_do || null,
        want_to_do: form.want_to_do || null,
        plan_data: form.plan_data,
      };

      if (plan?.id) {
        return api.put(`/api/staff/weekly-plans/${plan.id}`, payload);
      } else {
        return api.post('/api/staff/weekly-plans', payload);
      }
    },
    onSuccess: () => {
      setMessage({ type: 'success', text: '週間計画表を保存しました' });
      setIsEditMode(false);
      refetchPlan();
      queryClient.invalidateQueries({ queryKey: ['staff', 'weekly-plans'] });
    },
    onError: () => {
      setMessage({ type: 'error', text: '保存に失敗しました。もう一度お試しください。' });
    },
  });

  // バリデーション: 必須チェック + 類似文字列チェック
  const validationErrors = useMemo(() => {
    const errors: string[] = [];
    if (!form.weekly_goal.trim()) errors.push('「今週の目標」は必須です。');
    if (!form.shared_goal.trim()) errors.push('「いっしょに決めた目標」は必須です。');
    return errors;
  }, [form.weekly_goal, form.shared_goal]);

  const similarityWarnings = useMemo(() => {
    const warnings: string[] = [];
    const fields = [
      { key: 'must_do', label: 'やるべきこと' },
      { key: 'should_do', label: 'やったほうがいいこと' },
      { key: 'want_to_do', label: 'やりたいこと' },
    ] as const;

    // 20文字以上の共通部分文字列があるかチェック
    function hasLongCommon(a: string, b: string, minLen: number): boolean {
      if (!a || !b || a.length < minLen || b.length < minLen) return false;
      for (let i = 0; i <= a.length - minLen; i++) {
        const sub = a.substring(i, i + minLen);
        if (b.includes(sub)) return true;
      }
      return false;
    }

    for (let i = 0; i < fields.length; i++) {
      for (let j = i + 1; j < fields.length; j++) {
        const a = form[fields[i].key].trim();
        const b = form[fields[j].key].trim();
        if (a && b && hasLongCommon(a, b, 20)) {
          warnings.push(`「${fields[i].label}」と「${fields[j].label}」の内容が類似しています。それぞれ異なる内容を記述してください。`);
        }
      }
    }
    return warnings;
  }, [form.must_do, form.should_do, form.want_to_do]);

  const handleSave = useCallback(() => {
    if (validationErrors.length > 0) return;
    saveMutation.mutate();
  }, [saveMutation, validationErrors]);

  // Comment mutation
  const [commentText, setCommentText] = useState('');
  const commentMutation = useMutation({
    mutationFn: async () => {
      if (!plan?.id || !commentText.trim()) return;
      return api.put(`/api/staff/weekly-plans/${plan.id}`, { comment: commentText.trim() });
    },
    onSuccess: () => {
      setCommentText('');
      setMessage({ type: 'success', text: 'コメントを投稿しました' });
      refetchPlan();
    },
    onError: () => {
      setMessage({ type: 'error', text: 'コメントの投稿に失敗しました' });
    },
  });

  // Navigate weeks
  const goToPrevWeek = () => {
    setWeekOffset((o) => o - 1);
    setIsEditMode(false);
    setMessage(null);
  };
  const goToNextWeek = () => {
    setWeekOffset((o) => o + 1);
    setIsEditMode(false);
    setMessage(null);
  };
  const goToCurrentWeek = () => {
    setWeekOffset(0);
    setIsEditMode(false);
    setMessage(null);
  };

  // Compute back link with week offset
  const currentWeekStart = getWeekStart(new Date());
  const diffMs = weekStart.getTime() - currentWeekStart.getTime();
  const backWeekOffset = Math.round(diffMs / (7 * 24 * 60 * 60 * 1000));

  // Resolve plan data for display (独立カラム優先、plan_content にフォールバック)
  const planContent = (plan?.plan_content ?? {}) as Record<string, unknown>;
  const displayGoal = plan?.weekly_goal ?? (planContent.weekly_goal as string) ?? null;
  const displaySharedGoal = plan?.shared_goal ?? (planContent.shared_goal as string) ?? null;
  const displayMustDo = plan?.must_do ?? (planContent.must_do as string) ?? null;
  const displayShouldDo = plan?.should_do ?? (planContent.should_do as string) ?? null;
  const displayWantToDo = plan?.want_to_do ?? (planContent.want_to_do as string) ?? null;
  const displayPlanData = plan?.plan_data && Object.keys(plan.plan_data).length > 0
    ? plan.plan_data
    : ((planContent.plan_data ?? {}) as Record<string, string>);

  const isLoading = loadingStudent || loadingPlan;
  const studentName = student?.student_name ?? '読み込み中...';
  const isCurrentWeek =
    format(getWeekStart(new Date()), 'yyyy-MM-dd') === weekStartStr;

  return (
    <div className="space-y-4">
      {/* Header */}
      <div className="flex flex-wrap items-center justify-between gap-2">
        <div>
          <h1 className="text-2xl font-bold text-[var(--neutral-foreground-1)]">
            {studentName}さんの週間計画表
          </h1>
          <p className="text-sm text-[var(--neutral-foreground-3)]">
            {format(weekStart, 'yyyy年M月d日', { locale: ja })}の週
          </p>
        </div>
        <Link href={`/staff/weekly-plans`}>
          <Button variant="outline" size="sm" leftIcon={<MaterialIcon name="arrow_back" size={16} />}>
            一覧に戻る
          </Button>
        </Link>
      </div>

      {/* Message */}
      {message && (
        <div
          className={`rounded-lg border-l-4 px-4 py-3 text-sm ${
            message.type === 'success'
              ? 'border-green-500 bg-green-50 text-green-800'
              : 'border-red-500 bg-red-50 text-red-800'
          }`}
        >
          {message.text}
        </div>
      )}

      {/* Week Navigation */}
      <Card>
        <CardBody>
          <div className="flex items-center justify-between">
            <Button
              variant="outline"
              size="sm"
              leftIcon={<MaterialIcon name="chevron_left" size={16} />}
              onClick={goToPrevWeek}
            >
              前週
            </Button>

            <div className="text-center">
              <p className="text-xs text-[var(--neutral-foreground-3)]">{yearMonth}</p>
              <p className="text-lg font-bold text-[var(--neutral-foreground-1)]">{weekLabel}</p>
              {isCurrentWeek && <Badge variant="info">今週</Badge>}
            </div>

            <div className="flex items-center gap-2">
              <Button variant="outline" size="sm" onClick={goToNextWeek}>
                次週
                <MaterialIcon name="chevron_right" size={16} className="ml-1" />
              </Button>
            </div>
          </div>

          {!isCurrentWeek && (
            <div className="mt-3 text-center">
              <Button
                variant="ghost"
                size="sm"
                leftIcon={<MaterialIcon name="calendar_month" size={16} />}
                onClick={goToCurrentWeek}
              >
                今週に戻る
              </Button>
            </div>
          )}
        </CardBody>
      </Card>

      {/* Loading */}
      {isLoading ? (
        <div className="space-y-4">
          <Skeleton className="h-32 rounded-lg" />
          <Skeleton className="h-48 rounded-lg" />
        </div>
      ) : !plan && !isEditMode ? (
        /* No plan exists */
        <Card>
          <CardBody>
            <div className="py-16 text-center">
              <MaterialIcon name="calendar_month" size={48} className="mx-auto mb-4 text-[var(--neutral-foreground-disabled)]" />
              <p className="mb-4 text-[var(--neutral-foreground-4)]">
                この週の計画はまだ作成されていません
              </p>
              <Button variant="primary" onClick={enterEditMode} leftIcon={<MaterialIcon name="edit" size={16} />}>
                計画を作成する
              </Button>
            </div>
          </CardBody>
        </Card>
      ) : isEditMode ? (
        /* ============================================================
           Edit Mode
           ============================================================ */
        <Card>
          <CardBody>
            <div className="mb-6 flex flex-wrap items-center justify-between gap-2">
              <h2 className="flex items-center gap-2 text-lg font-bold text-[var(--neutral-foreground-1)]">
                <MaterialIcon name="edit" size={20} />
                週間計画を編集
              </h2>
              <div className="flex gap-2">
                <Button variant="outline" size="sm" onClick={cancelEdit} leftIcon={<MaterialIcon name="close" size={16} />}>
                  キャンセル
                </Button>
                <Button
                  variant="primary"
                  size="sm"
                  onClick={handleSave}
                  leftIcon={<MaterialIcon name="save" size={16} />}
                  disabled={saveMutation.isPending || validationErrors.length > 0}
                >
                  {saveMutation.isPending ? '保存中...' : '保存する'}
                </Button>
              </div>
            </div>

            {/* 今週の目標 */}
            <div className="mb-5">
              <label className="mb-2 flex items-center gap-2 text-sm font-semibold text-[var(--brand-60)]">
                <MaterialIcon name="target" size={16} className="h-4 w-4" />
                今週の目標 <span className="text-red-500">*</span>
              </label>
              <textarea
                className={`w-full min-h-[60px] rounded-lg border p-3 text-sm focus:ring-1 resize-y ${!form.weekly_goal.trim() ? 'border-red-300 focus:border-red-500 focus:ring-red-500' : 'border-[var(--neutral-stroke-1)] focus:border-[var(--brand-90)] focus:ring-[var(--brand-80)]'}`}
                placeholder="今週達成したい目標を記入してください"
                value={form.weekly_goal}
                onChange={(e) => setForm((f) => ({ ...f, weekly_goal: e.target.value }))}
              />
              {!form.weekly_goal.trim() && <p className="mt-1 text-xs text-red-500">必須項目です</p>}
            </div>

            {/* いっしょに決めた目標 */}
            <div className="mb-5">
              <label className="mb-2 flex items-center gap-2 text-sm font-semibold text-[var(--brand-60)]">
                <MaterialIcon name="handshake" size={16} />
                いっしょに決めた目標 <span className="text-red-500">*</span>
              </label>
              <textarea
                className={`w-full min-h-[60px] rounded-lg border p-3 text-sm focus:ring-1 resize-y ${!form.shared_goal.trim() ? 'border-red-300 focus:border-red-500 focus:ring-red-500' : 'border-[var(--neutral-stroke-1)] focus:border-[var(--brand-90)] focus:ring-[var(--brand-80)]'}`}
                placeholder="生徒と一緒に決めた目標を記入してください"
                value={form.shared_goal}
                onChange={(e) => setForm((f) => ({ ...f, shared_goal: e.target.value }))}
              />
              {!form.shared_goal.trim() && <p className="mt-1 text-xs text-red-500">必須項目です</p>}
            </div>

            {/* やるべきこと */}
            <div className="mb-5">
              <label className="mb-2 flex items-center gap-2 text-sm font-semibold text-[var(--brand-60)]">
                <MaterialIcon name="check_circle" size={16} />
                やるべきこと
              </label>
              <textarea
                className="w-full min-h-[60px] rounded-lg border border-[var(--neutral-stroke-1)] p-3 text-sm focus:border-[var(--brand-90)] focus:ring-1 focus:ring-[var(--brand-80)] resize-y"
                placeholder="必ずやるべきことを記入してください"
                value={form.must_do}
                onChange={(e) => setForm((f) => ({ ...f, must_do: e.target.value }))}
              />
            </div>

            {/* やったほうがいいこと */}
            <div className="mb-5">
              <label className="mb-2 flex items-center gap-2 text-sm font-semibold text-[var(--brand-60)]">
                <MaterialIcon name="thumb_up" size={16} className="h-4 w-4" />
                やったほうがいいこと
              </label>
              <textarea
                className="w-full min-h-[60px] rounded-lg border border-[var(--neutral-stroke-1)] p-3 text-sm focus:border-[var(--brand-90)] focus:ring-1 focus:ring-[var(--brand-80)] resize-y"
                placeholder="できればやったほうがいいことを記入してください"
                value={form.should_do}
                onChange={(e) => setForm((f) => ({ ...f, should_do: e.target.value }))}
              />
            </div>

            {/* やりたいこと */}
            <div className="mb-5">
              <label className="mb-2 flex items-center gap-2 text-sm font-semibold text-[var(--brand-60)]">
                <MaterialIcon name="lightbulb" size={16} className="h-4 w-4" />
                やりたいこと
              </label>
              <textarea
                className="w-full min-h-[60px] rounded-lg border border-[var(--neutral-stroke-1)] p-3 text-sm focus:border-[var(--brand-90)] focus:ring-1 focus:ring-[var(--brand-80)] resize-y"
                placeholder="本人がやりたいと思っていることを記入してください"
                value={form.want_to_do}
                onChange={(e) => setForm((f) => ({ ...f, want_to_do: e.target.value }))}
              />
            </div>

            {/* 各曜日の計画 */}
            <div className="mt-6">
              <h3 className="mb-3 flex items-center gap-2 text-base font-bold text-[var(--neutral-foreground-1)]">
                <MaterialIcon name="calendar_month" size={20} />
                各曜日の計画・目標
              </h3>
              {DAYS.map((day, index) => {
                const dayKey = `day_${index}`;
                const dateStr = format(addDays(weekStart, index), 'MM/dd');
                return (
                  <div
                    key={dayKey}
                    className="mb-3 grid grid-cols-1 gap-2 sm:grid-cols-[120px_1fr] sm:items-start"
                  >
                    <div className="sm:pt-3">
                      <div className="font-semibold text-[var(--brand-60)]">{day}</div>
                      <div className="text-xs text-[var(--neutral-foreground-4)]">{dateStr}</div>
                    </div>
                    <textarea
                      className="w-full min-h-[50px] rounded-lg border border-[var(--neutral-stroke-1)] p-3 text-sm focus:border-[var(--brand-90)] focus:ring-1 focus:ring-[var(--brand-80)] resize-y"
                      placeholder="この日の計画や目標を記入してください"
                      rows={2}
                      value={form.plan_data[dayKey] ?? ''}
                      onChange={(e) =>
                        setForm((f) => ({
                          ...f,
                          plan_data: { ...f.plan_data, [dayKey]: e.target.value },
                        }))
                      }
                    />
                  </div>
                );
              })}
            </div>

            {/* Validation errors & warnings */}
            {(validationErrors.length > 0 || similarityWarnings.length > 0) && (
              <div className="mt-4 space-y-2">
                {validationErrors.map((e, i) => (
                  <div key={`e-${i}`} className="rounded-lg border border-red-200 bg-red-50 px-4 py-2 text-sm text-red-700">{e}</div>
                ))}
                {similarityWarnings.map((w, i) => (
                  <div key={`w-${i}`} className="rounded-lg border border-amber-200 bg-amber-50 px-4 py-2 text-sm text-amber-700">{w}</div>
                ))}
              </div>
            )}

            {/* Bottom save button */}
            <div className="mt-6 flex justify-end gap-2">
              <Button variant="outline" onClick={cancelEdit} leftIcon={<MaterialIcon name="close" size={16} />}>
                キャンセル
              </Button>
              <Button
                variant="primary"
                onClick={handleSave}
                leftIcon={<MaterialIcon name="save" size={16} />}
                disabled={saveMutation.isPending || validationErrors.length > 0}
              >
                {saveMutation.isPending ? '保存中...' : '保存する'}
              </Button>
            </div>
          </CardBody>
        </Card>
      ) : (
        /* ============================================================
           View Mode
           ============================================================ */
        <>
          <Card>
            <CardBody>
              <div className="mb-6 flex flex-wrap items-center justify-between gap-2">
                <h2 className="flex items-center gap-2 text-lg font-bold text-[var(--neutral-foreground-1)]">
                  <MaterialIcon name="edit" size={20} />
                  週間計画
                </h2>
                <Button
                  variant="primary"
                  size="sm"
                  onClick={enterEditMode}
                  leftIcon={<MaterialIcon name="edit" size={16} />}
                >
                  編集する
                </Button>
              </div>

              {/* Goals */}
              <ViewSection
                icon={<MaterialIcon name="target" size={16} className="h-4 w-4" />}
                label="今週の目標"
                content={displayGoal}
              />
              <ViewSection
                icon={<MaterialIcon name="handshake" size={16} />}
                label="いっしょに決めた目標"
                content={displaySharedGoal}
              />
              <ViewSection
                icon={<MaterialIcon name="check_circle" size={16} />}
                label="やるべきこと"
                content={displayMustDo}
              />
              <ViewSection
                icon={<MaterialIcon name="thumb_up" size={16} className="h-4 w-4" />}
                label="やったほうがいいこと"
                content={displayShouldDo}
              />
              <ViewSection
                icon={<MaterialIcon name="lightbulb" size={16} className="h-4 w-4" />}
                label="やりたいこと"
                content={displayWantToDo}
              />

              {/* Daily Plans */}
              <div className="mt-6">
                <h3 className="mb-3 flex items-center gap-2 text-base font-bold text-[var(--neutral-foreground-1)]">
                  <MaterialIcon name="calendar_month" size={20} />
                  各曜日の計画・目標
                </h3>
                {DAYS.map((day, index) => {
                  const dayKey = `day_${index}`;
                  const dateStr = format(addDays(weekStart, index), 'MM/dd');
                  const content = displayPlanData[dayKey] ?? '';

                  return (
                    <div
                      key={dayKey}
                      className="mb-3 grid grid-cols-1 gap-2 sm:grid-cols-[120px_1fr] sm:items-start"
                    >
                      <div className="sm:pt-3">
                        <div className="font-semibold text-[var(--brand-60)]">{day}</div>
                        <div className="text-xs text-[var(--neutral-foreground-4)]">{dateStr}</div>
                      </div>
                      <div
                        className={`rounded-md border-l-4 border-[var(--brand-90)] bg-[var(--neutral-background-3)] p-3 text-sm leading-relaxed whitespace-pre-wrap ${
                          !content?.trim()
                            ? 'italic text-[var(--neutral-foreground-4)]'
                            : 'text-[var(--neutral-foreground-1)]'
                        }`}
                      >
                        {content?.trim() ? nl(content) : '予定なし'}
                      </div>
                    </div>
                  );
                })}
              </div>

              {/* Submissions */}
              {plan?.submissions && plan.submissions.length > 0 && (
                <div className="mt-8 border-t-2 border-[var(--neutral-stroke-2)] pt-6">
                  <h3 className="mb-3 flex items-center gap-2 text-base font-bold text-red-600">
                    <MaterialIcon name="check_circle" size={20} />
                    提出物一覧
                  </h3>
                  {plan.submissions.map((sub) => {
                    const dueDate = new Date(sub.due_date);
                    const today = new Date();
                    const diffDays = Math.round(
                      (dueDate.getTime() - today.getTime()) / (24 * 60 * 60 * 1000)
                    );
                    let dateClass = '';
                    let dateNote = '';
                    if (!sub.is_completed) {
                      if (diffDays < 0) {
                        dateClass = 'text-red-800 font-bold';
                        dateNote = `（${Math.abs(diffDays)}日超過）`;
                      } else if (diffDays === 0) {
                        dateClass = 'text-red-600 font-semibold';
                        dateNote = '（今日が期限）';
                      } else if (diffDays <= 3) {
                        dateClass = 'text-red-600 font-semibold';
                        dateNote = `（あと${diffDays}日）`;
                      }
                    }

                    return (
                      <div
                        key={sub.id}
                        className={`mb-3 flex items-center justify-between rounded-md border-l-4 bg-[var(--neutral-background-3)] p-3 ${
                          sub.is_completed
                            ? 'border-green-500 opacity-60 line-through'
                            : 'border-red-500'
                        }`}
                      >
                        <div>
                          <div className="font-semibold text-[var(--neutral-foreground-1)]">
                            {sub.is_completed && (
                              <MaterialIcon name="check_circle" size={16} className="mr-1 inline-block text-green-600" />
                            )}
                            {sub.submission_item}
                          </div>
                          <div className={`text-xs ${dateClass || 'text-[var(--neutral-foreground-4)]'}`}>
                            期限: {format(dueDate, 'yyyy年M月d日', { locale: ja })}
                            {dateNote}
                          </div>
                        </div>
                      </div>
                    );
                  })}
                </div>
              )}
            </CardBody>
          </Card>

          {/* Achievement Display (if evaluated) */}
          {plan?.evaluated_at && (
            <Card>
              <CardBody>
                <div className="mb-4 flex flex-wrap items-center justify-between gap-2">
                  <h3 className="text-lg font-bold text-[var(--brand-80)]">達成度評価</h3>
                  <span className="text-xs text-[var(--neutral-foreground-4)]">
                    評価日: {format(new Date(plan.evaluated_at), 'yyyy年M月d日', { locale: ja })}
                  </span>
                </div>
                <div className="rounded-lg bg-[var(--neutral-background-3)] p-4">
                  <AchievementDisplay
                    icon={<MaterialIcon name="target" size={16} className="h-4 w-4" />}
                    label="今週の目標"
                    goalText={displayGoal}
                    achievement={plan.weekly_goal_achievement}
                    comment={plan.weekly_goal_comment}
                  />
                  <AchievementDisplay
                    icon={<MaterialIcon name="handshake" size={16} />}
                    label="いっしょに決めた目標"
                    goalText={displaySharedGoal}
                    achievement={plan.shared_goal_achievement}
                    comment={plan.shared_goal_comment}
                  />
                  <AchievementDisplay
                    icon={<MaterialIcon name="check_circle" size={16} />}
                    label="やるべきこと"
                    goalText={displayMustDo}
                    achievement={plan.must_do_achievement}
                    comment={plan.must_do_comment}
                  />
                  <AchievementDisplay
                    icon={<MaterialIcon name="thumb_up" size={16} className="h-4 w-4" />}
                    label="やったほうがいいこと"
                    goalText={displayShouldDo}
                    achievement={plan.should_do_achievement}
                    comment={plan.should_do_comment}
                  />
                  <AchievementDisplay
                    icon={<MaterialIcon name="lightbulb" size={16} className="h-4 w-4" />}
                    label="やりたいこと"
                    goalText={displayWantToDo}
                    achievement={plan.want_to_do_achievement}
                    comment={plan.want_to_do_comment}
                  />

                  {/* Daily achievements */}
                  {plan.daily_achievement && Object.keys(plan.daily_achievement).length > 0 && (
                    <div className="mt-4">
                      <h4 className="mb-2 flex items-center gap-2 text-sm font-bold text-[var(--neutral-foreground-1)]">
                        <MaterialIcon name="calendar_month" size={16} />
                        各曜日の達成度
                      </h4>
                      {Object.entries(plan.daily_achievement).map(([key, data]) => {
                        if (!data || data.achievement <= 0) return null;
                        const a = data.achievement;
                        const colorClass = ACHIEVEMENT_COLORS[a] || 'bg-gray-400';
                        const label = ACHIEVEMENT_LABELS[a] || '未評価';
                        return (
                          <div key={key} className="mb-2 rounded bg-white p-3">
                            <span className="mr-2 font-semibold text-[var(--brand-60)]">{key}</span>
                            <span
                              className={`inline-block rounded px-2 py-0.5 text-xs font-bold text-white ${colorClass}`}
                            >
                              {label}
                            </span>
                            {data.comment && (
                              <p className="mt-1 text-xs text-[var(--neutral-foreground-3)]">{data.comment}</p>
                            )}
                          </div>
                        );
                      })}
                    </div>
                  )}

                  {/* Overall comment */}
                  {plan.overall_comment?.trim() && (
                    <div className="mt-4 rounded-lg bg-white p-4">
                      <h4 className="mb-2 text-sm font-bold text-[var(--brand-60)]">
                        週全体の総合コメント
                      </h4>
                      <p className="text-sm leading-relaxed whitespace-pre-wrap text-[var(--neutral-foreground-1)]">
                        {nl(plan.overall_comment)}
                      </p>
                    </div>
                  )}
                </div>
              </CardBody>
            </Card>
          )}

          {/* Comments Section */}
          <Card>
            <CardBody>
              <h3 className="mb-4 text-lg font-bold text-[var(--neutral-foreground-1)]">
                <MaterialIcon name="forum" size={20} className="mr-2 inline-block" />
                コメント
              </h3>

              {plan?.comments && plan.comments.length > 0 ? (
                <div className="space-y-3 mb-4">
                  {plan.comments.map((c) => (
                    <div
                      key={c.id}
                      className="rounded-lg border-l-4 border-green-500 bg-[var(--neutral-background-3)] p-3"
                    >
                      <div className="mb-1 flex items-center justify-between">
                        <span className="text-sm font-semibold text-[var(--brand-60)]">
                          {c.user?.full_name ?? '不明'}
                        </span>
                        <span className="text-xs text-[var(--neutral-foreground-4)]">
                          {c.created_at &&
                            format(new Date(c.created_at), 'M/d HH:mm', { locale: ja })}
                        </span>
                      </div>
                      <p className="text-sm leading-relaxed text-[var(--neutral-foreground-1)]">
                        {c.comment}
                      </p>
                    </div>
                  ))}
                </div>
              ) : (
                <p className="mb-4 text-sm text-[var(--neutral-foreground-4)]">
                  コメントはまだありません
                </p>
              )}

              {/* Comment form */}
              {plan?.id && (
                <div className="border-t border-[var(--neutral-stroke-2)] pt-4">
                  <textarea
                    className="w-full min-h-[80px] rounded-lg border border-[var(--neutral-stroke-1)] p-3 text-sm focus:border-[var(--brand-90)] focus:ring-1 focus:ring-[var(--brand-80)] resize-y"
                    placeholder="コメントを入力..."
                    value={commentText}
                    onChange={(e) => setCommentText(e.target.value)}
                  />
                  <div className="mt-2 flex justify-end">
                    <Button
                      variant="primary"
                      size="sm"
                      onClick={() => commentMutation.mutate()}
                      disabled={!commentText.trim() || commentMutation.isPending}
                    >
                      {commentMutation.isPending ? '送信中...' : 'コメントを投稿'}
                    </Button>
                  </div>
                </div>
              )}
            </CardBody>
          </Card>
        </>
      )}
    </div>
  );
}
