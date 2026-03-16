'use client';

import { useParams } from 'next/navigation';
import { useQuery } from '@tanstack/react-query';
import api from '@/lib/api';
import { Card, CardHeader, CardTitle, CardBody } from '@/components/ui/Card';
import { Button } from '@/components/ui/Button';
import { Badge } from '@/components/ui/Badge';
import { Skeleton } from '@/components/ui/Skeleton';
import { ChevronLeft, Printer, FileText } from 'lucide-react';
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
    is_submitted: boolean;
  } | null;
  monitoring: {
    monitoring_date: string;
    overall_comment: string | null;
    details: { category: string; sub_category: string; achievement_status: string; monitoring_comment: string }[];
  } | null;
}

const ACHIEVEMENT_LABELS: Record<string, { label: string; color: string }> = {
  '達成': { label: '達成', color: 'text-green-700 bg-green-100' },
  '進行中': { label: '進行中', color: 'text-blue-700 bg-blue-100' },
  '継続中': { label: '継続中', color: 'text-yellow-700 bg-yellow-100' },
  '未着手': { label: '未着手', color: 'text-gray-700 bg-gray-100' },
  '見直し必要': { label: '見直し必要', color: 'text-red-700 bg-red-100' },
};

export default function PlanBasisPage() {
  const params = useParams();
  const planId = params.planId as string;

  const { data: basis, isLoading } = useQuery({
    queryKey: ['staff', 'support-plan-basis', planId],
    queryFn: async () => {
      const res = await api.get<{ data: BasisData }>(`/api/staff/support-plans/${planId}/basis`);
      return res.data.data;
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

  const { plan, kakehashi_period, guardian_kakehashi, staff_kakehashi, monitoring } = basis;

  return (
    <div className="space-y-4 print:space-y-3">
      <style>{`
        @media print {
          .print\\:hidden { display: none !important; }
          @page { size: A4 portrait; margin: 12mm 15mm; }
          body { font-size: 9pt; }
        }
      `}</style>

      {/* Header */}
      <div className="flex items-center justify-between print:hidden">
        <Link href="/staff/kobetsu-plan">
          <Button variant="ghost" size="sm" leftIcon={<ChevronLeft className="h-4 w-4" />}>個別支援計画に戻る</Button>
        </Link>
        <Button leftIcon={<Printer className="h-4 w-4" />} onClick={() => window.print()}>印刷</Button>
      </div>

      <div className="text-center border-b-2 border-[var(--neutral-foreground-1)] pb-2 mb-4">
        <h1 className="text-xl font-bold text-[var(--neutral-foreground-1)]">個別支援計画 根拠資料</h1>
        <p className="text-sm text-[var(--neutral-foreground-3)]">
          {plan.student?.student_name} ・ 作成日: {formatDate(plan.created_date)}
          {kakehashi_period && ` ・ 対象期間: ${formatDate(kakehashi_period.start_date)} ～ ${formatDate(kakehashi_period.end_date)}`}
        </p>
      </div>

      {/* ============================================================== */}
      {/* Section 1: Goal Comparison */}
      {/* ============================================================== */}
      <Card>
        <CardHeader><CardTitle className="text-base">目標の比較と整合性</CardTitle></CardHeader>
        <CardBody>
          <p className="text-xs text-[var(--neutral-foreground-3)] mb-3">短期目標</p>
          <div className="grid grid-cols-3 gap-3 mb-4">
            <GoalBox label="保護者かけはし" text={guardian_kakehashi?.short_term_goal} color="pink" />
            <GoalBox label="スタッフかけはし" text={staff_kakehashi?.short_term_goal} color="green" />
            <GoalBox label="個別支援計画" text={plan.short_term_goal} color="blue" />
          </div>
          <p className="text-xs text-[var(--neutral-foreground-3)] mb-3">長期目標</p>
          <div className="grid grid-cols-3 gap-3">
            <GoalBox label="保護者かけはし" text={guardian_kakehashi?.long_term_goal} color="pink" />
            <GoalBox label="スタッフかけはし" text={staff_kakehashi?.long_term_goal} color="green" />
            <GoalBox label="個別支援計画" text={plan.long_term_goal} color="blue" />
          </div>
        </CardBody>
      </Card>

      {/* ============================================================== */}
      {/* Section 2: Guardian Kakehashi */}
      {/* ============================================================== */}
      <Card>
        <CardHeader>
          <CardTitle className="text-base">保護者からのかけはし</CardTitle>
          {guardian_kakehashi?.is_submitted && <Badge variant="success">提出済み</Badge>}
        </CardHeader>
        <CardBody>
          {guardian_kakehashi ? (
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
          ) : (
            <p className="text-sm text-[var(--neutral-foreground-4)]">保護者かけはしのデータがありません</p>
          )}
        </CardBody>
      </Card>

      {/* ============================================================== */}
      {/* Section 3: Staff Kakehashi */}
      {/* ============================================================== */}
      <Card>
        <CardHeader>
          <CardTitle className="text-base">スタッフからのかけはし</CardTitle>
          {staff_kakehashi?.is_submitted && <Badge variant="success">提出済み</Badge>}
        </CardHeader>
        <CardBody>
          {staff_kakehashi ? (
            <div className="grid gap-3 sm:grid-cols-2">
              <DataItem label="本人の願い（スタッフ観察）" value={staff_kakehashi.student_wish} />
              <DataItem label="健康・生活" value={staff_kakehashi.health_life} />
              <DataItem label="運動・感覚" value={staff_kakehashi.motor_sensory} />
              <DataItem label="認知・行動" value={staff_kakehashi.cognitive_behavior} />
              <DataItem label="言語・コミュニケーション" value={staff_kakehashi.language_communication} />
              <DataItem label="人間関係・社会性" value={staff_kakehashi.social_relations} />
            </div>
          ) : (
            <p className="text-sm text-[var(--neutral-foreground-4)]">スタッフかけはしのデータがありません</p>
          )}
        </CardBody>
      </Card>

      {/* ============================================================== */}
      {/* Section 4: Latest Monitoring */}
      {/* ============================================================== */}
      <Card>
        <CardHeader>
          <CardTitle className="text-base">直近のモニタリング情報</CardTitle>
          {monitoring && <span className="text-xs text-[var(--neutral-foreground-3)]">{formatDate(monitoring.monitoring_date)}</span>}
        </CardHeader>
        <CardBody>
          {monitoring ? (
            <div className="space-y-3">
              {monitoring.overall_comment && (
                <div className="rounded-lg bg-[var(--neutral-background-3)] p-3">
                  <p className="text-xs font-semibold text-[var(--neutral-foreground-3)] mb-1">総合所見</p>
                  <p className="text-sm whitespace-pre-wrap">{nl(monitoring.overall_comment)}</p>
                </div>
              )}
              {monitoring.details.length > 0 && (
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
                        const ach = ACHIEVEMENT_LABELS[d.achievement_status] || { label: d.achievement_status, color: 'text-gray-700 bg-gray-100' };
                        return (
                          <tr key={i}>
                            <td className="px-3 py-1.5 border border-[var(--neutral-stroke-2)] text-xs">
                              {d.category}{d.sub_category ? ` > ${d.sub_category}` : ''}
                            </td>
                            <td className="px-3 py-1.5 border border-[var(--neutral-stroke-2)]">
                              <span className={`inline-block rounded px-2 py-0.5 text-[10px] font-bold ${ach.color}`}>{ach.label}</span>
                            </td>
                            <td className="px-3 py-1.5 border border-[var(--neutral-stroke-2)] text-xs whitespace-pre-wrap">{nl(d.monitoring_comment)}</td>
                          </tr>
                        );
                      })}
                    </tbody>
                  </table>
                </div>
              )}
            </div>
          ) : (
            <p className="text-sm text-[var(--neutral-foreground-4)]">モニタリングデータがありません</p>
          )}
        </CardBody>
      </Card>
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
      <p className="text-sm whitespace-pre-wrap">{nl(text) || '（未入力）'}</p>
    </div>
  );
}

function DataItem({ label, value }: { label: string; value: string | null | undefined }) {
  if (!value) return null;
  return (
    <div>
      <p className="text-[10px] font-semibold text-[var(--neutral-foreground-3)]">{label}</p>
      <p className="text-sm text-[var(--neutral-foreground-1)] whitespace-pre-wrap mt-0.5">{nl(value)}</p>
    </div>
  );
}
