'use client';

import { useState, useEffect } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import api from '@/lib/api';
import { nl } from '@/lib/utils';
import { Card, CardBody } from '@/components/ui/Card';
import { Button } from '@/components/ui/Button';
import { Badge } from '@/components/ui/Badge';
import { SkeletonCard, Skeleton } from '@/components/ui/Skeleton';
import { useToast } from '@/components/ui/Toast';
import { format } from 'date-fns';
import Link from 'next/link';
import { MaterialIcon } from '@/components/ui/MaterialIcon';

// ---------------------------------------------------------------------------
// Types
// ---------------------------------------------------------------------------

interface StudentOption {
  id: number;
  student_name: string;
}

interface GuardianEntry {
  id: number;
  period_id: number;
  student_id: number;
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
}

interface KakehashiPeriod {
  id: number;
  student_id: number;
  period_name: string;
  start_date: string;
  end_date: string;
  submission_deadline: string;
  is_active: boolean;
  guardian_entries: GuardianEntry[];
}

// ---------------------------------------------------------------------------
// Form state
// ---------------------------------------------------------------------------

interface FormState {
  student_wish: string;
  home_challenges: string;
  short_term_goal: string;
  long_term_goal: string;
  domain_health_life: string;
  domain_motor_sensory: string;
  domain_cognitive_behavior: string;
  domain_language_communication: string;
  domain_social_relations: string;
  other_challenges: string;
}

const emptyForm: FormState = {
  student_wish: '',
  home_challenges: '',
  short_term_goal: '',
  long_term_goal: '',
  domain_health_life: '',
  domain_motor_sensory: '',
  domain_cognitive_behavior: '',
  domain_language_communication: '',
  domain_social_relations: '',
  other_challenges: '',
};

// ---------------------------------------------------------------------------
// Main Component
// ---------------------------------------------------------------------------

export default function GuardianKakehashiPage() {
  const queryClient = useQueryClient();
  const toast = useToast();
  const [selectedStudentId, setSelectedStudentId] = useState<string>('');
  const [selectedPeriodId, setSelectedPeriodId] = useState<number | null>(null);
  const [form, setForm] = useState<FormState>(emptyForm);

  // Fetch students
  const { data: students = [] } = useQuery({
    queryKey: ['guardian', 'students'],
    queryFn: async () => {
      const res = await api.get<{ data: StudentOption[] }>('/api/guardian/students');
      return res.data.data;
    },
  });

  const activeStudentId = selectedStudentId || (students.length > 0 ? String(students[0].id) : '');

  // Fetch periods for selected student
  const { data: periods = [], isLoading: loadingPeriods } = useQuery({
    queryKey: ['guardian', 'kakehashi', activeStudentId],
    queryFn: async () => {
      const res = await api.get<{ data: KakehashiPeriod[] }>(
        `/api/guardian/students/${activeStudentId}/kakehashi`
      );
      return res.data.data;
    },
    enabled: !!activeStudentId,
  });

  // Filter periods: active, not hidden
  const availablePeriods = periods.filter((p) => {
    if (!p.is_active) return false;
    const ge = p.guardian_entries?.[0];
    if (ge && ge.is_hidden) return false;
    return true;
  });

  const selectedPeriod = periods.find((p) => p.id === selectedPeriodId) ?? null;
  const guardianEntry = selectedPeriod?.guardian_entries?.[0] ?? null;

  // Load form data when guardian entry changes
  useEffect(() => {
    if (guardianEntry) {
      setForm({
        student_wish: guardianEntry.student_wish ?? '',
        home_challenges: guardianEntry.home_challenges ?? '',
        short_term_goal: guardianEntry.short_term_goal ?? '',
        long_term_goal: guardianEntry.long_term_goal ?? '',
        domain_health_life: guardianEntry.domain_health_life ?? '',
        domain_motor_sensory: guardianEntry.domain_motor_sensory ?? '',
        domain_cognitive_behavior: guardianEntry.domain_cognitive_behavior ?? '',
        domain_language_communication: guardianEntry.domain_language_communication ?? '',
        domain_social_relations: guardianEntry.domain_social_relations ?? '',
        other_challenges: guardianEntry.other_challenges ?? '',
      });
    } else {
      setForm(emptyForm);
    }
  }, [guardianEntry?.id, selectedPeriodId]);

  // Save/submit mutation
  const saveMutation = useMutation({
    mutationFn: async ({ action }: { action: 'save' | 'submit' }) => {
      return api.post(`/api/guardian/kakehashi/${selectedPeriodId}`, {
        ...form,
        action,
      });
    },
    onSuccess: (_, variables) => {
      queryClient.invalidateQueries({ queryKey: ['guardian', 'kakehashi'] });
      if (variables.action === 'submit') {
        toast.success('かけはしを提出しました。');
      } else {
        toast.success('下書きを保存しました。');
      }
    },
    onError: (error: any) => {
      const msg = error?.response?.data?.message || '保存に失敗しました';
      toast.error(msg);
    },
  });

  const handleSave = () => saveMutation.mutate({ action: 'save' });
  const handleSubmit = () => {
    if (window.confirm('提出すると変更できなくなります。提出してもよろしいですか？')) {
      saveMutation.mutate({ action: 'submit' });
    }
  };

  const isSubmitted = guardianEntry?.is_submitted ?? false;
  const isHidden = guardianEntry?.is_hidden ?? false;
  const canEdit = !isSubmitted && !isHidden;

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h1 className="text-2xl font-bold text-[var(--neutral-foreground-1)]">かけはし入力</h1>
          <p className="mt-1 text-sm text-[var(--neutral-foreground-3)]">
            かけはしの内容を入力してください
          </p>
        </div>
        <Link href="/guardian/kakehashi-history">
          <Button variant="outline" leftIcon={<MaterialIcon name="history" size={16} />}>
            かけはし履歴
          </Button>
        </Link>
      </div>

      {/* Student selector */}
      {students.length > 1 && (
        <Card>
          <CardBody>
            <label className="mb-2 block text-sm font-medium text-[var(--neutral-foreground-2)]">
              お子様を選択
            </label>
            <select
              value={activeStudentId}
              onChange={(e) => {
                setSelectedStudentId(e.target.value);
                setSelectedPeriodId(null);
              }}
              className="block w-full rounded-lg border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-1)] px-3 py-2 text-sm text-[var(--neutral-foreground-1)]"
            >
              {students.map((s) => (
                <option key={s.id} value={s.id}>{s.student_name}</option>
              ))}
            </select>
          </CardBody>
        </Card>
      )}

      {/* Period selector */}
      {activeStudentId && (
        <Card>
          <CardBody>
            <label className="mb-2 block text-sm font-medium text-[var(--neutral-foreground-2)]">
              かけはし提出期限を選択 <span className="text-[var(--status-danger-fg)]">*</span>
            </label>
            {loadingPeriods ? (
              <Skeleton className="h-10 w-full rounded-lg" />
            ) : availablePeriods.length === 0 ? (
              <p className="text-sm text-[var(--neutral-foreground-3)]">
                入力可能なかけはし期間がありません。
              </p>
            ) : (
              <select
                className="block w-full rounded-lg border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-1)] px-3 py-2 text-sm text-[var(--neutral-foreground-1)]"
                value={selectedPeriodId ?? ''}
                onChange={(e) => setSelectedPeriodId(e.target.value ? Number(e.target.value) : null)}
              >
                <option value="">-- 提出期限を選択してください --</option>
                {availablePeriods.map((p) => {
                  const ge = p.guardian_entries?.[0];
                  const statusLabel = ge?.is_submitted ? ' [提出済み]' : ge ? ' [下書き]' : '';
                  return (
                    <option key={p.id} value={p.id}>
                      提出期限: {format(new Date(p.submission_deadline), 'yyyy年M月d日')}
                      {' '}(対象期間: {format(new Date(p.start_date), 'yyyy/MM/dd')} ~ {format(new Date(p.end_date), 'yyyy/MM/dd')})
                      {statusLabel}
                    </option>
                  );
                })}
              </select>
            )}
          </CardBody>
        </Card>
      )}

      {/* Entry form or read-only view */}
      {selectedPeriod && (
        <Card>
          <CardBody>
            {/* Period info */}
            <div className="mb-4 rounded-lg bg-[var(--neutral-background-3)] p-4 space-y-1 text-sm">
              <div className="flex items-center gap-2">
                <MaterialIcon name="calendar_month" size={16} className="text-[var(--neutral-foreground-3)]" />
                <span className="font-medium">対象期間:</span>
                {format(new Date(selectedPeriod.start_date), 'yyyy年MM月dd日')} ~ {format(new Date(selectedPeriod.end_date), 'yyyy年MM月dd日')}
              </div>
              <div className="flex items-center gap-2">
                <MaterialIcon name="calendar_month" size={16} className="text-[var(--neutral-foreground-3)]" />
                <span className="font-medium">提出期限:</span>
                {format(new Date(selectedPeriod.submission_deadline), 'yyyy年MM月dd日')}
              </div>
              <div className="flex items-center gap-2">
                <span className="font-medium">状態:</span>
                {isSubmitted ? (
                  <Badge variant="success">
                    提出済み
                    {guardianEntry?.submitted_at && ` (${format(new Date(guardianEntry.submitted_at), 'yyyy年MM月dd日 HH:mm')})`}
                  </Badge>
                ) : guardianEntry ? (
                  <Badge variant="warning">下書き</Badge>
                ) : (
                  <Badge variant="default">未入力</Badge>
                )}
              </div>
            </div>

            {isSubmitted && (
              <div className="mb-4 rounded-lg border border-green-200 bg-green-50 p-3 text-sm text-green-800">
                既に提出済みです。提出後は変更できません。
              </div>
            )}

            {/* Section: 本人の願い */}
            <SectionHeader icon={<Heart className="h-4 w-4" />} title="本人の願い" color="var(--status-danger-fg)" />
            <div className="mb-4 ml-6">
              <label className="mb-1 block text-xs text-[var(--neutral-foreground-3)]">
                お子様が望んでいること、なりたい姿
              </label>
              {canEdit ? (
                <textarea
                  className="block w-full rounded-lg border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-1)] px-3 py-2 text-sm"
                  rows={3}
                  value={form.student_wish}
                  onChange={(e) => setForm({ ...form, student_wish: e.target.value })}
                  placeholder="お子様の願いを記入してください..."
                />
              ) : (
                <ViewBox text={form.student_wish} />
              )}
            </div>

            {/* Section: 家庭での願い */}
            <SectionHeader icon={<Home className="h-4 w-4" />} title="家庭での願い" color="var(--status-warning-fg)" />
            <div className="mb-4 ml-6">
              <label className="mb-1 block text-xs text-[var(--neutral-foreground-3)]">
                家庭で気になっていること、取り組みたいこと
              </label>
              {canEdit ? (
                <textarea
                  className="block w-full rounded-lg border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-1)] px-3 py-2 text-sm"
                  rows={3}
                  value={form.home_challenges}
                  onChange={(e) => setForm({ ...form, home_challenges: e.target.value })}
                  placeholder="家庭での気になることを記入してください..."
                />
              ) : (
                <ViewBox text={form.home_challenges} />
              )}
            </div>

            {/* Section: 目標設定 */}
            <SectionHeader icon={<Target className="h-4 w-4" />} title="目標設定" color="var(--brand-80)" />
            <div className="mb-4 ml-6 space-y-3">
              <div>
                <label className="mb-1 block text-xs font-semibold text-[var(--neutral-foreground-3)]">
                  短期目標（6か月）
                </label>
                {canEdit ? (
                  <textarea
                    className="block w-full rounded-lg border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-1)] px-3 py-2 text-sm"
                    rows={3}
                    value={form.short_term_goal}
                    onChange={(e) => setForm({ ...form, short_term_goal: e.target.value })}
                    placeholder="短期目標を記入してください..."
                  />
                ) : (
                  <ViewBox text={form.short_term_goal} />
                )}
              </div>
              <div>
                <label className="mb-1 block text-xs font-semibold text-[var(--neutral-foreground-3)]">
                  長期目標（1年以上）
                </label>
                {canEdit ? (
                  <textarea
                    className="block w-full rounded-lg border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-1)] px-3 py-2 text-sm"
                    rows={3}
                    value={form.long_term_goal}
                    onChange={(e) => setForm({ ...form, long_term_goal: e.target.value })}
                    placeholder="長期目標を記入してください..."
                  />
                ) : (
                  <ViewBox text={form.long_term_goal} />
                )}
              </div>
            </div>

            {/* Section: 五領域の課題 */}
            <SectionHeader icon={<Star className="h-4 w-4" />} title="五領域の課題" color="var(--status-info-fg)" />
            <div className="mb-4 ml-6 space-y-3">
              <DomainField
                label="健康・生活"
                value={form.domain_health_life}
                onChange={(v) => setForm({ ...form, domain_health_life: v })}
                canEdit={canEdit}
              />
              <DomainField
                label="運動・感覚"
                value={form.domain_motor_sensory}
                onChange={(v) => setForm({ ...form, domain_motor_sensory: v })}
                canEdit={canEdit}
              />
              <DomainField
                label="認知・行動"
                value={form.domain_cognitive_behavior}
                onChange={(v) => setForm({ ...form, domain_cognitive_behavior: v })}
                canEdit={canEdit}
              />
              <DomainField
                label="言語・コミュニケーション"
                value={form.domain_language_communication}
                onChange={(v) => setForm({ ...form, domain_language_communication: v })}
                canEdit={canEdit}
              />
              <DomainField
                label="人間関係・社会性"
                value={form.domain_social_relations}
                onChange={(v) => setForm({ ...form, domain_social_relations: v })}
                canEdit={canEdit}
              />
            </div>

            {/* Section: その他の課題 */}
            <SectionHeader icon={<MaterialIcon name="push_pin" size={16} />} title="その他の課題" color="var(--neutral-foreground-3)" />
            <div className="mb-4 ml-6">
              <label className="mb-1 block text-xs text-[var(--neutral-foreground-3)]">
                その他、お伝えしたいこと
              </label>
              {canEdit ? (
                <textarea
                  className="block w-full rounded-lg border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-1)] px-3 py-2 text-sm"
                  rows={3}
                  value={form.other_challenges}
                  onChange={(e) => setForm({ ...form, other_challenges: e.target.value })}
                  placeholder="その他、お伝えしたいことがあれば..."
                />
              ) : (
                <ViewBox text={form.other_challenges} />
              )}
            </div>

            {/* Action buttons */}
            {canEdit && (
              <div className="mt-6 flex flex-wrap justify-end gap-3 border-t border-[var(--neutral-stroke-2)] pt-4">
                <Button
                  variant="outline"
                  leftIcon={<MaterialIcon name="save" size={16} />}
                  onClick={handleSave}
                  isLoading={saveMutation.isPending}
                >
                  下書き保存
                </Button>
                <Button
                  variant="primary"
                  leftIcon={<MaterialIcon name="send" size={16} />}
                  onClick={handleSubmit}
                  isLoading={saveMutation.isPending}
                >
                  提出する
                </Button>
              </div>
            )}
          </CardBody>
        </Card>
      )}
    </div>
  );
}

// ---------------------------------------------------------------------------
// Helper Components
// ---------------------------------------------------------------------------

function SectionHeader({ icon, title, color }: { icon: React.ReactNode; title: string; color: string }) {
  return (
    <div className="flex items-center gap-2 mb-2 mt-4">
      <span style={{ color }}>{icon}</span>
      <h3 className="text-sm font-bold text-[var(--neutral-foreground-1)]">{title}</h3>
    </div>
  );
}

function ViewBox({ text }: { text: string }) {
  return (
    <div className="rounded-lg border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-1)] p-3 text-sm text-[var(--neutral-foreground-1)] whitespace-pre-wrap min-h-[60px]">
      {nl(text) || '（未入力）'}
    </div>
  );
}

function DomainField({
  label,
  value,
  onChange,
  canEdit,
}: {
  label: string;
  value: string;
  onChange: (v: string) => void;
  canEdit: boolean;
}) {
  return (
    <div>
      <label className="mb-1 block text-xs font-semibold text-[var(--neutral-foreground-3)]">{label}</label>
      {canEdit ? (
        <textarea
          className="block w-full rounded-lg border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-1)] px-3 py-2 text-sm"
          rows={2}
          value={value}
          onChange={(e) => onChange(e.target.value)}
          placeholder={`${label}について記入してください...`}
        />
      ) : (
        <ViewBox text={value} />
      )}
    </div>
  );
}
