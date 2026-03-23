'use client';

import { useState } from 'react';
import { useParams } from 'next/navigation';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import api from '@/lib/api';
import { Card, CardHeader, CardTitle, CardBody } from '@/components/ui/Card';
import { Button } from '@/components/ui/Button';
import { Skeleton } from '@/components/ui/Skeleton';
import { useToast } from '@/components/ui/Toast';
import { ChevronLeft, Printer, Sparkles } from 'lucide-react';
import Link from 'next/link';
import { format } from 'date-fns';
import { ja } from 'date-fns/locale';

function nl(t: string | null | undefined): string {
  if (!t) return '';
  return t.replace(/\\r\\n|\\n|\\r/g, '\n').replace(/\r\n|\r/g, '\n');
}

function formatDate(d: string | null | undefined): string {
  if (!d) return '-';
  try { return format(new Date(d), 'yyyy年M月d日', { locale: ja }); } catch { return d; }
}

interface BasisData {
  plan: {
    id: number;
    created_date: string;
    short_term_goal: string | null;
    long_term_goal: string | null;
    life_intention: string | null;
    overall_policy: string | null;
    basis_content: string | null;
    basis_generated_at: string | null;
    student?: { id: number; student_name: string };
  };
  kakehashi_period: {
    period_name: string;
    start_date: string;
    end_date: string;
    submission_deadline: string;
  } | null;
  guardian_kakehashi: {
    student_wish: string | null;
    home_challenges: string | null;
    short_term_goal: string | null;
    long_term_goal: string | null;
    domain_health_life: string | null;
    domain_motor_sensory: string | null;
    domain_cognitive_behavior: string | null;
    domain_language_communication: string | null;
    domain_social_relations: string | null;
    other_challenges: string | null;
    is_submitted: boolean;
    submitted_at: string | null;
  } | null;
  staff_kakehashi: {
    student_wish: string | null;
    short_term_goal: string | null;
    long_term_goal: string | null;
    health_life: string | null;
    motor_sensory: string | null;
    cognitive_behavior: string | null;
    language_communication: string | null;
    social_relations: string | null;
    other_challenges: string | null;
    is_submitted: boolean;
    submitted_at: string | null;
  } | null;
  monitoring: {
    monitoring_date: string;
    overall_comment: string | null;
    details: { category: string; sub_category: string; achievement_status: string; monitoring_comment: string }[];
  } | null;
  latest_monitoring?: {
    monitoring_date: string;
    overall_comment: string | null;
    details: { category: string; sub_category: string; achievement_status: string; monitoring_comment: string }[];
  } | null;
}

const ACHIEVEMENT_LABELS: Record<string, { label: string; color: string }> = {
  '達成': { label: '達成', color: 'text-green-700 bg-green-100' },
  '継続': { label: '継続', color: 'text-yellow-700 bg-yellow-100' },
  '未達成': { label: '未達成', color: 'text-red-700 bg-red-100' },
  '進行中': { label: '進行中', color: 'text-blue-700 bg-blue-100' },
  '継続中': { label: '継続中', color: 'text-yellow-700 bg-yellow-100' },
  '未着手': { label: '未着手', color: 'text-gray-700 bg-gray-100' },
  '見直し必要': { label: '見直し必要', color: 'text-red-700 bg-red-100' },
};

export default function PlanBasisPage() {
  const params = useParams();
  const planId = params.planId as string;
  const toast = useToast();
  const queryClient = useQueryClient();
  const [generating, setGenerating] = useState(false);

  const { data: basis, isLoading } = useQuery({
    queryKey: ['staff', 'support-plan-basis', planId],
    queryFn: async () => {
      const res = await api.get<{ data: BasisData }>(`/api/staff/support-plans/${planId}/basis`);
      return res.data.data;
    },
  });

  const generateBasisMutation = useMutation({
    mutationFn: async () => {
      setGenerating(true);
      const res = await api.post(`/api/staff/support-plans/${planId}/generate-basis`);
      return res.data;
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['staff', 'support-plan-basis', planId] });
      toast.success('全体所感を生成しました');
      setGenerating(false);
    },
    onError: () => {
      toast.error('全体所感の生成に失敗しました');
      setGenerating(false);
    },
  });

  if (isLoading) {
    return (
      <div className="space-y-4">
        <Skeleton className="h-8 w-64" />
        {[...Array(4)].map((_, i) => <Skeleton key={i} className="h-40 w-full rounded-lg" />)}
      </div>
    );
  }

  if (!basis) return <div className="p-8 text-center">データが見つかりません</div>;

  const { plan, kakehashi_period, guardian_kakehashi, staff_kakehashi } = basis;
  // Support both field names from API
  const monitoring = basis.monitoring || basis.latest_monitoring;

  const hasNoData = !kakehashi_period && !guardian_kakehashi && !staff_kakehashi && !monitoring;

  return (
    <div className="space-y-4 print:space-y-3">
      <style>{`
        @media print {
          .print\\:hidden { display: none !important; }
          @page { size: A4 portrait; margin: 14mm 16mm; }
          body { font-size: 9pt; print-color-adjust: exact; -webkit-print-color-adjust: exact; }
          .overflow-x-auto { overflow: visible !important; width: 100% !important; }
          table { page-break-inside: auto; }
          tr { page-break-inside: avoid; }
          thead { display: table-header-group; }
        }
      `}</style>

      {/* Navigation back link */}
      <div className="print:hidden">
        <Link href="/staff/kobetsu-plan">
          <Button variant="ghost" size="sm" leftIcon={<ChevronLeft className="h-4 w-4" />}>個別支援計画に戻る</Button>
        </Link>
      </div>

      {/* Header - gradient banner matching legacy */}
      <div className="rounded-lg p-6 text-white" style={{ background: 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)' }}>
        <h1 className="text-2xl font-bold mb-2">個別支援計画の根拠</h1>
        <div className="flex flex-wrap gap-6 text-sm opacity-90">
          <span>{plan.student?.student_name}</span>
          <span>計画作成日: {formatDate(plan.created_date)}</span>
          {kakehashi_period && (
            <span>根拠期間: {formatDate(kakehashi_period.submission_deadline)} 期限のかけはし</span>
          )}
        </div>
      </div>

      {hasNoData ? (
        <div className="rounded-lg bg-[var(--neutral-background-3)] p-8 text-center text-[var(--neutral-foreground-3)]">
          <h3 className="text-lg font-semibold mb-2">根拠データが見つかりません</h3>
          <p>この計画書に関連するかけはしデータやモニタリングデータが見つかりませんでした。</p>
          <p>計画書が手動で作成された可能性があります。</p>
        </div>
      ) : (
        <>
          {/* ============================================================== */}
          {/* Section 1: Goal Comparison */}
          {/* ============================================================== */}
          <Card>
            <CardHeader><CardTitle className="text-base">目標の比較と整合性</CardTitle></CardHeader>
            <CardBody>
              <p className="text-sm text-[var(--neutral-foreground-3)] mb-4">
                保護者・スタッフのかけはしで設定された目標と、個別支援計画で設定された目標を比較します。
              </p>
              <p className="text-sm font-semibold text-[var(--neutral-foreground-1)] mb-2">【短期目標】</p>
              <div className="grid grid-cols-1 md:grid-cols-3 gap-3 mb-6">
                <GoalBox label="保護者の目標" text={guardian_kakehashi?.short_term_goal} color="pink" />
                <GoalBox label="スタッフの目標" text={staff_kakehashi?.short_term_goal} color="green" />
                <GoalBox label="計画書の目標" text={plan.short_term_goal} color="blue" />
              </div>
              <p className="text-sm font-semibold text-[var(--neutral-foreground-1)] mb-2">【長期目標】</p>
              <div className="grid grid-cols-1 md:grid-cols-3 gap-3">
                <GoalBox label="保護者の目標" text={guardian_kakehashi?.long_term_goal} color="pink" />
                <GoalBox label="スタッフの目標" text={staff_kakehashi?.long_term_goal} color="green" />
                <GoalBox label="計画書の目標" text={plan.long_term_goal} color="blue" />
              </div>
            </CardBody>
          </Card>

          {/* ============================================================== */}
          {/* Section 2: Guardian Kakehashi */}
          {/* ============================================================== */}
          {guardian_kakehashi && (
            <Card>
              <CardHeader>
                <CardTitle className="text-base">保護者からのかけはし</CardTitle>
              </CardHeader>
              <CardBody>
                <p className="text-sm text-[var(--neutral-foreground-3)] mb-4">
                  提出日: {guardian_kakehashi.submitted_at ? formatDate(guardian_kakehashi.submitted_at) : '未提出'}
                </p>
                <div className="grid gap-3 sm:grid-cols-2">
                  <DataItem label="本人の願い" value={guardian_kakehashi.student_wish} />
                  <DataItem label="家庭での課題・願い" value={guardian_kakehashi.home_challenges} />
                  <DataItem label="健康・生活" value={guardian_kakehashi.domain_health_life} />
                  <DataItem label="運動・感覚" value={guardian_kakehashi.domain_motor_sensory} />
                  <DataItem label="認知・行動" value={guardian_kakehashi.domain_cognitive_behavior} />
                  <DataItem label="言語・コミュニケーション" value={guardian_kakehashi.domain_language_communication} />
                  <DataItem label="人間関係・社会性" value={guardian_kakehashi.domain_social_relations} />
                  <DataItem label="その他" value={guardian_kakehashi.other_challenges} />
                </div>
              </CardBody>
            </Card>
          )}

          {/* ============================================================== */}
          {/* Section 3: Staff Kakehashi */}
          {/* ============================================================== */}
          {staff_kakehashi && (
            <Card>
              <CardHeader>
                <CardTitle className="text-base">スタッフからのかけはし</CardTitle>
              </CardHeader>
              <CardBody>
                <p className="text-sm text-[var(--neutral-foreground-3)] mb-4">
                  提出日: {staff_kakehashi.submitted_at ? formatDate(staff_kakehashi.submitted_at) : '未提出'}
                </p>
                <div className="grid gap-3 sm:grid-cols-2">
                  <DataItem label="本人の願い（スタッフ観察）" value={staff_kakehashi.student_wish} />
                  <DataItem label="健康・生活" value={staff_kakehashi.health_life} />
                  <DataItem label="運動・感覚" value={staff_kakehashi.motor_sensory} />
                  <DataItem label="認知・行動" value={staff_kakehashi.cognitive_behavior} />
                  <DataItem label="言語・コミュニケーション" value={staff_kakehashi.language_communication} />
                  <DataItem label="人間関係・社会性" value={staff_kakehashi.social_relations} />
                  <DataItem label="その他" value={staff_kakehashi.other_challenges} />
                </div>
              </CardBody>
            </Card>
          )}

          {/* ============================================================== */}
          {/* Section 4: Latest Monitoring */}
          {/* ============================================================== */}
          {monitoring && (
            <Card>
              <CardHeader>
                <CardTitle className="text-base">直近のモニタリング情報</CardTitle>
              </CardHeader>
              <CardBody>
                <p className="text-sm text-[var(--neutral-foreground-3)] mb-4">
                  実施日: {formatDate(monitoring.monitoring_date)}
                </p>
                <div className="space-y-3">
                  <div className="rounded-lg bg-[var(--neutral-background-3)] p-3">
                    <p className="text-xs font-semibold text-[var(--neutral-foreground-3)] mb-1">総合所見</p>
                    <p className="text-sm whitespace-pre-wrap">{nl(monitoring.overall_comment) || '（未記入）'}</p>
                  </div>
                  {monitoring.details && monitoring.details.length > 0 && (
                    <div className="overflow-x-auto">
                      <table className="w-full text-sm border-collapse">
                        <thead>
                          <tr className="bg-[var(--neutral-background-3)]">
                            <th className="px-3 py-1.5 text-left text-xs border border-[var(--neutral-stroke-2)]">分野</th>
                            <th className="px-3 py-1.5 text-left text-xs border border-[var(--neutral-stroke-2)]">達成状況</th>
                            <th className="px-3 py-1.5 text-left text-xs border border-[var(--neutral-stroke-2)]">評価コメント</th>
                          </tr>
                        </thead>
                        <tbody>
                          {monitoring.details.map((d, i) => {
                            const ach = ACHIEVEMENT_LABELS[d.achievement_status] || { label: d.achievement_status || '（未評価）', color: 'text-gray-700 bg-gray-100' };
                            return (
                              <tr key={i}>
                                <td className="px-3 py-1.5 border border-[var(--neutral-stroke-2)] text-xs">
                                  {d.category}{d.sub_category ? ` - ${d.sub_category}` : ''}
                                </td>
                                <td className="px-3 py-1.5 border border-[var(--neutral-stroke-2)]">
                                  <span className={`inline-block rounded px-2 py-0.5 text-[10px] font-bold ${ach.color}`}>{ach.label}</span>
                                </td>
                                <td className="px-3 py-1.5 border border-[var(--neutral-stroke-2)] text-xs whitespace-pre-wrap">{nl(d.monitoring_comment) || '（コメントなし）'}</td>
                              </tr>
                            );
                          })}
                        </tbody>
                      </table>
                    </div>
                  )}
                </div>
              </CardBody>
            </Card>
          )}

          {/* ============================================================== */}
          {/* Section 5: AI-generated Basis Content (全体所感) - after monitoring, matching legacy order */}
          {/* ============================================================== */}
          {plan.basis_content && (
            <Card className="border border-indigo-200" style={{ background: 'linear-gradient(135deg, rgba(102, 126, 234, 0.1) 0%, rgba(118, 75, 162, 0.1) 100%)' }}>
              <CardHeader>
                <CardTitle className="text-base" style={{ color: '#667eea' }}>全体所感</CardTitle>
                {plan.basis_generated_at && (
                  <span className="text-xs text-[var(--neutral-foreground-3)]">
                    生成日時: {formatDate(plan.basis_generated_at)}
                  </span>
                )}
              </CardHeader>
              <CardBody>
                <div className="rounded-lg bg-white p-4">
                  <p className="text-sm whitespace-pre-wrap leading-relaxed">{nl(plan.basis_content)}</p>
                </div>
              </CardBody>
            </Card>
          )}
        </>
      )}

      {/* Buttons - matching legacy: AI generate, print */}
      <div className="flex flex-wrap gap-3 print:hidden">
        {!plan.basis_content ? (
          <Button
            leftIcon={<Sparkles className="h-4 w-4" />}
            onClick={() => generateBasisMutation.mutate()}
            isLoading={generating}
            className="text-white"
            style={{ background: 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)' }}
          >
            AIで全体所感を生成
          </Button>
        ) : (
          <Button
            variant="outline"
            leftIcon={<Sparkles className="h-4 w-4" />}
            onClick={() => generateBasisMutation.mutate()}
            isLoading={generating}
          >
            全体所感を再生成
          </Button>
        )}
        <Button variant="outline" leftIcon={<Printer className="h-4 w-4" />} onClick={() => window.print()}>印刷</Button>
      </div>
    </div>
  );
}

// ---------------------------------------------------------------------------
// Sub-components
// ---------------------------------------------------------------------------

function GoalBox({ label, text, color }: { label: string; text: string | null | undefined; color: 'pink' | 'green' | 'blue' }) {
  const colors = {
    pink: 'bg-pink-50 border-pink-200',
    green: 'bg-green-50 border-green-200',
    blue: 'bg-blue-50 border-blue-200',
  };
  const labelColors = { pink: 'text-pink-700', green: 'text-green-700', blue: 'text-blue-700' };

  return (
    <div className={`rounded-lg border p-3 ${colors[color]}`}>
      <p className={`text-[10px] font-semibold mb-1 ${labelColors[color]}`}>{label}</p>
      <p className={`text-sm whitespace-pre-wrap ${!nl(text) ? 'text-[var(--neutral-foreground-4)] italic' : ''}`}>{nl(text) || '（データなし）'}</p>
    </div>
  );
}

function DataItem({ label, value }: { label: string; value: string | null | undefined }) {
  return (
    <div className="bg-[var(--neutral-background-3)] rounded p-3 border-l-4 border-[var(--neutral-stroke-2)]">
      <p className="text-[10px] font-semibold text-[var(--neutral-foreground-3)] mb-1">{label}</p>
      <p className={`text-sm whitespace-pre-wrap ${!nl(value) ? 'text-[var(--neutral-foreground-4)] italic' : 'text-[var(--neutral-foreground-1)]'}`}>{nl(value) || '（未記入）'}</p>
    </div>
  );
}
