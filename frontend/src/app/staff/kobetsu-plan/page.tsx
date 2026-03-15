'use client';

import { useState, useRef } from 'react';
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
  Sparkles,
  FileText,
  Calendar,
  User,
  ChevronDown,
  ChevronUp,
  Loader2,
  Copy,
  PenLine,
} from 'lucide-react';
import { format } from 'date-fns';
import Link from 'next/link';
import { SignaturePad, type SignaturePadRef } from '@/components/ui/SignaturePad';

// ---------------------------------------------------------------------------
// Types
// ---------------------------------------------------------------------------

interface Student {
  id: number;
  student_name: string;
  support_start_date: string | null;
  support_plan_start_type: 'current' | 'next';
}

interface SupportPlanSummary {
  id: number;
  student_id: number;
  student_name: string;
  created_date: string;
  plan_start_date: string | null;
  plan_end_date: string | null;
  guardian_wish: string | null;
  child_wish: string | null;
  long_term_goal: string | null;
  short_term_goal: string | null;
  is_confirmed: boolean;
  confirmed_at: string | null;
  detail_count: number;
  has_signature: boolean;
  staff_signature_image: string | null;
  guardian_signature_image: string | null;
  staff_signer_name: string | null;
  staff_signature_date: string | null;
  guardian_signature_date: string | null;
  created_at: string;
}

interface PlanDetail {
  id?: number;
  category: string;
  sub_category: string;
  goal: string;
  support_content: string;
  achievement_date: string;
  staff_organization: string;
  row_order: number;
}

interface PlanForm {
  student_id: number | '';
  created_date: string;
  plan_start_date: string;
  plan_end_date: string;
  guardian_wish: string;
  child_wish: string;
  long_term_goal: string;
  short_term_goal: string;
  details: PlanDetail[];
}

const DEFAULT_DETAILS: PlanDetail[] = [
  { category: '本人支援', sub_category: '生活習慣（健康・生活）', goal: '', support_content: '', achievement_date: '', staff_organization: '保育士\n児童指導員', row_order: 1 },
  { category: '本人支援', sub_category: 'コミュニケーション（言語・コミュニケーション）', goal: '', support_content: '', achievement_date: '', staff_organization: '保育士\n児童指導員', row_order: 2 },
  { category: '本人支援', sub_category: '社会性（人間関係・社会性）', goal: '', support_content: '', achievement_date: '', staff_organization: '保育士\n児童指導員', row_order: 3 },
  { category: '本人支援', sub_category: '運動・感覚（運動・感覚）', goal: '', support_content: '', achievement_date: '', staff_organization: '保育士\n児童指導員', row_order: 4 },
  { category: '本人支援', sub_category: '認知・行動（認知・行動）', goal: '', support_content: '', achievement_date: '', staff_organization: '保育士\n児童指導員', row_order: 5 },
  { category: '家族支援', sub_category: '家族支援', goal: '', support_content: '', achievement_date: '', staff_organization: '児童発達支援管理責任者', row_order: 6 },
  { category: '移行支援', sub_category: '移行支援', goal: '', support_content: '', achievement_date: '', staff_organization: '児童発達支援管理責任者', row_order: 7 },
];

const emptyForm: PlanForm = {
  student_id: '',
  created_date: new Date().toISOString().split('T')[0],
  plan_start_date: '',
  plan_end_date: '',
  guardian_wish: '',
  child_wish: '',
  long_term_goal: '',
  short_term_goal: '',
  details: DEFAULT_DETAILS,
};

export default function KobetsuPlanPage() {
  const queryClient = useQueryClient();
  const toast = useToast();

  const [selectedStudentId, setSelectedStudentId] = useState<number | null>(null);
  const [editModal, setEditModal] = useState(false);
  const [editingPlan, setEditingPlan] = useState<SupportPlanSummary | null>(null);
  const [form, setForm] = useState<PlanForm>(emptyForm);
  const [generating, setGenerating] = useState(false);

  // Signature state
  const [signModal, setSignModal] = useState(false);
  const [signingPlan, setSigningPlan] = useState<SupportPlanSummary | null>(null);
  const [staffSignerName, setStaffSignerName] = useState('');
  const staffSigRef = useRef<SignaturePadRef>(null);
  const guardianSigRef = useRef<SignaturePadRef>(null);

  // Fetch students
  const { data: students = [], isLoading: loadingStudents } = useQuery({
    queryKey: ['staff', 'students'],
    queryFn: async () => {
      const res = await api.get<{ data: Student[] }>('/api/staff/students');
      return res.data.data;
    },
  });

  // Fetch plans for selected student
  const { data: plans = [], isLoading: loadingPlans } = useQuery({
    queryKey: ['staff', 'support-plans', 'individual', selectedStudentId],
    queryFn: async () => {
      const res = await api.get<{ data: SupportPlanSummary[] }>(`/api/staff/students/${selectedStudentId}/support-plans`);
      const payload = res.data?.data;
      return Array.isArray(payload) ? payload : [];
    },
    enabled: !!selectedStudentId,
  });

  // Create mutation
  const createMutation = useMutation({
    mutationFn: (data: PlanForm) => api.post(`/api/staff/students/${selectedStudentId}/support-plans`, data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['staff', 'support-plans', 'individual'] });
      toast.success('個別支援計画を作成しました');
      setEditModal(false);
      setForm(emptyForm);
    },
    onError: () => toast.error('作成に失敗しました'),
  });

  // Update mutation
  const updateMutation = useMutation({
    mutationFn: ({ id, ...data }: PlanForm & { id: number }) =>
      api.put(`/api/staff/support-plans/${id}`, data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['staff', 'support-plans', 'individual'] });
      toast.success('個別支援計画を更新しました');
      setEditModal(false);
      setEditingPlan(null);
      setForm(emptyForm);
    },
    onError: () => toast.error('更新に失敗しました'),
  });

  // Delete mutation
  const deleteMutation = useMutation({
    mutationFn: (id: number) => api.delete(`/api/staff/support-plans/${id}`),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['staff', 'support-plans', 'individual'] });
      toast.success('個別支援計画を削除しました');
    },
    onError: () => toast.error('削除に失敗しました'),
  });

  // Sign mutation
  const signMutation = useMutation({
    mutationFn: (data: { id: number; staff_signature: string; staff_signer_name: string; guardian_signature?: string }) =>
      api.post(`/api/staff/support-plans/${data.id}/sign`, data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['staff', 'support-plans', 'individual'] });
      toast.success('署名を保存し、計画書を確定しました');
      setSignModal(false);
      setSigningPlan(null);
    },
    onError: () => toast.error('署名の保存に失敗しました'),
  });

  const openSignModal = (plan: SupportPlanSummary) => {
    setSigningPlan(plan);
    setStaffSignerName('');
    setSignModal(true);
  };

  const handleSign = () => {
    if (!signingPlan) return;
    if (!staffSigRef.current || staffSigRef.current.isEmpty()) {
      toast.error('職員の署名を記入してください');
      return;
    }
    if (!staffSignerName.trim()) {
      toast.error('署名者名を入力してください');
      return;
    }
    const payload: { id: number; staff_signature: string; staff_signer_name: string; guardian_signature?: string } = {
      id: signingPlan.id,
      staff_signature: staffSigRef.current.toDataURL(),
      staff_signer_name: staffSignerName,
    };
    if (guardianSigRef.current && !guardianSigRef.current.isEmpty()) {
      payload.guardian_signature = guardianSigRef.current.toDataURL();
    }
    signMutation.mutate(payload);
  };

  // AI generation
  const handleAIGenerate = async () => {
    if (!form.student_id) {
      toast.error('生徒を選択してください');
      return;
    }
    setGenerating(true);
    try {
      const res = await api.post<{ data: { details: PlanDetail[] } }>('/api/staff/support-plans/generate', {
        student_id: form.student_id,
      });
      setForm((prev) => ({ ...prev, details: res.data.data.details }));
      toast.success('AI生成が完了しました');
    } catch {
      toast.error('AI生成に失敗しました');
    } finally {
      setGenerating(false);
    }
  };

  // PDF download
  const handlePdfDownload = async (planId: number) => {
    try {
      const res = await api.get(`/api/staff/support-plans/${planId}/pdf`, { responseType: 'blob' });
      const url = window.URL.createObjectURL(new Blob([res.data]));
      const link = document.createElement('a');
      link.href = url;
      link.download = `kobetsu_plan_${planId}.pdf`;
      link.click();
      window.URL.revokeObjectURL(url);
    } catch {
      toast.error('PDF生成に失敗しました');
    }
  };

  const openCreateModal = () => {
    setEditingPlan(null);
    setForm({ ...emptyForm, student_id: selectedStudentId ?? '' });
    setEditModal(true);
  };

  const openEditModal = async (plan: SupportPlanSummary) => {
    try {
      const res = await api.get<{ data: { plan: SupportPlanSummary; details: PlanDetail[] } }>(
        `/api/staff/support-plans/${plan.id}`
      );
      const { plan: planData, details } = res.data.data;
      setEditingPlan(plan);
      setForm({
        student_id: plan.student_id,
        created_date: planData.created_date,
        plan_start_date: planData.plan_start_date || '',
        plan_end_date: planData.plan_end_date || '',
        guardian_wish: planData.guardian_wish || '',
        child_wish: planData.child_wish || '',
        long_term_goal: planData.long_term_goal || '',
        short_term_goal: planData.short_term_goal || '',
        details: details.length > 0 ? details : DEFAULT_DETAILS,
      });
      setEditModal(true);
    } catch {
      toast.error('計画の読み込みに失敗しました');
    }
  };

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    if (editingPlan) {
      updateMutation.mutate({ id: editingPlan.id, ...form });
    } else {
      createMutation.mutate(form);
    }
  };

  const updateDetail = (index: number, field: keyof PlanDetail, value: string) => {
    setForm((prev) => ({
      ...prev,
      details: prev.details.map((d, i) => (i === index ? { ...d, [field]: value } : d)),
    }));
  };

  const addDetail = () => {
    setForm((prev) => ({
      ...prev,
      details: [
        ...prev.details,
        { category: '本人支援', sub_category: '', goal: '', support_content: '', achievement_date: '', staff_organization: '', row_order: prev.details.length + 1 },
      ],
    }));
  };

  const removeDetail = (index: number) => {
    setForm((prev) => ({
      ...prev,
      details: prev.details.filter((_, i) => i !== index),
    }));
  };

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-bold text-[var(--neutral-foreground-1)]">個別支援計画</h1>
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
              onChange={(e) => setSelectedStudentId(e.target.value ? Number(e.target.value) : null)}
            >
              <option value="">-- 生徒を選択してください --</option>
              {students.map((s) => (
                <option key={s.id} value={s.id}>{s.student_name}</option>
              ))}
            </select>
          )}
        </CardBody>
      </Card>

      {/* Plans list */}
      {selectedStudentId && (
        <>
          <div className="flex justify-end">
            <Button leftIcon={<Plus className="h-4 w-4" />} onClick={openCreateModal}>
              新規作成
            </Button>
          </div>

          {loadingPlans ? (
            <SkeletonList items={3} />
          ) : plans.length === 0 ? (
            <Card>
              <CardBody>
                <p className="py-8 text-center text-sm text-[var(--neutral-foreground-3)]">個別支援計画がありません</p>
              </CardBody>
            </Card>
          ) : (
            <div className="space-y-4">
              {plans.map((plan) => (
                <Card key={plan.id}>
                  <CardHeader>
                    <div className="flex items-center justify-between">
                      <div className="flex items-center gap-3">
                        <FileText className="h-5 w-5 text-[var(--brand-80)]" />
                        <CardTitle className="text-base">
                          {format(new Date(plan.created_date), 'yyyy年MM月dd日')} 作成
                        </CardTitle>
                        <Badge variant={plan.is_confirmed ? 'success' : 'warning'}>
                          {plan.is_confirmed ? '確認済み' : '未確認'}
                        </Badge>
                        {plan.has_signature && <Badge variant="info">署名済み</Badge>}
                      </div>
                      <div className="flex gap-1">
                        <Button variant="outline" size="sm" onClick={() => openSignModal(plan)} title="署名">
                          <PenLine className="h-4 w-4" />
                        </Button>
                        <Button variant="outline" size="sm" onClick={() => handlePdfDownload(plan.id)}>
                          <Download className="h-4 w-4" />
                        </Button>
                        <Button variant="outline" size="sm" onClick={() => openEditModal(plan)}>
                          <Pencil className="h-4 w-4" />
                        </Button>
                        <Button
                          variant="ghost"
                          size="sm"
                          onClick={() => { if (confirm('削除しますか？')) deleteMutation.mutate(plan.id); }}
                        >
                          <Trash2 className="h-4 w-4 text-[var(--status-danger-fg)]" />
                        </Button>
                      </div>
                    </div>
                  </CardHeader>
                  <CardBody>
                    <div className="grid gap-3 md:grid-cols-2">
                      {plan.plan_start_date && plan.plan_end_date && (
                        <div>
                          <p className="text-xs text-[var(--neutral-foreground-3)]">計画期間</p>
                          <p className="text-sm text-[var(--neutral-foreground-1)]">
                            {format(new Date(plan.plan_start_date), 'yyyy/MM/dd')} - {format(new Date(plan.plan_end_date), 'yyyy/MM/dd')}
                          </p>
                        </div>
                      )}
                      <div>
                        <p className="text-xs text-[var(--neutral-foreground-3)]">明細数</p>
                        <p className="text-sm text-[var(--neutral-foreground-1)]">{plan.detail_count}項目</p>
                      </div>
                      {plan.long_term_goal && (
                        <div className="md:col-span-2">
                          <p className="text-xs text-[var(--neutral-foreground-3)]">長期目標</p>
                          <p className="text-sm text-[var(--neutral-foreground-1)] line-clamp-2">{plan.long_term_goal}</p>
                        </div>
                      )}
                    </div>
                    {/* Existing signatures */}
                    {(plan.staff_signature_image || plan.guardian_signature_image) && (
                      <div className="mt-3 flex flex-wrap gap-4 border-t border-[var(--neutral-stroke-2)] pt-3">
                        {plan.staff_signature_image && (
                          <SignaturePad
                            readOnly
                            initialValue={plan.staff_signature_image}
                            label={`職員署名${plan.staff_signer_name ? ` (${plan.staff_signer_name})` : ''}${plan.staff_signature_date ? ` - ${plan.staff_signature_date}` : ''}`}
                            width={200}
                            height={80}
                          />
                        )}
                        {plan.guardian_signature_image && (
                          <SignaturePad
                            readOnly
                            initialValue={plan.guardian_signature_image}
                            label={`保護者署名${plan.guardian_signature_date ? ` - ${plan.guardian_signature_date}` : ''}`}
                            width={200}
                            height={80}
                          />
                        )}
                      </div>
                    )}
                  </CardBody>
                </Card>
              ))}
            </div>
          )}
        </>
      )}

      {/* Signature Modal */}
      <Modal
        isOpen={signModal}
        onClose={() => { setSignModal(false); setSigningPlan(null); }}
        title="電子署名 - 個別支援計画書"
        size="xl"
      >
        <div className="space-y-6">
          <p className="text-sm text-[var(--neutral-foreground-3)]">
            職員の署名が入力されると「確定して保存」ボタンが有効になります。保護者署名は任意です。
          </p>

          {/* Staff signer name */}
          <Input
            label="署名者名（職員）"
            value={staffSignerName}
            onChange={(e) => setStaffSignerName(e.target.value)}
            placeholder="職員氏名を入力..."
            required
          />

          {/* Staff signature */}
          <SignaturePad
            ref={staffSigRef}
            label="職員署名（必須）"
            width={400}
            height={150}
            initialValue={signingPlan?.staff_signature_image ?? undefined}
          />

          {/* Guardian signature */}
          <SignaturePad
            ref={guardianSigRef}
            label="保護者署名（任意）"
            width={400}
            height={150}
            initialValue={signingPlan?.guardian_signature_image ?? undefined}
          />

          {/* Existing signatures preview */}
          {signingPlan?.staff_signature_image && (
            <div className="rounded-lg bg-[var(--neutral-background-2)] p-3">
              <p className="mb-1 text-xs font-medium text-[var(--neutral-foreground-3)]">
                保存済みの職員署名 ({signingPlan.staff_signer_name} / {signingPlan.staff_signature_date})
              </p>
              <img src={signingPlan.staff_signature_image} alt="職員署名" className="max-h-20 rounded border border-[var(--neutral-stroke-2)] bg-white" />
            </div>
          )}
          {signingPlan?.guardian_signature_image && (
            <div className="rounded-lg bg-[var(--neutral-background-2)] p-3">
              <p className="mb-1 text-xs font-medium text-[var(--neutral-foreground-3)]">
                保存済みの保護者署名 ({signingPlan.guardian_signature_date})
              </p>
              <img src={signingPlan.guardian_signature_image} alt="保護者署名" className="max-h-20 rounded border border-[var(--neutral-stroke-2)] bg-white" />
            </div>
          )}

          <div className="flex justify-end gap-2 border-t border-[var(--neutral-stroke-2)] pt-4">
            <Button variant="secondary" onClick={() => { setSignModal(false); setSigningPlan(null); }}>
              キャンセル
            </Button>
            <Button onClick={handleSign} isLoading={signMutation.isPending}>
              確定して保存（正式版として提出）
            </Button>
          </div>
        </div>
      </Modal>

      {/* Create / Edit Modal */}
      <Modal
        isOpen={editModal}
        onClose={() => { setEditModal(false); setEditingPlan(null); setForm(emptyForm); }}
        title={editingPlan ? '個別支援計画を編集' : '個別支援計画を作成'}
        size="full"
      >
        <form onSubmit={handleSubmit} className="space-y-6">
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

          <div className="grid gap-4 md:grid-cols-2">
            <Input
              label="作成日"
              type="date"
              value={form.created_date}
              onChange={(e) => setForm({ ...form, created_date: e.target.value })}
              required
            />
            <div />
            <Input
              label="計画開始日"
              type="date"
              value={form.plan_start_date}
              onChange={(e) => setForm({ ...form, plan_start_date: e.target.value })}
            />
            <Input
              label="計画終了日"
              type="date"
              value={form.plan_end_date}
              onChange={(e) => setForm({ ...form, plan_end_date: e.target.value })}
            />
          </div>

          <div className="grid gap-4 md:grid-cols-2">
            <div>
              <label className="mb-1 block text-sm font-medium text-[var(--neutral-foreground-2)]">保護者の願い</label>
              <textarea
                value={form.guardian_wish}
                onChange={(e) => setForm({ ...form, guardian_wish: e.target.value })}
                className="block w-full rounded-lg border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-1)] px-3 py-2 text-sm text-[var(--neutral-foreground-1)]"
                rows={3}
              />
            </div>
            <div>
              <label className="mb-1 block text-sm font-medium text-[var(--neutral-foreground-2)]">本人の願い</label>
              <textarea
                value={form.child_wish}
                onChange={(e) => setForm({ ...form, child_wish: e.target.value })}
                className="block w-full rounded-lg border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-1)] px-3 py-2 text-sm text-[var(--neutral-foreground-1)]"
                rows={3}
              />
            </div>
          </div>

          <div className="grid gap-4 md:grid-cols-2">
            <div>
              <label className="mb-1 block text-sm font-medium text-[var(--neutral-foreground-2)]">長期目標</label>
              <textarea
                value={form.long_term_goal}
                onChange={(e) => setForm({ ...form, long_term_goal: e.target.value })}
                className="block w-full rounded-lg border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-1)] px-3 py-2 text-sm text-[var(--neutral-foreground-1)]"
                rows={3}
              />
            </div>
            <div>
              <label className="mb-1 block text-sm font-medium text-[var(--neutral-foreground-2)]">短期目標</label>
              <textarea
                value={form.short_term_goal}
                onChange={(e) => setForm({ ...form, short_term_goal: e.target.value })}
                className="block w-full rounded-lg border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-1)] px-3 py-2 text-sm text-[var(--neutral-foreground-1)]"
                rows={3}
              />
            </div>
          </div>

          {/* Plan details */}
          <div>
            <div className="mb-3 flex items-center justify-between">
              <h3 className="text-lg font-semibold text-[var(--neutral-foreground-1)]">支援内容明細</h3>
              <Button type="button" variant="outline" size="sm" leftIcon={<Plus className="h-4 w-4" />} onClick={addDetail}>
                行を追加
              </Button>
            </div>

            <div className="space-y-4">
              {form.details.map((detail, index) => (
                <div key={index} className="rounded-lg border border-[var(--neutral-stroke-2)] p-4">
                  <div className="mb-2 flex items-center justify-between">
                    <span className="text-sm font-medium text-[var(--neutral-foreground-2)]">
                      {detail.category} / {detail.sub_category || `明細 ${index + 1}`}
                    </span>
                    <Button type="button" variant="ghost" size="sm" onClick={() => removeDetail(index)}>
                      <Trash2 className="h-4 w-4 text-[var(--status-danger-fg)]" />
                    </Button>
                  </div>
                  <div className="grid gap-3 md:grid-cols-2">
                    <Input
                      label="カテゴリ"
                      value={detail.category}
                      onChange={(e) => updateDetail(index, 'category', e.target.value)}
                    />
                    <Input
                      label="サブカテゴリ"
                      value={detail.sub_category}
                      onChange={(e) => updateDetail(index, 'sub_category', e.target.value)}
                    />
                    <div className="md:col-span-2">
                      <label className="mb-1 block text-sm font-medium text-[var(--neutral-foreground-2)]">目標</label>
                      <textarea
                        value={detail.goal}
                        onChange={(e) => updateDetail(index, 'goal', e.target.value)}
                        className="block w-full rounded-lg border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-1)] px-3 py-2 text-sm text-[var(--neutral-foreground-1)]"
                        rows={2}
                      />
                    </div>
                    <div className="md:col-span-2">
                      <label className="mb-1 block text-sm font-medium text-[var(--neutral-foreground-2)]">支援内容</label>
                      <textarea
                        value={detail.support_content}
                        onChange={(e) => updateDetail(index, 'support_content', e.target.value)}
                        className="block w-full rounded-lg border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-1)] px-3 py-2 text-sm text-[var(--neutral-foreground-1)]"
                        rows={2}
                      />
                    </div>
                    <Input
                      label="達成予定日"
                      type="date"
                      value={detail.achievement_date}
                      onChange={(e) => updateDetail(index, 'achievement_date', e.target.value)}
                    />
                    <Input
                      label="担当職種・組織"
                      value={detail.staff_organization}
                      onChange={(e) => updateDetail(index, 'staff_organization', e.target.value)}
                    />
                  </div>
                </div>
              ))}
            </div>
          </div>

          <div className="flex justify-end gap-2 pt-4">
            <Button variant="secondary" type="button" onClick={() => { setEditModal(false); setEditingPlan(null); }}>
              キャンセル
            </Button>
            <Button type="submit" isLoading={createMutation.isPending || updateMutation.isPending}>
              {editingPlan ? '更新' : '作成'}
            </Button>
          </div>
        </form>
      </Modal>
    </div>
  );
}
