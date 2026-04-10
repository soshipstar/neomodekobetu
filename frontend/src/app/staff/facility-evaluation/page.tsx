'use client';

import React, { useState, useEffect, useCallback } from 'react';
import api from '@/lib/api';
import { Card, CardHeader, CardTitle, CardBody } from '@/components/ui/Card';
import { Button } from '@/components/ui/Button';
import { Badge } from '@/components/ui/Badge';
import { Tabs } from '@/components/ui/Tabs';
import { Modal } from '@/components/ui/Modal';
import { SkeletonList } from '@/components/ui/Skeleton';
import { useToast } from '@/components/ui/Toast';
import { MaterialIcon } from '@/components/ui/MaterialIcon';

// ---------------------------------------------------------------------------
// Types
// ---------------------------------------------------------------------------

interface Period {
  id: number;
  classroom_id: number;
  fiscal_year: number;
  title: string;
  status: 'draft' | 'collecting' | 'aggregating' | 'published';
  guardian_deadline: string | null;
  staff_deadline: string | null;
  created_at: string;
  guardian_submitted: number;
  guardian_total: number;
  staff_submitted: number;
  staff_total: number;
}

interface Question {
  id: number;
  question_number: number;
  question_text: string;
  category: string;
  question_type: 'guardian' | 'staff';
  sort_order: number;
}

interface StaffEvalData {
  period: Period | null;
  questions: Question[];
  evaluation: { id: number; is_submitted: boolean; submitted_at: string | null } | null;
  answers: Record<number, { answer: string; comment: string | null; improvement_plan: string | null }>;
}

interface ResponseUser {
  id: number;
  guardian_name?: string;
  staff_name?: string;
  is_submitted: boolean;
  submitted_at: string | null;
  started_at: string | null;
}

interface SummaryItem {
  question_id: number;
  question_number: number;
  question_text: string;
  category: string;
  yes_count: number;
  neutral_count: number;
  no_count: number;
  unknown_count: number;
  total_count: number;
  yes_percentage: number;
  facility_comment: string | null;
  comment_summary: string | null;
}

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

const STATUS_LABELS: Record<string, string> = {
  draft: '下書き',
  collecting: '回答収集中',
  aggregating: '集計中',
  published: '公表済み',
};

const STATUS_VARIANTS: Record<string, 'default' | 'success' | 'warning' | 'info' | 'danger'> = {
  draft: 'default',
  collecting: 'success',
  aggregating: 'warning',
  published: 'info',
};

const ANSWER_LABELS: Record<string, string> = {
  yes: 'はい',
  neutral: 'どちらともいえない',
  no: 'いいえ',
  unknown: 'わからない',
};

function formatDate(d: string | null): string {
  if (!d) return '—';
  return new Date(d).toLocaleDateString('ja-JP');
}

// ---------------------------------------------------------------------------
// Main Page
// ---------------------------------------------------------------------------

export default function FacilityEvaluationPage() {
  const toast = useToast();
  const [periods, setPeriods] = useState<Period[]>([]);
  const [loading, setLoading] = useState(true);
  const [selectedPeriod, setSelectedPeriod] = useState<Period | null>(null);
  const [activeView, setActiveView] = useState<'list' | 'eval' | 'responses' | 'summary' | 'self_eval'>('list');

  const fetchPeriods = useCallback(async () => {
    try {
      const res = await api.get('/api/staff/facility-evaluation/periods');
      setPeriods(res.data.data || []);
    } catch {
      toast.error('評価期間の取得に失敗しました');
    } finally {
      setLoading(false);
    }
  }, [toast]);

  useEffect(() => { fetchPeriods(); }, [fetchPeriods]);

  if (loading) {
    return (
      <div className="mx-auto max-w-4xl p-4">
        <h1 className="mb-4 text-lg font-semibold">事業所評価</h1>
        <SkeletonList items={3} />
      </div>
    );
  }

  if (activeView === 'eval' && selectedPeriod) {
    return (
      <StaffEvaluationForm
        period={selectedPeriod}
        onBack={() => setActiveView('list')}
      />
    );
  }

  if (activeView === 'responses' && selectedPeriod) {
    return (
      <ResponseStatusView
        period={selectedPeriod}
        onBack={() => setActiveView('list')}
      />
    );
  }

  if (activeView === 'summary' && selectedPeriod) {
    return (
      <SummaryView
        period={selectedPeriod}
        onBack={() => setActiveView('list')}
        onRefresh={fetchPeriods}
      />
    );
  }

  if (activeView === 'self_eval' && selectedPeriod) {
    return (
      <SelfEvaluationView
        period={selectedPeriod}
        onBack={() => setActiveView('list')}
      />
    );
  }

  return (
    <div className="mx-auto max-w-4xl p-4">
      <div className="mb-4 flex items-center justify-between">
        <h1 className="text-lg font-semibold">事業所評価</h1>
        <CreatePeriodButton onCreated={fetchPeriods} />
      </div>

      {periods.length === 0 ? (
        <Card>
          <CardBody>
            <div className="py-8 text-center text-[var(--neutral-foreground-4)]">
              <MaterialIcon name="analytics" size={32} className="mx-auto mb-2" />
              <p className="text-sm">評価期間がありません</p>
              <p className="mt-1 text-xs">新しい評価期間を作成してください</p>
            </div>
          </CardBody>
        </Card>
      ) : (
        <div className="space-y-3">
          {periods.map((period) => (
            <PeriodCard
              key={period.id}
              period={period}
              onSelect={(view) => {
                setSelectedPeriod(period);
                setActiveView(view);
              }}
              onStatusChange={fetchPeriods}
            />
          ))}
        </div>
      )}
    </div>
  );
}

// ---------------------------------------------------------------------------
// Period Card
// ---------------------------------------------------------------------------

function PeriodCard({
  period,
  onSelect,
  onStatusChange,
}: {
  period: Period;
  onSelect: (view: 'eval' | 'responses' | 'summary' | 'self_eval') => void;
  onStatusChange: () => void;
}) {
  const toast = useToast();
  const [updating, setUpdating] = useState(false);

  const handleStatusUpdate = async (newStatus: string) => {
    if (!confirm(`ステータスを「${STATUS_LABELS[newStatus]}」に変更しますか？`)) return;
    setUpdating(true);
    try {
      await api.put(`/api/staff/facility-evaluation/periods/${period.id}`, { status: newStatus });
      toast.success('ステータスを更新しました');
      onStatusChange();
    } catch {
      toast.error('更新に失敗しました');
    } finally {
      setUpdating(false);
    }
  };

  const nextStatus: Record<string, string | null> = {
    draft: 'collecting',
    collecting: 'aggregating',
    aggregating: 'published',
    published: null,
  };

  const next = nextStatus[period.status];

  return (
    <Card>
      <CardBody>
        <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
          <div className="min-w-0 flex-1">
            <div className="flex items-center gap-2">
              <h3 className="font-medium text-[var(--neutral-foreground-1)]">{period.title}</h3>
              <Badge variant={STATUS_VARIANTS[period.status]}>{STATUS_LABELS[period.status]}</Badge>
            </div>
            <div className="mt-1 flex flex-wrap gap-x-4 gap-y-1 text-xs text-[var(--neutral-foreground-3)]">
              <span>保護者回答: {period.guardian_submitted}/{period.guardian_total}</span>
              <span>スタッフ回答: {period.staff_submitted}/{period.staff_total}</span>
              {period.guardian_deadline && <span>保護者〆切: {formatDate(period.guardian_deadline)}</span>}
              {period.staff_deadline && <span>スタッフ〆切: {formatDate(period.staff_deadline)}</span>}
            </div>
          </div>
          <div className="flex flex-wrap items-center gap-2">
            {period.status === 'collecting' && (
              <Button variant="outline" size="sm" onClick={() => onSelect('eval')}>
                <MaterialIcon name="edit_note" size={16} className="mr-1" />
                自己評価
              </Button>
            )}
            <Button variant="outline" size="sm" onClick={() => onSelect('responses')}>
              <MaterialIcon name="people" size={16} className="mr-1" />
              回答状況
            </Button>
            {(period.status === 'aggregating' || period.status === 'published') && (
              <>
                <Button variant="outline" size="sm" onClick={() => onSelect('summary')}>
                  <MaterialIcon name="bar_chart" size={16} className="mr-1" />
                  集計結果
                </Button>
                <Button variant="outline" size="sm" onClick={() => onSelect('self_eval')}>
                  <MaterialIcon name="description" size={16} className="mr-1" />
                  自己評価総括表
                </Button>
              </>
            )}
            {next && (
              <Button
                variant="primary"
                size="sm"
                onClick={() => handleStatusUpdate(next)}
                disabled={updating}
              >
                {STATUS_LABELS[next]}へ
              </Button>
            )}
          </div>
        </div>
      </CardBody>
    </Card>
  );
}

// ---------------------------------------------------------------------------
// Create Period Button
// ---------------------------------------------------------------------------

function CreatePeriodButton({ onCreated }: { onCreated: () => void }) {
  const toast = useToast();
  const [open, setOpen] = useState(false);
  const [fiscalYear, setFiscalYear] = useState(new Date().getFullYear());
  const [guardianDeadline, setGuardianDeadline] = useState('');
  const [staffDeadline, setStaffDeadline] = useState('');
  const [creating, setCreating] = useState(false);

  const handleCreate = async () => {
    setCreating(true);
    try {
      await api.post('/api/staff/facility-evaluation/periods', {
        fiscal_year: fiscalYear,
        guardian_deadline: guardianDeadline || null,
        staff_deadline: staffDeadline || null,
      });
      toast.success('評価期間を作成しました');
      setOpen(false);
      onCreated();
    } catch (err: unknown) {
      const msg = (err as { response?: { data?: { message?: string } } })?.response?.data?.message || '作成に失敗しました';
      toast.error(msg);
    } finally {
      setCreating(false);
    }
  };

  return (
    <>
      <Button variant="primary" size="sm" onClick={() => setOpen(true)}>
        <MaterialIcon name="add" size={16} className="mr-1" />
        新規作成
      </Button>
      <Modal isOpen={open} title="評価期間の作成" onClose={() => setOpen(false)}>
        <div className="space-y-4">
          <div>
            <label className="mb-1 block text-sm font-medium">年度</label>
            <input
              type="number"
              value={fiscalYear}
              onChange={(e) => setFiscalYear(Number(e.target.value))}
              className="w-full rounded-md border border-[var(--neutral-stroke-1)] bg-[var(--neutral-background-1)] px-3 py-2 text-sm"
            />
          </div>
          <div>
            <label className="mb-1 block text-sm font-medium">保護者回答〆切</label>
            <input
              type="date"
              value={guardianDeadline}
              onChange={(e) => setGuardianDeadline(e.target.value)}
              className="w-full rounded-md border border-[var(--neutral-stroke-1)] bg-[var(--neutral-background-1)] px-3 py-2 text-sm"
            />
          </div>
          <div>
            <label className="mb-1 block text-sm font-medium">スタッフ回答〆切</label>
            <input
              type="date"
              value={staffDeadline}
              onChange={(e) => setStaffDeadline(e.target.value)}
              className="w-full rounded-md border border-[var(--neutral-stroke-1)] bg-[var(--neutral-background-1)] px-3 py-2 text-sm"
            />
          </div>
          <div className="flex justify-end gap-2">
            <Button variant="outline" onClick={() => setOpen(false)}>キャンセル</Button>
            <Button variant="primary" onClick={handleCreate} disabled={creating}>
              {creating ? '作成中...' : '作成'}
            </Button>
          </div>
        </div>
      </Modal>
    </>
  );
}

// ---------------------------------------------------------------------------
// Staff Self-Evaluation Form
// ---------------------------------------------------------------------------

function StaffEvaluationForm({ period, onBack }: { period: Period; onBack: () => void }) {
  const toast = useToast();
  const [loading, setLoading] = useState(true);
  const [data, setData] = useState<StaffEvalData | null>(null);
  const [answers, setAnswers] = useState<Record<number, { answer: string; comment: string; improvement_plan: string }>>({});
  const [saving, setSaving] = useState(false);

  useEffect(() => {
    (async () => {
      try {
        const res = await api.get('/api/staff/facility-evaluation/staff-evaluation', { params: { period_id: period.id } });
        const d = res.data.data as StaffEvalData;
        setData(d);
        const init: Record<number, { answer: string; comment: string; improvement_plan: string }> = {};
        for (const q of d.questions) {
          const existing = d.answers[q.id];
          init[q.id] = {
            answer: existing?.answer || '',
            comment: existing?.comment || '',
            improvement_plan: existing?.improvement_plan || '',
          };
        }
        setAnswers(init);
      } catch {
        toast.error('データの取得に失敗しました');
      } finally {
        setLoading(false);
      }
    })();
  }, [period.id, toast]);

  const handleSave = async (submit: boolean) => {
    if (!data) return;

    if (submit) {
      const unanswered = data.questions.filter((q) => !answers[q.id]?.answer);
      if (unanswered.length > 0) {
        toast.error(`未回答の質問が${unanswered.length}件あります`);
        return;
      }
      const noWithoutPlan = data.questions.filter(
        (q) => answers[q.id]?.answer === 'no' && !answers[q.id]?.improvement_plan?.trim()
      );
      if (noWithoutPlan.length > 0) {
        toast.error('「いいえ」と回答した質問には改善計画を入力してください');
        return;
      }
      if (!confirm('自己評価を提出しますか？提出後は変更できません。')) return;
    }

    setSaving(true);
    try {
      const payload = {
        period_id: period.id,
        submit,
        answers: data.questions
          .filter((q) => answers[q.id]?.answer)
          .map((q) => ({
            question_id: q.id,
            answer: answers[q.id].answer,
            comment: answers[q.id].comment || null,
            improvement_plan: answers[q.id].improvement_plan || null,
          })),
      };
      await api.post('/api/staff/facility-evaluation/staff-evaluation', payload);
      toast.success(submit ? '自己評価を提出しました' : '下書きを保存しました');
      if (submit) onBack();
    } catch (err: unknown) {
      const msg = (err as { response?: { data?: { message?: string } } })?.response?.data?.message || '保存に失敗しました';
      toast.error(msg);
    } finally {
      setSaving(false);
    }
  };

  if (loading) {
    return (
      <div className="mx-auto max-w-4xl p-4">
        <SkeletonList items={5} />
      </div>
    );
  }

  if (!data || !data.questions.length) {
    return (
      <div className="mx-auto max-w-4xl p-4">
        <Button variant="ghost" onClick={onBack} className="mb-4">
          <MaterialIcon name="arrow_back" size={16} className="mr-1" />
          戻る
        </Button>
        <p className="text-sm text-[var(--neutral-foreground-3)]">質問が登録されていません</p>
      </div>
    );
  }

  const isSubmitted = data.evaluation?.is_submitted;

  // Group questions by category
  const categories: Record<string, Question[]> = {};
  for (const q of data.questions) {
    const cat = q.category || '未分類';
    if (!categories[cat]) categories[cat] = [];
    categories[cat].push(q);
  }

  return (
    <div className="mx-auto max-w-4xl p-4">
      <Button variant="ghost" onClick={onBack} className="mb-4">
        <MaterialIcon name="arrow_back" size={16} className="mr-1" />
        戻る
      </Button>

      <div className="mb-4 flex items-center justify-between">
        <h1 className="text-lg font-semibold">スタッフ自己評価 — {period.title}</h1>
        {isSubmitted && <Badge variant="success">提出済み</Badge>}
      </div>

      {isSubmitted ? (
        <Card>
          <CardBody>
            <p className="text-sm text-[var(--neutral-foreground-3)]">
              この自己評価は{data.evaluation?.submitted_at ? formatDate(data.evaluation.submitted_at) : ''}に提出済みです。
            </p>
          </CardBody>
        </Card>
      ) : (
        <>
          {Object.entries(categories).map(([cat, questions]) => (
            <Card key={cat} className="mb-4">
              <CardHeader>
                <CardTitle>{cat}</CardTitle>
              </CardHeader>
              <CardBody>
                <div className="space-y-6">
                  {questions.map((q) => (
                    <div key={q.id} className="border-b border-[var(--neutral-stroke-3)] pb-4 last:border-b-0 last:pb-0">
                      <p className="mb-2 text-sm font-medium text-[var(--neutral-foreground-1)]">
                        {q.question_number}. {q.question_text}
                      </p>
                      <div className="mb-2 flex flex-wrap gap-2">
                        {(['yes', 'neutral', 'no'] as const).map((val) => (
                          <button
                            key={val}
                            onClick={() => setAnswers((prev) => ({ ...prev, [q.id]: { ...prev[q.id], answer: val } }))}
                            className={`rounded-md border px-3 py-1.5 text-xs font-medium transition-colors ${
                              answers[q.id]?.answer === val
                                ? val === 'yes'
                                  ? 'border-[var(--status-success-fg)] bg-[var(--status-success-fg)] text-white'
                                  : val === 'no'
                                    ? 'border-[var(--status-danger-fg)] bg-[var(--status-danger-fg)] text-white'
                                    : 'border-[var(--status-warning-fg)] bg-[var(--status-warning-fg)] text-white'
                                : 'border-[var(--neutral-stroke-1)] bg-[var(--neutral-background-1)] text-[var(--neutral-foreground-2)] hover:bg-[var(--neutral-background-3)]'
                            }`}
                          >
                            {ANSWER_LABELS[val]}
                          </button>
                        ))}
                      </div>
                      <textarea
                        placeholder="コメント（任意）"
                        value={answers[q.id]?.comment || ''}
                        onChange={(e) => setAnswers((prev) => ({ ...prev, [q.id]: { ...prev[q.id], comment: e.target.value } }))}
                        rows={2}
                        className="mb-1 w-full rounded-md border border-[var(--neutral-stroke-1)] bg-[var(--neutral-background-1)] px-3 py-2 text-sm"
                      />
                      {answers[q.id]?.answer === 'no' && (
                        <textarea
                          placeholder="改善計画（必須）"
                          value={answers[q.id]?.improvement_plan || ''}
                          onChange={(e) => setAnswers((prev) => ({ ...prev, [q.id]: { ...prev[q.id], improvement_plan: e.target.value } }))}
                          rows={2}
                          className="w-full rounded-md border border-[var(--status-danger-fg)] bg-[var(--neutral-background-1)] px-3 py-2 text-sm"
                        />
                      )}
                    </div>
                  ))}
                </div>
              </CardBody>
            </Card>
          ))}

          <div className="sticky bottom-0 flex gap-2 border-t border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-1)] py-3">
            <Button variant="outline" onClick={() => handleSave(false)} disabled={saving}>
              下書き保存
            </Button>
            <Button variant="primary" onClick={() => handleSave(true)} disabled={saving}>
              {saving ? '送信中...' : '提出する'}
            </Button>
          </div>
        </>
      )}
    </div>
  );
}

// ---------------------------------------------------------------------------
// Response Status View
// ---------------------------------------------------------------------------

function ResponseStatusView({ period, onBack }: { period: Period; onBack: () => void }) {
  const toast = useToast();
  const [loading, setLoading] = useState(true);
  const [guardianResponses, setGuardianResponses] = useState<ResponseUser[]>([]);
  const [staffResponses, setStaffResponses] = useState<ResponseUser[]>([]);

  useEffect(() => {
    (async () => {
      try {
        const res = await api.get(`/api/staff/facility-evaluation/periods/${period.id}/status`);
        setGuardianResponses(res.data.data.guardian_responses || []);
        setStaffResponses(res.data.data.staff_responses || []);
      } catch {
        toast.error('回答状況の取得に失敗しました');
      } finally {
        setLoading(false);
      }
    })();
  }, [period.id, toast]);

  if (loading) {
    return (
      <div className="mx-auto max-w-4xl p-4">
        <SkeletonList items={5} />
      </div>
    );
  }

  return (
    <div className="mx-auto max-w-4xl p-4">
      <Button variant="ghost" onClick={onBack} className="mb-4">
        <MaterialIcon name="arrow_back" size={16} className="mr-1" />
        戻る
      </Button>
      <h1 className="mb-4 text-lg font-semibold">回答状況 — {period.title}</h1>

      <Tabs
        items={[
          {
            key: 'guardian',
            label: '保護者',
            badge: guardianResponses.filter((r) => r.is_submitted).length,
            content: <ResponseTable responses={guardianResponses} nameKey="guardian_name" periodId={period.id} type="guardian" />,
          },
          {
            key: 'staff',
            label: 'スタッフ',
            badge: staffResponses.filter((r) => r.is_submitted).length,
            content: <ResponseTable responses={staffResponses} nameKey="staff_name" periodId={period.id} type="staff" />,
          },
        ]}
      />
    </div>
  );
}

function ResponseTable({ responses, nameKey, periodId, type }: { responses: ResponseUser[]; nameKey: string; periodId: number; type: 'guardian' | 'staff' }) {
  const toast = useToast();
  const submitted = responses.filter((r) => r.is_submitted).length;
  const [detailModal, setDetailModal] = useState<{ name: string; answers: { question_number: number; question_text: string; category: string; answer: string; comment: string | null; improvement_plan?: string | null }[] } | null>(null);
  const [loadingDetail, setLoadingDetail] = useState(false);

  const viewDetail = async (r: ResponseUser) => {
    if (!r.is_submitted) return;
    setLoadingDetail(true);
    try {
      const name = (r as unknown as Record<string, unknown>)[nameKey] as string;
      if (type === 'guardian') {
        const res = await api.get(`/api/staff/facility-evaluation/responses/${r.id}/pdf`);
        setDetailModal({ name, answers: res.data.data.answers || [] });
      } else {
        // スタッフ回答詳細を取得
        const res = await api.get('/api/staff/facility-evaluation/staff-evaluation-detail', { params: { evaluation_id: r.id } });
        setDetailModal({ name, answers: res.data.data.answers || [] });
      }
    } catch {
      toast.error('回答詳細の取得に失敗しました');
    } finally {
      setLoadingDetail(false);
    }
  };

  return (
    <div>
      <div className="mb-3 flex items-center gap-3 text-sm">
        <span className="text-[var(--neutral-foreground-2)]">
          提出済み: <strong>{submitted}</strong> / {responses.length}
        </span>
        {responses.length > 0 && (
          <div className="h-2 flex-1 overflow-hidden rounded-full bg-[var(--neutral-background-4)]">
            <div
              className="h-full rounded-full bg-[var(--status-success-fg)] transition-all"
              style={{ width: `${(submitted / responses.length) * 100}%` }}
            />
          </div>
        )}
      </div>
      <div className="overflow-x-auto">
        <table className="w-full text-sm">
          <thead>
            <tr className="border-b border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-3)]">
              <th className="px-4 py-2 text-left font-semibold">氏名</th>
              <th className="px-4 py-2 text-left font-semibold">状態</th>
              <th className="px-4 py-2 text-left font-semibold">提出日</th>
              <th className="px-4 py-2 text-center font-semibold">操作</th>
            </tr>
          </thead>
          <tbody>
            {responses.map((r) => (
              <tr key={r.id} className="border-b border-[var(--neutral-stroke-3)]">
                <td className="px-4 py-2 text-[var(--neutral-foreground-1)]">
                  {(r as unknown as Record<string, unknown>)[nameKey] as string}
                </td>
                <td className="px-4 py-2">
                  <Badge variant={r.is_submitted ? 'success' : r.started_at ? 'warning' : 'default'}>
                    {r.is_submitted ? '提出済み' : r.started_at ? '入力中' : '未開始'}
                  </Badge>
                </td>
                <td className="px-4 py-2 text-[var(--neutral-foreground-3)]">
                  {r.submitted_at ? formatDate(r.submitted_at) : '—'}
                </td>
                <td className="px-4 py-2 text-center">
                  {r.is_submitted && (
                    <Button variant="ghost" size="sm" onClick={() => viewDetail(r)} disabled={loadingDetail}>
                      <MaterialIcon name="visibility" size={16} className="mr-1" />
                      閲覧
                    </Button>
                  )}
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>

      {/* 回答詳細モーダル */}
      <Modal isOpen={!!detailModal} onClose={() => setDetailModal(null)} title={`${detailModal?.name ?? ''} の回答内容`} size="lg">
        {detailModal && (
          <div className="max-h-[70vh] overflow-y-auto">
            <table className="w-full text-sm">
              <thead>
                <tr className="border-b border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-3)]">
                  <th className="px-3 py-2 text-left text-xs font-semibold">No.</th>
                  <th className="px-3 py-2 text-left text-xs font-semibold">質問</th>
                  <th className="px-3 py-2 text-center text-xs font-semibold">回答</th>
                  <th className="px-3 py-2 text-left text-xs font-semibold">コメント</th>
                </tr>
              </thead>
              <tbody>
                {detailModal.answers.map((a, i) => (
                  <tr key={i} className="border-b border-[var(--neutral-stroke-3)]">
                    <td className="px-3 py-2 text-xs text-[var(--neutral-foreground-3)]">{a.question_number}</td>
                    <td className="max-w-xs px-3 py-2 text-xs">{a.question_text}</td>
                    <td className="px-3 py-2 text-center">
                      <Badge variant={a.answer === 'yes' ? 'success' : a.answer === 'no' ? 'danger' : a.answer === 'neutral' ? 'warning' : 'default'}>
                        {ANSWER_LABELS[a.answer] || a.answer}
                      </Badge>
                    </td>
                    <td className="max-w-xs px-3 py-2 text-xs text-[var(--neutral-foreground-2)]">
                      {a.comment || ''}
                      {a.improvement_plan && (
                        <div className="mt-1 text-[var(--status-danger-fg)]">改善計画: {a.improvement_plan}</div>
                      )}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </Modal>
    </div>
  );
}

// ---------------------------------------------------------------------------
// Summary View
// ---------------------------------------------------------------------------

function SummaryTable({
  items,
  commentsByQuestion,
  periodId,
  editable,
  onFacilityCommentSaved,
}: {
  items: SummaryItem[];
  commentsByQuestion?: Record<number, string[]>;
  periodId: number;
  editable?: boolean;
  onFacilityCommentSaved?: () => void;
}) {
  const toast = useToast();
  const [editingComment, setEditingComment] = useState<{ questionId: number; value: string } | null>(null);
  const [savingComment, setSavingComment] = useState(false);
  const [editingCounts, setEditingCounts] = useState<{ questionId: number; yes: number; neutral: number; no: number; unknown: number } | null>(null);
  const [savingCounts, setSavingCounts] = useState(false);

  const handleSaveCounts = async () => {
    if (!editingCounts) return;
    setSavingCounts(true);
    try {
      await api.post('/api/staff/facility-evaluation/update-summary-counts', {
        period_id: periodId,
        question_id: editingCounts.questionId,
        yes_count: editingCounts.yes,
        neutral_count: editingCounts.neutral,
        no_count: editingCounts.no,
        unknown_count: editingCounts.unknown,
      });
      toast.success('集計値を更新しました');
      setEditingCounts(null);
      onFacilityCommentSaved?.();
    } catch {
      toast.error('更新に失敗しました');
    } finally {
      setSavingCounts(false);
    }
  };

  const handleSaveFacilityComment = async () => {
    if (!editingComment) return;
    setSavingComment(true);
    try {
      await api.post('/api/staff/facility-evaluation/facility-comment', {
        period_id: periodId,
        question_id: editingComment.questionId,
        facility_comment: editingComment.value,
      });
      toast.success('事業所コメントを保存しました');
      setEditingComment(null);
      onFacilityCommentSaved?.();
    } catch {
      toast.error('保存に失敗しました');
    } finally {
      setSavingComment(false);
    }
  };

  return (
    <div className="overflow-x-auto">
      <table className="w-full text-sm">
        <thead>
          <tr className="border-b border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-3)]">
            <th className="px-3 py-2 text-left font-semibold">No.</th>
            <th className="px-3 py-2 text-left font-semibold">質問</th>
            <th className="px-3 py-2 text-center font-semibold">はい</th>
            <th className="px-3 py-2 text-center font-semibold">どちらとも</th>
            <th className="px-3 py-2 text-center font-semibold">いいえ</th>
            <th className="px-3 py-2 text-center font-semibold">わからない</th>
            <th className="px-3 py-2 text-center font-semibold">はい%</th>
          </tr>
        </thead>
        <tbody>
          {items.map((item) => {
            const userComments = commentsByQuestion?.[item.question_id] || [];
            return (
              <React.Fragment key={item.question_id}>
                <tr className="border-b border-[var(--neutral-stroke-3)]">
                  <td className="px-3 py-2 text-[var(--neutral-foreground-3)]">{item.question_number}</td>
                  <td className="max-w-xs px-3 py-2 text-[var(--neutral-foreground-1)]">{item.question_text}</td>
                  {editingCounts?.questionId === item.question_id ? (
                    <>
                      <td className="px-1 py-1 text-center">
                        <input type="number" min={0} className="w-14 rounded border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-1)] px-1 py-0.5 text-center text-sm" value={editingCounts.yes} onChange={(e) => setEditingCounts({ ...editingCounts, yes: Number(e.target.value) })} />
                      </td>
                      <td className="px-1 py-1 text-center">
                        <input type="number" min={0} className="w-14 rounded border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-1)] px-1 py-0.5 text-center text-sm" value={editingCounts.neutral} onChange={(e) => setEditingCounts({ ...editingCounts, neutral: Number(e.target.value) })} />
                      </td>
                      <td className="px-1 py-1 text-center">
                        <input type="number" min={0} className="w-14 rounded border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-1)] px-1 py-0.5 text-center text-sm" value={editingCounts.no} onChange={(e) => setEditingCounts({ ...editingCounts, no: Number(e.target.value) })} />
                      </td>
                      <td className="px-1 py-1 text-center">
                        <input type="number" min={0} className="w-14 rounded border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-1)] px-1 py-0.5 text-center text-sm" value={editingCounts.unknown} onChange={(e) => setEditingCounts({ ...editingCounts, unknown: Number(e.target.value) })} />
                      </td>
                      <td className="px-1 py-1 text-center">
                        <div className="flex gap-1 justify-center">
                          <Button size="sm" variant="primary" onClick={handleSaveCounts} disabled={savingCounts}>保存</Button>
                          <Button size="sm" variant="ghost" onClick={() => setEditingCounts(null)}>取消</Button>
                        </div>
                      </td>
                    </>
                  ) : (
                    <>
                      <td className={`px-3 py-2 text-center ${editable ? 'cursor-pointer hover:bg-[var(--neutral-background-3)]' : ''}`} onClick={() => editable && setEditingCounts({ questionId: item.question_id, yes: item.yes_count, neutral: item.neutral_count, no: item.no_count, unknown: item.unknown_count })}>{item.yes_count}</td>
                      <td className={`px-3 py-2 text-center ${editable ? 'cursor-pointer hover:bg-[var(--neutral-background-3)]' : ''}`} onClick={() => editable && setEditingCounts({ questionId: item.question_id, yes: item.yes_count, neutral: item.neutral_count, no: item.no_count, unknown: item.unknown_count })}>{item.neutral_count}</td>
                      <td className={`px-3 py-2 text-center ${editable ? 'cursor-pointer hover:bg-[var(--neutral-background-3)]' : ''}`} onClick={() => editable && setEditingCounts({ questionId: item.question_id, yes: item.yes_count, neutral: item.neutral_count, no: item.no_count, unknown: item.unknown_count })}>{item.no_count}</td>
                      <td className={`px-3 py-2 text-center ${editable ? 'cursor-pointer hover:bg-[var(--neutral-background-3)]' : ''}`} onClick={() => editable && setEditingCounts({ questionId: item.question_id, yes: item.yes_count, neutral: item.neutral_count, no: item.no_count, unknown: item.unknown_count })}>{item.unknown_count}</td>
                      <td className="px-3 py-2 text-center font-medium">
                        <span className={item.yes_percentage >= 80 ? 'text-[var(--status-success-fg)]' : item.yes_percentage >= 50 ? 'text-[var(--status-warning-fg)]' : 'text-[var(--status-danger-fg)]'}>
                          {item.yes_percentage}%
                        </span>
                      </td>
                    </>
                  )}
                </tr>
                {/* コメント行 */}
                {(userComments.length > 0 || item.facility_comment || editable) && (
                  <tr className="bg-[var(--neutral-background-2)]">
                    <td></td>
                    <td colSpan={6} className="px-3 py-2">
                      {userComments.length > 0 && (
                        <div className="mb-2">
                          <span className="text-xs font-semibold text-[var(--neutral-foreground-3)]">回答者コメント:</span>
                          <ul className="mt-1 list-inside list-disc text-xs text-[var(--neutral-foreground-2)]">
                            {userComments.map((c, i) => (
                              <li key={i}>{c}</li>
                            ))}
                          </ul>
                        </div>
                      )}
                      {editable ? (
                        <div className="flex items-start gap-2">
                          <div className="flex-1">
                            <span className="text-xs font-semibold text-[var(--neutral-foreground-3)]">事業所コメント:</span>
                            {editingComment?.questionId === item.question_id ? (
                              <div className="mt-1 flex gap-2">
                                <textarea
                                  className="flex-1 rounded border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-1)] p-1.5 text-xs"
                                  rows={2}
                                  value={editingComment.value}
                                  onChange={(e) => setEditingComment({ ...editingComment, value: e.target.value })}
                                />
                                <div className="flex flex-col gap-1">
                                  <Button size="sm" variant="primary" onClick={handleSaveFacilityComment} disabled={savingComment}>
                                    保存
                                  </Button>
                                  <Button size="sm" variant="ghost" onClick={() => setEditingComment(null)}>
                                    取消
                                  </Button>
                                </div>
                              </div>
                            ) : (
                              <div
                                className="mt-1 cursor-pointer rounded border border-dashed border-[var(--neutral-stroke-3)] p-1.5 text-xs text-[var(--neutral-foreground-2)] hover:bg-[var(--neutral-background-3)]"
                                onClick={() => setEditingComment({ questionId: item.question_id, value: item.facility_comment || '' })}
                              >
                                {item.facility_comment || 'クリックして事業所コメントを入力...'}
                              </div>
                            )}
                          </div>
                        </div>
                      ) : item.facility_comment ? (
                        <div>
                          <span className="text-xs font-semibold text-[var(--neutral-foreground-3)]">事業所コメント:</span>
                          <p className="mt-1 text-xs text-[var(--neutral-foreground-2)]">{item.facility_comment}</p>
                        </div>
                      ) : null}
                    </td>
                  </tr>
                )}
              </React.Fragment>
            );
          })}
        </tbody>
      </table>
    </div>
  );
}

function SummaryView({
  period,
  onBack,
  onRefresh,
}: {
  period: Period;
  onBack: () => void;
  onRefresh: () => void;
}) {
  const toast = useToast();
  const [loading, setLoading] = useState(true);
  const [guardianSummary, setGuardianSummary] = useState<SummaryItem[]>([]);
  const [staffSummary, setStaffSummary] = useState<SummaryItem[]>([]);
  const [guardianComments, setGuardianComments] = useState<Record<number, string[]>>({});
  const [staffComments, setStaffComments] = useState<Record<number, string[]>>({});
  const [totalRespondents, setTotalRespondents] = useState(0);
  const [totalStaffRespondents, setTotalStaffRespondents] = useState(0);
  const [aggregating, setAggregating] = useState(false);
  const [activeTab, setActiveTab] = useState<'guardian' | 'staff'>('guardian');

  const fetchSummary = useCallback(async () => {
    try {
      const res = await api.get('/api/staff/facility-evaluation/summary', { params: { period_id: period.id } });
      setGuardianSummary(res.data.data.summary || []);
      setStaffSummary(res.data.data.staff_summary || []);
      setGuardianComments(res.data.data.comments_by_question || {});
      setStaffComments(res.data.data.staff_comments_by_question || {});
      setTotalRespondents(res.data.data.total_respondents || 0);
      setTotalStaffRespondents(res.data.data.total_staff_respondents || 0);
    } catch {
      toast.error('集計結果の取得に失敗しました');
    } finally {
      setLoading(false);
    }
  }, [period.id, toast]);

  useEffect(() => { fetchSummary(); }, [fetchSummary]);

  const handleAggregate = async () => {
    if (!confirm('集計を実行しますか？')) return;
    setAggregating(true);
    try {
      await api.post('/api/staff/facility-evaluation/aggregate', { period_id: period.id });
      toast.success('集計が完了しました');
      fetchSummary();
      onRefresh();
    } catch {
      toast.error('集計に失敗しました');
    } finally {
      setAggregating(false);
    }
  };

  if (loading) {
    return (
      <div className="mx-auto max-w-4xl p-4">
        <SkeletonList items={5} />
      </div>
    );
  }

  const currentSummary = activeTab === 'guardian' ? guardianSummary : staffSummary;
  const currentRespondents = activeTab === 'guardian' ? totalRespondents : totalStaffRespondents;
  const currentComments = activeTab === 'guardian' ? guardianComments : staffComments;

  // Group by category
  const categories: Record<string, SummaryItem[]> = {};
  for (const item of currentSummary) {
    const cat = item.category || '未分類';
    if (!categories[cat]) categories[cat] = [];
    categories[cat].push(item);
  }

  return (
    <div className="mx-auto max-w-4xl p-4">
      <Button variant="ghost" onClick={onBack} className="mb-4">
        <MaterialIcon name="arrow_back" size={16} className="mr-1" />
        戻る
      </Button>

      <div className="mb-4 flex items-center justify-between">
        <div>
          <h1 className="text-lg font-semibold">集計結果 — {period.title}</h1>
        </div>
        {period.status === 'aggregating' && (
          <Button variant="primary" size="sm" onClick={handleAggregate} disabled={aggregating}>
            <MaterialIcon name="calculate" size={16} className="mr-1" />
            {aggregating ? '集計中...' : '再集計'}
          </Button>
        )}
      </div>

      {/* タブ切り替え */}
      <div className="mb-4 flex gap-1 rounded-lg bg-[var(--neutral-background-3)] p-1">
        <button
          className={`flex-1 rounded-md px-4 py-2 text-sm font-medium transition-colors ${activeTab === 'guardian' ? 'bg-[var(--neutral-background-1)] text-[var(--neutral-foreground-1)] shadow-sm' : 'text-[var(--neutral-foreground-3)] hover:text-[var(--neutral-foreground-2)]'}`}
          onClick={() => setActiveTab('guardian')}
        >
          保護者評価
        </button>
        <button
          className={`flex-1 rounded-md px-4 py-2 text-sm font-medium transition-colors ${activeTab === 'staff' ? 'bg-[var(--neutral-background-1)] text-[var(--neutral-foreground-1)] shadow-sm' : 'text-[var(--neutral-foreground-3)] hover:text-[var(--neutral-foreground-2)]'}`}
          onClick={() => setActiveTab('staff')}
        >
          事業所内評価（スタッフ）
        </button>
      </div>

      <div className="mb-3 flex items-center justify-between">
        <p className="text-xs text-[var(--neutral-foreground-3)]">回答者数: {currentRespondents}名</p>
        {currentSummary.length > 0 && (
          <Button
            variant="outline"
            size="sm"
            onClick={async () => {
              try {
                const res = await api.get(`/api/staff/facility-evaluation/summary-pdf?period_id=${period.id}&type=${activeTab}`, { responseType: 'blob' });
                const url = URL.createObjectURL(new Blob([res.data], { type: 'application/pdf' }));
                const a = document.createElement('a');
                a.href = url;
                a.download = `facility-evaluation-${activeTab}-${period.id}.pdf`;
                a.click();
                URL.revokeObjectURL(url);
              } catch {
                toast.error('PDF出力に失敗しました');
              }
            }}
          >
            <MaterialIcon name="picture_as_pdf" size={16} className="mr-1" />
            PDF出力
          </Button>
        )}
      </div>

      {currentSummary.length === 0 ? (
        <Card>
          <CardBody>
            <div className="py-8 text-center text-[var(--neutral-foreground-4)]">
              <MaterialIcon name="bar_chart" size={32} className="mx-auto mb-2" />
              <p className="text-sm">集計結果がありません</p>
              <Button variant="primary" size="sm" className="mt-3" onClick={handleAggregate} disabled={aggregating}>
                集計を実行
              </Button>
            </div>
          </CardBody>
        </Card>
      ) : (
        Object.entries(categories).map(([cat, items]) => (
          <Card key={cat} className="mb-4">
            <CardHeader>
              <CardTitle>{cat}</CardTitle>
            </CardHeader>
            <CardBody>
              <SummaryTable
                items={items}
                commentsByQuestion={currentComments}
                periodId={period.id}
                editable={period.status === 'aggregating'}
                onFacilityCommentSaved={fetchSummary}
              />
            </CardBody>
          </Card>
        ))
      )}
    </div>
  );
}

// ---------------------------------------------------------------------------
// Self Evaluation View (別紙3)
// ---------------------------------------------------------------------------

interface SelfEvalItem {
  category: string;
  sort_order: number;
  current_status: string;
  issues: string;
  improvement_plan: string;
}

function SelfEvaluationView({
  period,
  onBack,
}: {
  period: Period;
  onBack: () => void;
}) {
  const toast = useToast();
  const [loading, setLoading] = useState(true);
  const [generating, setGenerating] = useState(false);
  const [saving, setSaving] = useState(false);
  const [strengths, setStrengths] = useState<SelfEvalItem[]>([
    { category: 'strength', sort_order: 1, current_status: '', issues: '', improvement_plan: '' },
    { category: 'strength', sort_order: 2, current_status: '', issues: '', improvement_plan: '' },
    { category: 'strength', sort_order: 3, current_status: '', issues: '', improvement_plan: '' },
  ]);
  const [weaknesses, setWeaknesses] = useState<SelfEvalItem[]>([
    { category: 'weakness', sort_order: 1, current_status: '', issues: '', improvement_plan: '' },
    { category: 'weakness', sort_order: 2, current_status: '', issues: '', improvement_plan: '' },
    { category: 'weakness', sort_order: 3, current_status: '', issues: '', improvement_plan: '' },
  ]);

  const fetchData = useCallback(async () => {
    try {
      const res = await api.get('/api/staff/facility-evaluation/self-summary', { params: { period_id: period.id } });
      const items: SelfEvalItem[] = res.data.data.self_summary_items || [];
      const s = items.filter((i: SelfEvalItem) => i.category === 'strength').sort((a: SelfEvalItem, b: SelfEvalItem) => a.sort_order - b.sort_order);
      const w = items.filter((i: SelfEvalItem) => i.category === 'weakness').sort((a: SelfEvalItem, b: SelfEvalItem) => a.sort_order - b.sort_order);
      if (s.length > 0) setStrengths(prev => prev.map((p, i) => s[i] || p));
      if (w.length > 0) setWeaknesses(prev => prev.map((p, i) => w[i] || p));
    } catch {
      toast.error('データの取得に失敗しました');
    } finally {
      setLoading(false);
    }
  }, [period.id, toast]);

  useEffect(() => { fetchData(); }, [fetchData]);

  const handleGenerate = async () => {
    if (!confirm('AIで自己評価総括表を生成しますか？既存の内容は上書きされます。')) return;
    setGenerating(true);
    try {
      const res = await api.post('/api/staff/facility-evaluation/generate-self-evaluation', { period_id: period.id });
      const data = res.data.data;
      if (data.strengths) {
        setStrengths(data.strengths.map((s: { current_status?: string; improvement_plan?: string }, i: number) => ({
          category: 'strength', sort_order: i + 1,
          current_status: s.current_status || '', issues: '', improvement_plan: s.improvement_plan || '',
        })));
      }
      if (data.weaknesses) {
        setWeaknesses(data.weaknesses.map((w: { issues?: string; improvement_plan?: string }, i: number) => ({
          category: 'weakness', sort_order: i + 1,
          current_status: '', issues: w.issues || '', improvement_plan: w.improvement_plan || '',
        })));
      }
      toast.success('AIで生成しました');
    } catch {
      toast.error('AI生成に失敗しました');
    } finally {
      setGenerating(false);
    }
  };

  const handleSave = async () => {
    setSaving(true);
    try {
      await api.post('/api/staff/facility-evaluation/self-summary', {
        period_id: period.id,
        items: [...strengths, ...weaknesses],
      });
      toast.success('保存しました');
    } catch {
      toast.error('保存に失敗しました');
    } finally {
      setSaving(false);
    }
  };

  const updateStrength = (index: number, field: keyof SelfEvalItem, value: string) => {
    setStrengths(prev => prev.map((s, i) => i === index ? { ...s, [field]: value } : s));
  };

  const updateWeakness = (index: number, field: keyof SelfEvalItem, value: string) => {
    setWeaknesses(prev => prev.map((w, i) => i === index ? { ...w, [field]: value } : w));
  };

  if (loading) {
    return (
      <div className="mx-auto max-w-5xl p-4">
        <SkeletonList items={5} />
      </div>
    );
  }

  return (
    <div className="mx-auto max-w-5xl p-4">
      <Button variant="ghost" onClick={onBack} className="mb-4">
        <MaterialIcon name="arrow_back" size={16} className="mr-1" />
        戻る
      </Button>

      <div className="mb-4 flex items-center justify-between">
        <div>
          <h1 className="text-lg font-semibold">自己評価結果（別紙3） — {period.title}</h1>
          <p className="text-xs text-[var(--neutral-foreground-3)]">事業所における自己評価結果（公表）</p>
        </div>
        <div className="flex gap-2">
          <Button variant="outline" size="sm" onClick={handleGenerate} disabled={generating}>
            <MaterialIcon name="auto_awesome" size={16} className="mr-1" />
            {generating ? 'AI生成中...' : 'AI生成'}
          </Button>
          <Button variant="outline" size="sm" onClick={async () => {
            try {
              const res = await api.get(`/api/staff/facility-evaluation/self-evaluation-pdf?period_id=${period.id}`, { responseType: 'blob' });
              const url = URL.createObjectURL(new Blob([res.data], { type: 'application/pdf' }));
              const a = document.createElement('a');
              a.href = url;
              a.download = `facility-self-evaluation-${period.id}.pdf`;
              a.click();
              URL.revokeObjectURL(url);
            } catch {
              toast.error('PDF出力に失敗しました');
            }
          }}>
            <MaterialIcon name="picture_as_pdf" size={16} className="mr-1" />
            PDF出力
          </Button>
          <Button variant="primary" size="sm" onClick={handleSave} disabled={saving}>
            <MaterialIcon name="save" size={16} className="mr-1" />
            {saving ? '保存中...' : '保存'}
          </Button>
        </div>
      </div>

      {/* 強み */}
      <Card className="mb-4">
        <CardHeader>
          <CardTitle className="text-[var(--status-success-fg)]">
            事業所の強み — より強化・充実を図ることが期待されること
          </CardTitle>
        </CardHeader>
        <CardBody>
          <div className="overflow-x-auto">
            <table className="w-full text-sm">
              <thead>
                <tr className="border-b border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-3)]">
                  <th className="w-8 px-2 py-2 text-center">#</th>
                  <th className="px-3 py-2 text-left">工夫していることや意識的に行っている取組等</th>
                  <th className="px-3 py-2 text-left">さらに充実を図るための取組等</th>
                </tr>
              </thead>
              <tbody>
                {strengths.map((item, i) => (
                  <tr key={i} className="border-b border-[var(--neutral-stroke-3)]">
                    <td className="px-2 py-2 text-center font-medium text-[var(--neutral-foreground-3)]">{i + 1}</td>
                    <td className="px-3 py-2">
                      <textarea
                        className="w-full rounded border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-1)] p-2 text-sm"
                        rows={3}
                        value={item.current_status}
                        onChange={(e) => updateStrength(i, 'current_status', e.target.value)}
                      />
                    </td>
                    <td className="px-3 py-2">
                      <textarea
                        className="w-full rounded border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-1)] p-2 text-sm"
                        rows={3}
                        value={item.improvement_plan}
                        onChange={(e) => updateStrength(i, 'improvement_plan', e.target.value)}
                      />
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </CardBody>
      </Card>

      {/* 弱み */}
      <Card className="mb-4">
        <CardHeader>
          <CardTitle className="text-[var(--status-danger-fg)]">
            事業所の弱み — 事業所の課題や改善が必要だと思われること
          </CardTitle>
        </CardHeader>
        <CardBody>
          <div className="overflow-x-auto">
            <table className="w-full text-sm">
              <thead>
                <tr className="border-b border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-3)]">
                  <th className="w-8 px-2 py-2 text-center">#</th>
                  <th className="px-3 py-2 text-left">事業所として考えている課題の要因等</th>
                  <th className="px-3 py-2 text-left">改善に向けて必要な取組や工夫が必要な点等</th>
                </tr>
              </thead>
              <tbody>
                {weaknesses.map((item, i) => (
                  <tr key={i} className="border-b border-[var(--neutral-stroke-3)]">
                    <td className="px-2 py-2 text-center font-medium text-[var(--neutral-foreground-3)]">{i + 1}</td>
                    <td className="px-3 py-2">
                      <textarea
                        className="w-full rounded border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-1)] p-2 text-sm"
                        rows={3}
                        value={item.issues}
                        onChange={(e) => updateWeakness(i, 'issues', e.target.value)}
                      />
                    </td>
                    <td className="px-3 py-2">
                      <textarea
                        className="w-full rounded border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-1)] p-2 text-sm"
                        rows={3}
                        value={item.improvement_plan}
                        onChange={(e) => updateWeakness(i, 'improvement_plan', e.target.value)}
                      />
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </CardBody>
      </Card>
    </div>
  );
}
