'use client';

import { useState, useEffect, useCallback } from 'react';
import api from '@/lib/api';
import { Card, CardHeader, CardTitle, CardBody } from '@/components/ui/Card';
import { Badge } from '@/components/ui/Badge';
import { Button } from '@/components/ui/Button';
import { Modal } from '@/components/ui/Modal';
import { Input } from '@/components/ui/Input';
import { Tabs } from '@/components/ui/Tabs';
import { Skeleton } from '@/components/ui/Skeleton';
import { useToast } from '@/components/ui/Toast';
import {
  BarChart3,
  Users,
  FileText,
  Plus,
  CheckCircle2,
  Clock,
  XCircle,
  ChevronRight,
  Send,
  Save,
  MessageCircle,
} from 'lucide-react';

// ---------------------------------------------------------------------------
// Types
// ---------------------------------------------------------------------------

interface EvaluationPeriod {
  id: number;
  fiscal_year: number;
  title: string;
  status: 'draft' | 'collecting' | 'aggregating' | 'published';
  guardian_deadline: string | null;
  staff_deadline: string | null;
  guardian_submitted: number;
  guardian_total: number;
  staff_submitted: number;
  staff_total: number;
}

interface QuestionSummary {
  question_id: number;
  question_number: number;
  question_text: string;
  category: string;
  yes_count: number;
  neutral_count: number;
  no_count: number;
  unknown_count: number;
  total_count: number;
}

interface Comment {
  question_number: number;
  question_text: string;
  comment: string;
}

interface ResponseStatus {
  id: number;
  guardian_name?: string;
  staff_name?: string;
  is_submitted: boolean;
  submitted_at: string | null;
}

interface StaffQuestion {
  id: number;
  question_type: string;
  category: string;
  question_number: number;
  question_text: string;
}

interface StaffAnswer {
  answer: string;
  comment: string | null;
  improvement_plan: string | null;
}

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

const STATUS_MAP: Record<string, { label: string; variant: 'default' | 'info' | 'warning' | 'success' }> = {
  draft: { label: '下書き', variant: 'default' },
  collecting: { label: '回答収集中', variant: 'info' },
  aggregating: { label: '集計中', variant: 'warning' },
  published: { label: '公表済み', variant: 'success' },
};

const ANSWER_LABELS: Record<string, string> = {
  yes: 'はい',
  neutral: 'どちらともいえない',
  no: 'いいえ',
  unknown: 'わからない',
};

function answerColor(answer: string): string {
  switch (answer) {
    case 'yes': return 'var(--status-success-fg)';
    case 'neutral': return 'var(--status-warning-fg)';
    case 'no': return 'var(--status-danger-fg)';
    default: return 'var(--neutral-foreground-4)';
  }
}

// ---------------------------------------------------------------------------
// Component
// ---------------------------------------------------------------------------

export default function FacilityEvaluationPage() {
  const toast = useToast();
  const [periods, setPeriods] = useState<EvaluationPeriod[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [selectedPeriod, setSelectedPeriod] = useState<EvaluationPeriod | null>(null);
  const [showCreate, setShowCreate] = useState(false);
  const [createForm, setCreateForm] = useState({
    fiscal_year: new Date().getFullYear(),
    title: '',
    guardian_deadline: '',
    staff_deadline: '',
  });
  const [isSaving, setIsSaving] = useState(false);

  const fetchPeriods = useCallback(async () => {
    setIsLoading(true);
    try {
      const res = await api.get('/api/staff/facility-evaluation/periods');
      const data = res.data?.data;
      setPeriods(Array.isArray(data) ? data : []);
    } catch {
      setPeriods([]);
    } finally {
      setIsLoading(false);
    }
  }, []);

  useEffect(() => { fetchPeriods(); }, [fetchPeriods]);

  const handleCreatePeriod = async () => {
    setIsSaving(true);
    try {
      await api.post('/api/staff/facility-evaluation/periods', createForm);
      toast.success('評価期間を作成しました');
      setShowCreate(false);
      setCreateForm({ fiscal_year: new Date().getFullYear(), title: '', guardian_deadline: '', staff_deadline: '' });
      fetchPeriods();
    } catch {
      toast.error('作成に失敗しました');
    } finally {
      setIsSaving(false);
    }
  };

  const handleUpdateStatus = async (periodId: number, status: string) => {
    try {
      await api.put(`/api/staff/facility-evaluation/periods/${periodId}`, { status });
      toast.success('ステータスを更新しました');
      fetchPeriods();
      if (selectedPeriod?.id === periodId) {
        setSelectedPeriod((prev) => prev ? { ...prev, status: status as EvaluationPeriod['status'] } : null);
      }
    } catch {
      toast.error('更新に失敗しました');
    }
  };

  if (selectedPeriod) {
    return (
      <PeriodDetail
        period={selectedPeriod}
        onBack={() => setSelectedPeriod(null)}
        onUpdateStatus={handleUpdateStatus}
      />
    );
  }

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-bold text-[var(--neutral-foreground-1)]">事業所評価</h1>
        <Button leftIcon={<Plus className="h-4 w-4" />} onClick={() => setShowCreate(true)}>
          新規評価期間
        </Button>
      </div>

      {isLoading ? (
        <div className="space-y-3">
          {[...Array(3)].map((_, i) => <Skeleton key={i} className="h-24 w-full rounded-lg" />)}
        </div>
      ) : periods.length === 0 ? (
        <Card>
          <CardBody>
            <div className="py-8 text-center text-[var(--neutral-foreground-4)]">
              <FileText className="mx-auto h-10 w-10 mb-2" />
              <p>評価期間がありません</p>
            </div>
          </CardBody>
        </Card>
      ) : (
        <div className="space-y-3">
          {periods.map((period) => {
            const st = STATUS_MAP[period.status] || STATUS_MAP.draft;
            return (
              <Card key={period.id}>
                <button
                  onClick={() => setSelectedPeriod(period)}
                  className="w-full text-left p-4 hover:bg-[var(--neutral-background-3)] transition-colors rounded-lg"
                >
                  <div className="flex items-center justify-between">
                    <div className="flex items-center gap-3">
                      <div>
                        <div className="flex items-center gap-2">
                          <h3 className="font-semibold text-[var(--neutral-foreground-1)]">{period.title}</h3>
                          <Badge variant={st.variant}>{st.label}</Badge>
                        </div>
                        <p className="mt-1 text-xs text-[var(--neutral-foreground-3)]">
                          {period.fiscal_year}年度
                          {period.guardian_deadline && ` ・ 保護者締切: ${period.guardian_deadline}`}
                          {period.staff_deadline && ` ・ スタッフ締切: ${period.staff_deadline}`}
                        </p>
                      </div>
                    </div>
                    <div className="flex items-center gap-4">
                      <div className="text-right text-xs">
                        <div className="text-[var(--neutral-foreground-3)]">
                          保護者: <span className="font-semibold text-[var(--neutral-foreground-1)]">{period.guardian_submitted}/{period.guardian_total}</span>
                        </div>
                        <div className="text-[var(--neutral-foreground-3)]">
                          スタッフ: <span className="font-semibold text-[var(--neutral-foreground-1)]">{period.staff_submitted}/{period.staff_total}</span>
                        </div>
                      </div>
                      <ChevronRight className="h-5 w-5 text-[var(--neutral-foreground-4)]" />
                    </div>
                  </div>
                </button>
              </Card>
            );
          })}
        </div>
      )}

      {/* Create period modal */}
      <Modal isOpen={showCreate} onClose={() => setShowCreate(false)} title="評価期間を作成">
        <div className="space-y-4">
          <Input
            label="年度"
            type="number"
            value={createForm.fiscal_year}
            onChange={(e) => setCreateForm({ ...createForm, fiscal_year: parseInt(e.target.value) })}
          />
          <Input
            label="タイトル"
            value={createForm.title}
            onChange={(e) => setCreateForm({ ...createForm, title: e.target.value })}
            placeholder="例: 2025年度 事業所評価"
          />
          <Input
            label="保護者締切日"
            type="date"
            value={createForm.guardian_deadline}
            onChange={(e) => setCreateForm({ ...createForm, guardian_deadline: e.target.value })}
          />
          <Input
            label="スタッフ締切日"
            type="date"
            value={createForm.staff_deadline}
            onChange={(e) => setCreateForm({ ...createForm, staff_deadline: e.target.value })}
          />
          <div className="flex justify-end gap-2 pt-2">
            <Button variant="ghost" onClick={() => setShowCreate(false)}>キャンセル</Button>
            <Button onClick={handleCreatePeriod} isLoading={isSaving} disabled={!createForm.title}>作成</Button>
          </div>
        </div>
      </Modal>
    </div>
  );
}

// ---------------------------------------------------------------------------
// Period Detail view
// ---------------------------------------------------------------------------

function PeriodDetail({
  period,
  onBack,
  onUpdateStatus,
}: {
  period: EvaluationPeriod;
  onBack: () => void;
  onUpdateStatus: (periodId: number, status: string) => Promise<void>;
}) {
  const st = STATUS_MAP[period.status] || STATUS_MAP.draft;

  return (
    <div className="space-y-6">
      <div className="flex items-center gap-3">
        <Button variant="ghost" onClick={onBack}>← 戻る</Button>
        <div className="flex-1">
          <div className="flex items-center gap-2">
            <h1 className="text-2xl font-bold text-[var(--neutral-foreground-1)]">{period.title}</h1>
            <Badge variant={st.variant}>{st.label}</Badge>
          </div>
        </div>
        {/* Status workflow buttons */}
        <div className="flex items-center gap-2">
          {period.status === 'draft' && (
            <Button size="sm" onClick={() => onUpdateStatus(period.id, 'collecting')}>
              回答収集を開始
            </Button>
          )}
          {period.status === 'collecting' && (
            <Button size="sm" onClick={() => onUpdateStatus(period.id, 'aggregating')}>
              集計開始
            </Button>
          )}
          {period.status === 'aggregating' && (
            <Button size="sm" onClick={() => onUpdateStatus(period.id, 'published')}>
              公表する
            </Button>
          )}
        </div>
      </div>

      <Tabs
        items={[
          {
            key: 'status',
            label: '回答状況',
            icon: <Users className="h-4 w-4" />,
            content: <ResponseStatusTab periodId={period.id} />,
          },
          {
            key: 'guardian-results',
            label: '保護者評価結果',
            icon: <BarChart3 className="h-4 w-4" />,
            content: <GuardianResultsTab periodId={period.id} />,
          },
          {
            key: 'staff-results',
            label: 'スタッフ自己評価',
            icon: <FileText className="h-4 w-4" />,
            content: <StaffSelfEvaluationTab periodId={period.id} />,
          },
          {
            key: 'comments',
            label: 'コメント一覧',
            icon: <MessageCircle className="h-4 w-4" />,
            content: <CommentsTab periodId={period.id} />,
          },
        ]}
      />
    </div>
  );
}

// ---------------------------------------------------------------------------
// Response Status Tab
// ---------------------------------------------------------------------------

function ResponseStatusTab({ periodId }: { periodId: number }) {
  const [data, setData] = useState<{
    guardian_responses: ResponseStatus[];
    staff_responses: ResponseStatus[];
  }>({ guardian_responses: [], staff_responses: [] });
  const [isLoading, setIsLoading] = useState(true);

  useEffect(() => {
    setIsLoading(true);
    api.get(`/api/staff/facility-evaluation/periods/${periodId}/status`)
      .then((res) => setData(res.data?.data || { guardian_responses: [], staff_responses: [] }))
      .catch(() => {})
      .finally(() => setIsLoading(false));
  }, [periodId]);

  if (isLoading) {
    return <div className="space-y-2">{[...Array(5)].map((_, i) => <Skeleton key={i} className="h-10 w-full rounded" />)}</div>;
  }

  const renderList = (items: ResponseStatus[], nameKey: 'guardian_name' | 'staff_name') => (
    <div className="space-y-1">
      {items.map((item) => (
        <div key={item.id} className="flex items-center justify-between rounded-lg border border-[var(--neutral-stroke-3)] px-3 py-2">
          <span className="text-sm text-[var(--neutral-foreground-2)]">
            {item[nameKey] || '不明'}
          </span>
          {item.is_submitted ? (
            <div className="flex items-center gap-1.5 text-xs text-[var(--status-success-fg)]">
              <CheckCircle2 className="h-3.5 w-3.5" />
              回答済み
              {item.submitted_at && (
                <span className="text-[var(--neutral-foreground-4)]">
                  ({new Date(item.submitted_at).toLocaleDateString('ja-JP')})
                </span>
              )}
            </div>
          ) : (
            <div className="flex items-center gap-1.5 text-xs text-[var(--status-danger-fg)]">
              <XCircle className="h-3.5 w-3.5" />
              未回答
            </div>
          )}
        </div>
      ))}
      {items.length === 0 && (
        <p className="py-4 text-center text-sm text-[var(--neutral-foreground-4)]">対象者がいません</p>
      )}
    </div>
  );

  return (
    <div className="grid gap-6 lg:grid-cols-2">
      <Card>
        <CardHeader>
          <CardTitle className="text-base">保護者回答状況</CardTitle>
          <Badge variant="info">
            {data.guardian_responses.filter((r) => r.is_submitted).length}/{data.guardian_responses.length}
          </Badge>
        </CardHeader>
        <CardBody>{renderList(data.guardian_responses, 'guardian_name')}</CardBody>
      </Card>
      <Card>
        <CardHeader>
          <CardTitle className="text-base">スタッフ回答状況</CardTitle>
          <Badge variant="info">
            {data.staff_responses.filter((r) => r.is_submitted).length}/{data.staff_responses.length}
          </Badge>
        </CardHeader>
        <CardBody>{renderList(data.staff_responses, 'staff_name')}</CardBody>
      </Card>
    </div>
  );
}

// ---------------------------------------------------------------------------
// Guardian Results Tab
// ---------------------------------------------------------------------------

function GuardianResultsTab({ periodId }: { periodId: number }) {
  const [summary, setSummary] = useState<QuestionSummary[]>([]);
  const [totalRespondents, setTotalRespondents] = useState(0);
  const [isLoading, setIsLoading] = useState(true);

  useEffect(() => {
    setIsLoading(true);
    api.get('/api/staff/facility-evaluation/summary', { params: { period_id: periodId } })
      .then((res) => {
        const d = res.data?.data;
        setSummary(Array.isArray(d?.summary) ? d.summary : []);
        setTotalRespondents(d?.total_respondents ?? 0);
      })
      .catch(() => {})
      .finally(() => setIsLoading(false));
  }, [periodId]);

  if (isLoading) {
    return <div className="space-y-2">{[...Array(5)].map((_, i) => <Skeleton key={i} className="h-12 w-full rounded" />)}</div>;
  }

  if (summary.length === 0) {
    return (
      <div className="py-8 text-center text-[var(--neutral-foreground-4)]">
        <BarChart3 className="mx-auto h-10 w-10 mb-2" />
        <p>まだ回答がありません</p>
      </div>
    );
  }

  // Group by category
  const categories: Record<string, QuestionSummary[]> = {};
  summary.forEach((q) => {
    if (!categories[q.category]) categories[q.category] = [];
    categories[q.category].push(q);
  });

  return (
    <div className="space-y-4">
      <div className="text-sm text-[var(--neutral-foreground-3)]">
        回答者数: <span className="font-semibold text-[var(--neutral-foreground-1)]">{totalRespondents}名</span>
      </div>
      {Object.entries(categories).map(([category, questions]) => (
        <Card key={category}>
          <CardHeader>
            <CardTitle className="text-base">{category}</CardTitle>
          </CardHeader>
          <CardBody>
            <div className="space-y-4">
              {questions.map((q) => {
                const total = q.total_count || 1;
                return (
                  <div key={q.question_id} className="border-b border-[var(--neutral-stroke-3)] pb-3 last:border-0 last:pb-0">
                    <p className="text-sm text-[var(--neutral-foreground-2)] mb-2">
                      <span className="font-semibold">Q{q.question_number}.</span> {q.question_text}
                    </p>
                    <div className="flex gap-1 h-6 rounded overflow-hidden">
                      {q.yes_count > 0 && (
                        <div
                          className="flex items-center justify-center text-[10px] text-white font-medium"
                          style={{ width: `${(q.yes_count / total) * 100}%`, backgroundColor: 'var(--status-success-fg)' }}
                          title={`はい: ${q.yes_count}`}
                        >
                          {q.yes_count}
                        </div>
                      )}
                      {q.neutral_count > 0 && (
                        <div
                          className="flex items-center justify-center text-[10px] text-white font-medium"
                          style={{ width: `${(q.neutral_count / total) * 100}%`, backgroundColor: 'var(--status-warning-fg)' }}
                          title={`どちらともいえない: ${q.neutral_count}`}
                        >
                          {q.neutral_count}
                        </div>
                      )}
                      {q.no_count > 0 && (
                        <div
                          className="flex items-center justify-center text-[10px] text-white font-medium"
                          style={{ width: `${(q.no_count / total) * 100}%`, backgroundColor: 'var(--status-danger-fg)' }}
                          title={`いいえ: ${q.no_count}`}
                        >
                          {q.no_count}
                        </div>
                      )}
                      {q.unknown_count > 0 && (
                        <div
                          className="flex items-center justify-center text-[10px] text-white font-medium"
                          style={{ width: `${(q.unknown_count / total) * 100}%`, backgroundColor: 'var(--neutral-foreground-4)' }}
                          title={`わからない: ${q.unknown_count}`}
                        >
                          {q.unknown_count}
                        </div>
                      )}
                    </div>
                    <div className="mt-1 flex gap-3 text-[10px] text-[var(--neutral-foreground-3)]">
                      <span>はい: {q.yes_count}</span>
                      <span>どちらとも: {q.neutral_count}</span>
                      <span>いいえ: {q.no_count}</span>
                      <span>わからない: {q.unknown_count}</span>
                    </div>
                  </div>
                );
              })}
            </div>
          </CardBody>
        </Card>
      ))}
    </div>
  );
}

// ---------------------------------------------------------------------------
// Staff Self-Evaluation Tab
// ---------------------------------------------------------------------------

function StaffSelfEvaluationTab({ periodId }: { periodId: number }) {
  const toast = useToast();
  const [questions, setQuestions] = useState<StaffQuestion[]>([]);
  const [answers, setAnswers] = useState<Record<number, StaffAnswer>>({});
  const [isSubmitted, setIsSubmitted] = useState(false);
  const [isLoading, setIsLoading] = useState(true);
  const [isSaving, setIsSaving] = useState(false);

  useEffect(() => {
    setIsLoading(true);
    api.get('/api/staff/facility-evaluation/staff-evaluation', { params: { period_id: periodId } })
      .then((res) => {
        const d = res.data?.data;
        setQuestions(Array.isArray(d?.questions) ? d.questions : []);
        setAnswers(d?.answers || {});
        setIsSubmitted(d?.evaluation?.is_submitted || false);
      })
      .catch(() => {})
      .finally(() => setIsLoading(false));
  }, [periodId]);

  const updateAnswer = (questionId: number, field: keyof StaffAnswer, value: string) => {
    setAnswers((prev) => ({
      ...prev,
      [questionId]: {
        answer: prev[questionId]?.answer || '',
        comment: prev[questionId]?.comment || null,
        improvement_plan: prev[questionId]?.improvement_plan || null,
        [field]: value,
      },
    }));
  };

  const handleSave = async (submit: boolean) => {
    const answersList = Object.entries(answers)
      .filter(([, a]) => a.answer)
      .map(([qId, a]) => ({
        question_id: parseInt(qId),
        answer: a.answer,
        comment: a.comment || null,
        improvement_plan: a.improvement_plan || null,
      }));

    if (submit && answersList.length < questions.length) {
      toast.error('すべての質問に回答してください');
      return;
    }

    setIsSaving(true);
    try {
      await api.post('/api/staff/facility-evaluation/staff-evaluation', {
        period_id: periodId,
        answers: answersList,
        submit,
      });
      toast.success(submit ? '自己評価を提出しました' : '途中保存しました');
      if (submit) setIsSubmitted(true);
    } catch {
      toast.error('保存に失敗しました');
    } finally {
      setIsSaving(false);
    }
  };

  if (isLoading) {
    return <div className="space-y-2">{[...Array(5)].map((_, i) => <Skeleton key={i} className="h-20 w-full rounded" />)}</div>;
  }

  if (questions.length === 0) {
    return (
      <div className="py-8 text-center text-[var(--neutral-foreground-4)]">
        <FileText className="mx-auto h-10 w-10 mb-2" />
        <p>スタッフ用の質問がありません</p>
      </div>
    );
  }

  // Group by category
  const categories: Record<string, StaffQuestion[]> = {};
  questions.forEach((q) => {
    if (!categories[q.category]) categories[q.category] = [];
    categories[q.category].push(q);
  });

  return (
    <div className="space-y-4">
      {isSubmitted && (
        <div className="rounded-lg bg-[var(--status-success-bg)] px-4 py-3 text-sm text-[var(--status-success-fg)] flex items-center gap-2">
          <CheckCircle2 className="h-4 w-4" />
          自己評価は提出済みです
        </div>
      )}

      {Object.entries(categories).map(([category, qs]) => (
        <Card key={category}>
          <CardHeader>
            <CardTitle className="text-base">{category}</CardTitle>
          </CardHeader>
          <CardBody>
            <div className="space-y-6">
              {qs.map((q) => {
                const current = answers[q.id];
                return (
                  <div key={q.id} className="border-b border-[var(--neutral-stroke-3)] pb-4 last:border-0 last:pb-0">
                    <p className="text-sm font-medium text-[var(--neutral-foreground-1)] mb-2">
                      Q{q.question_number}. {q.question_text}
                    </p>
                    <div className="flex flex-wrap gap-2 mb-2">
                      {(['yes', 'neutral', 'no', 'unknown'] as const).map((opt) => (
                        <button
                          key={opt}
                          onClick={() => !isSubmitted && updateAnswer(q.id, 'answer', opt)}
                          disabled={isSubmitted}
                          className={`rounded-lg px-3 py-1.5 text-xs font-medium transition-colors border ${
                            current?.answer === opt
                              ? 'text-white border-transparent'
                              : 'border-[var(--neutral-stroke-2)] text-[var(--neutral-foreground-3)] hover:bg-[var(--neutral-background-3)]'
                          }`}
                          style={current?.answer === opt ? { backgroundColor: answerColor(opt) } : undefined}
                        >
                          {ANSWER_LABELS[opt]}
                        </button>
                      ))}
                    </div>
                    <textarea
                      placeholder="コメント（任意）"
                      value={current?.comment || ''}
                      onChange={(e) => updateAnswer(q.id, 'comment', e.target.value)}
                      disabled={isSubmitted}
                      className="mb-1 block w-full rounded-lg border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-1)] px-3 py-1.5 text-xs text-[var(--neutral-foreground-1)] disabled:opacity-60"
                      rows={2}
                    />
                    <textarea
                      placeholder="改善計画（任意）"
                      value={current?.improvement_plan || ''}
                      onChange={(e) => updateAnswer(q.id, 'improvement_plan', e.target.value)}
                      disabled={isSubmitted}
                      className="block w-full rounded-lg border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-1)] px-3 py-1.5 text-xs text-[var(--neutral-foreground-1)] disabled:opacity-60"
                      rows={2}
                    />
                  </div>
                );
              })}
            </div>
          </CardBody>
        </Card>
      ))}

      {!isSubmitted && (
        <div className="flex justify-end gap-2">
          <Button variant="outline" onClick={() => handleSave(false)} isLoading={isSaving} leftIcon={<Save className="h-4 w-4" />}>
            途中保存
          </Button>
          <Button onClick={() => handleSave(true)} isLoading={isSaving} leftIcon={<Send className="h-4 w-4" />}>
            提出する
          </Button>
        </div>
      )}
    </div>
  );
}

// ---------------------------------------------------------------------------
// Comments Tab
// ---------------------------------------------------------------------------

function CommentsTab({ periodId }: { periodId: number }) {
  const [comments, setComments] = useState<Comment[]>([]);
  const [isLoading, setIsLoading] = useState(true);

  useEffect(() => {
    setIsLoading(true);
    api.get('/api/staff/facility-evaluation/summary', { params: { period_id: periodId } })
      .then((res) => {
        const d = res.data?.data;
        setComments(Array.isArray(d?.comments) ? d.comments : []);
      })
      .catch(() => {})
      .finally(() => setIsLoading(false));
  }, [periodId]);

  if (isLoading) {
    return <div className="space-y-2">{[...Array(5)].map((_, i) => <Skeleton key={i} className="h-10 w-full rounded" />)}</div>;
  }

  if (comments.length === 0) {
    return (
      <div className="py-8 text-center text-[var(--neutral-foreground-4)]">
        <MessageCircle className="mx-auto h-10 w-10 mb-2" />
        <p>コメントはありません</p>
      </div>
    );
  }

  return (
    <div className="space-y-3">
      {comments.map((c, i) => (
        <div key={i} className="rounded-lg border border-[var(--neutral-stroke-2)] p-3">
          <p className="text-xs font-semibold text-[var(--neutral-foreground-3)] mb-1">
            Q{c.question_number}. {c.question_text}
          </p>
          <p className="text-sm text-[var(--neutral-foreground-2)]">{c.comment}</p>
        </div>
      ))}
    </div>
  );
}
