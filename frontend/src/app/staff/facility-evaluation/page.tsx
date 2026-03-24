'use client';

import { useState, useEffect, useCallback } from 'react';
import api from '@/lib/api';
import { nl } from '@/lib/utils';
import { Card, CardHeader, CardTitle, CardBody } from '@/components/ui/Card';
import { Badge } from '@/components/ui/Badge';
import { Button } from '@/components/ui/Button';
import { Modal } from '@/components/ui/Modal';
import { Input } from '@/components/ui/Input';
import { Tabs } from '@/components/ui/Tabs';
import { Skeleton } from '@/components/ui/Skeleton';
import { useToast } from '@/components/ui/Toast';
import { MaterialIcon } from '@/components/ui/MaterialIcon';

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
  facility_comment?: string | null;
  comment_summary?: string | null;
  yes_percentage?: number;
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

interface SelfSummaryItem {
  id?: number;
  period_id: number;
  item_type: 'strength' | 'weakness';
  item_number: number;
  description: string;
  current_efforts: string;
  improvement_plan: string;
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

const STAFF_ANSWER_LABELS: Record<string, string> = {
  yes: 'はい',
  neutral: 'どちらともいえない',
  no: 'いいえ',
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

  // 年度計算（4月始まり）
  const now = new Date();
  const currentFiscalYear = now.getMonth() >= 3 ? now.getFullYear() : now.getFullYear() - 1;
  const defaultDeadline = new Date(now.getTime() + 30 * 24 * 60 * 60 * 1000).toISOString().split('T')[0];

  const [createForm, setCreateForm] = useState({
    fiscal_year: currentFiscalYear,
    title: '',
    guardian_deadline: defaultDeadline,
    staff_deadline: defaultDeadline,
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
      setCreateForm({ fiscal_year: currentFiscalYear, title: '', guardian_deadline: defaultDeadline, staff_deadline: defaultDeadline });
      fetchPeriods();
    } catch (e: unknown) {
      const msg = (e as { response?: { data?: { message?: string } } })?.response?.data?.message || '作成に失敗しました';
      toast.error(msg);
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
        onBack={() => { setSelectedPeriod(null); fetchPeriods(); }}
        onUpdateStatus={handleUpdateStatus}
      />
    );
  }

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold text-[var(--neutral-foreground-1)]">事業所評価シート</h1>
          <p className="text-sm text-[var(--neutral-foreground-3)]">年度ごとの事業所評価アンケートを管理します</p>
        </div>
        <Button leftIcon={<MaterialIcon name="add" size={16} />} onClick={() => setShowCreate(true)}>
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
              <MaterialIcon name="description" size={40} className="mx-auto mb-2" />
              <p>評価期間がまだ作成されていません。</p>
              <p className="text-xs mt-1">上のボタンから新しい評価期間を作成してください。</p>
            </div>
          </CardBody>
        </Card>
      ) : (
        <div className="space-y-3">
          {periods.map((period) => {
            const st = STATUS_MAP[period.status] || STATUS_MAP.draft;
            const guardianPercent = period.guardian_total > 0
              ? Math.round((period.guardian_submitted / period.guardian_total) * 100)
              : 0;
            const staffPercent = period.staff_total > 0
              ? Math.round((period.staff_submitted / period.staff_total) * 100)
              : 0;
            return (
              <Card key={period.id}>
                <button
                  onClick={() => setSelectedPeriod(period)}
                  className="w-full text-left p-4 hover:bg-[var(--neutral-background-3)] transition-colors rounded-lg"
                >
                  <div className="flex items-center justify-between mb-3">
                    <div className="flex items-center gap-2">
                      <h3 className="font-semibold text-[var(--neutral-foreground-1)]">{period.title}</h3>
                      <Badge variant={st.variant}>{st.label}</Badge>
                    </div>
                    <MaterialIcon name="chevron_right" size={20} className="text-[var(--neutral-foreground-4)]" />
                  </div>

                  <div className="grid grid-cols-2 lg:grid-cols-4 gap-4 text-sm">
                    <div>
                      <span className="text-xs text-[var(--neutral-foreground-3)]">保護者回答</span>
                      <div className="font-semibold text-[var(--neutral-foreground-1)]">
                        {period.guardian_submitted} / {period.guardian_total}件
                      </div>
                      <div className="mt-1 h-1.5 bg-[var(--neutral-background-4)] rounded-full overflow-hidden">
                        <div className="h-full bg-[var(--status-success-fg)]" style={{ width: `${guardianPercent}%` }} />
                      </div>
                    </div>
                    <div>
                      <span className="text-xs text-[var(--neutral-foreground-3)]">スタッフ回答</span>
                      <div className="font-semibold text-[var(--neutral-foreground-1)]">
                        {period.staff_submitted} / {period.staff_total}件
                      </div>
                      <div className="mt-1 h-1.5 bg-[var(--neutral-background-4)] rounded-full overflow-hidden">
                        <div className="h-full bg-[var(--status-success-fg)]" style={{ width: `${staffPercent}%` }} />
                      </div>
                    </div>
                    <div>
                      <span className="text-xs text-[var(--neutral-foreground-3)]">保護者期限</span>
                      <div className="font-semibold text-[var(--neutral-foreground-1)]">
                        {period.guardian_deadline ? new Date(period.guardian_deadline).toLocaleDateString('ja-JP') : '未設定'}
                      </div>
                    </div>
                    <div>
                      <span className="text-xs text-[var(--neutral-foreground-3)]">スタッフ期限</span>
                      <div className="font-semibold text-[var(--neutral-foreground-1)]">
                        {period.staff_deadline ? new Date(period.staff_deadline).toLocaleDateString('ja-JP') : '未設定'}
                      </div>
                    </div>
                  </div>

                  {period.status === 'collecting' && (
                    <div className="mt-3 rounded-md bg-[var(--status-success-bg,rgba(52,199,89,0.1))] px-3 py-2 text-xs text-[var(--status-success-fg)]">
                      保護者の画面に通知が表示されています。メニューの「事業所評価」から回答できます。
                    </div>
                  )}
                </button>
              </Card>
            );
          })}
        </div>
      )}

      {/* Create period modal */}
      <Modal isOpen={showCreate} onClose={() => setShowCreate(false)} title="新しい評価期間を作成">
        <div className="space-y-4">
          <div>
            <label className="block text-sm font-medium text-[var(--neutral-foreground-2)] mb-1">年度</label>
            <select
              value={createForm.fiscal_year}
              onChange={(e) => setCreateForm({ ...createForm, fiscal_year: parseInt(e.target.value) })}
              className="w-full rounded-lg border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-1)] px-3 py-2 text-sm"
            >
              {Array.from({ length: 7 }, (_, i) => currentFiscalYear + 1 - i).map((y) => (
                <option key={y} value={y}>{y}年度</option>
              ))}
            </select>
          </div>
          <Input
            label="タイトル（空欄で自動生成）"
            value={createForm.title}
            onChange={(e) => setCreateForm({ ...createForm, title: e.target.value })}
            placeholder={`${createForm.fiscal_year}年度 事業所評価`}
          />
          <Input
            label="保護者回答期限"
            type="date"
            value={createForm.guardian_deadline}
            onChange={(e) => setCreateForm({ ...createForm, guardian_deadline: e.target.value })}
          />
          <Input
            label="スタッフ回答期限"
            type="date"
            value={createForm.staff_deadline}
            onChange={(e) => setCreateForm({ ...createForm, staff_deadline: e.target.value })}
          />
          <div className="flex justify-end gap-2 pt-2">
            <Button variant="ghost" onClick={() => setShowCreate(false)}>キャンセル</Button>
            <Button onClick={handleCreatePeriod} isLoading={isSaving}>作成</Button>
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
  period: initialPeriod,
  onBack,
  onUpdateStatus,
}: {
  period: EvaluationPeriod;
  onBack: () => void;
  onUpdateStatus: (periodId: number, status: string) => Promise<void>;
}) {
  const toast = useToast();
  const [period, setPeriod] = useState(initialPeriod);
  const st = STATUS_MAP[period.status] || STATUS_MAP.draft;

  const handleAggregate = async () => {
    try {
      await api.post('/api/staff/facility-evaluation/aggregate', { period_id: period.id });
      toast.success('集計が完了しました');
      setPeriod((p) => ({ ...p, status: 'aggregating' }));
    } catch {
      toast.error('集計に失敗しました');
    }
  };

  const handlePublish = async () => {
    if (!confirm('公表してよろしいですか？')) return;
    await onUpdateStatus(period.id, 'published');
    setPeriod((p) => ({ ...p, status: 'published' }));
  };

  return (
    <div className="space-y-6">
      <div className="flex items-center gap-3 flex-wrap">
        <Button variant="ghost" onClick={onBack}>← 戻る</Button>
        <div className="flex-1">
          <div className="flex items-center gap-2">
            <h1 className="text-2xl font-bold text-[var(--neutral-foreground-1)]">{period.title}</h1>
            <Badge variant={st.variant}>{st.label}</Badge>
          </div>
        </div>
        {/* Status workflow buttons */}
        <div className="flex items-center gap-2 flex-wrap">
          {period.status === 'draft' && (
            <Button size="sm" onClick={() => { onUpdateStatus(period.id, 'collecting'); setPeriod((p) => ({ ...p, status: 'collecting' })); }}>
              回答収集を開始
            </Button>
          )}
          {(period.status === 'collecting' || period.status === 'aggregating') && (
            <Button size="sm" variant="outline" onClick={handleAggregate} leftIcon={<MaterialIcon name="refresh" size={16} className="h-3.5 w-3.5" />}>
              {period.status === 'aggregating' ? '再集計' : '集計開始'}
            </Button>
          )}
          {period.status === 'aggregating' && (
            <Button size="sm" onClick={handlePublish} leftIcon={<Globe className="h-3.5 w-3.5" />}>
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
            icon: <MaterialIcon name="group" size={16} />,
            content: <ResponseStatusTab periodId={period.id} />,
          },
          {
            key: 'guardian-results',
            label: '保護者評価（別紙4）',
            icon: <MaterialIcon name="analytics" size={16} />,
            content: <GuardianResultsTab periodId={period.id} />,
          },
          {
            key: 'staff-results',
            label: 'スタッフ自己評価（別紙5）',
            icon: <MaterialIcon name="description" size={16} />,
            content: <StaffSelfEvaluationTab periodId={period.id} />,
          },
          {
            key: 'self-summary',
            label: '自己評価総括表（別紙3）',
            icon: <MaterialIcon name="checklist" size={16} />,
            content: <SelfEvaluationSummaryTab periodId={period.id} />,
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

  const guardianSubmitted = data.guardian_responses.filter((r) => r.is_submitted).length;
  const staffSubmitted = data.staff_responses.filter((r) => r.is_submitted).length;

  const renderList = (items: ResponseStatus[], nameKey: 'guardian_name' | 'staff_name') => (
    <div className="space-y-1">
      {items.map((item, index) => (
        <div key={`${item.id}-${index}`} className="flex items-center justify-between rounded-lg border border-[var(--neutral-stroke-3)] px-3 py-2">
          <span className="text-sm text-[var(--neutral-foreground-2)]">
            {item[nameKey] || '不明'}
          </span>
          {item.is_submitted ? (
            <div className="flex items-center gap-1.5 text-xs text-[var(--status-success-fg)]">
              <MaterialIcon name="check_circle" size={14} />
              回答済み
              {item.submitted_at && (
                <span className="text-[var(--neutral-foreground-4)]">
                  ({new Date(item.submitted_at).toLocaleDateString('ja-JP')})
                </span>
              )}
            </div>
          ) : (
            <div className="flex items-center gap-1.5 text-xs text-[var(--status-danger-fg)]">
              <MaterialIcon name="cancel" size={16} className="h-3.5 w-3.5" />
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
            {guardianSubmitted}/{data.guardian_responses.length}
          </Badge>
        </CardHeader>
        <CardBody>{renderList(data.guardian_responses, 'guardian_name')}</CardBody>
      </Card>
      <Card>
        <CardHeader>
          <CardTitle className="text-base">スタッフ回答状況</CardTitle>
          <Badge variant="info">
            {staffSubmitted}/{data.staff_responses.length}
          </Badge>
        </CardHeader>
        <CardBody>{renderList(data.staff_responses, 'staff_name')}</CardBody>
      </Card>
    </div>
  );
}

// ---------------------------------------------------------------------------
// Guardian Results Tab (with facility comments, individual comments, percentages)
// ---------------------------------------------------------------------------

function GuardianResultsTab({ periodId }: { periodId: number }) {
  const toast = useToast();
  const [summary, setSummary] = useState<QuestionSummary[]>([]);
  const [totalRespondents, setTotalRespondents] = useState(0);
  const [commentsByQuestion, setCommentsByQuestion] = useState<Record<number, string[]>>({});
  const [isLoading, setIsLoading] = useState(true);
  const [facilityComments, setFacilityComments] = useState<Record<number, string>>({});
  const [expandedComments, setExpandedComments] = useState<Record<number, boolean>>({});

  const fetchData = useCallback(() => {
    setIsLoading(true);
    api.get('/api/staff/facility-evaluation/summary', { params: { period_id: periodId } })
      .then((res) => {
        const d = res.data?.data;
        const summaryData = Array.isArray(d?.summary) ? d.summary : [];
        setSummary(summaryData);
        setTotalRespondents(d?.total_respondents ?? 0);
        setCommentsByQuestion(d?.comments_by_question || {});
        // Initialize facility comments
        const fc: Record<number, string> = {};
        summaryData.forEach((q: QuestionSummary) => {
          fc[q.question_id] = q.facility_comment || '';
        });
        setFacilityComments(fc);
      })
      .catch(() => {})
      .finally(() => setIsLoading(false));
  }, [periodId]);

  useEffect(() => { fetchData(); }, [fetchData]);

  const saveFacilityComment = async (questionId: number) => {
    try {
      await api.post('/api/staff/facility-evaluation/facility-comment', {
        period_id: periodId,
        question_id: questionId,
        facility_comment: facilityComments[questionId] || '',
      });
      toast.success('事業所コメントを保存しました');
    } catch {
      toast.error('保存に失敗しました');
    }
  };

  if (isLoading) {
    return <div className="space-y-2">{[...Array(5)].map((_, i) => <Skeleton key={i} className="h-12 w-full rounded" />)}</div>;
  }

  if (summary.length === 0) {
    return (
      <div className="py-8 text-center text-[var(--neutral-foreground-4)]">
        <MaterialIcon name="analytics" size={40} className="mx-auto mb-2" />
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
          <div className="bg-[var(--brand-primary,#7c3aed)] text-white px-5 py-3 font-semibold text-base">
            {category}
          </div>
          <CardBody>
            <div className="space-y-6">
              {questions.map((q) => {
                const totalExcludeUnknown = q.yes_count + q.neutral_count + q.no_count;
                const total = q.total_count || 1;
                const yesPercent = totalExcludeUnknown > 0 ? Math.round((q.yes_count / totalExcludeUnknown) * 100) : 0;
                const neutralPercent = totalExcludeUnknown > 0 ? Math.round((q.neutral_count / totalExcludeUnknown) * 100) : 0;
                const noPercent = totalExcludeUnknown > 0 ? Math.round((q.no_count / totalExcludeUnknown) * 100) : 0;
                const qComments = commentsByQuestion[q.question_id] || [];
                const isExpanded = expandedComments[q.question_id] || false;

                return (
                  <div key={q.question_id} className="border-b border-[var(--neutral-stroke-3)] pb-4 last:border-0 last:pb-0">
                    <p className="text-sm text-[var(--neutral-foreground-2)] mb-2">
                      <span className="inline-flex items-center justify-center w-6 h-6 rounded-full bg-[var(--brand-primary,#7c3aed)] text-white text-xs font-semibold mr-2">
                        {q.question_number}
                      </span>
                      {q.question_text}
                    </p>

                    {/* Bar chart */}
                    <div className="flex gap-1 h-6 rounded overflow-hidden mb-2">
                      {q.yes_count > 0 && (
                        <div
                          className="flex items-center justify-center text-[10px] text-white font-medium"
                          style={{ width: `${(q.yes_count / total) * 100}%`, backgroundColor: 'var(--status-success-fg)' }}
                        >{q.yes_count}</div>
                      )}
                      {q.neutral_count > 0 && (
                        <div
                          className="flex items-center justify-center text-[10px] text-white font-medium"
                          style={{ width: `${(q.neutral_count / total) * 100}%`, backgroundColor: 'var(--status-warning-fg)' }}
                        >{q.neutral_count}</div>
                      )}
                      {q.no_count > 0 && (
                        <div
                          className="flex items-center justify-center text-[10px] text-white font-medium"
                          style={{ width: `${(q.no_count / total) * 100}%`, backgroundColor: 'var(--status-danger-fg)' }}
                        >{q.no_count}</div>
                      )}
                    </div>

                    {/* Counts with percentages */}
                    <div className="flex flex-wrap gap-3 text-xs text-[var(--neutral-foreground-3)]">
                      <span className="flex items-center gap-1">
                        <span className="w-2.5 h-2.5 rounded-full" style={{ backgroundColor: 'var(--status-success-fg)' }} />
                        はい: {q.yes_count} ({yesPercent}%)
                      </span>
                      <span className="flex items-center gap-1">
                        <span className="w-2.5 h-2.5 rounded-full" style={{ backgroundColor: 'var(--status-warning-fg)' }} />
                        どちらとも: {q.neutral_count} ({neutralPercent}%)
                      </span>
                      <span className="flex items-center gap-1">
                        <span className="w-2.5 h-2.5 rounded-full" style={{ backgroundColor: 'var(--status-danger-fg)' }} />
                        いいえ: {q.no_count} ({noPercent}%)
                      </span>
                      <span className="flex items-center gap-1">
                        <span className="w-2.5 h-2.5 rounded-full bg-gray-400" />
                        わからない: {q.unknown_count}
                      </span>
                    </div>

                    {/* Individual comments */}
                    {qComments.length > 0 && (
                      <div className="mt-3 rounded-md border-l-3 border-[var(--status-info-fg,#3b82f6)] bg-[rgba(59,130,246,0.04)] p-3">
                        <button
                          className="flex items-center gap-1 text-xs font-semibold text-[var(--status-info-fg,#3b82f6)] mb-2"
                          onClick={() => setExpandedComments((prev) => ({ ...prev, [q.question_id]: !prev[q.question_id] }))}
                        >
                          <MaterialIcon name="chat" size={14} />
                          ご意見 ({qComments.length}件)
                          {isExpanded ? <MaterialIcon name="expand_less" size={12} /> : <MaterialIcon name="expand_more" size={12} />}
                        </button>
                        {isExpanded && (
                          <ul className="space-y-1">
                            {qComments.map((c, ci) => (
                              <li key={ci} className="rounded border border-[var(--neutral-stroke-3)] bg-[var(--neutral-background-1)] px-3 py-2 text-xs text-[var(--neutral-foreground-2)] whitespace-pre-wrap">
                                {nl(c)}
                              </li>
                            ))}
                          </ul>
                        )}
                      </div>
                    )}

                    {/* AI comment summary */}
                    {q.comment_summary && (
                      <div className="mt-2 rounded-md bg-[var(--neutral-background-3)] p-3">
                        <h4 className="text-xs font-semibold text-[var(--neutral-foreground-3)] mb-1">ご意見の要約（AI）</h4>
                        <p className="text-xs text-[var(--neutral-foreground-2)] whitespace-pre-wrap">{nl(q.comment_summary)}</p>
                      </div>
                    )}

                    {/* Facility comment */}
                    <div className="mt-2 rounded-md bg-[var(--neutral-background-3)] p-3">
                      <h4 className="text-xs font-semibold text-[var(--neutral-foreground-3)] mb-1">事業所コメント</h4>
                      <textarea
                        value={facilityComments[q.question_id] || ''}
                        onChange={(e) => setFacilityComments((prev) => ({ ...prev, [q.question_id]: e.target.value }))}
                        placeholder="事業所からの回答・改善策を入力"
                        className="block w-full rounded border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-1)] px-2 py-1.5 text-xs text-[var(--neutral-foreground-1)]"
                        rows={2}
                      />
                      <div className="mt-1 flex gap-1">
                        <Button size="sm" onClick={() => saveFacilityComment(q.question_id)}>保存</Button>
                      </div>
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
// Staff Self-Evaluation Tab (yes/neutral/no only, improvement_plan required for no)
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

    if (submit) {
      if (answersList.length < questions.length) {
        toast.error('すべての質問に回答してください');
        return;
      }
      // Check improvement_plan required for 'no'
      const missingImprovement = answersList.filter((a) => a.answer === 'no' && !a.improvement_plan?.trim());
      if (missingImprovement.length > 0) {
        toast.error('「いいえ」と回答した質問には改善計画を入力してください');
        return;
      }
      if (!confirm('提出すると修正できなくなります。提出してよろしいですか？')) return;
    }

    setIsSaving(true);
    try {
      await api.post('/api/staff/facility-evaluation/staff-evaluation', {
        period_id: periodId,
        answers: answersList,
        submit,
      });
      toast.success(submit ? '自己評価を提出しました' : '下書きを保存しました');
      if (submit) setIsSubmitted(true);
    } catch (e: unknown) {
      const msg = (e as { response?: { data?: { message?: string } } })?.response?.data?.message || '保存に失敗しました';
      toast.error(msg);
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
        <MaterialIcon name="description" size={40} className="mx-auto mb-2" />
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
        <div className="rounded-lg bg-[var(--status-info-bg,rgba(59,130,246,0.1))] border border-[var(--status-info-fg,#3b82f6)] px-4 py-3 text-sm text-[var(--status-info-fg,#3b82f6)] text-center flex items-center justify-center gap-2">
          <MaterialIcon name="check_circle" size={16} />
          自己評価は提出済みです
        </div>
      )}

      <p className="text-sm text-[var(--neutral-foreground-3)]">
        各項目について、現在の事業所の状況を評価してください
      </p>

      {Object.entries(categories).map(([category, qs]) => (
        <Card key={category}>
          <div className="bg-[var(--brand-primary,#7c3aed)] text-white px-5 py-3 font-semibold text-base">
            {category}
          </div>
          <CardBody>
            <div className="space-y-6">
              {qs.map((q) => {
                const current = answers[q.id];
                return (
                  <div key={q.id} className="border-b border-[var(--neutral-stroke-3)] pb-4 last:border-0 last:pb-0">
                    <p className="text-sm font-medium text-[var(--neutral-foreground-1)] mb-2">
                      <span className="inline-flex items-center justify-center w-7 h-7 rounded-full bg-[var(--brand-primary,#7c3aed)] text-white text-xs font-semibold mr-2">
                        {q.question_number}
                      </span>
                      {q.question_text}
                    </p>

                    {/* Staff only gets yes/neutral/no (no "unknown") */}
                    <div className="flex flex-wrap gap-2 mb-2">
                      {(['yes', 'neutral', 'no'] as const).map((opt) => (
                        <button
                          key={opt}
                          onClick={() => !isSubmitted && updateAnswer(q.id, 'answer', opt)}
                          disabled={isSubmitted}
                          className={`rounded-lg px-3 py-1.5 text-xs font-medium transition-colors border ${
                            current?.answer === opt
                              ? 'text-white border-transparent'
                              : 'border-[var(--neutral-stroke-2)] text-[var(--neutral-foreground-3)] hover:bg-[var(--neutral-background-3)]'
                          } ${isSubmitted ? 'opacity-60 cursor-not-allowed' : ''}`}
                          style={current?.answer === opt ? { backgroundColor: answerColor(opt) } : undefined}
                        >
                          {STAFF_ANSWER_LABELS[opt]}
                        </button>
                      ))}
                    </div>

                    <div className="mb-1">
                      <label className="block text-xs text-[var(--neutral-foreground-3)] mb-0.5">
                        工夫している点・課題や改善すべき点
                      </label>
                      <textarea
                        placeholder="任意で記入してください"
                        value={current?.comment || ''}
                        onChange={(e) => updateAnswer(q.id, 'comment', e.target.value)}
                        disabled={isSubmitted}
                        className="block w-full rounded-lg border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-1)] px-3 py-1.5 text-xs text-[var(--neutral-foreground-1)] disabled:opacity-60"
                        rows={2}
                      />
                    </div>

                    <div>
                      <label className="block text-xs text-[var(--neutral-foreground-3)] mb-0.5">
                        改善計画（いいえの場合は必須）
                      </label>
                      <textarea
                        placeholder="改善計画を記入してください"
                        value={current?.improvement_plan || ''}
                        onChange={(e) => updateAnswer(q.id, 'improvement_plan', e.target.value)}
                        disabled={isSubmitted}
                        className={`block w-full rounded-lg border bg-[var(--neutral-background-1)] px-3 py-1.5 text-xs text-[var(--neutral-foreground-1)] disabled:opacity-60 ${
                          current?.answer === 'no' && !current?.improvement_plan?.trim()
                            ? 'border-red-500'
                            : 'border-[var(--neutral-stroke-2)]'
                        }`}
                        rows={2}
                      />
                    </div>
                  </div>
                );
              })}
            </div>
          </CardBody>
        </Card>
      ))}

      {!isSubmitted && (
        <div className="flex justify-end gap-2">
          <Button variant="outline" onClick={() => handleSave(false)} isLoading={isSaving} leftIcon={<MaterialIcon name="save" size={16} />}>
            下書き保存
          </Button>
          <Button onClick={() => handleSave(true)} isLoading={isSaving} leftIcon={<MaterialIcon name="send" size={16} />}>
            提出する
          </Button>
        </div>
      )}
    </div>
  );
}

// ---------------------------------------------------------------------------
// Self-Evaluation Summary Tab (別紙3)
// ---------------------------------------------------------------------------

function SelfEvaluationSummaryTab({ periodId }: { periodId: number }) {
  const toast = useToast();
  const [isLoading, setIsLoading] = useState(true);
  const [isSaving, setIsSaving] = useState(false);
  const [selfSummaryItems, setSelfSummaryItems] = useState<SelfSummaryItem[]>([]);
  const [staffRespondents, setStaffRespondents] = useState(0);
  const [staffSummary, setStaffSummary] = useState<QuestionSummary[]>([]);

  // Initialize 3 strengths and 3 weaknesses
  const initItems = (): SelfSummaryItem[] => {
    const items: SelfSummaryItem[] = [];
    for (let i = 1; i <= 3; i++) {
      items.push({ period_id: periodId, item_type: 'strength', item_number: i, description: '', current_efforts: '', improvement_plan: '' });
      items.push({ period_id: periodId, item_type: 'weakness', item_number: i, description: '', current_efforts: '', improvement_plan: '' });
    }
    return items;
  };

  useEffect(() => {
    setIsLoading(true);
    api.get('/api/staff/facility-evaluation/self-summary', { params: { period_id: periodId } })
      .then((res) => {
        const d = res.data?.data;
        setStaffRespondents(d?.staff_respondents ?? 0);
        setStaffSummary(Array.isArray(d?.summary) ? d.summary : []);

        const serverItems = Array.isArray(d?.self_summary_items) ? d.self_summary_items : [];
        const items = initItems();
        // Merge server data into initialized items
        serverItems.forEach((si: SelfSummaryItem) => {
          const idx = items.findIndex(
            (item) => item.item_type === si.item_type && item.item_number === si.item_number
          );
          if (idx >= 0) {
            items[idx] = { ...items[idx], ...si };
          }
        });
        setSelfSummaryItems(items);
      })
      .catch(() => {
        setSelfSummaryItems(initItems());
      })
      .finally(() => setIsLoading(false));
  // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [periodId]);

  const updateItem = (itemType: 'strength' | 'weakness', itemNumber: number, field: keyof SelfSummaryItem, value: string) => {
    setSelfSummaryItems((prev) =>
      prev.map((item) =>
        item.item_type === itemType && item.item_number === itemNumber
          ? { ...item, [field]: value }
          : item
      )
    );
  };

  const handleSave = async () => {
    setIsSaving(true);
    try {
      await api.post('/api/staff/facility-evaluation/self-summary', {
        period_id: periodId,
        items: selfSummaryItems.map((item) => ({
          item_type: item.item_type,
          item_number: item.item_number,
          description: item.description,
          current_efforts: item.current_efforts,
          improvement_plan: item.improvement_plan,
        })),
      });
      toast.success('自己評価総括表を保存しました');
    } catch {
      toast.error('保存に失敗しました');
    } finally {
      setIsSaving(false);
    }
  };

  if (isLoading) {
    return <div className="space-y-2">{[...Array(3)].map((_, i) => <Skeleton key={i} className="h-24 w-full rounded" />)}</div>;
  }

  const strengths = selfSummaryItems.filter((i) => i.item_type === 'strength').sort((a, b) => a.item_number - b.item_number);
  const weaknesses = selfSummaryItems.filter((i) => i.item_type === 'weakness').sort((a, b) => a.item_number - b.item_number);

  return (
    <div className="space-y-6">
      {/* Meta info */}
      <Card>
        <CardBody>
          <div className="space-y-1 text-sm">
            <div className="flex gap-4">
              <span className="text-[var(--neutral-foreground-3)] min-w-[150px]">従業者評価回答数</span>
              <span className="text-[var(--neutral-foreground-1)]">{staffRespondents}件</span>
            </div>
          </div>
        </CardBody>
      </Card>

      {/* Staff results summary (brief) */}
      {staffSummary.length > 0 && (
        <Card>
          <CardHeader>
            <CardTitle className="text-base">スタッフ自己評価集計結果</CardTitle>
          </CardHeader>
          <CardBody>
            <div className="space-y-3">
              {(() => {
                const cats: Record<string, QuestionSummary[]> = {};
                staffSummary.forEach((q) => {
                  if (!cats[q.category]) cats[q.category] = [];
                  cats[q.category].push(q);
                });
                return Object.entries(cats).map(([category, qs]) => {
                  const totalYes = qs.reduce((s, q) => s + q.yes_count, 0);
                  const totalNeutral = qs.reduce((s, q) => s + q.neutral_count, 0);
                  const totalNo = qs.reduce((s, q) => s + q.no_count, 0);
                  return (
                    <div key={category} className="flex items-center gap-3 text-xs">
                      <span className="min-w-[120px] font-medium text-[var(--neutral-foreground-2)]">{category}</span>
                      <Badge variant="success">○ {totalYes}</Badge>
                      <Badge variant="warning">△ {totalNeutral}</Badge>
                      <Badge variant="default">× {totalNo}</Badge>
                    </div>
                  );
                });
              })()}
            </div>
          </CardBody>
        </Card>
      )}

      {/* Strengths */}
      <Card>
        <CardHeader>
          <CardTitle className="text-base text-[var(--status-success-fg)]">
            事業所の強み（より強化・充実を図ることが期待されること）
          </CardTitle>
        </CardHeader>
        <CardBody>
          <div className="space-y-4">
            {strengths.map((item) => (
              <div key={item.item_number} className="rounded-lg border border-[var(--neutral-stroke-3)] p-4">
                <div className="font-semibold text-sm text-[var(--neutral-foreground-1)] mb-3">{item.item_number}</div>
                <div className="space-y-3">
                  <div>
                    <label className="block text-xs text-[var(--neutral-foreground-3)] mb-1">事業所の強みだと思われること</label>
                    <textarea
                      value={item.description}
                      onChange={(e) => updateItem('strength', item.item_number, 'description', e.target.value)}
                      placeholder="強みの内容を入力"
                      className="block w-full rounded border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-1)] px-3 py-2 text-sm"
                      rows={2}
                    />
                  </div>
                  <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <div>
                      <label className="block text-xs text-[var(--neutral-foreground-3)] mb-1">工夫していることや意識的に行っている取組等</label>
                      <textarea
                        value={item.current_efforts}
                        onChange={(e) => updateItem('strength', item.item_number, 'current_efforts', e.target.value)}
                        placeholder="現在の取組を入力"
                        className="block w-full rounded border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-1)] px-3 py-2 text-sm"
                        rows={2}
                      />
                    </div>
                    <div>
                      <label className="block text-xs text-[var(--neutral-foreground-3)] mb-1">さらに充実を図るための取組等</label>
                      <textarea
                        value={item.improvement_plan}
                        onChange={(e) => updateItem('strength', item.item_number, 'improvement_plan', e.target.value)}
                        placeholder="今後の取組を入力"
                        className="block w-full rounded border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-1)] px-3 py-2 text-sm"
                        rows={2}
                      />
                    </div>
                  </div>
                </div>
              </div>
            ))}
          </div>
        </CardBody>
      </Card>

      {/* Weaknesses */}
      <Card>
        <CardHeader>
          <CardTitle className="text-base text-[var(--status-warning-fg)]">
            事業所の弱み（事業所の課題や改善が必要だと思われること）
          </CardTitle>
        </CardHeader>
        <CardBody>
          <div className="space-y-4">
            {weaknesses.map((item) => (
              <div key={item.item_number} className="rounded-lg border border-[var(--neutral-stroke-3)] p-4">
                <div className="font-semibold text-sm text-[var(--neutral-foreground-1)] mb-3">{item.item_number}</div>
                <div className="space-y-3">
                  <div>
                    <label className="block text-xs text-[var(--neutral-foreground-3)] mb-1">事業所の弱みだと思われること</label>
                    <textarea
                      value={item.description}
                      onChange={(e) => updateItem('weakness', item.item_number, 'description', e.target.value)}
                      placeholder="課題・弱みの内容を入力"
                      className="block w-full rounded border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-1)] px-3 py-2 text-sm"
                      rows={2}
                    />
                  </div>
                  <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <div>
                      <label className="block text-xs text-[var(--neutral-foreground-3)] mb-1">事業所として考えている課題の要因等</label>
                      <textarea
                        value={item.current_efforts}
                        onChange={(e) => updateItem('weakness', item.item_number, 'current_efforts', e.target.value)}
                        placeholder="課題の要因を入力"
                        className="block w-full rounded border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-1)] px-3 py-2 text-sm"
                        rows={2}
                      />
                    </div>
                    <div>
                      <label className="block text-xs text-[var(--neutral-foreground-3)] mb-1">改善に向けて必要な取組や工夫が必要な点等</label>
                      <textarea
                        value={item.improvement_plan}
                        onChange={(e) => updateItem('weakness', item.item_number, 'improvement_plan', e.target.value)}
                        placeholder="改善策を入力"
                        className="block w-full rounded border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-1)] px-3 py-2 text-sm"
                        rows={2}
                      />
                    </div>
                  </div>
                </div>
              </div>
            ))}
          </div>
        </CardBody>
      </Card>

      <div className="flex justify-center">
        <Button onClick={handleSave} isLoading={isSaving} leftIcon={<MaterialIcon name="save" size={16} />}>
          自己評価総括表を保存
        </Button>
      </div>
    </div>
  );
}
