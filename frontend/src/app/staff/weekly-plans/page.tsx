'use client';

import { useState, useMemo, useCallback } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import api from '@/lib/api';
import { Card, CardHeader, CardTitle, CardBody } from '@/components/ui/Card';
import { Button } from '@/components/ui/Button';
import { Modal } from '@/components/ui/Modal';
import { Input } from '@/components/ui/Input';
import { Badge } from '@/components/ui/Badge';
import { SkeletonList } from '@/components/ui/Skeleton';
import { useToast } from '@/components/ui/Toast';
import { formatDate, formatDateTime } from '@/lib/utils';
import {
  ChevronLeft,
  ChevronRight,
  Calendar,
  User,
  CheckCircle,
  Target,
  Handshake,
  ThumbsUp,
  Lightbulb,
  FileText,
  MessageSquare,
  Plus,
  Trash2,
  Download,
  Pencil,
  X,
  Star,
  Send,
  ClipboardList,
} from 'lucide-react';
import { format, addDays, addWeeks, startOfWeek, parseISO, differenceInDays } from 'date-fns';
import { ja } from 'date-fns/locale';

// ---------------------------------------------------------------------------
// Types
// ---------------------------------------------------------------------------

interface Student {
  id: number;
  student_name: string;
}

interface WeeklyPlanSummary {
  id: number;
  student_id: number;
  week_start_date: string;
  weekly_goal: string | null;
  plan_data: Record<string, string> | null;
  updated_at: string;
  evaluated_at: string | null;
}

interface Submission {
  id?: number;
  submission_item: string;
  due_date: string;
  is_completed: boolean;
  completed_at: string | null;
}

interface Comment {
  id: number;
  commenter_type: 'staff' | 'guardian' | 'student';
  commenter_name: string;
  comment: string;
  created_at: string;
}

interface DomainGoal {
  sub_category: string;
  support_goal: string;
}

interface DailyAchievementEntry {
  achievement: number;
  comment: string;
}

interface WeeklyPlanDetail {
  id: number;
  student_id: number;
  week_start_date: string;
  weekly_goal: string;
  shared_goal: string;
  must_do: string;
  should_do: string;
  want_to_do: string;
  plan_data: Record<string, string>;
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
  daily_achievement: Record<string, DailyAchievementEntry> | null;
  overall_comment: string | null;
  evaluated_at: string | null;
  created_at: string;
  updated_at: string;
  submissions: Submission[];
  comments: Comment[];
  domain_goals: DomainGoal[];
  student: Student;
  prev_week_plan: WeeklyPlanDetail | null;
}

interface StudentWithPlan {
  student: Student;
  plan: WeeklyPlanSummary | null;
}

interface PlanForm {
  weekly_goal: string;
  shared_goal: string;
  must_do: string;
  should_do: string;
  want_to_do: string;
  plan_data: Record<string, string>;
  submissions: Submission[];
}

interface EvalForm {
  weekly_goal_achievement: number;
  weekly_goal_comment: string;
  shared_goal_achievement: number;
  shared_goal_comment: string;
  must_do_achievement: number;
  must_do_comment: string;
  should_do_achievement: number;
  should_do_comment: string;
  want_to_do_achievement: number;
  want_to_do_comment: string;
  daily_achievement: Record<string, { achievement: number; comment: string }>;
  overall_comment: string;
}

// ---------------------------------------------------------------------------
// Constants
// ---------------------------------------------------------------------------

const DAY_LABELS = ['月曜日', '火曜日', '水曜日', '木曜日', '金曜日', '土曜日', '日曜日'];
const DAY_KEYS = ['day_0', 'day_1', 'day_2', 'day_3', 'day_4', 'day_5', 'day_6'];

const ACHIEVEMENT_LABELS: Record<number, string> = {
  0: '未評価',
  1: '1 - できなかった',
  2: '2',
  3: '3',
  4: '4',
  5: '5 - よくできた',
};

const ACHIEVEMENT_COLORS: Record<number, string> = {
  0: '#999',
  1: '#e74c3c',
  2: '#f39c12',
  3: '#3498db',
  4: '#2ecc71',
  5: '#27ae60',
};

const emptyForm: PlanForm = {
  weekly_goal: '',
  shared_goal: '',
  must_do: '',
  should_do: '',
  want_to_do: '',
  plan_data: {},
  submissions: [],
};

const emptyEvalForm: EvalForm = {
  weekly_goal_achievement: 3,
  weekly_goal_comment: '',
  shared_goal_achievement: 3,
  shared_goal_comment: '',
  must_do_achievement: 3,
  must_do_comment: '',
  should_do_achievement: 3,
  should_do_comment: '',
  want_to_do_achievement: 3,
  want_to_do_comment: '',
  daily_achievement: {},
  overall_comment: '',
};

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function getWeekStart(date: Date): Date {
  return startOfWeek(date, { weekStartsOn: 1 });
}

function formatWeekDate(dateStr: string, dayIndex: number): string {
  const base = parseISO(dateStr);
  const d = addDays(base, dayIndex);
  return format(d, 'M/d', { locale: ja });
}

// ---------------------------------------------------------------------------
// Sub-components
// ---------------------------------------------------------------------------

function AchievementScale({
  value,
  onChange,
}: {
  value: number;
  onChange: (v: number) => void;
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
              ? 'border-[var(--brand-80)] bg-[var(--brand-80)] text-white'
              : 'border-[var(--neutral-stroke-2)] bg-white text-[var(--neutral-foreground-4)] hover:border-[var(--brand-80)]'
          }`}
        >
          {n}
        </button>
      ))}
      <span className="ml-2 text-xs text-[var(--neutral-foreground-4)]">
        1=できなかった ... 5=よくできた
      </span>
    </div>
  );
}

function AchievementBadge({ value }: { value: number }) {
  const color = ACHIEVEMENT_COLORS[value] || ACHIEVEMENT_COLORS[0];
  const label = ACHIEVEMENT_LABELS[value] || ACHIEVEMENT_LABELS[0];
  return (
    <span
      className="inline-block rounded px-3 py-1 text-xs font-semibold text-white"
      style={{ backgroundColor: color }}
    >
      {label}
    </span>
  );
}

function GoalSection({
  icon,
  label,
  content,
}: {
  icon: React.ReactNode;
  label: string;
  content: string | null;
}) {
  return (
    <div className="mb-4">
      <h4 className="mb-2 flex items-center gap-2 text-sm font-semibold text-[var(--brand-80)]">
        {icon} {label}
      </h4>
      <div
        className={`whitespace-pre-wrap rounded border-l-4 border-[var(--brand-80)] bg-[var(--neutral-background-3)] p-3 text-sm leading-relaxed ${
          !content ? 'italic text-[var(--neutral-foreground-4)]' : 'text-[var(--neutral-foreground-1)]'
        }`}
      >
        {content || '未記入'}
      </div>
    </div>
  );
}

// ---------------------------------------------------------------------------
// Main Component
// ---------------------------------------------------------------------------

export default function WeeklyPlansPage() {
  const queryClient = useQueryClient();
  const toast = useToast();

  // Week navigation
  const [weekOffset, setWeekOffset] = useState(0);
  const weekStart = useMemo(() => {
    const today = new Date();
    const thisWeekStart = getWeekStart(today);
    return addWeeks(thisWeekStart, weekOffset);
  }, [weekOffset]);
  const weekStartStr = format(weekStart, 'yyyy-MM-dd');
  const weekEnd = addDays(weekStart, 6);

  // Detail view state
  const [selectedStudentId, setSelectedStudentId] = useState<number | null>(null);
  const [isEditing, setIsEditing] = useState(false);
  const [form, setForm] = useState<PlanForm>(emptyForm);
  const [newComment, setNewComment] = useState('');

  // Evaluation modal
  const [showEvalModal, setShowEvalModal] = useState(false);
  const [evalTarget, setEvalTarget] = useState<'current' | 'prev'>('current');
  const [evalForm, setEvalForm] = useState<EvalForm>(emptyEvalForm);

  // -----------------------------------------------------------------------
  // Queries
  // -----------------------------------------------------------------------

  // Student list with plan status for the selected week
  const { data: studentsWithPlans, isLoading: isLoadingList } = useQuery({
    queryKey: ['staff', 'weekly-plans', 'list', weekStartStr],
    queryFn: async () => {
      const res = await api.get<{ data: StudentWithPlan[] }>(
        `/api/staff/weekly-plans?week_start_date=${weekStartStr}`
      );
      return res.data.data;
    },
  });

  // Detail for selected student
  const { data: planDetail, isLoading: isLoadingDetail } = useQuery({
    queryKey: ['staff', 'weekly-plans', 'detail', selectedStudentId, weekStartStr],
    queryFn: async () => {
      const res = await api.get<{ data: WeeklyPlanDetail }>(
        `/api/staff/weekly-plans/${selectedStudentId}?week_start_date=${weekStartStr}`
      );
      return res.data.data;
    },
    enabled: !!selectedStudentId,
  });

  // -----------------------------------------------------------------------
  // Mutations
  // -----------------------------------------------------------------------

  const savePlanMutation = useMutation({
    mutationFn: async () => {
      const payload = {
        student_id: selectedStudentId,
        week_start_date: weekStartStr,
        ...form,
      };
      if (planDetail?.id) {
        await api.put(`/api/staff/weekly-plans/${planDetail.id}`, payload);
      } else {
        await api.post('/api/staff/weekly-plans', payload);
      }
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['staff', 'weekly-plans'] });
      setIsEditing(false);
      toast.success('週間計画を保存しました');
    },
    onError: () => toast.error('保存に失敗しました'),
  });

  const saveEvalMutation = useMutation({
    mutationFn: async (planId: number) => {
      await api.post(`/api/staff/weekly-plans/${planId}/evaluate`, evalForm);
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['staff', 'weekly-plans'] });
      setShowEvalModal(false);
      toast.success('達成度評価を保存しました');
    },
    onError: () => toast.error('保存に失敗しました'),
  });

  const addCommentMutation = useMutation({
    mutationFn: async () => {
      await api.post(`/api/staff/weekly-plans/${planDetail!.id}/comments`, {
        comment: newComment,
      });
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['staff', 'weekly-plans', 'detail'] });
      setNewComment('');
      toast.success('コメントを投稿しました');
    },
    onError: () => toast.error('投稿に失敗しました'),
  });

  // -----------------------------------------------------------------------
  // Handlers
  // -----------------------------------------------------------------------

  const openDetail = useCallback((studentId: number) => {
    setSelectedStudentId(studentId);
    setIsEditing(false);
  }, []);

  const closeDetail = useCallback(() => {
    setSelectedStudentId(null);
    setIsEditing(false);
  }, []);

  const startEditing = useCallback(() => {
    if (planDetail) {
      setForm({
        weekly_goal: planDetail.weekly_goal || '',
        shared_goal: planDetail.shared_goal || '',
        must_do: planDetail.must_do || '',
        should_do: planDetail.should_do || '',
        want_to_do: planDetail.want_to_do || '',
        plan_data: planDetail.plan_data || {},
        submissions: planDetail.submissions || [],
      });
    } else {
      setForm({ ...emptyForm });
    }
    setIsEditing(true);
  }, [planDetail]);

  const openEvaluation = useCallback(
    (target: 'current' | 'prev') => {
      const plan = target === 'current' ? planDetail : planDetail?.prev_week_plan;
      if (!plan) return;
      const dailyAch: Record<string, { achievement: number; comment: string }> = {};
      DAY_KEYS.forEach((key) => {
        if (plan.plan_data?.[key]) {
          dailyAch[key] = { achievement: 3, comment: '' };
        }
      });
      setEvalForm({
        ...emptyEvalForm,
        daily_achievement: dailyAch,
      });
      setEvalTarget(target);
      setShowEvalModal(true);
    },
    [planDetail]
  );

  const updateFormField = useCallback(
    (field: keyof PlanForm, value: string) => {
      setForm((prev) => ({ ...prev, [field]: value }));
    },
    []
  );

  const updateDayPlan = useCallback((dayKey: string, value: string) => {
    setForm((prev) => ({
      ...prev,
      plan_data: { ...prev.plan_data, [dayKey]: value },
    }));
  }, []);

  const addSubmission = useCallback(() => {
    setForm((prev) => ({
      ...prev,
      submissions: [
        ...prev.submissions,
        { submission_item: '', due_date: '', is_completed: false, completed_at: null },
      ],
    }));
  }, []);

  const updateSubmission = useCallback(
    (index: number, field: keyof Submission, value: string | boolean) => {
      setForm((prev) => {
        const subs = [...prev.submissions];
        subs[index] = { ...subs[index], [field]: value };
        return { ...prev, submissions: subs };
      });
    },
    []
  );

  const removeSubmission = useCallback((index: number) => {
    setForm((prev) => ({
      ...prev,
      submissions: prev.submissions.filter((_, i) => i !== index),
    }));
  }, []);

  // -----------------------------------------------------------------------
  // Render: Week Navigation
  // -----------------------------------------------------------------------

  const isCurrentWeek = weekOffset === 0;
  const weekLabel = isCurrentWeek
    ? '今週'
    : weekOffset < 0
      ? `${Math.abs(weekOffset)}週前`
      : `${weekOffset}週後`;

  const renderWeekNav = () => (
    <Card className="mb-6">
      <CardBody>
        <div className="flex items-center justify-between">
          <Button
            variant="ghost"
            size="sm"
            leftIcon={<ChevronLeft className="h-4 w-4" />}
            onClick={() => setWeekOffset((p) => p - 1)}
          >
            前の週
          </Button>
          <div className="text-center">
            <h2 className="text-lg font-bold text-[var(--brand-80)]">
              {format(weekStart, 'yyyy年M月', { locale: ja })}
            </h2>
            <p className="text-sm text-[var(--neutral-foreground-3)]">
              {format(weekStart, 'M/d', { locale: ja })}（月）〜{' '}
              {format(weekEnd, 'M/d', { locale: ja })}（日）
            </p>
            <Badge variant={isCurrentWeek ? 'primary' : 'default'} className="mt-1">
              {weekLabel}
            </Badge>
          </div>
          <Button
            variant="ghost"
            size="sm"
            onClick={() => setWeekOffset((p) => p + 1)}
          >
            次の週
            <ChevronRight className="ml-1 h-4 w-4" />
          </Button>
        </div>
        {!isCurrentWeek && (
          <div className="mt-3 text-center">
            <Button
              variant="outline"
              size="sm"
              leftIcon={<Calendar className="h-4 w-4" />}
              onClick={() => setWeekOffset(0)}
            >
              今週に戻る
            </Button>
          </div>
        )}
      </CardBody>
    </Card>
  );

  // -----------------------------------------------------------------------
  // Render: Student List
  // -----------------------------------------------------------------------

  const renderStudentList = () => {
    if (isLoadingList) return <SkeletonList items={6} />;

    if (!studentsWithPlans || studentsWithPlans.length === 0) {
      return (
        <Card>
          <CardBody>
            <div className="py-12 text-center text-[var(--neutral-foreground-4)]">
              <User className="mx-auto mb-4 h-16 w-16 opacity-30" />
              <p>生徒が登録されていません</p>
            </div>
          </CardBody>
        </Card>
      );
    }

    return (
      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
        {studentsWithPlans.map(({ student, plan }) => (
          <Card
            key={student.id}
            className="cursor-pointer transition-all hover:-translate-y-1 hover:shadow-lg"
            onClick={() => openDetail(student.id)}
          >
            <CardBody>
              <div className="mb-3 flex items-center gap-3 border-b border-[var(--neutral-stroke-2)] pb-3">
                <div className="flex h-10 w-10 items-center justify-center rounded-full bg-[var(--brand-80)] text-white">
                  <User className="h-5 w-5" />
                </div>
                <span className="text-base font-semibold text-[var(--neutral-foreground-1)]">
                  {student.student_name}
                </span>
              </div>
              <div>
                {plan ? (
                  <>
                    <Badge variant="success" dot>
                      計画あり
                    </Badge>
                    {plan.evaluated_at && (
                      <Badge variant="info" dot className="ml-2">
                        評価済み
                      </Badge>
                    )}
                    <p className="mt-2 text-xs text-[var(--neutral-foreground-4)]">
                      最終更新: {formatDateTime(plan.updated_at)}
                    </p>
                  </>
                ) : (
                  <>
                    <Badge variant="danger" dot>
                      計画なし
                    </Badge>
                    <p className="mt-2 text-xs text-[var(--neutral-foreground-4)]">
                      この週の計画はまだ作成されていません
                    </p>
                  </>
                )}
              </div>
            </CardBody>
          </Card>
        ))}
      </div>
    );
  };

  // -----------------------------------------------------------------------
  // Render: Detail View (view mode)
  // -----------------------------------------------------------------------

  const renderDetailView = () => {
    if (!planDetail) return null;

    const plan = planDetail;
    const hasPlan = !!plan.id;

    return (
      <div className="space-y-6">
        {/* Header */}
        <div className="flex items-center justify-between">
          <h2 className="text-xl font-bold text-[var(--neutral-foreground-1)]">
            {plan.student.student_name}さんの週間計画表
          </h2>
          <div className="flex gap-2">
            {hasPlan && (
              <Button
                variant="outline"
                size="sm"
                leftIcon={<Download className="h-4 w-4" />}
                onClick={() => {
                  window.open(
                    `${api.defaults.baseURL}/api/staff/weekly-plans/${plan.id}/pdf`,
                    '_blank'
                  );
                }}
              >
                PDF出力
              </Button>
            )}
            <Button
              variant="primary"
              size="sm"
              leftIcon={<Pencil className="h-4 w-4" />}
              onClick={startEditing}
            >
              {hasPlan ? '編集する' : '計画を作成する'}
            </Button>
          </div>
        </div>

        {/* Previous week evaluation prompt */}
        {plan.prev_week_plan && !plan.prev_week_plan.evaluated_at && (
          <Card className="border-2 border-[var(--status-warning-fg)]">
            <CardBody>
              <div className="flex items-center justify-between">
                <div className="flex items-center gap-2">
                  <Star className="h-5 w-5 text-[var(--status-warning-fg)]" />
                  <span className="font-semibold text-[var(--neutral-foreground-1)]">
                    前週の達成度が未入力です
                  </span>
                </div>
                <Button
                  variant="primary"
                  size="sm"
                  onClick={() => openEvaluation('prev')}
                >
                  前週の達成度を入力
                </Button>
              </div>
            </CardBody>
          </Card>
        )}

        {!hasPlan ? (
          <Card>
            <CardBody>
              <div className="py-12 text-center text-[var(--neutral-foreground-4)]">
                <FileText className="mx-auto mb-4 h-16 w-16 opacity-30" />
                <p className="mb-4">この週の計画はまだ作成されていません</p>
                <Button variant="primary" onClick={startEditing}>
                  計画を作成する
                </Button>
              </div>
            </CardBody>
          </Card>
        ) : (
          <>
            {/* Goals display */}
            <Card>
              <CardHeader>
                <CardTitle>週間計画</CardTitle>
              </CardHeader>
              <CardBody>
                <GoalSection
                  icon={<Target className="h-4 w-4" />}
                  label="今週の目標"
                  content={plan.weekly_goal}
                />
                <GoalSection
                  icon={<Handshake className="h-4 w-4" />}
                  label="いっしょに決めた目標"
                  content={plan.shared_goal}
                />
                <GoalSection
                  icon={<CheckCircle className="h-4 w-4" />}
                  label="やるべきこと"
                  content={plan.must_do}
                />
                <GoalSection
                  icon={<ThumbsUp className="h-4 w-4" />}
                  label="やったほうがいいこと"
                  content={plan.should_do}
                />
                <GoalSection
                  icon={<Lightbulb className="h-4 w-4" />}
                  label="やりたいこと"
                  content={plan.want_to_do}
                />

                {/* Daily plans */}
                <div className="mt-6">
                  <h4 className="mb-3 flex items-center gap-2 text-sm font-semibold text-[var(--neutral-foreground-1)]">
                    <Calendar className="h-4 w-4" />
                    各曜日の計画・目標
                  </h4>
                  <div className="space-y-2">
                    {DAY_LABELS.map((day, index) => {
                      const dayKey = DAY_KEYS[index];
                      const content = plan.plan_data?.[dayKey] || '';
                      const dateLabel = formatWeekDate(plan.week_start_date, index);
                      return (
                        <div key={dayKey} className="grid grid-cols-[100px_1fr] gap-3">
                          <div>
                            <div className="text-sm font-semibold text-[var(--brand-80)]">
                              {day}
                            </div>
                            <div className="text-xs text-[var(--neutral-foreground-4)]">
                              {dateLabel}
                            </div>
                          </div>
                          <div
                            className={`whitespace-pre-wrap rounded border-l-4 border-[var(--brand-80)] bg-[var(--neutral-background-3)] p-2 text-sm ${
                              !content
                                ? 'italic text-[var(--neutral-foreground-4)]'
                                : 'text-[var(--neutral-foreground-1)]'
                            }`}
                          >
                            {content || '予定なし'}
                          </div>
                        </div>
                      );
                    })}
                  </div>
                </div>

                {/* Submissions display */}
                {plan.submissions && plan.submissions.length > 0 && (
                  <div className="mt-6 border-t border-[var(--neutral-stroke-2)] pt-6">
                    <h4 className="mb-3 flex items-center gap-2 text-sm font-semibold text-[var(--status-danger-fg)]">
                      <ClipboardList className="h-4 w-4" />
                      提出物一覧
                    </h4>
                    <div className="space-y-2">
                      {plan.submissions.map((sub, i) => {
                        const daysUntilDue = differenceInDays(
                          parseISO(sub.due_date),
                          new Date()
                        );
                        let urgencyClass = '';
                        let urgencyLabel = '';
                        if (!sub.is_completed) {
                          if (daysUntilDue < 0) {
                            urgencyClass = 'text-red-700 font-bold';
                            urgencyLabel = `（${Math.abs(daysUntilDue)}日超過）`;
                          } else if (daysUntilDue === 0) {
                            urgencyClass = 'text-red-600 font-semibold';
                            urgencyLabel = '（今日が期限）';
                          } else if (daysUntilDue <= 3) {
                            urgencyClass = 'text-red-600 font-semibold';
                            urgencyLabel = `（あと${daysUntilDue}日）`;
                          }
                        }
                        return (
                          <div
                            key={sub.id || i}
                            className={`flex items-center justify-between rounded border-l-4 p-3 ${
                              sub.is_completed
                                ? 'border-green-500 bg-[var(--neutral-background-3)] opacity-60 line-through'
                                : 'border-red-500 bg-[var(--neutral-background-3)]'
                            }`}
                          >
                            <div>
                              <div className="font-semibold text-[var(--neutral-foreground-1)]">
                                {sub.is_completed && (
                                  <CheckCircle className="mr-1 inline h-4 w-4 text-green-600" />
                                )}
                                {sub.submission_item}
                              </div>
                              <div className={`text-xs ${urgencyClass || 'text-[var(--neutral-foreground-4)]'}`}>
                                期限: {formatDate(sub.due_date)} {urgencyLabel}
                              </div>
                            </div>
                          </div>
                        );
                      })}
                    </div>
                  </div>
                )}
              </CardBody>
            </Card>

            {/* Evaluation section (not yet evaluated) */}
            {!plan.evaluated_at && (
              <Card className="border-2 border-[var(--brand-80)]">
                <CardBody>
                  <div className="flex items-center justify-between">
                    <h3 className="flex items-center gap-2 font-semibold text-[var(--brand-80)]">
                      <Star className="h-5 w-5" />
                      一週間の振り返り評価
                    </h3>
                    <Button
                      variant="primary"
                      size="sm"
                      onClick={() => openEvaluation('current')}
                    >
                      振り返りを入力
                    </Button>
                  </div>
                </CardBody>
              </Card>
            )}

            {/* Evaluation display (already evaluated) */}
            {plan.evaluated_at && (
              <Card className="border-2 border-blue-400">
                <CardHeader>
                  <div className="flex items-center justify-between">
                    <CardTitle className="flex items-center gap-2 text-blue-600">
                      <Star className="h-5 w-5" />
                      達成度評価
                    </CardTitle>
                    <span className="text-xs text-[var(--neutral-foreground-4)]">
                      評価日: {formatDate(plan.evaluated_at)}
                    </span>
                  </div>
                </CardHeader>
                <CardBody>
                  <div className="space-y-3">
                    {plan.weekly_goal && (
                      <EvalDisplayItem
                        icon={<Target className="h-4 w-4" />}
                        label="今週の目標"
                        content={plan.weekly_goal}
                        achievement={plan.weekly_goal_achievement}
                        comment={plan.weekly_goal_comment}
                      />
                    )}
                    {plan.shared_goal && (
                      <EvalDisplayItem
                        icon={<Handshake className="h-4 w-4" />}
                        label="いっしょに決めた目標"
                        content={plan.shared_goal}
                        achievement={plan.shared_goal_achievement}
                        comment={plan.shared_goal_comment}
                      />
                    )}
                    {plan.must_do && (
                      <EvalDisplayItem
                        icon={<CheckCircle className="h-4 w-4" />}
                        label="やるべきこと"
                        content={plan.must_do}
                        achievement={plan.must_do_achievement}
                        comment={plan.must_do_comment}
                      />
                    )}
                    {plan.should_do && (
                      <EvalDisplayItem
                        icon={<ThumbsUp className="h-4 w-4" />}
                        label="やったほうがいいこと"
                        content={plan.should_do}
                        achievement={plan.should_do_achievement}
                        comment={plan.should_do_comment}
                      />
                    )}
                    {plan.want_to_do && (
                      <EvalDisplayItem
                        icon={<Lightbulb className="h-4 w-4" />}
                        label="やりたいこと"
                        content={plan.want_to_do}
                        achievement={plan.want_to_do_achievement}
                        comment={plan.want_to_do_comment}
                      />
                    )}

                    {/* Daily achievement display */}
                    {plan.daily_achievement && Object.keys(plan.daily_achievement).length > 0 && (
                      <div className="mt-4">
                        <h4 className="mb-2 flex items-center gap-2 text-sm font-semibold text-[var(--neutral-foreground-1)]">
                          <Calendar className="h-4 w-4" />
                          各曜日の達成度
                        </h4>
                        <div className="space-y-2">
                          {DAY_KEYS.map((dayKey, index) => {
                            const entry = plan.daily_achievement?.[dayKey];
                            if (!entry || !entry.achievement) return null;
                            return (
                              <div
                                key={dayKey}
                                className="flex items-center justify-between rounded bg-[var(--neutral-background-3)] p-3"
                              >
                                <span className="font-semibold text-[var(--neutral-foreground-1)]">
                                  {DAY_LABELS[index]}
                                </span>
                                <AchievementBadge value={entry.achievement} />
                              </div>
                            );
                          })}
                        </div>
                      </div>
                    )}

                    {/* Overall comment */}
                    {plan.overall_comment && (
                      <div className="mt-4 rounded border-l-4 border-blue-400 bg-[var(--neutral-background-3)] p-3">
                        <div className="mb-1 font-semibold text-blue-600">
                          週全体の総合コメント
                        </div>
                        <div className="whitespace-pre-wrap text-sm text-[var(--neutral-foreground-1)]">
                          {plan.overall_comment}
                        </div>
                      </div>
                    )}
                  </div>
                </CardBody>
              </Card>
            )}

            {/* Comments section */}
            <Card>
              <CardHeader>
                <CardTitle className="flex items-center gap-2">
                  <MessageSquare className="h-5 w-5" />
                  コメント
                </CardTitle>
              </CardHeader>
              <CardBody>
                {plan.comments && plan.comments.length > 0 ? (
                  <div className="mb-4 space-y-3">
                    {plan.comments.map((comment) => {
                      const borderColor =
                        comment.commenter_type === 'staff'
                          ? 'border-green-500'
                          : comment.commenter_type === 'guardian'
                            ? 'border-orange-500'
                            : 'border-[var(--brand-80)]';
                      return (
                        <div
                          key={comment.id}
                          className={`rounded border-l-4 ${borderColor} bg-[var(--neutral-background-3)] p-3`}
                        >
                          <div className="mb-1 flex items-center justify-between">
                            <span className="text-sm font-semibold text-[var(--brand-80)]">
                              {comment.commenter_name}
                              <Badge
                                variant={
                                  comment.commenter_type === 'staff'
                                    ? 'success'
                                    : comment.commenter_type === 'guardian'
                                      ? 'warning'
                                      : 'info'
                                }
                                className="ml-2"
                              >
                                {comment.commenter_type === 'staff'
                                  ? 'スタッフ'
                                  : comment.commenter_type === 'guardian'
                                    ? '保護者'
                                    : '生徒'}
                              </Badge>
                            </span>
                            <span className="text-xs text-[var(--neutral-foreground-4)]">
                              {formatDateTime(comment.created_at)}
                            </span>
                          </div>
                          <p className="whitespace-pre-wrap text-sm leading-relaxed text-[var(--neutral-foreground-1)]">
                            {comment.comment}
                          </p>
                        </div>
                      );
                    })}
                  </div>
                ) : (
                  <p className="mb-4 py-4 text-center text-sm text-[var(--neutral-foreground-4)]">
                    まだコメントはありません
                  </p>
                )}

                {/* Comment form */}
                <div className="border-t border-[var(--neutral-stroke-2)] pt-4">
                  <textarea
                    value={newComment}
                    onChange={(e) => setNewComment(e.target.value)}
                    placeholder="コメントを入力..."
                    rows={3}
                    className="mb-2 block w-full resize-y rounded-lg border border-[var(--neutral-stroke-2)] px-3 py-2 text-sm focus:border-[var(--brand-80)] focus:outline-none focus:ring-2 focus:ring-[var(--brand-80)]/20"
                  />
                  <Button
                    variant="primary"
                    size="sm"
                    leftIcon={<Send className="h-4 w-4" />}
                    onClick={() => addCommentMutation.mutate()}
                    isLoading={addCommentMutation.isPending}
                    disabled={!newComment.trim()}
                  >
                    コメントを投稿
                  </Button>
                </div>
              </CardBody>
            </Card>
          </>
        )}
      </div>
    );
  };

  // -----------------------------------------------------------------------
  // Render: Edit Form
  // -----------------------------------------------------------------------

  const renderEditForm = () => (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <h2 className="text-xl font-bold text-[var(--neutral-foreground-1)]">
          {planDetail?.student.student_name || ''}さんの週間計画を編集
        </h2>
        <div className="flex gap-2">
          <Button variant="ghost" size="sm" onClick={() => setIsEditing(false)}>
            キャンセル
          </Button>
          <Button
            variant="primary"
            size="sm"
            onClick={() => savePlanMutation.mutate()}
            isLoading={savePlanMutation.isPending}
          >
            保存する
          </Button>
        </div>
      </div>

      <Card>
        <CardBody>
          {/* Goals */}
          <div className="space-y-4">
            <div>
              <label className="mb-1 flex items-center gap-2 text-sm font-semibold text-[var(--brand-80)]">
                <Target className="h-4 w-4" /> 今週の目標
              </label>
              <textarea
                value={form.weekly_goal}
                onChange={(e) => updateFormField('weekly_goal', e.target.value)}
                placeholder="今週達成したい目標を記入してください"
                rows={2}
                className="block w-full resize-y rounded-lg border border-[var(--neutral-stroke-2)] px-3 py-2 text-sm focus:border-[var(--brand-80)] focus:outline-none focus:ring-2 focus:ring-[var(--brand-80)]/20"
              />
            </div>

            <div>
              <label className="mb-1 flex items-center gap-2 text-sm font-semibold text-[var(--brand-80)]">
                <Handshake className="h-4 w-4" /> いっしょに決めた目標
              </label>
              <textarea
                value={form.shared_goal}
                onChange={(e) => updateFormField('shared_goal', e.target.value)}
                placeholder="生徒と一緒に決めた目標を記入してください"
                rows={2}
                className="block w-full resize-y rounded-lg border border-[var(--neutral-stroke-2)] px-3 py-2 text-sm focus:border-[var(--brand-80)] focus:outline-none focus:ring-2 focus:ring-[var(--brand-80)]/20"
              />
            </div>

            {/* Domain goals reference */}
            {planDetail?.domain_goals && planDetail.domain_goals.length > 0 && (
              <div className="rounded-lg border border-dashed border-green-500 bg-green-50 p-4">
                <h4 className="mb-2 flex items-center gap-2 text-sm font-semibold text-green-700">
                  <ClipboardList className="h-4 w-4" /> 個別支援計画の目標（参考）
                </h4>
                <div className="space-y-2">
                  {planDetail.domain_goals.map((goal, i) => (
                    <div
                      key={i}
                      className="rounded border-l-[3px] border-green-500 bg-white p-2"
                    >
                      <div className="text-xs text-[var(--neutral-foreground-4)]">
                        {goal.sub_category}
                      </div>
                      <div className="text-sm text-[var(--neutral-foreground-1)]">
                        {goal.support_goal}
                      </div>
                    </div>
                  ))}
                </div>
              </div>
            )}

            <div>
              <label className="mb-1 flex items-center gap-2 text-sm font-semibold text-[var(--brand-80)]">
                <CheckCircle className="h-4 w-4" /> やるべきこと
              </label>
              <textarea
                value={form.must_do}
                onChange={(e) => updateFormField('must_do', e.target.value)}
                placeholder="必ずやるべきことを記入してください"
                rows={2}
                className="block w-full resize-y rounded-lg border border-[var(--neutral-stroke-2)] px-3 py-2 text-sm focus:border-[var(--brand-80)] focus:outline-none focus:ring-2 focus:ring-[var(--brand-80)]/20"
              />
            </div>

            <div>
              <label className="mb-1 flex items-center gap-2 text-sm font-semibold text-[var(--brand-80)]">
                <ThumbsUp className="h-4 w-4" /> やったほうがいいこと
              </label>
              <textarea
                value={form.should_do}
                onChange={(e) => updateFormField('should_do', e.target.value)}
                placeholder="できればやったほうがいいことを記入してください"
                rows={2}
                className="block w-full resize-y rounded-lg border border-[var(--neutral-stroke-2)] px-3 py-2 text-sm focus:border-[var(--brand-80)] focus:outline-none focus:ring-2 focus:ring-[var(--brand-80)]/20"
              />
            </div>

            <div>
              <label className="mb-1 flex items-center gap-2 text-sm font-semibold text-[var(--brand-80)]">
                <Lightbulb className="h-4 w-4" /> やりたいこと
              </label>
              <textarea
                value={form.want_to_do}
                onChange={(e) => updateFormField('want_to_do', e.target.value)}
                placeholder="本人がやりたいと思っていることを記入してください"
                rows={2}
                className="block w-full resize-y rounded-lg border border-[var(--neutral-stroke-2)] px-3 py-2 text-sm focus:border-[var(--brand-80)] focus:outline-none focus:ring-2 focus:ring-[var(--brand-80)]/20"
              />
            </div>
          </div>

          {/* Daily plans */}
          <div className="mt-6">
            <h4 className="mb-3 flex items-center gap-2 text-sm font-semibold text-[var(--neutral-foreground-1)]">
              <Calendar className="h-4 w-4" /> 各曜日の計画・目標
            </h4>
            <div className="space-y-3">
              {DAY_LABELS.map((day, index) => {
                const dayKey = DAY_KEYS[index];
                const dateLabel = formatWeekDate(weekStartStr, index);
                return (
                  <div key={dayKey} className="grid grid-cols-[100px_1fr] gap-3">
                    <div>
                      <div className="text-sm font-semibold text-[var(--brand-80)]">
                        {day}
                      </div>
                      <div className="text-xs text-[var(--neutral-foreground-4)]">
                        {dateLabel}
                      </div>
                    </div>
                    <textarea
                      value={form.plan_data[dayKey] || ''}
                      onChange={(e) => updateDayPlan(dayKey, e.target.value)}
                      placeholder="この日の計画や目標を記入してください"
                      rows={2}
                      className="block w-full resize-y rounded-lg border border-[var(--neutral-stroke-2)] px-3 py-2 text-sm focus:border-[var(--brand-80)] focus:outline-none focus:ring-2 focus:ring-[var(--brand-80)]/20"
                    />
                  </div>
                );
              })}
            </div>
          </div>

          {/* Submissions edit */}
          <div className="mt-6 border-t border-[var(--neutral-stroke-2)] pt-6">
            <h4 className="mb-3 flex items-center gap-2 text-sm font-semibold text-[var(--status-danger-fg)]">
              <ClipboardList className="h-4 w-4" /> 提出物管理
            </h4>
            <div className="space-y-3">
              {form.submissions.map((sub, index) => (
                <div
                  key={index}
                  className="grid grid-cols-[1fr_150px_80px_40px] items-center gap-2 sm:grid-cols-[1fr_150px_80px_40px]"
                >
                  <input
                    type="text"
                    value={sub.submission_item}
                    onChange={(e) =>
                      updateSubmission(index, 'submission_item', e.target.value)
                    }
                    placeholder="提出物名"
                    className="rounded-lg border border-[var(--neutral-stroke-2)] px-3 py-2 text-sm focus:border-[var(--brand-80)] focus:outline-none"
                  />
                  <input
                    type="date"
                    value={sub.due_date}
                    onChange={(e) =>
                      updateSubmission(index, 'due_date', e.target.value)
                    }
                    className="rounded-lg border border-[var(--neutral-stroke-2)] px-3 py-2 text-sm focus:border-[var(--brand-80)] focus:outline-none"
                  />
                  <label className="flex cursor-pointer items-center gap-1 text-xs">
                    <input
                      type="checkbox"
                      checked={sub.is_completed}
                      onChange={(e) =>
                        updateSubmission(index, 'is_completed', e.target.checked)
                      }
                      className="h-4 w-4 cursor-pointer"
                    />
                    完了
                  </label>
                  <button
                    type="button"
                    onClick={() => removeSubmission(index)}
                    className="flex h-8 w-8 items-center justify-center rounded bg-red-500 text-white hover:bg-red-600"
                  >
                    <X className="h-4 w-4" />
                  </button>
                </div>
              ))}
            </div>
            <Button
              variant="outline"
              size="sm"
              className="mt-3"
              leftIcon={<Plus className="h-4 w-4" />}
              onClick={addSubmission}
            >
              提出物を追加
            </Button>
          </div>
        </CardBody>
      </Card>

      {/* Bottom save bar */}
      <div className="flex justify-end gap-2">
        <Button variant="ghost" onClick={() => setIsEditing(false)}>
          キャンセル
        </Button>
        <Button
          variant="primary"
          onClick={() => savePlanMutation.mutate()}
          isLoading={savePlanMutation.isPending}
        >
          保存する
        </Button>
      </div>
    </div>
  );

  // -----------------------------------------------------------------------
  // Render: Evaluation Modal
  // -----------------------------------------------------------------------

  const evalPlan =
    evalTarget === 'current' ? planDetail : planDetail?.prev_week_plan;

  const renderEvalModal = () => {
    if (!evalPlan) return null;

    const goalItems: {
      key: string;
      icon: React.ReactNode;
      label: string;
      content: string | null;
      achievementKey: keyof EvalForm;
      commentKey: keyof EvalForm;
    }[] = [
      {
        key: 'weekly_goal',
        icon: <Target className="h-4 w-4" />,
        label: '今週の目標',
        content: evalPlan.weekly_goal,
        achievementKey: 'weekly_goal_achievement',
        commentKey: 'weekly_goal_comment',
      },
      {
        key: 'shared_goal',
        icon: <Handshake className="h-4 w-4" />,
        label: 'いっしょに決めた目標',
        content: evalPlan.shared_goal,
        achievementKey: 'shared_goal_achievement',
        commentKey: 'shared_goal_comment',
      },
      {
        key: 'must_do',
        icon: <CheckCircle className="h-4 w-4" />,
        label: 'やるべきこと',
        content: evalPlan.must_do,
        achievementKey: 'must_do_achievement',
        commentKey: 'must_do_comment',
      },
      {
        key: 'should_do',
        icon: <ThumbsUp className="h-4 w-4" />,
        label: 'やったほうがいいこと',
        content: evalPlan.should_do,
        achievementKey: 'should_do_achievement',
        commentKey: 'should_do_comment',
      },
      {
        key: 'want_to_do',
        icon: <Lightbulb className="h-4 w-4" />,
        label: 'やりたいこと',
        content: evalPlan.want_to_do,
        achievementKey: 'want_to_do_achievement',
        commentKey: 'want_to_do_comment',
      },
    ];

    return (
      <Modal
        isOpen={showEvalModal}
        onClose={() => setShowEvalModal(false)}
        title={
          evalTarget === 'prev'
            ? `前週（${formatDate(evalPlan.week_start_date)}の週）の達成度評価`
            : '一週間の振り返り評価'
        }
        size="xl"
      >
        <div className="max-h-[70vh] space-y-4 overflow-y-auto pr-2">
          {goalItems
            .filter((item) => item.content)
            .map((item) => (
              <div
                key={item.key}
                className="rounded-lg bg-[var(--neutral-background-3)] p-4"
              >
                <div className="mb-2 flex items-center gap-2 text-sm font-semibold text-[var(--neutral-foreground-1)]">
                  {item.icon} {item.label}
                </div>
                <div className="mb-3 whitespace-pre-wrap rounded bg-[var(--neutral-background-1)] p-2 text-sm text-[var(--neutral-foreground-2)]">
                  {item.content}
                </div>
                <AchievementScale
                  value={evalForm[item.achievementKey] as number}
                  onChange={(v) =>
                    setEvalForm((prev) => ({
                      ...prev,
                      [item.achievementKey]: v,
                    }))
                  }
                />
                <textarea
                  value={evalForm[item.commentKey] as string}
                  onChange={(e) =>
                    setEvalForm((prev) => ({
                      ...prev,
                      [item.commentKey]: e.target.value,
                    }))
                  }
                  placeholder="コメント（任意）"
                  rows={2}
                  className="mt-2 block w-full resize-y rounded-lg border border-[var(--neutral-stroke-2)] px-3 py-2 text-sm focus:border-[var(--brand-80)] focus:outline-none focus:ring-2 focus:ring-[var(--brand-80)]/20"
                />
              </div>
            ))}

          {/* Daily achievement */}
          {DAY_KEYS.some((k) => evalPlan.plan_data?.[k]) && (
            <div className="rounded-lg bg-[var(--neutral-background-3)] p-4">
              <h4 className="mb-3 flex items-center gap-2 text-sm font-semibold text-[var(--neutral-foreground-1)]">
                <Calendar className="h-4 w-4" /> 各曜日の計画達成度
              </h4>
              <div className="space-y-3">
                {DAY_KEYS.map((dayKey, index) => {
                  const content = evalPlan.plan_data?.[dayKey];
                  if (!content) return null;
                  return (
                    <div
                      key={dayKey}
                      className="rounded bg-[var(--neutral-background-1)] p-3"
                    >
                      <div className="mb-1 font-semibold text-[var(--brand-80)]">
                        {DAY_LABELS[index]}
                      </div>
                      <div className="mb-2 whitespace-pre-wrap text-sm text-[var(--neutral-foreground-2)]">
                        {content}
                      </div>
                      <AchievementScale
                        value={
                          evalForm.daily_achievement[dayKey]?.achievement || 3
                        }
                        onChange={(v) =>
                          setEvalForm((prev) => ({
                            ...prev,
                            daily_achievement: {
                              ...prev.daily_achievement,
                              [dayKey]: {
                                ...(prev.daily_achievement[dayKey] || {
                                  comment: '',
                                }),
                                achievement: v,
                              },
                            },
                          }))
                        }
                      />
                    </div>
                  );
                })}
              </div>
            </div>
          )}

          {/* Overall comment */}
          <div className="rounded-lg bg-[var(--neutral-background-3)] p-4">
            <h4 className="mb-2 font-semibold text-[var(--brand-80)]">
              週全体の総合コメント
            </h4>
            <textarea
              value={evalForm.overall_comment}
              onChange={(e) =>
                setEvalForm((prev) => ({
                  ...prev,
                  overall_comment: e.target.value,
                }))
              }
              placeholder="週全体を振り返っての総合コメントを入力してください"
              rows={3}
              className="block w-full resize-y rounded-lg border border-[var(--neutral-stroke-2)] px-3 py-2 text-sm focus:border-[var(--brand-80)] focus:outline-none focus:ring-2 focus:ring-[var(--brand-80)]/20"
            />
          </div>
        </div>

        <div className="mt-4 flex justify-end gap-2 border-t border-[var(--neutral-stroke-2)] pt-4">
          <Button variant="ghost" onClick={() => setShowEvalModal(false)}>
            キャンセル
          </Button>
          <Button
            variant="primary"
            onClick={() => saveEvalMutation.mutate(evalPlan.id)}
            isLoading={saveEvalMutation.isPending}
          >
            振り返りを完了
          </Button>
        </div>
      </Modal>
    );
  };

  // -----------------------------------------------------------------------
  // Main Render
  // -----------------------------------------------------------------------

  return (
    <div className="space-y-6">
      {/* Page header */}
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold text-[var(--neutral-foreground-1)]">
            生徒週間計画表
          </h1>
          <p className="text-sm text-[var(--neutral-foreground-3)]">
            各生徒の週間計画を確認
          </p>
        </div>
        {selectedStudentId && (
          <Button variant="ghost" onClick={closeDetail}>
            一覧に戻る
          </Button>
        )}
      </div>

      {/* Week navigation */}
      {renderWeekNav()}

      {/* Content: either student list or detail */}
      {selectedStudentId ? (
        isLoadingDetail ? (
          <SkeletonList items={4} />
        ) : isEditing ? (
          renderEditForm()
        ) : (
          renderDetailView()
        )
      ) : (
        renderStudentList()
      )}

      {/* Evaluation modal */}
      {renderEvalModal()}
    </div>
  );
}

// ---------------------------------------------------------------------------
// Evaluation Display Item
// ---------------------------------------------------------------------------

function EvalDisplayItem({
  icon,
  label,
  content,
  achievement,
  comment,
}: {
  icon: React.ReactNode;
  label: string;
  content: string;
  achievement: number | null;
  comment: string | null;
}) {
  return (
    <div className="rounded bg-[var(--neutral-background-3)] p-3">
      <div className="mb-1 flex items-center gap-2 text-sm font-semibold text-[var(--neutral-foreground-1)]">
        {icon} {label}
      </div>
      <div className="mb-2 whitespace-pre-wrap rounded bg-[var(--neutral-background-1)] p-2 text-sm text-[var(--neutral-foreground-2)]">
        {content}
      </div>
      <AchievementBadge value={achievement || 0} />
      {comment && (
        <div className="mt-2 rounded border-l-[3px] border-orange-400 bg-[var(--neutral-background-1)] p-2 text-xs text-[var(--neutral-foreground-2)]">
          {comment}
        </div>
      )}
    </div>
  );
}
