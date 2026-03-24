'use client';

import { useState, useCallback } from 'react';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import api from '@/lib/api';
import { Card, CardHeader, CardTitle, CardBody } from '@/components/ui/Card';
import { Button } from '@/components/ui/Button';
import { Badge } from '@/components/ui/Badge';
import { Skeleton, SkeletonList } from '@/components/ui/Skeleton';
import { useToast } from '@/components/ui/Toast';
import { format } from 'date-fns';
import Link from 'next/link';
import { MaterialIcon } from '@/components/ui/MaterialIcon';

// ---------------------------------------------------------------------------
// Types
// ---------------------------------------------------------------------------

interface Student {
  id: number;
  student_name: string;
}

interface GuardianEntry {
  id: number;
  period_id: number;
  student_id: number;
  guardian_id: number | null;
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
  is_hidden: boolean;
  created_at: string;
  updated_at: string;
  guardian?: { id: number; full_name: string } | null;
}

interface StaffEntry {
  id: number;
  is_submitted: boolean;
  submitted_at: string | null;
}

interface KakehashiPeriod {
  id: number;
  student_id: number;
  period_name: string;
  start_date: string;
  end_date: string;
  submission_deadline: string;
  is_active: boolean;
  staff_entries: StaffEntry[];
  guardian_entries: GuardianEntry[];
}

/** Normalize line breaks */
function nl(text: string | null | undefined): string {
  if (!text) return '';
  return text.replace(/\\r\\n|\\n|\\r/g, '\n').replace(/\r\n|\r/g, '\n');
}

// ---------------------------------------------------------------------------
// Main Component
// ---------------------------------------------------------------------------

export default function KakehashiGuardianViewPage() {
  const queryClient = useQueryClient();
  const toast = useToast();

  const [selectedStudentId, setSelectedStudentId] = useState<number | null>(null);
  const [selectedPeriodId, setSelectedPeriodId] = useState<number | null>(null);
  const [showHidden, setShowHidden] = useState(false);

  // Fetch students
  const { data: students = [], isLoading: loadingStudents } = useQuery({
    queryKey: ['staff', 'kakehashi', 'students'],
    queryFn: async () => {
      const res = await api.get<{ data: Student[] }>('/api/staff/students');
      return res.data.data;
    },
  });

  // Fetch periods for selected student
  const { data: periods = [], isLoading: loadingPeriods } = useQuery({
    queryKey: ['staff', 'kakehashi', 'periods', selectedStudentId],
    queryFn: async () => {
      const res = await api.get<{ data: KakehashiPeriod[] }>(
        `/api/staff/students/${selectedStudentId}/kakehashi`
      );
      return res.data.data;
    },
    enabled: !!selectedStudentId,
  });

  // Filtered periods that have guardian entries
  const periodsWithGuardian = periods.filter((p) => {
    const ge = p.guardian_entries?.[0];
    if (!ge) return false;
    if (!showHidden && ge.is_hidden) return false;
    return true;
  });

  const selectedPeriod = periods.find((p) => p.id === selectedPeriodId) ?? null;
  const guardianEntry = selectedPeriod?.guardian_entries?.[0] ?? null;
  const selectedStudent = students.find((s) => s.id === selectedStudentId);

  // PDF download
  const handlePdfDownload = useCallback(async (periodId: number, periodName: string) => {
    try {
      const res = await api.get(`/api/staff/kakehashi/${periodId}/pdf`, { responseType: 'blob' });
      const url = window.URL.createObjectURL(new Blob([res.data]));
      const link = document.createElement('a');
      link.href = url;
      link.download = `${periodName}.pdf`;
      link.click();
      window.URL.revokeObjectURL(url);
    } catch {
      toast.error('PDF生成に失敗しました');
    }
  }, [toast]);

  // Print current page
  const handlePrint = useCallback(() => {
    window.print();
  }, []);

  return (
    <div className="space-y-6">
      {/* Header */}
      <div>
        <h1 className="text-2xl font-bold text-[var(--neutral-foreground-1)]">保護者入力かけはし確認</h1>
        <p className="mt-1 text-sm text-[var(--neutral-foreground-3)]">保護者が入力したかけはしを確認</p>
      </div>

      {/* Navigation tabs */}
      <div className="flex flex-wrap gap-2">
        <Link href="/staff/kakehashi-guardian">
          <Button variant="primary" size="sm" leftIcon={<MaterialIcon name="visibility" size={16} />}>
            保護者入力かけはし確認
          </Button>
        </Link>
        <Link href="/staff/kakehashi-staff">
          <Button variant="outline" size="sm" leftIcon={<MaterialIcon name="edit" size={16} />}>
            スタッフ入力
          </Button>
        </Link>
      </div>

      {/* Student selector */}
      <Card>
        <CardBody>
          <label className="mb-2 block text-sm font-medium text-[var(--neutral-foreground-2)]">
            生徒を選択 <span className="text-[var(--status-danger-fg)]">*</span>
          </label>
          {loadingStudents ? (
            <Skeleton className="h-10 w-full rounded-lg" />
          ) : (
            <select
              className="block w-full rounded-lg border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-1)] px-3 py-2 text-sm text-[var(--neutral-foreground-1)]"
              value={selectedStudentId ?? ''}
              onChange={(e) => {
                const id = e.target.value ? Number(e.target.value) : null;
                setSelectedStudentId(id);
                setSelectedPeriodId(null);
              }}
            >
              <option value="">-- 生徒を選択してください --</option>
              {students.map((s) => (
                <option key={s.id} value={s.id}>{s.student_name}</option>
              ))}
            </select>
          )}
        </CardBody>
      </Card>

      {/* Period selector & filter */}
      {selectedStudentId && (
        <Card>
          <CardBody>
            <div className="flex items-center justify-between mb-2">
              <label className="block text-sm font-medium text-[var(--neutral-foreground-2)]">
                かけはし提出期限を選択 <span className="text-[var(--status-danger-fg)]">*</span>
              </label>
              <label className="flex items-center gap-1.5 text-xs text-[var(--neutral-foreground-3)] cursor-pointer">
                <input
                  type="checkbox"
                  checked={showHidden}
                  onChange={(e) => setShowHidden(e.target.checked)}
                  className="rounded border-[var(--neutral-stroke-2)]"
                />
                非表示を含む
              </label>
            </div>
            {loadingPeriods ? (
              <Skeleton className="h-10 w-full rounded-lg" />
            ) : periodsWithGuardian.length === 0 ? (
              <p className="text-sm text-[var(--neutral-foreground-3)]">保護者が入力したかけはしはありません。</p>
            ) : (
              <select
                className="block w-full rounded-lg border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-1)] px-3 py-2 text-sm text-[var(--neutral-foreground-1)]"
                value={selectedPeriodId ?? ''}
                onChange={(e) => setSelectedPeriodId(e.target.value ? Number(e.target.value) : null)}
              >
                <option value="">-- 期間を選択してください --</option>
                {periodsWithGuardian.map((p) => (
                  <option key={p.id} value={p.id}>
                    提出期限: {format(new Date(p.submission_deadline), 'yyyy年M月d日')} (対象期間: {format(new Date(p.start_date), 'yyyy/MM/dd')} ～ {format(new Date(p.end_date), 'yyyy/MM/dd')})
                  </option>
                ))}
              </select>
            )}
          </CardBody>
        </Card>
      )}

      {/* Guardian entry display */}
      {selectedPeriod && guardianEntry && (
        <>
          {/* Meta info */}
          <Card>
            <CardBody>
              <div className="rounded-lg bg-[var(--neutral-background-3)] p-4 space-y-2 text-sm">
                <div className="flex items-center gap-2">
                  <MaterialIcon name="person" size={16} className="text-[var(--neutral-foreground-3)]" />
                  <span className="font-medium">生徒:</span> {selectedStudent?.student_name}
                </div>
                {guardianEntry.guardian && (
                  <div className="flex items-center gap-2">
                    <MaterialIcon name="person" size={16} className="text-[var(--neutral-foreground-3)]" />
                    <span className="font-medium">保護者:</span> {guardianEntry.guardian.full_name}
                  </div>
                )}
                <div className="flex items-center gap-2">
                  <MaterialIcon name="calendar_month" size={16} className="text-[var(--neutral-foreground-3)]" />
                  <span className="font-medium">対象期間:</span>
                  {format(new Date(selectedPeriod.start_date), 'yyyy年MM月dd日')} ～ {format(new Date(selectedPeriod.end_date), 'yyyy年MM月dd日')}
                </div>
                <div className="flex items-center gap-2">
                  <MaterialIcon name="calendar_month" size={16} className="text-[var(--neutral-foreground-3)]" />
                  <span className="font-medium">提出期限:</span>
                  {format(new Date(selectedPeriod.submission_deadline), 'yyyy年MM月dd日')}
                </div>
                <div className="flex items-center gap-2">
                  <span className="font-medium">状態:</span>
                  {guardianEntry.is_submitted ? (
                    <Badge variant="success">
                      提出済み
                      {guardianEntry.submitted_at && ` （提出日時: ${format(new Date(guardianEntry.submitted_at), 'yyyy年MM月dd日 HH:mm')}）`}
                    </Badge>
                  ) : (
                    <Badge variant="warning">下書き</Badge>
                  )}
                </div>
              </div>

              {/* Action buttons */}
              <div className="mt-4 flex flex-wrap gap-2">
                <Button
                  variant="outline"
                  size="sm"
                  leftIcon={<MaterialIcon name="download" size={16} />}
                  onClick={() => handlePdfDownload(selectedPeriod.id, selectedPeriod.period_name)}
                >
                  PDF印刷
                </Button>
                <Button
                  variant="outline"
                  size="sm"
                  leftIcon={<MaterialIcon name="print" size={16} />}
                  onClick={handlePrint}
                >
                  このページを印刷
                </Button>
              </div>
            </CardBody>
          </Card>

          {/* Section: 本人の願い */}
          <SectionCard
            icon={<MaterialIcon name="favorite" size={16} className="h-4 w-4" />}
            title="本人の願い"
            subtitle="お子様が望んでいること、なりたい姿"
            color="var(--status-danger-fg)"
          >
            <ViewBox text={guardianEntry.student_wish} />
          </SectionCard>

          {/* Section: 家庭での願い */}
          <SectionCard
            icon={<MaterialIcon name="home" size={16} className="h-4 w-4" />}
            title="家庭での願い"
            subtitle="家庭で気になっていること、取り組みたいこと"
            color="var(--status-warning-fg)"
          >
            <ViewBox text={guardianEntry.home_challenges} />
          </SectionCard>

          {/* Section: 目標設定 */}
          <SectionCard
            icon={<MaterialIcon name="target" size={16} className="h-4 w-4" />}
            title="目標設定"
            color="var(--brand-80)"
          >
            <div className="space-y-4">
              <div>
                <p className="text-xs font-semibold text-[var(--neutral-foreground-3)] mb-1">短期目標（6か月）</p>
                <ViewBox text={guardianEntry.short_term_goal} />
              </div>
              <div>
                <p className="text-xs font-semibold text-[var(--neutral-foreground-3)] mb-1">長期目標（1年以上）</p>
                <ViewBox text={guardianEntry.long_term_goal} />
              </div>
            </div>
          </SectionCard>

          {/* Section: 五領域の課題 */}
          <SectionCard
            icon={<MaterialIcon name="star" size={16} className="h-4 w-4" />}
            title="五領域の課題"
            color="var(--status-info-fg)"
          >
            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              <DomainItem label="健康・生活" value={guardianEntry.domain_health_life} />
              <DomainItem label="運動・感覚" value={guardianEntry.domain_motor_sensory} />
              <DomainItem label="認知・行動" value={guardianEntry.domain_cognitive_behavior} />
              <DomainItem label="言語・コミュニケーション" value={guardianEntry.domain_language_communication} />
              <DomainItem label="人間関係・社会性" value={guardianEntry.domain_social_relations} />
            </div>
          </SectionCard>

          {/* Section: その他の課題 */}
          <SectionCard
            icon={<MaterialIcon name="push_pin" size={16} />}
            title="その他の課題"
            subtitle="その他、お伝えしたいこと"
            color="var(--neutral-foreground-3)"
          >
            <ViewBox text={guardianEntry.other_challenges} />
          </SectionCard>

          {/* Hidden toggle */}
          <Card>
            <CardBody>
              <button
                className={`flex items-center gap-2 text-xs transition-colors ${
                  guardianEntry.is_hidden
                    ? 'text-green-600 hover:text-green-700'
                    : 'text-[var(--neutral-foreground-3)] hover:text-[var(--neutral-foreground-2)]'
                }`}
                onClick={async () => {
                  const action = guardianEntry.is_hidden ? '再表示' : '非表示に';
                  if (!guardianEntry.is_hidden && !window.confirm(`この保護者用かけはしを非表示にしてもよろしいですか？\n再表示することもできます。`)) {
                    return;
                  }
                  try {
                    await api.post(`/api/staff/kakehashi/${selectedPeriod.id}/toggle-guardian-hidden`);
                    queryClient.invalidateQueries({ queryKey: ['staff', 'kakehashi'] });
                    toast.success(`保護者用かけはしを${action}しました。`);
                  } catch {
                    toast.error('操作に失敗しました');
                  }
                }}
              >
                {guardianEntry.is_hidden ? (
                  <><MaterialIcon name="visibility" size={16} /> この保護者用かけはしを再表示</>
                ) : (
                  <><MaterialIcon name="visibility_off" size={16} /> この保護者用かけはしを非表示</>
                )}
              </button>
            </CardBody>
          </Card>
        </>
      )}
    </div>
  );
}

// ---------------------------------------------------------------------------
// Section Card component
// ---------------------------------------------------------------------------

function SectionCard({
  icon,
  title,
  subtitle,
  color,
  children,
}: {
  icon: React.ReactNode;
  title: string;
  subtitle?: string;
  color: string;
  children: React.ReactNode;
}) {
  return (
    <Card>
      <CardHeader>
        <CardTitle>
          <div className="flex items-center gap-2">
            <span style={{ color }}>{icon}</span>
            <span>{title}</span>
          </div>
        </CardTitle>
      </CardHeader>
      <CardBody>
        {subtitle && (
          <p className="text-xs text-[var(--neutral-foreground-3)] mb-3">{subtitle}</p>
        )}
        {children}
      </CardBody>
    </Card>
  );
}

// ---------------------------------------------------------------------------
// View Box
// ---------------------------------------------------------------------------

function ViewBox({ text }: { text: string | null }) {
  return (
    <div className="rounded-lg border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-1)] p-3 text-sm text-[var(--neutral-foreground-1)] whitespace-pre-wrap min-h-[60px]">
      {nl(text) || '（未入力）'}
    </div>
  );
}

// ---------------------------------------------------------------------------
// Domain Item
// ---------------------------------------------------------------------------

function DomainItem({ label, value }: { label: string; value: string | null }) {
  return (
    <div>
      <p className="text-xs font-semibold text-[var(--neutral-foreground-3)] mb-1">{label}</p>
      <ViewBox text={value} />
    </div>
  );
}
