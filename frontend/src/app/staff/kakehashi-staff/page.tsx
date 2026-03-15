'use client';

import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import api from '@/lib/api';
import { Card, CardHeader, CardTitle, CardBody } from '@/components/ui/Card';
import { Button } from '@/components/ui/Button';
import { Input } from '@/components/ui/Input';
import { Badge } from '@/components/ui/Badge';
import { Modal } from '@/components/ui/Modal';
import { SkeletonList } from '@/components/ui/Skeleton';
import { useToast } from '@/components/ui/Toast';
import {
  Plus,
  Pencil,
  Trash2,
  Download,
  Send,
  Sparkles,
  User,
  Calendar,
  ChevronRight,
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

interface KakehashiPeriod {
  id: number;
  student_id: number;
  period_name: string;
  start_date: string;
  end_date: string;
  submission_deadline: string;
  is_active: boolean;
}

interface KakehashiStaff {
  id: number;
  student_id: number;
  period_id: number;
  student_wish: string;
  short_term_goal: string;
  long_term_goal: string;
  health_life: string;
  motor_sensory: string;
  cognitive_behavior: string;
  language_communication: string;
  social_relations: string;
  is_submitted: boolean;
  created_at: string;
  updated_at: string;
}

const DOMAIN_FIELDS = [
  { key: 'health_life', label: '健康・生活' },
  { key: 'motor_sensory', label: '運動・感覚' },
  { key: 'cognitive_behavior', label: '認知・行動' },
  { key: 'language_communication', label: '言語・コミュニケーション' },
  { key: 'social_relations', label: '人間関係・社会性' },
] as const;

type DomainKey = typeof DOMAIN_FIELDS[number]['key'];

interface KakehashiForm {
  student_wish: string;
  short_term_goal: string;
  long_term_goal: string;
  health_life: string;
  motor_sensory: string;
  cognitive_behavior: string;
  language_communication: string;
  social_relations: string;
}

const emptyForm: KakehashiForm = {
  student_wish: '',
  short_term_goal: '',
  long_term_goal: '',
  health_life: '',
  motor_sensory: '',
  cognitive_behavior: '',
  language_communication: '',
  social_relations: '',
};

export default function KakehashiStaffPage() {
  const queryClient = useQueryClient();
  const toast = useToast();

  const [selectedStudentId, setSelectedStudentId] = useState<number | null>(null);
  const [selectedPeriodId, setSelectedPeriodId] = useState<number | null>(null);
  const [editModal, setEditModal] = useState(false);
  const [editingEntry, setEditingEntry] = useState<KakehashiStaff | null>(null);
  const [form, setForm] = useState<KakehashiForm>(emptyForm);
  const [generating, setGenerating] = useState(false);

  // Fetch students
  const { data: students = [], isLoading: loadingStudents } = useQuery({
    queryKey: ['staff', 'kakehashi', 'students'],
    queryFn: async () => {
      const res = await api.get<{ data: Student[] }>('/api/staff/students');
      return res.data.data;
    },
  });

  // Fetch periods (with staff/guardian entries) for selected student
  const { data: periodsRaw = [], isLoading: loadingEntries } = useQuery({
    queryKey: ['staff', 'kakehashi', 'periods', selectedStudentId],
    queryFn: async () => {
      const res = await api.get<{ data: (KakehashiPeriod & { staff_entries?: KakehashiStaff[]; guardian_entries?: unknown[] })[] }>(
        `/api/staff/students/${selectedStudentId}/kakehashi`
      );
      return res.data.data;
    },
    enabled: !!selectedStudentId,
  });

  // Extract periods and entries from the combined response
  const periods: KakehashiPeriod[] = periodsRaw.map(({ staff_entries, guardian_entries, ...period }) => period);
  const entries: KakehashiStaff[] = periodsRaw.flatMap((p) => p.staff_entries ?? []);

  // Create mutation
  const createMutation = useMutation({
    mutationFn: (data: KakehashiForm & { student_id: number; period_id: number }) =>
      api.post('/api/staff/kakehashi', data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['staff', 'kakehashi'] });
      toast.success('かけはしを作成しました');
      setEditModal(false);
      setForm(emptyForm);
    },
    onError: () => toast.error('作成に失敗しました'),
  });

  // Update mutation
  const updateMutation = useMutation({
    mutationFn: ({ id, ...data }: KakehashiForm & { id: number }) =>
      api.put(`/api/staff/kakehashi/${id}`, data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['staff', 'kakehashi'] });
      toast.success('かけはしを更新しました');
      setEditModal(false);
      setEditingEntry(null);
      setForm(emptyForm);
    },
    onError: () => toast.error('更新に失敗しました'),
  });

  // Delete mutation
  const deleteMutation = useMutation({
    mutationFn: (id: number) => api.delete(`/api/staff/kakehashi/${id}`),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['staff', 'kakehashi'] });
      toast.success('かけはしを削除しました');
    },
    onError: () => toast.error('削除に失敗しました'),
  });

  // Submit mutation
  const submitMutation = useMutation({
    mutationFn: (id: number) => api.post(`/api/staff/kakehashi/${id}/submit`),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['staff', 'kakehashi'] });
      toast.success('かけはしを提出しました');
    },
    onError: () => toast.error('提出に失敗しました'),
  });

  // AI generation
  const handleAIGenerate = async () => {
    if (!selectedStudentId || !selectedPeriodId) {
      toast.error('生徒と期間を選択してください');
      return;
    }
    setGenerating(true);
    try {
      const res = await api.post<{ data: KakehashiForm }>('/api/staff/kakehashi/generate', {
        student_id: selectedStudentId,
        period_id: selectedPeriodId,
      });
      setForm(res.data.data);
      toast.success('AI生成が完了しました');
    } catch {
      toast.error('AI生成に失敗しました');
    } finally {
      setGenerating(false);
    }
  };

  // PDF download
  const handlePdfDownload = async (entryId: number) => {
    try {
      const res = await api.get(`/api/staff/kakehashi/${entryId}/pdf`, { responseType: 'blob' });
      const url = window.URL.createObjectURL(new Blob([res.data]));
      const link = document.createElement('a');
      link.href = url;
      link.download = `kakehashi_staff_${entryId}.pdf`;
      link.click();
      window.URL.revokeObjectURL(url);
    } catch {
      toast.error('PDF生成に失敗しました');
    }
  };

  const openCreateModal = (periodId: number) => {
    setSelectedPeriodId(periodId);
    setEditingEntry(null);
    setForm(emptyForm);
    setEditModal(true);
  };

  const openEditModal = (entry: KakehashiStaff) => {
    setSelectedPeriodId(entry.period_id);
    setEditingEntry(entry);
    setForm({
      student_wish: entry.student_wish,
      short_term_goal: entry.short_term_goal,
      long_term_goal: entry.long_term_goal,
      health_life: entry.health_life,
      motor_sensory: entry.motor_sensory,
      cognitive_behavior: entry.cognitive_behavior,
      language_communication: entry.language_communication,
      social_relations: entry.social_relations,
    });
    setEditModal(true);
  };

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    if (editingEntry) {
      updateMutation.mutate({ id: editingEntry.id, ...form });
    } else if (selectedStudentId && selectedPeriodId) {
      createMutation.mutate({ student_id: selectedStudentId, period_id: selectedPeriodId, ...form });
    }
  };

  const updateField = (key: string, value: string) => {
    setForm((prev) => ({ ...prev, [key]: value }));
  };

  const selectedStudent = students.find((s) => s.id === selectedStudentId);

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-bold text-[var(--neutral-foreground-1)]">かけはし（職員）</h1>
      </div>

      {/* Student selector */}
      <Card>
        <CardBody>
          <label className="mb-2 block text-sm font-medium text-[var(--neutral-foreground-2)]">生徒を選択</label>
          {loadingStudents ? (
            <SkeletonList items={1} />
          ) : (
            <select
              className="block w-full rounded-lg border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-1)] px-3 py-2 text-sm text-[var(--neutral-foreground-1)]"
              value={selectedStudentId ?? ''}
              onChange={(e) => {
                setSelectedStudentId(e.target.value ? Number(e.target.value) : null);
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

      {/* Periods and entries */}
      {selectedStudentId && (
        <>
          {periods.length === 0 && !loadingEntries ? (
            <Card>
              <CardBody>
                <p className="text-sm text-[var(--neutral-foreground-3)]">かけはし期間がありません。</p>
              </CardBody>
            </Card>
          ) : (
            <div className="space-y-4">
              {periods.map((period) => {
                const periodEntries = entries.filter((e) => e.period_id === period.id);
                const isOverdue = new Date(period.submission_deadline) < new Date();

                return (
                  <Card key={period.id}>
                    <CardHeader>
                      <div className="flex items-center justify-between">
                        <div className="flex items-center gap-3">
                          <CardTitle>{period.period_name}</CardTitle>
                          <Badge variant={isOverdue ? 'danger' : 'default'}>
                            期限: {format(new Date(period.submission_deadline), 'yyyy/MM/dd')}
                          </Badge>
                        </div>
                        {periodEntries.length === 0 && (
                          <Button size="sm" leftIcon={<Plus className="h-4 w-4" />} onClick={() => openCreateModal(period.id)}>
                            作成
                          </Button>
                        )}
                      </div>
                    </CardHeader>
                    <CardBody>
                      {periodEntries.length === 0 ? (
                        <p className="text-sm text-[var(--neutral-foreground-3)]">まだ入力されていません。</p>
                      ) : (
                        <div className="space-y-3">
                          {periodEntries.map((entry) => (
                            <div key={entry.id} className="rounded-lg border border-[var(--neutral-stroke-2)] p-4">
                              <div className="mb-3 flex items-center justify-between">
                                <div className="flex items-center gap-2">
                                  <Badge variant={entry.is_submitted ? 'success' : 'warning'}>
                                    {entry.is_submitted ? '提出済み' : '下書き'}
                                  </Badge>
                                  <span className="text-xs text-[var(--neutral-foreground-3)]">
                                    更新: {format(new Date(entry.updated_at), 'yyyy/MM/dd HH:mm')}
                                  </span>
                                </div>
                                <div className="flex gap-1">
                                  <Button variant="outline" size="sm" onClick={() => handlePdfDownload(entry.id)}>
                                    <Download className="h-4 w-4" />
                                  </Button>
                                  {!entry.is_submitted && (
                                    <>
                                      <Button variant="outline" size="sm" onClick={() => openEditModal(entry)}>
                                        <Pencil className="h-4 w-4" />
                                      </Button>
                                      <Button
                                        variant="outline"
                                        size="sm"
                                        onClick={() => { if (confirm('提出しますか？提出後は編集できません。')) submitMutation.mutate(entry.id); }}
                                      >
                                        <Send className="h-4 w-4" />
                                      </Button>
                                      <Button
                                        variant="ghost"
                                        size="sm"
                                        onClick={() => { if (confirm('削除しますか？')) deleteMutation.mutate(entry.id); }}
                                      >
                                        <Trash2 className="h-4 w-4 text-[var(--status-danger-fg)]" />
                                      </Button>
                                    </>
                                  )}
                                </div>
                              </div>

                              <div className="grid gap-3 md:grid-cols-2">
                                <div>
                                  <p className="text-xs font-medium text-[var(--neutral-foreground-3)]">本人の願い</p>
                                  <p className="text-sm text-[var(--neutral-foreground-1)] whitespace-pre-wrap">{entry.student_wish || '-'}</p>
                                </div>
                                <div>
                                  <p className="text-xs font-medium text-[var(--neutral-foreground-3)]">短期目標</p>
                                  <p className="text-sm text-[var(--neutral-foreground-1)] whitespace-pre-wrap">{entry.short_term_goal || '-'}</p>
                                </div>
                                <div className="md:col-span-2">
                                  <p className="text-xs font-medium text-[var(--neutral-foreground-3)]">長期目標</p>
                                  <p className="text-sm text-[var(--neutral-foreground-1)] whitespace-pre-wrap">{entry.long_term_goal || '-'}</p>
                                </div>
                              </div>

                              <div className="mt-3 space-y-2">
                                {DOMAIN_FIELDS.map(({ key, label }) => (
                                  <div key={key}>
                                    <p className="text-xs font-medium text-[var(--neutral-foreground-3)]">{label}</p>
                                    <p className="text-sm text-[var(--neutral-foreground-1)] whitespace-pre-wrap">
                                      {entry[key as DomainKey] || '-'}
                                    </p>
                                  </div>
                                ))}
                              </div>
                            </div>
                          ))}
                        </div>
                      )}
                    </CardBody>
                  </Card>
                );
              })}
            </div>
          )}
        </>
      )}

      {/* Create / Edit Modal */}
      <Modal
        isOpen={editModal}
        onClose={() => { setEditModal(false); setEditingEntry(null); setForm(emptyForm); }}
        title={editingEntry ? 'かけはし編集' : 'かけはし作成'}
        size="full"
      >
        <form onSubmit={handleSubmit} className="space-y-4">
          <div className="flex justify-end">
            <Button
              type="button"
              variant="secondary"
              size="sm"
              leftIcon={<Sparkles className="h-4 w-4" />}
              onClick={handleAIGenerate}
              isLoading={generating}
            >
              AI生成
            </Button>
          </div>

          <Input
            label="本人の願い"
            value={form.student_wish}
            onChange={(e) => updateField('student_wish', e.target.value)}
          />

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

          <h3 className="text-lg font-semibold text-[var(--neutral-foreground-1)]">5領域</h3>

          {DOMAIN_FIELDS.map(({ key, label }) => (
            <div key={key}>
              <label className="mb-1 block text-sm font-medium text-[var(--neutral-foreground-2)]">{label}</label>
              <textarea
                value={form[key as DomainKey]}
                onChange={(e) => updateField(key, e.target.value)}
                className="block w-full rounded-lg border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-1)] px-3 py-2 text-sm text-[var(--neutral-foreground-1)]"
                rows={3}
              />
            </div>
          ))}

          <div className="flex justify-end gap-2 pt-4">
            <Button variant="secondary" type="button" onClick={() => { setEditModal(false); setEditingEntry(null); }}>
              キャンセル
            </Button>
            <Button type="submit" isLoading={createMutation.isPending || updateMutation.isPending}>
              {editingEntry ? '更新' : '作成'}
            </Button>
          </div>
        </form>
      </Modal>
    </div>
  );
}
