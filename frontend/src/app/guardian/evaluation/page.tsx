'use client';

import { useState, useEffect, useCallback } from 'react';
import api from '@/lib/api';
import { Card, CardHeader, CardTitle, CardBody } from '@/components/ui/Card';
import { Button } from '@/components/ui/Button';
import { Badge } from '@/components/ui/Badge';
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
  status: string;
  guardian_deadline: string | null;
  staff_deadline: string | null;
}

interface EvaluationQuestion {
  id: number;
  question_type: string;
  category: string;
  question_number: number;
  question_text: string;
}

interface ExistingAnswer {
  question_id: number;
  answer: string | null;
  comment: string;
}

interface EvaluationData {
  period: EvaluationPeriod | null;
  evaluation: { id: number; is_submitted: boolean; submitted_at: string | null } | null;
  questions: EvaluationQuestion[];
  answers: Record<number, ExistingAnswer>;
  message?: string;
}

const ANSWER_OPTIONS = [
  { value: 'yes', label: 'はい' },
  { value: 'neutral', label: 'どちらともいえない' },
  { value: 'no', label: 'いいえ' },
  { value: 'unknown', label: 'わからない' },
] as const;

function answerColor(answer: string): string {
  switch (answer) {
    case 'yes': return 'var(--status-success-fg, #22c55e)';
    case 'neutral': return 'var(--status-warning-fg, #f59e0b)';
    case 'no': return 'var(--status-danger-fg, #ef4444)';
    default: return 'var(--neutral-foreground-4, #9ca3af)';
  }
}

// ---------------------------------------------------------------------------
// Component
// ---------------------------------------------------------------------------

export default function FacilityEvaluationPage() {
  const toast = useToast();
  const [data, setData] = useState<EvaluationData | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [isSaving, setIsSaving] = useState(false);
  const [answers, setAnswers] = useState<Record<number, { answer: string | null; comment: string }>>({});
  const [unansweredIds, setUnansweredIds] = useState<number[]>([]);

  const fetchData = useCallback(async () => {
    setIsLoading(true);
    try {
      const res = await api.get('/api/guardian/evaluation');
      const d = res.data?.data;
      setData(d);

      // Initialize answers from existing data
      if (d?.answers) {
        const initial: Record<number, { answer: string | null; comment: string }> = {};
        if (typeof d.answers === 'object') {
          Object.entries(d.answers).forEach(([qId, ans]) => {
            const a = ans as ExistingAnswer;
            initial[Number(qId)] = {
              answer: a.answer || null,
              comment: a.comment || '',
            };
          });
        }
        setAnswers(initial);
      }
    } catch {
      setData(null);
    } finally {
      setIsLoading(false);
    }
  }, []);

  useEffect(() => { fetchData(); }, [fetchData]);

  const updateAnswer = (questionId: number, value: string) => {
    setAnswers((prev) => ({
      ...prev,
      [questionId]: {
        answer: value,
        comment: prev[questionId]?.comment || '',
      },
    }));
    // Clear unanswered highlight
    setUnansweredIds((prev) => prev.filter((id) => id !== questionId));
  };

  const updateComment = (questionId: number, comment: string) => {
    setAnswers((prev) => ({
      ...prev,
      [questionId]: {
        answer: prev[questionId]?.answer || null,
        comment,
      },
    }));
  };

  const handleSave = async (isSubmit: boolean) => {
    if (!data?.period) return;

    // Validation on submit
    if (isSubmit) {
      const unanswered = (data.questions || [])
        .filter((q) => !answers[q.id]?.answer)
        .map((q) => q.id);
      if (unanswered.length > 0) {
        setUnansweredIds(unanswered);
        toast.error('未回答の質問があります。すべての質問に回答してください。');
        // Scroll to first unanswered
        const el = document.getElementById(`question_${unanswered[0]}`);
        if (el) {
          setTimeout(() => el.scrollIntoView({ behavior: 'smooth', block: 'center' }), 100);
        }
        return;
      }
    }

    const answersList = (data.questions || []).map((q) => ({
      question_id: q.id,
      answer: answers[q.id]?.answer || null,
      comment: answers[q.id]?.comment || '',
    }));

    setIsSaving(true);
    try {
      await api.post('/api/guardian/evaluation', {
        period_id: data.period.id,
        answers: answersList,
        is_submit: isSubmit,
      });
      if (isSubmit) {
        toast.success('ご回答いただきありがとうございました。今後のサービス向上に活用させていただきます。');
      } else {
        toast.success('下書きを保存しました。後から続きを入力できます。');
      }
      fetchData();
    } catch {
      toast.error('保存に失敗しました');
    } finally {
      setIsSaving(false);
    }
  };

  if (isLoading) {
    return (
      <div className="space-y-4">
        <h1 className="text-2xl font-bold text-[var(--neutral-foreground-1)]">事業所評価</h1>
        {[...Array(3)].map((_, i) => <Skeleton key={i} className="h-24 w-full rounded-lg" />)}
      </div>
    );
  }

  // No active evaluation period
  if (!data?.period) {
    return (
      <div className="space-y-6">
        <h1 className="text-2xl font-bold text-[var(--neutral-foreground-1)]">事業所評価</h1>
        <Card>
          <CardBody>
            <div className="flex flex-col items-center py-8 text-center">
              <MaterialIcon name="info" size={48} className="text-[var(--neutral-foreground-4)] mb-3" />
              <h2 className="text-lg font-semibold text-[var(--neutral-foreground-1)] mb-2">
                現在、回答を受け付けている評価はありません
              </h2>
              <p className="text-sm text-[var(--neutral-foreground-3)]">
                評価期間が始まりましたらお知らせいたします。
              </p>
            </div>
          </CardBody>
        </Card>
      </div>
    );
  }

  const isSubmitted = data.evaluation?.is_submitted || false;

  // Group questions by category
  const questionsByCategory: Record<string, EvaluationQuestion[]> = {};
  (data.questions || []).forEach((q) => {
    if (!questionsByCategory[q.category]) questionsByCategory[q.category] = [];
    questionsByCategory[q.category].push(q);
  });

  return (
    <div className="space-y-6">
      <h1 className="text-2xl font-bold text-[var(--neutral-foreground-1)]">
        {data.period.title}
      </h1>

      {/* Submitted notice: 提出済みは完了メッセージのみ表示し、回答内容は隠す */}
      {isSubmitted && (
        <Card>
          <CardBody>
            <div className="py-6 text-center">
              <MaterialIcon
                name="check_circle"
                size={48}
                className="mx-auto mb-3 text-[var(--status-success-fg,#22c55e)]"
              />
              <p className="text-base font-medium text-[var(--neutral-foreground-1)] mb-1">
                {data.evaluation?.submitted_at
                  ? new Date(data.evaluation.submitted_at).toLocaleDateString('ja-JP', {
                      year: 'numeric', month: 'long', day: 'numeric',
                    })
                  : ''}
                に提出済みです
              </p>
              <p className="text-sm text-[var(--neutral-foreground-3)]">
                ご協力ありがとうございました。
              </p>
              <p className="mt-3 text-xs text-[var(--neutral-foreground-4)]">
                ※ 回答内容の確認・修正はできません。
              </p>
            </div>
          </CardBody>
        </Card>
      )}

      {/* Introduction + deadline (only if not submitted) */}
      {!isSubmitted && (
        <Card>
          <CardBody>
            <h2 className="text-lg font-semibold text-[var(--neutral-foreground-1)] mb-2">ご協力のお願い</h2>
            <p className="text-sm text-[var(--neutral-foreground-3)] leading-relaxed mb-1">
              日頃より当事業所をご利用いただきありがとうございます。
              皆様からのご意見・ご感想をもとに、今後のサービス向上に努めてまいりますので、
              アンケートへのご協力をお願いいたします。
            </p>
            <p className="text-sm text-[var(--neutral-foreground-3)] leading-relaxed">
              各質問について「はい」「どちらともいえない」「いいえ」「わからない」からお選びください。
              また、ご意見やご要望がございましたら、ご意見欄にご記入ください。
            </p>
            {data.period.guardian_deadline && (
              <div className="mt-3 flex items-center gap-2 rounded-md bg-[var(--status-warning-bg,rgba(245,158,11,0.1))] px-3 py-2 text-sm text-[var(--status-warning-fg,#f59e0b)]">
                <MaterialIcon name="schedule" size={16} />
                回答期限: {new Date(data.period.guardian_deadline).toLocaleDateString('ja-JP', { year: 'numeric', month: 'long', day: 'numeric' })}
              </div>
            )}
          </CardBody>
        </Card>
      )}

      {/* Questions by category: 未提出時のみ表示 */}
      {!isSubmitted && Object.entries(questionsByCategory).map(([category, questions]) => (
        <Card key={category}>
          <div className="bg-[var(--brand-primary,#7c3aed)] text-white px-5 py-3 font-semibold text-base">
            {category}
          </div>
          <CardBody>
            <div className="divide-y divide-[var(--neutral-stroke-3)]">
              {questions.map((q) => {
                const current = answers[q.id];
                const isUnanswered = unansweredIds.includes(q.id);
                return (
                  <div
                    key={q.id}
                    id={`question_${q.id}`}
                    className={`py-4 first:pt-0 last:pb-0 ${isUnanswered ? 'bg-red-50 border-l-4 border-red-500 pl-3 -ml-3' : ''}`}
                  >
                    <p className="text-sm text-[var(--neutral-foreground-1)] mb-3 leading-relaxed">
                      <span className="inline-flex items-center justify-center w-7 h-7 rounded-full bg-[var(--brand-primary,#7c3aed)] text-white text-xs font-semibold mr-2">
                        {q.question_number}
                      </span>
                      {q.question_text}
                    </p>

                    <div className="flex flex-wrap gap-2 mb-3">
                      {ANSWER_OPTIONS.map((opt) => (
                        <button
                          key={opt.value}
                          onClick={() => !isSubmitted && updateAnswer(q.id, opt.value)}
                          disabled={isSubmitted}
                          className={`rounded-lg px-3 py-1.5 text-sm font-medium transition-colors border ${
                            current?.answer === opt.value
                              ? 'text-white border-transparent'
                              : 'border-[var(--neutral-stroke-2)] text-[var(--neutral-foreground-3)] hover:bg-[var(--neutral-background-3)]'
                          } ${isSubmitted ? 'opacity-60 cursor-not-allowed' : 'cursor-pointer'}`}
                          style={current?.answer === opt.value ? { backgroundColor: answerColor(opt.value) } : undefined}
                        >
                          {opt.label}
                        </button>
                      ))}
                    </div>

                    <div>
                      <label className="block text-xs text-[var(--neutral-foreground-3)] mb-1">
                        ご意見（任意）
                      </label>
                      <textarea
                        placeholder="ご意見やご要望がありましたらご記入ください"
                        value={current?.comment || ''}
                        onChange={(e) => updateComment(q.id, e.target.value)}
                        disabled={isSubmitted}
                        className="block w-full rounded-lg border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-1)] px-3 py-2 text-sm text-[var(--neutral-foreground-1)] disabled:opacity-60"
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

      {/* Action buttons */}
      {!isSubmitted && (
        <div className="sticky bottom-0 bg-[var(--neutral-background-1)] border-t border-[var(--neutral-stroke-3)] py-3 flex justify-center gap-3"
             style={{ boxShadow: '0 -2px 8px rgba(0,0,0,0.1)' }}>
          <Button
            variant="outline"
            onClick={() => handleSave(false)}
            isLoading={isSaving}
            leftIcon={<MaterialIcon name="save" size={16} />}
          >
            下書き保存
          </Button>
          <Button
            onClick={() => {
              if (confirm('提出すると修正できなくなります。提出してよろしいですか？')) {
                handleSave(true);
              }
            }}
            isLoading={isSaving}
            leftIcon={<MaterialIcon name="send" size={16} />}
          >
            回答を提出する
          </Button>
        </div>
      )}
    </div>
  );
}
