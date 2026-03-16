'use client';

import { useState, useCallback } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import api from '@/lib/api';
import { Card, CardHeader, CardTitle, CardBody } from '@/components/ui/Card';
import { Button } from '@/components/ui/Button';
import { Input } from '@/components/ui/Input';
import { Badge } from '@/components/ui/Badge';
import { SkeletonList } from '@/components/ui/Skeleton';
import { useToast } from '@/components/ui/Toast';
import {
  Plus,
  Pencil,
  Trash2,
  Download,
  Sparkles,
  FileText,
  Save,
  Send,
  PenLine,
  ChevronLeft,
  ExternalLink,
} from 'lucide-react';
import Link from 'next/link';

// ---------------------------------------------------------------------------
// Text normalizer
// ---------------------------------------------------------------------------
function nl(text: string | null | undefined): string {
  if (!text) return '';
  return text.replace(/\\n/g, '\n').replace(/\r\n/g, '\n');
}

// ---------------------------------------------------------------------------
// Types
// ---------------------------------------------------------------------------

interface Student {
  id: number;
  student_name: string;
}

type PlanStatus = 'draft' | 'proposal' | 'official';

interface SupportPlanSummary {
  id: number;
  student_id: number;
  student_name: string;
  created_date: string;
  status: PlanStatus;
  is_confirmed: boolean;
  has_signature: boolean;
  detail_count: number;
  long_term_goal: string | null;
  short_term_goal: string | null;
  created_at: string;
}

interface SupportPlanDetail {
  id?: number;
  category: string;
  sub_category: string;
  support_goal: string;
  support_content: string;
  achievement_date: string;
  staff_organization: string;
  notes: string;
  priority: number;
  sort_order: number;
}

interface PlanFullData {
  id: number;
  student_id: number;
  created_date: string;
  status: PlanStatus;
  guardian_wish: string;
  overall_policy: string;
  long_term_goal: string;
  long_term_goal_date: string;
  short_term_goal: string;
  short_term_goal_date: string;
  manager_name: string;
  consent_date: string;
  guardian_signature_text: string;
  signature_status: string;
  details: SupportPlanDetail[];
}

interface PlanForm {
  created_date: string;
  guardian_wish: string;
  overall_policy: string;
  long_term_goal: string;
  long_term_goal_date: string;
  short_term_goal: string;
  short_term_goal_date: string;
  manager_name: string;
  consent_date: string;
  guardian_signature_text: string;
  details: SupportPlanDetail[];
}

// ---------------------------------------------------------------------------
// Defaults
// ---------------------------------------------------------------------------

const DEFAULT_DETAILS: SupportPlanDetail[] = [
  { category: '本人支援', sub_category: '生活習慣（健康・生活）', support_goal: '', support_content: '', achievement_date: '', staff_organization: '保育士\n児童指導員', notes: '', priority: 1, sort_order: 1 },
  { category: '本人支援', sub_category: 'コミュニケーション（言語・コミュニケーション）', support_goal: '', support_content: '', achievement_date: '', staff_organization: '保育士\n児童指導員', notes: '', priority: 2, sort_order: 2 },
  { category: '本人支援', sub_category: '社会性（人間関係・社会性）', support_goal: '', support_content: '', achievement_date: '', staff_organization: '保育士\n児童指導員', notes: '', priority: 3, sort_order: 3 },
  { category: '本人支援', sub_category: '運動・感覚（運動・感覚）', support_goal: '', support_content: '', achievement_date: '', staff_organization: '保育士\n児童指導員', notes: '', priority: 4, sort_order: 4 },
  { category: '本人支援', sub_category: '学習（認知・行動）', support_goal: '', support_content: '', achievement_date: '', staff_organization: '保育士\n児童指導員', notes: '', priority: 5, sort_order: 5 },
  { category: '家族支援', sub_category: '保護者支援', support_goal: '', support_content: '', achievement_date: '', staff_organization: '児童発達支援管理責任者', notes: '', priority: 6, sort_order: 6 },
  { category: '地域支援', sub_category: '関係機関連携', support_goal: '', support_content: '', achievement_date: '', staff_organization: '児童発達支援管理責任者', notes: '', priority: 7, sort_order: 7 },
];

const emptyForm = (): PlanForm => ({
  created_date: new Date().toISOString().split('T')[0],
  guardian_wish: '',
  overall_policy: '',
  long_term_goal: '',
  long_term_goal_date: '',
  short_term_goal: '',
  short_term_goal_date: '',
  manager_name: '',
  consent_date: '',
  guardian_signature_text: '',
  details: DEFAULT_DETAILS.map((d) => ({ ...d })),
});

// ---------------------------------------------------------------------------
// Status helpers
// ---------------------------------------------------------------------------

function statusLabel(status: PlanStatus): string {
  switch (status) {
    case 'draft': return '下書き';
    case 'proposal': return '確認依頼中';
    case 'official': return '署名済み';
    default: return String(status);
  }
}

function statusVariant(status: PlanStatus): 'warning' | 'info' | 'success' {
  switch (status) {
    case 'draft': return 'warning';
    case 'proposal': return 'info';
    case 'official': return 'success';
    default: return 'warning';
  }
}

// ---------------------------------------------------------------------------
// Shared styles
// ---------------------------------------------------------------------------
const textareaClass =
  'block w-full rounded-lg border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-1)] px-3 py-2 text-sm text-[var(--neutral-foreground-1)] focus:border-[var(--brand-80)] focus:outline-none focus:ring-1 focus:ring-[var(--brand-80)]';

const selectClass =
  'block w-full rounded-lg border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-1)] px-3 py-2 text-sm text-[var(--neutral-foreground-1)] focus:border-[var(--brand-80)] focus:outline-none focus:ring-1 focus:ring-[var(--brand-80)]';

const labelClass = 'mb-1 block text-sm font-medium text-[var(--neutral-foreground-2)]';

const thClass =
  'px-3 py-2 text-left text-xs font-semibold text-[var(--neutral-foreground-2)] bg-[var(--neutral-background-3)] border-b border-[var(--neutral-stroke-2)]';

const tdClass =
  'px-3 py-2 text-sm text-[var(--neutral-foreground-1)] border-b border-[var(--neutral-stroke-2)]';

// ---------------------------------------------------------------------------
// Component
// ---------------------------------------------------------------------------

export default function KobetsuPlanPage() {
  const queryClient = useQueryClient();
  const toast = useToast();

  // View state: 'list' | 'editor'
  const [view, setView] = useState<'list' | 'editor'>('list');
  const [selectedStudentId, setSelectedStudentId] = useState<number | null>(null);
  const [editingPlanId, setEditingPlanId] = useState<number | null>(null);
  const [form, setForm] = useState<PlanForm>(emptyForm());
  const [generating, setGenerating] = useState(false);

  // ------ Data fetching ------

  const { data: students = [], isLoading: loadingStudents } = useQuery({
    queryKey: ['staff', 'students'],
    queryFn: async () => {
      const res = await api.get<{ data: Student[] }>('/api/staff/students');
      return res.data.data;
    },
  });

  const { data: plans = [], isLoading: loadingPlans } = useQuery({
    queryKey: ['staff', 'support-plans', 'individual', selectedStudentId],
    queryFn: async () => {
      const res = await api.get<{ data: SupportPlanSummary[] }>(
        `/api/staff/students/${selectedStudentId}/support-plans`
      );
      const payload = res.data?.data;
      return Array.isArray(payload) ? payload : [];
    },
    enabled: !!selectedStudentId,
  });

  // ------ Mutations ------

  const createMutation = useMutation({
    mutationFn: (data: PlanForm) =>
      api.post(`/api/staff/students/${selectedStudentId}/support-plans`, data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['staff', 'support-plans', 'individual'] });
      toast.success('個別支援計画を作成しました');
      setView('list');
    },
    onError: () => toast.error('作成に失敗しました'),
  });

  const updateMutation = useMutation({
    mutationFn: ({ id, ...data }: PlanForm & { id: number }) =>
      api.put(`/api/staff/support-plans/${id}`, data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['staff', 'support-plans', 'individual'] });
      toast.success('個別支援計画を更新しました');
      setView('list');
    },
    onError: () => toast.error('更新に失敗しました'),
  });

  const deleteMutation = useMutation({
    mutationFn: (id: number) => api.delete(`/api/staff/support-plans/${id}`),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['staff', 'support-plans', 'individual'] });
      toast.success('個別支援計画を削除しました');
    },
    onError: () => toast.error('削除に失敗しました'),
  });

  const publishMutation = useMutation({
    mutationFn: (id: number) => api.post(`/api/staff/support-plans/${id}/publish`),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['staff', 'support-plans', 'individual'] });
      toast.success('計画書案として提出しました');
      setView('list');
    },
    onError: () => toast.error('提出に失敗しました'),
  });

  const makeOfficialMutation = useMutation({
    mutationFn: (id: number) => api.post(`/api/staff/support-plans/${id}/make-official`),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['staff', 'support-plans', 'individual'] });
      toast.success('正式版として確定しました');
      setView('list');
    },
    onError: () => toast.error('確定に失敗しました'),
  });

  const signMutation = useMutation({
    mutationFn: (data: { id: number; guardian_signature_text: string; consent_date: string; manager_name: string }) =>
      api.post(`/api/staff/support-plans/${data.id}/sign`, data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['staff', 'support-plans', 'individual'] });
      toast.success('署名を保存しました');
      setView('list');
    },
    onError: () => toast.error('署名の保存に失敗しました'),
  });

  // ------ Handlers ------

  const openCreate = () => {
    setEditingPlanId(null);
    setForm(emptyForm());
    setView('editor');
  };

  const openEdit = useCallback(async (planId: number) => {
    try {
      const res = await api.get<{ data: PlanFullData }>(`/api/staff/support-plans/${planId}`);
      const p = res.data.data;
      setEditingPlanId(planId);
      setForm({
        created_date: p.created_date || new Date().toISOString().split('T')[0],
        guardian_wish: nl(p.guardian_wish),
        overall_policy: nl(p.overall_policy),
        long_term_goal: nl(p.long_term_goal),
        long_term_goal_date: p.long_term_goal_date || '',
        short_term_goal: nl(p.short_term_goal),
        short_term_goal_date: p.short_term_goal_date || '',
        manager_name: p.manager_name || '',
        consent_date: p.consent_date || '',
        guardian_signature_text: p.guardian_signature_text || '',
        details: p.details && p.details.length > 0
          ? p.details.map((d) => ({
              ...d,
              support_goal: nl(d.support_goal),
              support_content: nl(d.support_content),
              staff_organization: nl(d.staff_organization),
              notes: nl(d.notes),
            }))
          : DEFAULT_DETAILS.map((d) => ({ ...d })),
      });
      setView('editor');
    } catch {
      toast.error('計画の読み込みに失敗しました');
    }
  }, [toast]);

  const handleSaveDraft = (e: React.FormEvent) => {
    e.preventDefault();
    if (editingPlanId) {
      updateMutation.mutate({ id: editingPlanId, ...form });
    } else {
      createMutation.mutate(form);
    }
  };

  const handlePublish = () => {
    if (!editingPlanId) {
      toast.error('まず下書き保存してください');
      return;
    }
    publishMutation.mutate(editingPlanId);
  };

  const handleSign = () => {
    if (!editingPlanId) return;
    signMutation.mutate({
      id: editingPlanId,
      guardian_signature_text: form.guardian_signature_text,
      consent_date: form.consent_date,
      manager_name: form.manager_name,
    });
  };

  const handleAIGenerate = async () => {
    if (!selectedStudentId) {
      toast.error('生徒を選択してください');
      return;
    }
    setGenerating(true);
    try {
      const endpoint = editingPlanId
        ? `/api/staff/support-plans/${editingPlanId}/generate-ai`
        : '/api/staff/support-plans/generate-ai';
      const res = await api.post<{ data: { details: SupportPlanDetail[]; long_term_goal?: string; short_term_goal?: string; overall_policy?: string } }>(
        endpoint,
        { student_id: selectedStudentId }
      );
      const aiData = res.data.data;
      setForm((prev) => ({
        ...prev,
        details: aiData.details || prev.details,
        long_term_goal: aiData.long_term_goal || prev.long_term_goal,
        short_term_goal: aiData.short_term_goal || prev.short_term_goal,
        overall_policy: aiData.overall_policy || prev.overall_policy,
      }));
      toast.success('AI生成が完了しました');
    } catch {
      toast.error('AI生成に失敗しました');
    } finally {
      setGenerating(false);
    }
  };

  const handlePdfDownload = async (planId: number, type: 'proposal' | 'official' = 'proposal') => {
    try {
      const res = await api.get(`/api/staff/support-plans/${planId}/pdf`, {
        responseType: 'blob',
        params: { type },
      });
      const url = window.URL.createObjectURL(new Blob([res.data]));
      const link = document.createElement('a');
      link.href = url;
      link.download = `kobetsu_plan_${type}_${planId}.pdf`;
      link.click();
      window.URL.revokeObjectURL(url);
    } catch {
      toast.error('PDF生成に失敗しました');
    }
  };

  const handleCsvExport = async () => {
    if (!editingPlanId) return;
    try {
      const res = await api.get(`/api/staff/support-plans/${editingPlanId}/csv`, {
        responseType: 'blob',
      });
      const url = window.URL.createObjectURL(new Blob([res.data]));
      const link = document.createElement('a');
      link.href = url;
      link.download = `kobetsu_plan_${editingPlanId}.csv`;
      link.click();
      window.URL.revokeObjectURL(url);
    } catch {
      toast.error('CSV出力に失敗しました');
    }
  };

  const updateDetail = (index: number, field: keyof SupportPlanDetail, value: string | number) => {
    setForm((prev) => ({
      ...prev,
      details: prev.details.map((d, i) =>
        i === index ? { ...d, [field]: value } : d
      ),
    }));
  };

  const addDetail = () => {
    setForm((prev) => ({
      ...prev,
      details: [
        ...prev.details,
        {
          category: '',
          sub_category: '',
          support_goal: '',
          support_content: '',
          achievement_date: '',
          staff_organization: '',
          notes: '',
          priority: prev.details.length + 1,
          sort_order: prev.details.length + 1,
        },
      ],
    }));
  };

  const removeDetail = (index: number) => {
    setForm((prev) => ({
      ...prev,
      details: prev.details.filter((_, i) => i !== index),
    }));
  };

  const selectedStudent = students.find((s) => s.id === selectedStudentId);

  // =========================================================================
  // RENDER: Editor View
  // =========================================================================
  if (view === 'editor') {
    return (
      <div className="space-y-6">
        {/* Header */}
        <div className="flex items-center gap-3">
          <Button variant="ghost" size="sm" onClick={() => setView('list')}>
            <ChevronLeft className="h-4 w-4" />
          </Button>
          <h1 className="text-2xl font-bold text-[var(--neutral-foreground-1)]">
            {editingPlanId ? '個別支援計画を編集' : '個別支援計画を作成'}
          </h1>
          {selectedStudent && (
            <span className="text-sm text-[var(--neutral-foreground-3)]">
              - {selectedStudent.student_name}
            </span>
          )}
        </div>

        <form onSubmit={handleSaveDraft} className="space-y-8">
          {/* ============================================================= */}
          {/* Section A: Basic Info */}
          {/* ============================================================= */}
          <Card>
            <CardHeader>
              <CardTitle>A. 基本情報</CardTitle>
            </CardHeader>
            <CardBody>
              <div className="space-y-4">
                <div className="max-w-xs">
                  <Input
                    label="作成年月日"
                    type="date"
                    value={form.created_date}
                    onChange={(e) => setForm({ ...form, created_date: e.target.value })}
                    required
                  />
                </div>

                <div>
                  <label className={labelClass}>利用児及び家族の生活に対する意向</label>
                  <textarea
                    className={textareaClass}
                    rows={4}
                    value={form.guardian_wish}
                    onChange={(e) => setForm({ ...form, guardian_wish: e.target.value })}
                    placeholder="保護者や本人の生活に対する意向を記入してください..."
                  />
                </div>

                <div>
                  <label className={labelClass}>総合的な支援の方針</label>
                  <textarea
                    className={textareaClass}
                    rows={4}
                    value={form.overall_policy}
                    onChange={(e) => setForm({ ...form, overall_policy: e.target.value })}
                    placeholder="総合的な支援方針を記入してください..."
                  />
                </div>
              </div>
            </CardBody>
          </Card>

          {/* ============================================================= */}
          {/* Section B: Goals */}
          {/* ============================================================= */}
          <Card>
            <CardHeader>
              <CardTitle>B. 目標</CardTitle>
            </CardHeader>
            <CardBody>
              <div className="space-y-6">
                {/* Long-term goal */}
                <div className="rounded-lg border border-[var(--neutral-stroke-2)] p-4">
                  <h4 className="mb-3 text-sm font-semibold text-[var(--neutral-foreground-1)]">長期目標</h4>
                  <div className="grid gap-4 md:grid-cols-[200px_1fr]">
                    <Input
                      label="達成時期"
                      type="date"
                      value={form.long_term_goal_date}
                      onChange={(e) => setForm({ ...form, long_term_goal_date: e.target.value })}
                    />
                    <div>
                      <label className={labelClass}>目標内容</label>
                      <textarea
                        className={textareaClass}
                        rows={4}
                        value={form.long_term_goal}
                        onChange={(e) => setForm({ ...form, long_term_goal: e.target.value })}
                        placeholder="長期目標を記入してください..."
                      />
                    </div>
                  </div>
                </div>

                {/* Short-term goal */}
                <div className="rounded-lg border border-[var(--neutral-stroke-2)] p-4">
                  <h4 className="mb-3 text-sm font-semibold text-[var(--neutral-foreground-1)]">短期目標</h4>
                  <div className="grid gap-4 md:grid-cols-[200px_1fr]">
                    <Input
                      label="達成時期"
                      type="date"
                      value={form.short_term_goal_date}
                      onChange={(e) => setForm({ ...form, short_term_goal_date: e.target.value })}
                    />
                    <div>
                      <label className={labelClass}>目標内容</label>
                      <textarea
                        className={textareaClass}
                        rows={4}
                        value={form.short_term_goal}
                        onChange={(e) => setForm({ ...form, short_term_goal: e.target.value })}
                        placeholder="短期目標を記入してください..."
                      />
                    </div>
                  </div>
                </div>
              </div>
            </CardBody>
          </Card>

          {/* ============================================================= */}
          {/* Section C: Support Details Table */}
          {/* ============================================================= */}
          <Card>
            <CardHeader>
              <div className="flex items-center justify-between">
                <CardTitle>C. 支援内容</CardTitle>
                <Button
                  type="button"
                  variant="outline"
                  size="sm"
                  leftIcon={<Plus className="h-4 w-4" />}
                  onClick={addDetail}
                >
                  行を追加
                </Button>
              </div>
            </CardHeader>
            <CardBody>
              <div className="overflow-x-auto">
                <table className="w-full min-w-[1000px] border-collapse">
                  <thead>
                    <tr>
                      <th className={thClass} style={{ width: '120px' }}>項目</th>
                      <th className={thClass} style={{ width: '140px' }}>支援目標</th>
                      <th className={thClass} style={{ width: '160px' }}>支援内容</th>
                      <th className={thClass} style={{ width: '110px' }}>達成時期</th>
                      <th className={thClass} style={{ width: '110px' }}>担当者</th>
                      <th className={thClass} style={{ width: '120px' }}>留意事項</th>
                      <th className={thClass} style={{ width: '60px' }}>優先順位</th>
                      <th className={thClass} style={{ width: '40px' }}></th>
                    </tr>
                  </thead>
                  <tbody>
                    {form.details.map((detail, index) => (
                      <tr key={index} className="align-top">
                        {/* 項目 (category + sub_category) */}
                        <td className={tdClass}>
                          <input
                            type="text"
                            className="mb-1 block w-full rounded border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-1)] px-2 py-1 text-xs text-[var(--neutral-foreground-1)]"
                            value={detail.category}
                            onChange={(e) => updateDetail(index, 'category', e.target.value)}
                            placeholder="カテゴリ"
                          />
                          <textarea
                            className="block w-full rounded border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-1)] px-2 py-1 text-xs text-[var(--neutral-foreground-1)]"
                            rows={2}
                            value={detail.sub_category}
                            onChange={(e) => updateDetail(index, 'sub_category', e.target.value)}
                            placeholder="サブカテゴリ"
                          />
                        </td>
                        {/* 支援目標 */}
                        <td className={tdClass}>
                          <textarea
                            className="block w-full rounded border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-1)] px-2 py-1 text-xs text-[var(--neutral-foreground-1)]"
                            rows={3}
                            value={detail.support_goal}
                            onChange={(e) => updateDetail(index, 'support_goal', e.target.value)}
                            placeholder="支援目標を入力..."
                          />
                        </td>
                        {/* 支援内容 */}
                        <td className={tdClass}>
                          <textarea
                            className="block w-full rounded border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-1)] px-2 py-1 text-xs text-[var(--neutral-foreground-1)]"
                            rows={3}
                            value={detail.support_content}
                            onChange={(e) => updateDetail(index, 'support_content', e.target.value)}
                            placeholder="支援内容を入力..."
                          />
                        </td>
                        {/* 達成時期 */}
                        <td className={tdClass}>
                          <input
                            type="date"
                            className="block w-full rounded border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-1)] px-2 py-1 text-xs text-[var(--neutral-foreground-1)]"
                            value={detail.achievement_date}
                            onChange={(e) => updateDetail(index, 'achievement_date', e.target.value)}
                          />
                        </td>
                        {/* 担当者 */}
                        <td className={tdClass}>
                          <textarea
                            className="block w-full rounded border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-1)] px-2 py-1 text-xs text-[var(--neutral-foreground-1)]"
                            rows={2}
                            value={detail.staff_organization}
                            onChange={(e) => updateDetail(index, 'staff_organization', e.target.value)}
                            placeholder="担当者"
                          />
                        </td>
                        {/* 留意事項 */}
                        <td className={tdClass}>
                          <textarea
                            className="block w-full rounded border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-1)] px-2 py-1 text-xs text-[var(--neutral-foreground-1)]"
                            rows={2}
                            value={detail.notes}
                            onChange={(e) => updateDetail(index, 'notes', e.target.value)}
                            placeholder="留意事項..."
                          />
                        </td>
                        {/* 優先順位 */}
                        <td className={tdClass}>
                          <input
                            type="number"
                            min={1}
                            className="block w-full rounded border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-1)] px-2 py-1 text-xs text-center text-[var(--neutral-foreground-1)]"
                            value={detail.priority}
                            onChange={(e) => updateDetail(index, 'priority', parseInt(e.target.value, 10) || 0)}
                          />
                        </td>
                        {/* Delete */}
                        <td className={tdClass}>
                          <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            onClick={() => removeDetail(index)}
                            title="削除"
                          >
                            <Trash2 className="h-3.5 w-3.5 text-[var(--status-danger-fg)]" />
                          </Button>
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>

              <div className="mt-3 flex justify-start">
                <Button
                  type="button"
                  variant="outline"
                  size="sm"
                  leftIcon={<Plus className="h-4 w-4" />}
                  onClick={addDetail}
                >
                  + 行を追加
                </Button>
              </div>
            </CardBody>
          </Card>

          {/* ============================================================= */}
          {/* Section D: Consent */}
          {/* ============================================================= */}
          <Card>
            <CardHeader>
              <CardTitle>D. 同意・署名</CardTitle>
            </CardHeader>
            <CardBody>
              <div className="grid gap-4 md:grid-cols-2">
                <Input
                  label="管理責任者氏名"
                  value={form.manager_name}
                  onChange={(e) => setForm({ ...form, manager_name: e.target.value })}
                  placeholder="管理責任者名を入力..."
                />
                <Input
                  label="同意日"
                  type="date"
                  value={form.consent_date}
                  onChange={(e) => setForm({ ...form, consent_date: e.target.value })}
                />
                <Input
                  label="保護者署名（テキスト）"
                  value={form.guardian_signature_text}
                  onChange={(e) => setForm({ ...form, guardian_signature_text: e.target.value })}
                  placeholder="保護者名を入力..."
                />
                <div>
                  <label className={labelClass}>署名状況</label>
                  <div className="flex items-center gap-2 rounded-lg border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-2)] px-3 py-2 text-sm text-[var(--neutral-foreground-3)]">
                    {form.guardian_signature_text
                      ? `署名済み: ${form.guardian_signature_text}`
                      : '未署名'}
                  </div>
                </div>
              </div>
            </CardBody>
          </Card>

          {/* ============================================================= */}
          {/* Section E: Action Buttons */}
          {/* ============================================================= */}
          <Card>
            <CardBody>
              <div className="flex flex-wrap items-center gap-3">
                {/* Save draft */}
                <Button
                  type="submit"
                  variant="secondary"
                  leftIcon={<Save className="h-4 w-4" />}
                  isLoading={createMutation.isPending || updateMutation.isPending}
                >
                  下書き保存（保護者非公開）
                </Button>

                {/* Publish as proposal */}
                <Button
                  type="button"
                  variant="primary"
                  leftIcon={<Send className="h-4 w-4" />}
                  onClick={handlePublish}
                  isLoading={publishMutation.isPending}
                  disabled={!editingPlanId}
                >
                  計画書案として提出（保護者確認依頼）
                </Button>

                {/* Sign */}
                <Button
                  type="button"
                  variant="outline"
                  leftIcon={<PenLine className="h-4 w-4" />}
                  onClick={handleSign}
                  isLoading={signMutation.isPending}
                  disabled={!editingPlanId}
                >
                  署名入力へ進む
                </Button>

                <div className="flex-1" />

                {/* CSV */}
                <Button
                  type="button"
                  variant="ghost"
                  size="sm"
                  leftIcon={<Download className="h-4 w-4" />}
                  onClick={handleCsvExport}
                  disabled={!editingPlanId}
                >
                  CSV出力
                </Button>

                {/* PDF */}
                <Button
                  type="button"
                  variant="ghost"
                  size="sm"
                  leftIcon={<FileText className="h-4 w-4" />}
                  onClick={() => editingPlanId && handlePdfDownload(editingPlanId)}
                  disabled={!editingPlanId}
                >
                  PDF出力
                </Button>

                {/* AI Generate */}
                <Button
                  type="button"
                  variant="outline"
                  size="sm"
                  leftIcon={<Sparkles className="h-4 w-4" />}
                  onClick={handleAIGenerate}
                  isLoading={generating}
                >
                  AI生成
                </Button>
              </div>
            </CardBody>
          </Card>
        </form>
      </div>
    );
  }

  // =========================================================================
  // RENDER: List View
  // =========================================================================
  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-bold text-[var(--neutral-foreground-1)]">個別支援計画</h1>
      </div>

      {/* Student selector */}
      <Card>
        <CardBody>
          <label className={labelClass}>生徒を選択</label>
          {loadingStudents ? (
            <SkeletonList items={1} />
          ) : (
            <select
              className={selectClass}
              value={selectedStudentId ?? ''}
              onChange={(e) => setSelectedStudentId(e.target.value ? Number(e.target.value) : null)}
            >
              <option value="">-- 生徒を選択してください --</option>
              {students.map((s) => (
                <option key={s.id} value={s.id}>
                  {s.student_name}
                </option>
              ))}
            </select>
          )}
        </CardBody>
      </Card>

      {/* Plan list table */}
      {selectedStudentId && (
        <Card>
          <CardHeader>
            <div className="flex items-center justify-between">
              <CardTitle>
                {selectedStudent?.student_name} の個別支援計画一覧
              </CardTitle>
            </div>
          </CardHeader>
          <CardBody>
            {loadingPlans ? (
              <SkeletonList items={3} />
            ) : (
              <div className="overflow-x-auto">
                <table className="w-full border-collapse">
                  <thead>
                    <tr>
                      <th className={thClass}>作成日</th>
                      <th className={thClass}>状態</th>
                      <th className={thClass}>編集</th>
                      <th className={thClass}>計画案PDF</th>
                      <th className={thClass}>正式版PDF</th>
                      <th className={thClass}>根拠</th>
                    </tr>
                  </thead>
                  <tbody>
                    {plans.length === 0 && (
                      <tr>
                        <td colSpan={6} className="py-8 text-center text-sm text-[var(--neutral-foreground-3)]">
                          個別支援計画がありません
                        </td>
                      </tr>
                    )}

                    {plans.map((plan) => {
                      const status: PlanStatus = plan.status || (plan.has_signature ? 'official' : plan.is_confirmed ? 'proposal' : 'draft');

                      return (
                        <tr key={plan.id} className="hover:bg-[var(--neutral-background-2)]">
                          {/* 作成日 */}
                          <td className={tdClass}>
                            {plan.created_date}
                          </td>

                          {/* 状態 */}
                          <td className={tdClass}>
                            <Badge variant={statusVariant(status)}>
                              {statusLabel(status)}
                            </Badge>
                          </td>

                          {/* 編集 */}
                          <td className={tdClass}>
                            <div className="flex items-center gap-1">
                              <Button
                                variant="outline"
                                size="sm"
                                onClick={() => openEdit(plan.id)}
                                title="編集"
                              >
                                <Pencil className="h-3.5 w-3.5" />
                              </Button>
                              <Button
                                variant="ghost"
                                size="sm"
                                onClick={() => {
                                  if (confirm('この計画を削除しますか？')) {
                                    deleteMutation.mutate(plan.id);
                                  }
                                }}
                                title="削除"
                              >
                                <Trash2 className="h-3.5 w-3.5 text-[var(--status-danger-fg)]" />
                              </Button>
                            </div>
                          </td>

                          {/* 計画案PDF */}
                          <td className={tdClass}>
                            {status !== 'draft' ? (
                              <a href={`/staff/kobetsu-plan/${plan.id}/preview`} target="_blank" rel="noopener noreferrer">
                                <Button
                                  variant="ghost"
                                  size="sm"
                                  title="計画案プレビュー"
                                >
                                  <FileText className="h-3.5 w-3.5" />
                                  <span className="ml-1 text-xs">計画案</span>
                                </Button>
                              </a>
                            ) : (
                              <span className="text-xs text-[var(--neutral-foreground-3)]">-</span>
                            )}
                          </td>

                          {/* 正式版PDF */}
                          <td className={tdClass}>
                            {status === 'official' ? (
                              <a href={`/staff/kobetsu-plan/${plan.id}/preview?type=official`} target="_blank" rel="noopener noreferrer">
                                <Button
                                  variant="ghost"
                                  size="sm"
                                  title="正式版プレビュー"
                                >
                                <FileText className="h-3.5 w-3.5" />
                                <span className="ml-1 text-xs">正式版</span>
                                </Button>
                              </a>
                            ) : (
                              <span className="text-xs text-[var(--neutral-foreground-3)]">-</span>
                            )}
                          </td>

                          {/* 根拠 */}
                          <td className={tdClass}>
                            <Link
                              href={`/staff/kobetsu-plan/${plan.id}/basis`}
                              className="inline-flex items-center gap-1 text-xs text-[var(--brand-80)] hover:underline"
                            >
                              <ExternalLink className="h-3 w-3" />
                              根拠
                            </Link>
                          </td>
                        </tr>
                      );
                    })}

                    {/* New plan row */}
                    <tr className="hover:bg-[var(--neutral-background-2)]">
                      <td colSpan={6} className={tdClass}>
                        <Button
                          variant="subtle"
                          size="sm"
                          leftIcon={<Plus className="h-4 w-4" />}
                          onClick={openCreate}
                        >
                          新規作成
                        </Button>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
            )}
          </CardBody>
        </Card>
      )}
    </div>
  );
}
