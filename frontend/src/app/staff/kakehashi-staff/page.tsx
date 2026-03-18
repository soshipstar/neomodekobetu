'use client';

import { useState, useCallback } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import api from '@/lib/api';
import { Card, CardHeader, CardTitle, CardBody } from '@/components/ui/Card';
import { Button } from '@/components/ui/Button';
import { Badge } from '@/components/ui/Badge';
import { Skeleton, SkeletonList } from '@/components/ui/Skeleton';
import { useToast } from '@/components/ui/Toast';
import {
  Pencil,
  Download,
  Send,
  Sparkles,
  Save,
  ChevronDown,
  ChevronRight,
  FileText,
} from 'lucide-react';
import { format } from 'date-fns';

// ---------------------------------------------------------------------------
// Types
// ---------------------------------------------------------------------------

interface Student {
  id: number;
  student_name: string;
  support_start_date: string | null;
}

interface KakehashiStaffEntry {
  id: number;
  student_id: number;
  period_id: number;
  staff_id: number | null;
  student_wish: string;
  short_term_goal: string;
  long_term_goal: string;
  health_life: string;
  motor_sensory: string;
  cognitive_behavior: string;
  language_communication: string;
  social_relations: string;
  other_challenges: string;
  is_submitted: boolean;
  submitted_at: string | null;
  guardian_confirmed: boolean;
  guardian_confirmed_at: string | null;
  created_at: string;
  updated_at: string;
}

interface KakehashiGuardianEntry {
  id: number;
  home_situation: string;
  concerns: string;
  requests: string;
  is_submitted: boolean;
}

interface KakehashiPeriod {
  id: number;
  student_id: number;
  period_name: string;
  start_date: string;
  end_date: string;
  submission_deadline: string;
  is_active: boolean;
  staff_entries: KakehashiStaffEntry[];
  guardian_entries: KakehashiGuardianEntry[];
}

interface KakehashiForm {
  student_wish: string;
  short_term_goal: string;
  long_term_goal: string;
  health_life: string;
  motor_sensory: string;
  cognitive_behavior: string;
  language_communication: string;
  social_relations: string;
  other_challenges: string;
}

const DOMAIN_FIELDS = [
  { key: 'health_life', label: '健康・生活', color: 'var(--status-success-fg)' },
  { key: 'motor_sensory', label: '運動・感覚', color: 'var(--brand-80)' },
  { key: 'cognitive_behavior', label: '認知・行動', color: 'var(--status-warning-fg)' },
  { key: 'language_communication', label: '言語・コミュニケーション', color: 'var(--status-info-fg)' },
  { key: 'social_relations', label: '人間関係・社会性', color: 'var(--status-danger-fg)' },
] as const;

type DomainKey = typeof DOMAIN_FIELDS[number]['key'];

const emptyForm: KakehashiForm = {
  student_wish: '',
  short_term_goal: '',
  long_term_goal: '',
  health_life: '',
  motor_sensory: '',
  cognitive_behavior: '',
  language_communication: '',
  social_relations: '',
  other_challenges: '',
};

/** Normalize line breaks for display */
function nl(text: string | null | undefined): string {
  if (!text) return '';
  return text.replace(/\\r\\n|\\n|\\r/g, '\n').replace(/\r\n|\r/g, '\n');
}

// ---------------------------------------------------------------------------
// Main Component
// ---------------------------------------------------------------------------

export default function KakehashiStaffPage() {
  const queryClient = useQueryClient();
  const toast = useToast();

  const [selectedStudentId, setSelectedStudentId] = useState<number | null>(null);
  const [selectedPeriodId, setSelectedPeriodId] = useState<number | null>(null);
  const [editingPeriodId, setEditingPeriodId] = useState<number | null>(null);
  const [form, setForm] = useState<KakehashiForm>(emptyForm);
  const [generating, setGenerating] = useState(false);
  const [expandedPeriods, setExpandedPeriods] = useState<Set<number>>(new Set());

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

  // Save/submit mutation (POST to /api/staff/kakehashi/{periodId})
  const saveMutation = useMutation({
    mutationFn: async ({ periodId, action, ...data }: KakehashiForm & { periodId: number; action: 'save' | 'submit' }) =>
      api.post(`/api/staff/kakehashi/${periodId}`, { ...data, action }),
    onSuccess: (_, vars) => {
      queryClient.invalidateQueries({ queryKey: ['staff', 'kakehashi', 'periods', selectedStudentId] });
      toast.success(vars.action === 'submit' ? 'かけはしを提出しました' : '下書きを保存しました');
      if (vars.action === 'submit') {
        setEditingPeriodId(null);
      }
    },
    onError: () => toast.error('保存に失敗しました'),
  });

  // Update mutation (PUT to /api/staff/kakehashi/{periodId})
  const updateMutation = useMutation({
    mutationFn: async ({ periodId, ...data }: KakehashiForm & { periodId: number }) =>
      api.put(`/api/staff/kakehashi/${periodId}`, data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['staff', 'kakehashi', 'periods', selectedStudentId] });
      toast.success('かけはしを更新しました');
      setEditingPeriodId(null);
    },
    onError: () => toast.error('更新に失敗しました'),
  });

  // AI generation
  const handleAIGenerate = useCallback(async (periodId: number) => {
    if (!selectedStudentId) return;
    setGenerating(true);
    try {
      const res = await api.post<{ data: KakehashiForm; record_count: number }>('/api/staff/kakehashi/generate', {
        student_id: selectedStudentId,
        period_id: periodId,
      });
      setForm(prev => ({ ...prev, ...res.data.data }));
      toast.success(`AI生成完了（連絡帳${res.data.record_count}件を参照）`);
    } catch {
      toast.error('AI生成に失敗しました');
    } finally {
      setGenerating(false);
    }
  }, [selectedStudentId, toast]);

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

  // Start editing a period
  const startEditing = useCallback((periodId: number, entry?: KakehashiStaffEntry) => {
    setEditingPeriodId(periodId);
    if (entry) {
      setForm({
        student_wish: nl(entry.student_wish),
        short_term_goal: nl(entry.short_term_goal),
        long_term_goal: nl(entry.long_term_goal),
        health_life: nl(entry.health_life),
        motor_sensory: nl(entry.motor_sensory),
        cognitive_behavior: nl(entry.cognitive_behavior),
        language_communication: nl(entry.language_communication),
        social_relations: nl(entry.social_relations),
        other_challenges: nl(entry.other_challenges),
      });
    } else {
      setForm(emptyForm);
    }
  }, []);

  const handleSave = (periodId: number, action: 'save' | 'submit') => {
    saveMutation.mutate({ periodId, action, ...form });
  };

  const handleUpdate = (periodId: number) => {
    updateMutation.mutate({ periodId, ...form });
  };

  const togglePeriod = (id: number) => {
    setExpandedPeriods((prev) => {
      const next = new Set(prev);
      if (next.has(id)) next.delete(id);
      else next.add(id);
      return next;
    });
  };

  const updateField = (key: string, value: string) => {
    setForm((prev) => ({ ...prev, [key]: value }));
  };

  return (
    <div className="space-y-6">
      <h1 className="text-2xl font-bold text-[var(--neutral-foreground-1)]">かけはし（職員）</h1>

      {/* Student selector */}
      <Card>
        <CardBody>
          <label className="mb-2 block text-sm font-medium text-[var(--neutral-foreground-2)]">生徒を選択</label>
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
                setEditingPeriodId(null);
                setExpandedPeriods(new Set());
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

      {/* Periods */}
      {selectedStudentId && (
        loadingPeriods ? (
          <SkeletonList items={3} />
        ) : periods.length === 0 ? (
          <Card>
            <CardBody>
              <p className="text-sm text-[var(--neutral-foreground-3)]">かけはし期間がありません。</p>
            </CardBody>
          </Card>
        ) : (
          <div className="space-y-4">
            {periods.map((period) => {
              const entry = period.staff_entries?.[0] ?? null;
              const guardianEntry = period.guardian_entries?.[0] ?? null;
              const isExpanded = expandedPeriods.has(period.id);
              const isEditing = editingPeriodId === period.id;
              const isOverdue = new Date(period.submission_deadline) < new Date();
              const deadlineStr = format(new Date(period.submission_deadline), 'yyyy/MM/dd');
              const periodStr = `${format(new Date(period.start_date), 'yyyy/MM/dd')} ～ ${format(new Date(period.end_date), 'yyyy/MM/dd')}`;

              return (
                <Card key={period.id}>
                  {/* Period header (clickable accordion) */}
                  <button
                    onClick={() => togglePeriod(period.id)}
                    className="flex w-full items-center justify-between px-6 py-4 text-left hover:bg-[var(--neutral-background-3)] transition-colors rounded-t-lg"
                  >
                    <div className="flex items-center gap-3">
                      {isExpanded
                        ? <ChevronDown className="h-5 w-5 text-[var(--neutral-foreground-3)]" />
                        : <ChevronRight className="h-5 w-5 text-[var(--neutral-foreground-3)]" />
                      }
                      <div>
                        <h3 className="font-semibold text-[var(--neutral-foreground-1)]">{period.period_name}</h3>
                        <p className="text-xs text-[var(--neutral-foreground-3)]">{periodStr}</p>
                      </div>
                    </div>
                    <div className="flex items-center gap-2">
                      {entry ? (
                        <Badge variant={entry.is_submitted ? 'success' : 'warning'}>
                          {entry.is_submitted ? '提出済み' : '下書き'}
                        </Badge>
                      ) : (
                        <Badge variant="default">未入力</Badge>
                      )}
                      <Badge variant={isOverdue ? 'danger' : 'info'}>
                        期限: {deadlineStr}
                      </Badge>
                    </div>
                  </button>

                  {/* Expanded content */}
                  {isExpanded && (
                    <CardBody>
                      {isEditing ? (
                        /* ======================== EDIT MODE ======================== */
                        <div className="space-y-5">
                          {/* AI Generate button */}
                          <div className="flex items-center justify-between rounded-lg bg-[var(--neutral-background-3)] px-4 py-3">
                            <div>
                              <p className="text-sm font-medium text-[var(--neutral-foreground-1)]">AIで自動生成</p>
                              <p className="text-xs text-[var(--neutral-foreground-3)]">
                                期間内の連絡帳データを元にAIが内容を生成します
                              </p>
                            </div>
                            <Button
                              variant="secondary"
                              size="sm"
                              leftIcon={<Sparkles className="h-4 w-4" />}
                              onClick={() => handleAIGenerate(period.id)}
                              isLoading={generating}
                            >
                              AI生成
                            </Button>
                          </div>

                          {/* Goals */}
                          <div>
                            <label className="mb-1 block text-sm font-medium text-[var(--neutral-foreground-2)]">本人の願い</label>
                            <textarea
                              value={form.student_wish}
                              onChange={(e) => updateField('student_wish', e.target.value)}
                              className="block w-full rounded-lg border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-1)] px-3 py-2 text-sm text-[var(--neutral-foreground-1)]"
                              rows={2}
                            />
                          </div>

                          <div className="grid gap-4 md:grid-cols-2">
                            <div>
                              <label className="mb-1 block text-sm font-medium text-[var(--neutral-foreground-2)]">短期目標</label>
                              <textarea
                                value={form.short_term_goal}
                                onChange={(e) => updateField('short_term_goal', e.target.value)}
                                className="block w-full rounded-lg border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-1)] px-3 py-2 text-sm text-[var(--neutral-foreground-1)]"
                                rows={3}
                              />
                            </div>
                            <div>
                              <label className="mb-1 block text-sm font-medium text-[var(--neutral-foreground-2)]">長期目標</label>
                              <textarea
                                value={form.long_term_goal}
                                onChange={(e) => updateField('long_term_goal', e.target.value)}
                                className="block w-full rounded-lg border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-1)] px-3 py-2 text-sm text-[var(--neutral-foreground-1)]"
                                rows={3}
                              />
                            </div>
                          </div>

                          {/* 5 Domains */}
                          <h4 className="text-sm font-semibold text-[var(--neutral-foreground-1)] border-b border-[var(--neutral-stroke-2)] pb-2">
                            5領域
                          </h4>
                          {DOMAIN_FIELDS.map(({ key, label, color }) => (
                            <div key={key}>
                              <label className="mb-1 flex items-center gap-2 text-sm font-medium text-[var(--neutral-foreground-2)]">
                                <span className="h-2.5 w-2.5 rounded-full" style={{ backgroundColor: color }} />
                                {label}
                              </label>
                              <textarea
                                value={form[key as DomainKey]}
                                onChange={(e) => updateField(key, e.target.value)}
                                className="block w-full rounded-lg border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-1)] px-3 py-2 text-sm text-[var(--neutral-foreground-1)]"
                                rows={3}
                              />
                            </div>
                          ))}

                          {/* Other challenges */}
                          <div>
                            <label className="mb-1 block text-sm font-medium text-[var(--neutral-foreground-2)]">その他の課題</label>
                            <textarea
                              value={form.other_challenges}
                              onChange={(e) => updateField('other_challenges', e.target.value)}
                              className="block w-full rounded-lg border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-1)] px-3 py-2 text-sm text-[var(--neutral-foreground-1)]"
                              rows={2}
                            />
                          </div>

                          {/* Action buttons */}
                          <div className="flex items-center justify-between border-t border-[var(--neutral-stroke-2)] pt-4">
                            <Button
                              variant="ghost"
                              onClick={() => { setEditingPeriodId(null); setForm(emptyForm); }}
                            >
                              キャンセル
                            </Button>
                            <div className="flex gap-2">
                              {entry?.is_submitted ? (
                                <Button
                                  leftIcon={<Save className="h-4 w-4" />}
                                  onClick={() => handleUpdate(period.id)}
                                  isLoading={updateMutation.isPending}
                                >
                                  更新
                                </Button>
                              ) : (
                                <>
                                  <Button
                                    variant="secondary"
                                    leftIcon={<Save className="h-4 w-4" />}
                                    onClick={() => handleSave(period.id, 'save')}
                                    isLoading={saveMutation.isPending}
                                  >
                                    下書き保存
                                  </Button>
                                  <Button
                                    leftIcon={<Send className="h-4 w-4" />}
                                    onClick={() => {
                                      if (confirm('提出しますか？提出後も内容の修正は可能です。')) {
                                        handleSave(period.id, 'submit');
                                      }
                                    }}
                                    isLoading={saveMutation.isPending}
                                  >
                                    提出
                                  </Button>
                                </>
                              )}
                            </div>
                          </div>
                        </div>
                      ) : (
                        /* ======================== VIEW MODE ======================== */
                        <div className="space-y-4">
                          {/* Actions */}
                          <div className="flex items-center justify-end gap-2">
                            <Button
                              variant="outline"
                              size="sm"
                              leftIcon={<Download className="h-4 w-4" />}
                              onClick={() => handlePdfDownload(period.id, period.period_name)}
                            >
                              PDF
                            </Button>
                            <Button
                              variant="outline"
                              size="sm"
                              leftIcon={<Pencil className="h-4 w-4" />}
                              onClick={() => startEditing(period.id, entry ?? undefined)}
                            >
                              {entry ? '編集' : '入力開始'}
                            </Button>
                          </div>

                          {!entry ? (
                            <div className="py-8 text-center text-[var(--neutral-foreground-4)]">
                              <FileText className="mx-auto mb-2 h-10 w-10" />
                              <p className="text-sm">まだ入力されていません</p>
                              <p className="mt-1 text-xs">「入力開始」ボタンから記入を始めてください</p>
                            </div>
                          ) : (
                            <>
                              {/* Goals display */}
                              <div className="grid gap-4 md:grid-cols-3">
                                <DisplayField label="本人の願い" value={entry.student_wish} />
                                <DisplayField label="短期目標" value={entry.short_term_goal} />
                                <DisplayField label="長期目標" value={entry.long_term_goal} />
                              </div>

                              {/* 5 Domains display */}
                              <h4 className="text-sm font-semibold text-[var(--neutral-foreground-1)] border-b border-[var(--neutral-stroke-2)] pb-2 mt-2">
                                5領域
                              </h4>
                              <div className="grid gap-3 md:grid-cols-2 lg:grid-cols-3">
                                {DOMAIN_FIELDS.map(({ key, label, color }) => (
                                  <div
                                    key={key}
                                    className="rounded-lg border border-[var(--neutral-stroke-2)] p-3"
                                  >
                                    <div className="mb-1 flex items-center gap-2">
                                      <span className="h-2.5 w-2.5 rounded-full" style={{ backgroundColor: color }} />
                                      <span className="text-xs font-semibold text-[var(--neutral-foreground-2)]">{label}</span>
                                    </div>
                                    <p className="text-sm text-[var(--neutral-foreground-1)] whitespace-pre-wrap">
                                      {nl(entry[key as DomainKey]) || '-'}
                                    </p>
                                  </div>
                                ))}
                              </div>

                              {/* Other challenges */}
                              {entry.other_challenges && (
                                <div className="rounded-lg bg-[var(--neutral-background-3)] p-3 mt-2">
                                  <p className="text-xs font-medium text-[var(--neutral-foreground-3)] mb-1">その他の課題</p>
                                  <p className="text-sm text-[var(--neutral-foreground-1)] whitespace-pre-wrap">{nl(entry.other_challenges)}</p>
                                </div>
                              )}

                              {/* Guardian confirmation status */}
                              {entry.is_submitted && (
                                <div className="flex items-center gap-2 mt-2">
                                  <Badge variant={entry.guardian_confirmed ? 'success' : 'warning'}>
                                    {entry.guardian_confirmed ? '保護者確認済み' : '保護者未確認'}
                                  </Badge>
                                  {entry.guardian_confirmed && entry.guardian_confirmed_at && (
                                    <span className="text-xs text-[var(--neutral-foreground-4)]">
                                      {format(new Date(entry.guardian_confirmed_at), 'yyyy/MM/dd HH:mm')}
                                    </span>
                                  )}
                                </div>
                              )}

                              {/* Guardian entry summary if exists */}
                              {guardianEntry && guardianEntry.is_submitted && (
                                <div className="mt-4 rounded-lg border border-pink-200 bg-pink-50 p-4">
                                  <h4 className="text-sm font-semibold text-pink-800 mb-2">保護者記入（提出済み）</h4>
                                  <div className="grid gap-3 md:grid-cols-3 text-sm">
                                    <div>
                                      <p className="text-xs text-pink-600 font-medium">家庭での状況</p>
                                      <p className="text-pink-900 whitespace-pre-wrap">{nl(guardianEntry.home_situation) || '-'}</p>
                                    </div>
                                    <div>
                                      <p className="text-xs text-pink-600 font-medium">心配事・気になること</p>
                                      <p className="text-pink-900 whitespace-pre-wrap">{nl(guardianEntry.concerns) || '-'}</p>
                                    </div>
                                    <div>
                                      <p className="text-xs text-pink-600 font-medium">要望</p>
                                      <p className="text-pink-900 whitespace-pre-wrap">{nl(guardianEntry.requests) || '-'}</p>
                                    </div>
                                  </div>
                                </div>
                              )}

                              {/* Meta */}
                              <p className="text-xs text-[var(--neutral-foreground-4)] text-right">
                                最終更新: {format(new Date(entry.updated_at), 'yyyy/MM/dd HH:mm')}
                                {entry.submitted_at && ` ・ 提出: ${format(new Date(entry.submitted_at), 'yyyy/MM/dd HH:mm')}`}
                              </p>
                            </>
                          )}
                        </div>
                      )}
                    </CardBody>
                  )}
                </Card>
              );
            })}
          </div>
        )
      )}
    </div>
  );
}

// ---------------------------------------------------------------------------
// Display Field
// ---------------------------------------------------------------------------

function DisplayField({ label, value }: { label: string; value: string | null }) {
  return (
    <div className="rounded-lg bg-[var(--neutral-background-3)] p-3">
      <p className="text-xs font-medium text-[var(--neutral-foreground-3)] mb-1">{label}</p>
      <p className="text-sm text-[var(--neutral-foreground-1)] whitespace-pre-wrap">{nl(value) || '-'}</p>
    </div>
  );
}
