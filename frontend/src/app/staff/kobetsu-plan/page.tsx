'use client';

import { useState, useCallback, useRef, useMemo, useEffect } from 'react';
import { useSearchParams } from 'next/navigation';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import api from '@/lib/api';
import { cn } from '@/lib/utils';
import { Card, CardHeader, CardTitle, CardBody } from '@/components/ui/Card';
import { Button } from '@/components/ui/Button';
import { Input } from '@/components/ui/Input';
import { Badge } from '@/components/ui/Badge';
import { SkeletonList } from '@/components/ui/Skeleton';
import { useToast } from '@/components/ui/Toast';
import { SignaturePad, type SignaturePadRef } from '@/components/ui/SignaturePad';
import { MaterialIcon } from '@/components/ui/MaterialIcon';
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
  guardian_signature: string;
  staff_signature: string;
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
  const searchParams = useSearchParams();

  // View state: 'list' | 'editor'
  const [view, setView] = useState<'list' | 'editor'>('list');
  const [selectedStudentId, setSelectedStudentId] = useState<number | null>(null);
  const [editingPlanId, setEditingPlanId] = useState<number | null>(null);
  const [isReadOnly, setIsReadOnly] = useState(false);
  const [form, setForm] = useState<PlanForm>(emptyForm());
  const [generating, setGenerating] = useState(false);
  const [generatingWish, setGeneratingWish] = useState(false);

  // Detail editing modal state
  const [editingDetailIdx, setEditingDetailIdx] = useState<number | null>(null);

  // Signature pad refs
  const staffSigRef = useRef<SignaturePadRef>(null);
  const guardianSigRef = useRef<SignaturePadRef>(null);

  // Existing signature images (loaded from backend for read-only display)
  const [existingStaffSig, setExistingStaffSig] = useState<string | undefined>(undefined);
  const [existingGuardianSig, setExistingGuardianSig] = useState<string | undefined>(undefined);

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

  // Map form fields to API fields (legacy compat: life_intention, consent_name, manager_name)
  const formToApi = (data: PlanForm) => ({
    ...data,
    life_intention: data.guardian_wish,
    consent_name: data.manager_name,
    manager_name: data.manager_name,
  });

  const createMutation = useMutation({
    mutationFn: (data: PlanForm) =>
      api.post(`/api/staff/students/${selectedStudentId}/support-plans`, formToApi(data)),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['staff', 'support-plans', 'individual'] });
      toast.success('個別支援計画を作成しました');
      setView('list');
    },
    onError: () => toast.error('作成に失敗しました'),
  });

  const updateMutation = useMutation({
    mutationFn: ({ id, ...data }: PlanForm & { id: number }) =>
      api.put(`/api/staff/support-plans/${id}`, formToApi(data)),
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
    onError: (e: any) => toast.error(e?.response?.data?.message || '提出に失敗しました（下書き状態でない可能性があります）'),
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
    mutationFn: (data: { id: number; staff_signature: string; staff_signer_name: string; guardian_signature?: string }) =>
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
    setIsReadOnly(false);
    setForm(emptyForm());
    setExistingStaffSig(undefined);
    setExistingGuardianSig(undefined);
    setView('editor');
  };

  const openEdit = useCallback(async (planId: number) => {
    try {
      const res = await api.get<{ data: any }>(`/api/staff/support-plans/${planId}`);
      const p = res.data.data;
      const dt = (v: string | null) => v ? v.split('T')[0] : '';
      setEditingPlanId(planId);
      setForm({
        created_date: dt(p.created_date) || new Date().toISOString().split('T')[0],
        guardian_wish: nl(p.life_intention || p.guardian_wish),
        overall_policy: nl(p.overall_policy),
        long_term_goal: nl(p.long_term_goal),
        long_term_goal_date: dt(p.long_term_goal_date),
        short_term_goal: nl(p.short_term_goal),
        short_term_goal_date: dt(p.short_term_goal_date),
        manager_name: p.consent_name || p.manager_name || '',
        consent_date: dt(p.consent_date),
        details: p.details && p.details.length > 0
          ? p.details.map((d: any) => ({
              ...d,
              support_goal: nl(d.support_goal || d.goal),
              support_content: nl(d.support_content),
              staff_organization: nl(d.staff_organization),
              notes: nl(d.notes),
              achievement_date: dt(d.achievement_date),
            }))
          : DEFAULT_DETAILS.map((d) => ({ ...d })),
      });
      // Load existing signature images
      setExistingStaffSig(p.staff_signature || undefined);
      setExistingGuardianSig(p.guardian_signature || undefined);
      // Track if plan is official (read-only)
      setIsReadOnly(p.is_official === true || p.status === 'official');
      setView('editor');
    } catch {
      toast.error('計画の読み込みに失敗しました');
    }
  }, [toast]);

  // Auto-open from URL params (e.g. from pending-tasks)
  const autoOpenRef = useRef(false);
  useEffect(() => {
    if (autoOpenRef.current) return;
    const sid = searchParams.get('student_id');
    const pid = searchParams.get('plan_id');
    if (sid) {
      autoOpenRef.current = true;
      setSelectedStudentId(Number(sid));
      if (pid) {
        openEdit(Number(pid));
      }
    }
  }, [searchParams, openEdit]);

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
    // Check if staff signature exists (either drawn new or loaded from existing)
    const hasNewStaffSig = staffSigRef.current && !staffSigRef.current.isEmpty();
    const hasExistingStaffSig = !!existingStaffSig;
    if (!hasNewStaffSig && !hasExistingStaffSig) {
      toast.error('職員の署名を記入してください');
      return;
    }
    if (!form.manager_name.trim()) {
      toast.error('管理責任者氏名を入力してください');
      return;
    }
    const payload: { id: number; staff_signature: string; staff_signer_name: string; guardian_signature?: string } = {
      id: editingPlanId,
      staff_signature: hasNewStaffSig ? staffSigRef.current!.toDataURL() : existingStaffSig!,
      staff_signer_name: form.manager_name,
    };
    // Guardian signature: use new if drawn, else use existing
    const hasNewGuardianSig = guardianSigRef.current && !guardianSigRef.current.isEmpty();
    if (hasNewGuardianSig) {
      payload.guardian_signature = guardianSigRef.current!.toDataURL();
    } else if (existingGuardianSig) {
      payload.guardian_signature = existingGuardianSig;
    }
    signMutation.mutate(payload);
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
        : `/api/staff/students/${selectedStudentId}/support-plans/ai-generate`;
      // eslint-disable-next-line @typescript-eslint/no-explicit-any
      const res = await api.post<{ data: any }>(
        endpoint,
        { student_id: selectedStudentId }
      );
      const aiData = res.data.data;
      setForm((prev) => ({
        ...prev,
        guardian_wish: aiData.life_intention || prev.guardian_wish,
        overall_policy: aiData.overall_policy || prev.overall_policy,
        long_term_goal: aiData.long_term_goal_text || aiData.long_term_goal || prev.long_term_goal,
        short_term_goal: aiData.short_term_goal_text || aiData.short_term_goal || prev.short_term_goal,
        created_date: aiData.created_date || prev.created_date,
        long_term_goal_date: aiData.long_term_goal_date || prev.long_term_goal_date,
        short_term_goal_date: aiData.short_term_goal_date || prev.short_term_goal_date,
        consent_date: aiData.consent_date || prev.consent_date,
        details: aiData.details && aiData.details.length > 0
          // eslint-disable-next-line @typescript-eslint/no-explicit-any
          ? aiData.details.map((d: any, i: number) => ({
              category: d.category || '',
              sub_category: d.sub_category || '',
              support_goal: d.support_goal || d.goal || '',
              support_content: d.support_content || '',
              achievement_date: d.achievement_date || prev.details[i]?.achievement_date || '',
              staff_organization: d.staff_organization || prev.details[i]?.staff_organization || '',
              notes: d.notes || '',
              priority: d.priority ?? (i + 1),
              sort_order: i + 1,
            }))
          : prev.details,
      }));
      toast.success('AI生成が完了しました');
    } catch {
      toast.error('AI生成に失敗しました');
    } finally {
      setGenerating(false);
    }
  };

  const handleGenerateWish = async () => {
    if (!selectedStudentId) {
      toast.error('生徒を選択してください');
      return;
    }
    setGeneratingWish(true);
    try {
      // eslint-disable-next-line @typescript-eslint/no-explicit-any
      const res = await api.post<{ data: { wish: string } }>(
        `/api/staff/students/${selectedStudentId}/generate-wish`
      );
      const wish = res.data.data.wish;
      if (wish) {
        setForm((prev) => ({ ...prev, guardian_wish: wish }));
        toast.success('面談記録から意向を生成しました');
      } else {
        toast.error('面談記録が見つかりませんでした');
      }
    } catch {
      toast.error('意向の生成に失敗しました');
    } finally {
      setGeneratingWish(false);
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
      const res = await api.get(`/api/staff/support-plans/${editingPlanId}/export`, {
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
  // Editor section open/close state
  const [openSections, setOpenSections] = useState<Record<string, boolean>>({
    A: true, B: true, C: true, D: true,
  });
  const toggleSection = (key: string) => setOpenSections((prev) => ({ ...prev, [key]: !prev[key] }));

  // Section completion check
  const sectionStatus = useMemo(() => ({
    A: !!(form.created_date && form.guardian_wish.trim() && form.overall_policy.trim()),
    B: !!(form.long_term_goal.trim() && form.short_term_goal.trim()),
    C: form.details.some((d) => d.support_goal.trim() || d.support_content.trim()),
    D: !!(form.manager_name.trim()),
  }), [form]);

  const EDITOR_SECTIONS = [
    { key: 'A', label: '基本情報・意向', icon: '📋' },
    { key: 'B', label: '目標設定', icon: '🎯' },
    { key: 'C', label: '支援内容', icon: '📝' },
    { key: 'D', label: '同意・署名', icon: '✍️' },
  ] as const;

  if (view === 'editor') {
    return (
      <div className="space-y-4">
        {/* Header */}
        <div className="flex items-center gap-3">
          <Button variant="ghost" size="sm" onClick={() => setView('list')}>
            <MaterialIcon name="arrow_back" size={18} />
          </Button>
          <div className="flex-1">
            <h1 className="text-xl font-bold text-[var(--neutral-foreground-1)]">
              {isReadOnly ? '個別支援計画（閲覧）' : editingPlanId ? '個別支援計画を編集' : '個別支援計画を作成'}
            </h1>
            <div className="flex items-center gap-2 mt-1">
              {selectedStudent && (
                <span className="text-sm text-[var(--neutral-foreground-3)]">{selectedStudent.student_name}</span>
              )}
              {isReadOnly && <Badge variant="success">署名済み（正式版）</Badge>}
              {!isReadOnly && existingStaffSig && <Badge variant="info">職員署名あり</Badge>}
              {!isReadOnly && existingGuardianSig && <Badge variant="info">保護者署名あり</Badge>}
            </div>
          </div>
        </div>

        {/* Section navigation bar */}
        <div className="sticky top-0 z-30 -mx-4 px-4 py-2 bg-[var(--neutral-background-2)] border-b border-[var(--neutral-stroke-2)]">
          <div className="flex items-center gap-1 overflow-x-auto">
            {EDITOR_SECTIONS.map((sec) => (
              <button
                key={sec.key}
                type="button"
                onClick={() => { setOpenSections((prev) => ({ ...prev, [sec.key]: true })); document.getElementById(`section-${sec.key}`)?.scrollIntoView({ behavior: 'smooth', block: 'start' }); }}
                className={cn(
                  'flex items-center gap-1.5 rounded-full px-3 py-1.5 text-xs font-medium whitespace-nowrap transition-colors',
                  sectionStatus[sec.key as keyof typeof sectionStatus]
                    ? 'bg-green-100 text-green-700'
                    : 'bg-[var(--neutral-background-3)] text-[var(--neutral-foreground-3)] hover:bg-[var(--neutral-background-4)]'
                )}
              >
                <span>{sec.icon}</span>
                <span>{sec.key}. {sec.label}</span>
                {sectionStatus[sec.key as keyof typeof sectionStatus] && <span className="text-green-600">✓</span>}
              </button>
            ))}
          </div>
        </div>

        {isReadOnly && (
          <div className="rounded-lg bg-amber-50 border border-amber-300 p-3 text-sm text-amber-800">
            この計画は署名済み（正式版）のため、編集できません。内容の閲覧のみ可能です。
          </div>
        )}

        <fieldset disabled={isReadOnly} className="disabled:opacity-75">
        <form onSubmit={handleSaveDraft} className="space-y-4">
          {/* ============================================================= */}
          {/* Section A: Basic Info */}
          {/* ============================================================= */}
          <Card id="section-A">
            <CardHeader>
              <button type="button" className="flex w-full items-center justify-between" onClick={() => toggleSection('A')}>
                <CardTitle>
                  <span className="flex items-center gap-2">
                    📋 A. 基本情報・意向
                    {sectionStatus.A && <span className="text-xs text-green-600 font-normal">✓ 入力済み</span>}
                  </span>
                </CardTitle>
                <span className={cn('text-[var(--neutral-foreground-4)] transition-transform', openSections.A ? 'rotate-180' : '')}>▼</span>
              </button>
            </CardHeader>
            {openSections.A && <CardBody>
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
                  <div className="mb-1 flex items-center justify-between">
                    <label className="text-sm font-medium text-[var(--neutral-foreground-2)]">利用児及び家族の生活に対する意向</label>
                    {!isReadOnly && (
                      <Button
                        size="sm"
                        variant="outline"
                        onClick={handleGenerateWish}
                        disabled={generatingWish || !selectedStudentId}
                      >
                        <MaterialIcon name="auto_awesome" size={14} className="mr-1" />
                        {generatingWish ? '生成中...' : '面談から生成'}
                      </Button>
                    )}
                  </div>
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
            </CardBody>}
          </Card>

          {/* ============================================================= */}
          {/* Section B: Goals */}
          {/* ============================================================= */}
          <Card id="section-B">
            <CardHeader>
              <button type="button" className="flex w-full items-center justify-between" onClick={() => toggleSection('B')}>
                <CardTitle>
                  <span className="flex items-center gap-2">
                    🎯 B. 目標設定
                    {sectionStatus.B && <span className="text-xs text-green-600 font-normal">✓ 入力済み</span>}
                  </span>
                </CardTitle>
                <span className={cn('text-[var(--neutral-foreground-4)] transition-transform', openSections.B ? 'rotate-180' : '')}>▼</span>
              </button>
            </CardHeader>
            {openSections.B && <CardBody>
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
            </CardBody>}
          </Card>

          {/* ============================================================= */}
          {/* Section C: Support Details Table */}
          {/* ============================================================= */}
          <Card id="section-C">
            <CardHeader>
              <div className="flex items-center justify-between">
                <button type="button" className="flex items-center gap-2" onClick={() => toggleSection('C')}>
                  <CardTitle>
                    <span className="flex items-center gap-2">
                      📝 C. 支援内容
                      {sectionStatus.C && <span className="text-xs text-green-600 font-normal">✓ 入力済み</span>}
                    </span>
                  </CardTitle>
                  <span className={cn('text-[var(--neutral-foreground-4)] transition-transform', openSections.C ? 'rotate-180' : '')}>▼</span>
                </button>
                <Button
                  type="button"
                  variant="outline"
                  size="sm"
                  leftIcon={<MaterialIcon name="add" size={18} />}
                  onClick={addDetail}
                >
                  行を追加
                </Button>
              </div>
            </CardHeader>
            {openSections.C && <CardBody>
              <div className="overflow-x-auto">
                <table className="w-full min-w-[900px] border-collapse">
                  <thead>
                    <tr>
                      <th className={thClass} style={{ width: '110px' }}>項目</th>
                      <th className={thClass} style={{ width: '140px' }}>支援目標</th>
                      <th className={thClass} style={{ width: '180px' }}>支援内容</th>
                      <th className={thClass} style={{ width: '90px' }}>達成時期</th>
                      <th className={thClass} style={{ width: '90px' }}>担当者</th>
                      <th className={thClass} style={{ width: '130px' }}>留意事項</th>
                      <th className={thClass} style={{ width: '50px' }}>優先</th>
                      <th className={thClass} style={{ width: '60px' }}></th>
                    </tr>
                  </thead>
                  <tbody>
                    {form.details.map((detail, index) => {
                      const cellClick = 'cursor-pointer hover:bg-[var(--brand-160)] transition-colors rounded';
                      const truncText = (t: string, max = 40) => t && t.length > max ? t.slice(0, max) + '...' : t;
                      return (
                        <tr key={index} className="align-top">
                          <td className={tdClass}>
                            <div className={cellClick} onClick={() => setEditingDetailIdx(index)}>
                              {detail.category ? (
                                <div className="text-xs font-semibold text-[var(--neutral-foreground-1)]">{detail.category}</div>
                              ) : (
                                <div className="text-xs text-[var(--neutral-foreground-4)] italic">カテゴリ</div>
                              )}
                              {detail.sub_category && <div className="text-xs text-[var(--neutral-foreground-3)] mt-0.5">{truncText(detail.sub_category, 20)}</div>}
                            </div>
                          </td>
                          <td className={tdClass}>
                            <div className={cellClick} onClick={() => setEditingDetailIdx(index)}>
                              {detail.support_goal ? (
                                <div className="text-xs text-[var(--neutral-foreground-1)] whitespace-pre-wrap line-clamp-3">{truncText(detail.support_goal, 60)}</div>
                              ) : (
                                <div className="text-xs text-[var(--neutral-foreground-4)] italic">クリックして入力</div>
                              )}
                            </div>
                          </td>
                          <td className={tdClass}>
                            <div className={cellClick} onClick={() => setEditingDetailIdx(index)}>
                              {detail.support_content ? (
                                <div className="text-xs text-[var(--neutral-foreground-1)] whitespace-pre-wrap line-clamp-3">{truncText(detail.support_content, 80)}</div>
                              ) : (
                                <div className="text-xs text-[var(--neutral-foreground-4)] italic">クリックして入力</div>
                              )}
                            </div>
                          </td>
                          <td className={tdClass}>
                            <div className={cellClick} onClick={() => setEditingDetailIdx(index)}>
                              <div className="text-xs text-[var(--neutral-foreground-1)]">{detail.achievement_date || <span className="text-[var(--neutral-foreground-4)] italic">未設定</span>}</div>
                            </div>
                          </td>
                          <td className={tdClass}>
                            <div className={cellClick} onClick={() => setEditingDetailIdx(index)}>
                              {detail.staff_organization ? (
                                <div className="text-xs text-[var(--neutral-foreground-1)]">{truncText(detail.staff_organization, 20)}</div>
                              ) : (
                                <div className="text-xs text-[var(--neutral-foreground-4)] italic">未設定</div>
                              )}
                            </div>
                          </td>
                          <td className={tdClass}>
                            <div className={cellClick} onClick={() => setEditingDetailIdx(index)}>
                              {detail.notes ? (
                                <div className="text-xs text-[var(--neutral-foreground-1)] whitespace-pre-wrap line-clamp-2">{truncText(detail.notes, 40)}</div>
                              ) : (
                                <div className="text-xs text-[var(--neutral-foreground-4)] italic">-</div>
                              )}
                            </div>
                          </td>
                          <td className={tdClass}>
                            <div className="text-xs text-center text-[var(--neutral-foreground-1)]">{detail.priority || '-'}</div>
                          </td>
                          <td className={tdClass}>
                            <div className="flex items-center gap-1">
                              <button
                                type="button"
                                onClick={() => setEditingDetailIdx(index)}
                                className="rounded p-1 text-[var(--brand-80)] hover:bg-[var(--brand-160)]"
                                title="編集"
                              >
                                <MaterialIcon name="edit" size={16} />
                              </button>
                              <button
                                type="button"
                                onClick={() => removeDetail(index)}
                                className="rounded p-1 text-[var(--status-danger-fg)] hover:bg-red-50"
                                title="削除"
                              >
                                <MaterialIcon name="delete" size={16} />
                              </button>
                            </div>
                          </td>
                        </tr>
                      );
                    })}
                  </tbody>
                </table>
              </div>

              {form.details.length === 0 && (
                <div className="py-6 text-center text-sm text-[var(--neutral-foreground-4)]">
                  支援内容がありません。「行を追加」ボタンで追加してください。
                </div>
              )}

              <div className="mt-3 flex justify-start">
                <Button
                  type="button"
                  variant="outline"
                  size="sm"
                  leftIcon={<MaterialIcon name="add" size={18} />}
                  onClick={addDetail}
                >
                  + 行を追加
                </Button>
              </div>

              {/* Detail editing modal */}
              {editingDetailIdx !== null && form.details[editingDetailIdx] && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" onClick={() => setEditingDetailIdx(null)}>
                  <div className="w-full max-w-2xl max-h-[90vh] overflow-y-auto rounded-xl bg-[var(--neutral-background-1)] shadow-[var(--shadow-28)]" onClick={(e) => e.stopPropagation()}>
                    <div className="flex items-center justify-between border-b border-[var(--neutral-stroke-2)] px-5 py-4">
                      <h3 className="text-lg font-bold text-[var(--neutral-foreground-1)]">
                        支援内容の編集（{editingDetailIdx + 1}行目）
                      </h3>
                      <button
                        type="button"
                        onClick={() => setEditingDetailIdx(null)}
                        className="rounded-lg p-1 text-[var(--neutral-foreground-4)] hover:text-[var(--neutral-foreground-1)]"
                      >
                        <MaterialIcon name="close" size={20} />
                      </button>
                    </div>
                    <div className="p-5 space-y-4">
                      {/* Category */}
                      <div className="grid gap-4 sm:grid-cols-2">
                        <div>
                          <label className={labelClass}>カテゴリ</label>
                          <select
                            className={selectClass}
                            value={form.details[editingDetailIdx].category}
                            onChange={(e) => updateDetail(editingDetailIdx, 'category', e.target.value)}
                          >
                            <option value="">選択してください</option>
                            <option value="本人支援">本人支援</option>
                            <option value="家族支援">家族支援</option>
                            <option value="地域支援">地域支援</option>
                          </select>
                        </div>
                        <div>
                          <label className={labelClass}>サブカテゴリ / 領域</label>
                          <input
                            type="text"
                            className={textareaClass}
                            value={form.details[editingDetailIdx].sub_category}
                            onChange={(e) => updateDetail(editingDetailIdx, 'sub_category', e.target.value)}
                            placeholder="例：健康・生活、運動・感覚"
                          />
                        </div>
                      </div>

                      {/* Support Goal */}
                      <div>
                        <label className={labelClass}>支援目標（具体的な到達目標）</label>
                        <textarea
                          className={textareaClass}
                          rows={4}
                          value={form.details[editingDetailIdx].support_goal}
                          onChange={(e) => updateDetail(editingDetailIdx, 'support_goal', e.target.value)}
                          placeholder="具体的な支援目標を入力してください..."
                          autoFocus
                        />
                      </div>

                      {/* Support Content */}
                      <div>
                        <label className={labelClass}>支援内容（内容・5領域との関連性等）</label>
                        <textarea
                          className={textareaClass}
                          rows={5}
                          value={form.details[editingDetailIdx].support_content}
                          onChange={(e) => updateDetail(editingDetailIdx, 'support_content', e.target.value)}
                          placeholder="支援内容を詳しく入力してください..."
                        />
                      </div>

                      {/* Achievement date, Staff, Priority */}
                      <div className="grid gap-4 sm:grid-cols-3">
                        <div>
                          <label className={labelClass}>達成時期</label>
                          <input
                            type="date"
                            className={textareaClass}
                            value={form.details[editingDetailIdx].achievement_date}
                            onChange={(e) => updateDetail(editingDetailIdx, 'achievement_date', e.target.value)}
                          />
                        </div>
                        <div>
                          <label className={labelClass}>担当者</label>
                          <input
                            type="text"
                            className={textareaClass}
                            value={form.details[editingDetailIdx].staff_organization}
                            onChange={(e) => updateDetail(editingDetailIdx, 'staff_organization', e.target.value)}
                            placeholder="担当者名"
                          />
                        </div>
                        <div>
                          <label className={labelClass}>優先順位</label>
                          <input
                            type="number"
                            min={1}
                            className={textareaClass}
                            value={form.details[editingDetailIdx].priority}
                            onChange={(e) => updateDetail(editingDetailIdx, 'priority', parseInt(e.target.value, 10) || 0)}
                          />
                        </div>
                      </div>

                      {/* Notes */}
                      <div>
                        <label className={labelClass}>留意事項</label>
                        <textarea
                          className={textareaClass}
                          rows={3}
                          value={form.details[editingDetailIdx].notes}
                          onChange={(e) => updateDetail(editingDetailIdx, 'notes', e.target.value)}
                          placeholder="留意事項を入力..."
                        />
                      </div>
                    </div>
                    <div className="flex items-center justify-between border-t border-[var(--neutral-stroke-2)] px-5 py-4">
                      <div className="flex items-center gap-2">
                        {editingDetailIdx > 0 && (
                          <Button type="button" variant="ghost" size="sm" onClick={() => setEditingDetailIdx(editingDetailIdx - 1)} leftIcon={<MaterialIcon name="chevron_left" size={16} />}>
                            前へ
                          </Button>
                        )}
                        {editingDetailIdx < form.details.length - 1 && (
                          <Button type="button" variant="ghost" size="sm" onClick={() => setEditingDetailIdx(editingDetailIdx + 1)}>
                            次へ <MaterialIcon name="chevron_right" size={16} className="ml-1" />
                          </Button>
                        )}
                      </div>
                      <Button
                        type="button"
                        variant="primary"
                        size="sm"
                        leftIcon={<MaterialIcon name="check" size={16} />}
                        onClick={() => setEditingDetailIdx(null)}
                      >
                        入力完了
                      </Button>
                    </div>
                  </div>
                </div>
              )}
            </CardBody>}
          </Card>

          {/* ============================================================= */}
          {/* Section D: Consent */}
          {/* ============================================================= */}
          <Card id="section-D">
            <CardHeader>
              <button type="button" className="flex w-full items-center justify-between" onClick={() => toggleSection('D')}>
                <CardTitle>
                  <span className="flex items-center gap-2">
                    ✍️ D. 同意・署名
                    {sectionStatus.D && <span className="text-xs text-green-600 font-normal">✓ 入力済み</span>}
                  </span>
                </CardTitle>
                <span className={cn('text-[var(--neutral-foreground-4)] transition-transform', openSections.D ? 'rotate-180' : '')}>▼</span>
              </button>
            </CardHeader>
            {openSections.D && <CardBody>
              <div className="space-y-6">
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
                </div>

                {/* Signature pads */}
                <div className="grid gap-6 md:grid-cols-2">
                  {/* Staff signature */}
                  <div>
                    <SignaturePad
                      ref={staffSigRef}
                      label="職員署名（署名してください）"
                      readOnly={isReadOnly}
                      initialValue={existingStaffSig}
                      width={400}
                      height={150}
                    />
                  </div>

                  {/* Guardian signature */}
                  <div>
                    <SignaturePad
                      ref={guardianSigRef}
                      label="保護者署名（署名してください）"
                      readOnly={isReadOnly}
                      initialValue={existingGuardianSig}
                      width={400}
                      height={150}
                    />
                  </div>
                </div>
              </div>
            </CardBody>}
          </Card>

          {/* ============================================================= */}
          {/* Section E: Action Buttons (sticky) */}
          {/* ============================================================= */}
          <div className="sticky bottom-0 z-30 -mx-4 px-4 py-3 bg-[var(--neutral-background-1)] border-t border-[var(--neutral-stroke-2)] shadow-[0_-2px_8px_rgba(0,0,0,0.08)]">
            <div className="flex flex-wrap items-center gap-2">
              {/* Primary actions */}
              <Button
                type="submit"
                variant="secondary"
                size="sm"
                leftIcon={<MaterialIcon name="save" size={18} />}
                isLoading={createMutation.isPending || updateMutation.isPending}
              >
                下書き保存
              </Button>

              {editingPlanId && !isReadOnly && (
                <>
                  {!existingStaffSig && (
                    <Button
                      type="button"
                      variant="primary"
                      size="sm"
                      leftIcon={<MaterialIcon name="send" size={18} />}
                      onClick={handlePublish}
                      isLoading={publishMutation.isPending}
                    >
                      確認依頼
                    </Button>
                  )}
                  <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    leftIcon={<MaterialIcon name="draw" size={18} />}
                    onClick={handleSign}
                    isLoading={signMutation.isPending}
                  >
                    署名して確定
                  </Button>
                  <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    leftIcon={<MaterialIcon name="description" size={18} />}
                    onClick={() => {
                      if (confirm('紙面で署名済みとして確定しますか？\nこの操作により下書き状態が解除され、正式な計画書として扱われます。')) {
                        makeOfficialMutation.mutate(editingPlanId);
                      }
                    }}
                    isLoading={makeOfficialMutation.isPending}
                  >
                    紙面でサイン済み
                  </Button>
                </>
              )}
              {isReadOnly && (
                <Badge variant="success">この計画は署名済みです</Badge>
              )}

              <div className="flex-1" />

              {/* Secondary actions */}
              <Button type="button" variant="ghost" size="sm" leftIcon={<MaterialIcon name="auto_awesome" size={18} />} onClick={handleAIGenerate} isLoading={generating}>
                AI生成
              </Button>
              {editingPlanId && (
                <>
                  <Button type="button" variant="ghost" size="sm" leftIcon={<MaterialIcon name="description" size={18} />} onClick={() => handlePdfDownload(editingPlanId)}>PDF</Button>
                  <Button type="button" variant="ghost" size="sm" leftIcon={<MaterialIcon name="download" size={18} />} onClick={handleCsvExport}>CSV</Button>
                </>
              )}
            </div>
          </div>
        </form>
        </fieldset>
      </div>
    );
  }

  // =========================================================================
  // RENDER: List View
  // =========================================================================

  const STEPS = [
    { key: 'draft', label: '下書き', icon: '1' },
    { key: 'proposal', label: '確認依頼', icon: '2' },
    { key: 'official', label: '署名済み', icon: '3' },
  ] as const;

  function StepIndicator({ status }: { status: PlanStatus }) {
    const stepIdx = status === 'official' ? 2 : status === 'proposal' ? 1 : 0;
    return (
      <div className="flex items-center gap-1">
        {STEPS.map((step, i) => (
          <div key={step.key} className="flex items-center">
            <div className={cn(
              'flex h-6 w-6 items-center justify-center rounded-full text-xs font-bold transition-colors',
              i <= stepIdx
                ? i === stepIdx
                  ? 'bg-[var(--brand-80)] text-white'
                  : 'bg-green-500 text-white'
                : 'bg-[var(--neutral-background-4)] text-[var(--neutral-foreground-4)]'
            )}>
              {i < stepIdx ? '✓' : step.icon}
            </div>
            {i < STEPS.length - 1 && (
              <div className={cn(
                'mx-1 h-0.5 w-6',
                i < stepIdx ? 'bg-green-500' : 'bg-[var(--neutral-background-4)]'
              )} />
            )}
          </div>
        ))}
      </div>
    );
  }

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold text-[var(--neutral-foreground-1)]">個別支援計画</h1>
          <p className="mt-1 text-sm text-[var(--neutral-foreground-3)]">生徒ごとの個別支援計画を作成・管理します</p>
        </div>
      </div>

      {/* Workflow guide */}
      <Card>
        <CardBody>
          <div className="flex items-center justify-between flex-wrap gap-3">
            <div className="flex items-center gap-6 text-xs text-[var(--neutral-foreground-3)]">
              <span className="flex items-center gap-1.5"><span className="flex h-5 w-5 items-center justify-center rounded-full bg-amber-100 text-amber-700 text-[10px] font-bold">1</span>下書き作成</span>
              <span className="text-[var(--neutral-foreground-4)]">→</span>
              <span className="flex items-center gap-1.5"><span className="flex h-5 w-5 items-center justify-center rounded-full bg-[var(--brand-160)] text-[var(--brand-70)] text-[10px] font-bold">2</span>保護者に確認依頼</span>
              <span className="text-[var(--neutral-foreground-4)]">→</span>
              <span className="flex items-center gap-1.5"><span className="flex h-5 w-5 items-center justify-center rounded-full bg-green-100 text-green-700 text-[10px] font-bold">3</span>署名して確定</span>
            </div>
          </div>
        </CardBody>
      </Card>

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

      {/* Plan list */}
      {selectedStudentId && (
        <div className="space-y-4">
          <div className="flex items-center justify-between">
            <h2 className="text-lg font-semibold text-[var(--neutral-foreground-1)]">
              {selectedStudent?.student_name} の計画一覧
            </h2>
            <Button leftIcon={<MaterialIcon name="add" size={18} />} onClick={openCreate}>
              新規作成
            </Button>
          </div>

          {loadingPlans ? (
            <SkeletonList items={3} />
          ) : plans.length === 0 ? (
            <Card>
              <CardBody>
                <div className="py-12 text-center">
                  <MaterialIcon name="description" size={48} className="mx-auto mb-3 text-[var(--neutral-foreground-4)]" />
                  <p className="text-[var(--neutral-foreground-3)]">個別支援計画がありません</p>
                  <Button className="mt-4" leftIcon={<MaterialIcon name="add" size={18} />} onClick={openCreate}>
                    最初の計画を作成
                  </Button>
                </div>
              </CardBody>
            </Card>
          ) : (
            <div className="space-y-3">
              {plans.map((plan) => {
                const status: PlanStatus = plan.status || (plan.has_signature ? 'official' : plan.is_confirmed ? 'proposal' : 'draft');
                const dateStr = plan.created_date?.split('T')[0] || '';

                return (
                  <Card key={plan.id} className="transition-shadow hover:shadow-md">
                    <CardBody>
                      <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        {/* Left: Info */}
                        <div className="flex-1 min-w-0">
                          <div className="flex items-center gap-2 mb-1">
                            <Badge variant={statusVariant(status)}>{statusLabel(status)}</Badge>
                            <span className="text-sm text-[var(--neutral-foreground-3)]">作成日: {dateStr}</span>
                          </div>
                          {plan.short_term_goal && (
                            <p className="text-sm text-[var(--neutral-foreground-2)] truncate">
                              短期目標: {plan.short_term_goal}
                            </p>
                          )}
                          <div className="mt-2">
                            <StepIndicator status={status} />
                          </div>
                        </div>

                        {/* Right: Actions */}
                        <div className="flex flex-wrap items-center gap-2 shrink-0">
                          <Button variant="outline" size="sm" leftIcon={<MaterialIcon name="edit" size={16} />} onClick={() => openEdit(plan.id)}>
                            {status === 'official' ? '閲覧' : '編集'}
                          </Button>

                          {status !== 'draft' && (
                            <a href={`/staff/kobetsu-plan/${plan.id}/preview`} target="_blank" rel="noopener noreferrer">
                              <Button variant="ghost" size="sm" leftIcon={<MaterialIcon name="description" size={16} />}>
                                計画案
                              </Button>
                            </a>
                          )}

                          {status === 'official' && (
                            <a href={`/staff/kobetsu-plan/${plan.id}/preview?type=official`} target="_blank" rel="noopener noreferrer">
                              <Button variant="ghost" size="sm" leftIcon={<MaterialIcon name="draw" size={16} />}>
                                正式版
                              </Button>
                            </a>
                          )}

                          <Link href={`/staff/kobetsu-plan/${plan.id}/basis`}>
                            <Button variant="ghost" size="sm" leftIcon={<MaterialIcon name="open_in_new" size={16} />}>
                              根拠
                            </Button>
                          </Link>

                          {status === 'draft' && (
                            <Button
                              variant="ghost"
                              size="sm"
                              onClick={() => { if (confirm('この計画を削除しますか？')) deleteMutation.mutate(plan.id); }}
                            >
                              <MaterialIcon name="delete" size={16} className="text-[var(--status-danger-fg)]" />
                            </Button>
                          )}
                        </div>
                      </div>
                    </CardBody>
                  </Card>
                );
              })}
            </div>
          )}
        </div>
      )}
    </div>
  );
}
