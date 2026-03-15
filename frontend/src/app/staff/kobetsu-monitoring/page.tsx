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
  ClipboardCheck,
  Loader2,
  PenLine,
} from 'lucide-react';
import { format } from 'date-fns';
import { SignaturePad, type SignaturePadRef } from '@/components/ui/SignaturePad';

// ---------------------------------------------------------------------------
// Types
// ---------------------------------------------------------------------------

interface Student {
  id: number;
  student_name: string;
}

interface PlanOption {
  id: number;
  created_date: string;
  plan_start_date: string | null;
  plan_end_date: string | null;
}

interface MonitoringRecord {
  id: number;
  student_id: number;
  plan_id: number;
  monitoring_date: string;
  overall_assessment: string | null;
  next_plan_direction: string | null;
  is_confirmed: boolean;
  confirmed_at: string | null;
  staff_signature_image: string | null;
  guardian_signature_image: string | null;
  staff_signer_name: string | null;
  staff_signature_date: string | null;
  guardian_signature_date: string | null;
  created_at: string;
  updated_at: string;
  details: MonitoringDetail[];
}

interface MonitoringDetail {
  id?: number;
  category: string;
  sub_category: string;
  original_goal: string;
  achievement_status: 'achieved' | 'partial' | 'not_achieved' | 'ongoing';
  assessment: string;
  future_direction: string;
  row_order: number;
}

interface MonitoringForm {
  plan_id: number | '';
  monitoring_date: string;
  overall_assessment: string;
  next_plan_direction: string;
  details: MonitoringDetail[];
}

const ACHIEVEMENT_STATUS: Record<string, { label: string; variant: 'success' | 'warning' | 'danger' | 'default' }> = {
  achieved: { label: '達成', variant: 'success' },
  partial: { label: '一部達成', variant: 'warning' },
  not_achieved: { label: '未達成', variant: 'danger' },
  ongoing: { label: '継続中', variant: 'default' },
};

const emptyForm: MonitoringForm = {
  plan_id: '',
  monitoring_date: new Date().toISOString().split('T')[0],
  overall_assessment: '',
  next_plan_direction: '',
  details: [],
};

export default function KobetsuMonitoringPage() {
  const queryClient = useQueryClient();
  const toast = useToast();

  const [selectedStudentId, setSelectedStudentId] = useState<number | null>(null);
  const [selectedPlanId, setSelectedPlanId] = useState<number | null>(null);
  const [editModal, setEditModal] = useState(false);
  const [editingRecord, setEditingRecord] = useState<MonitoringRecord | null>(null);
  const [form, setForm] = useState<MonitoringForm>(emptyForm);
  const [generating, setGenerating] = useState(false);

  // Signature state
  const [signModal, setSignModal] = useState(false);
  const [signingRecord, setSigningRecord] = useState<MonitoringRecord | null>(null);
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

  // Fetch plans for student (for plan selector)
  const { data: planOptions = [] } = useQuery({
    queryKey: ['staff', 'monitoring', 'plans', selectedStudentId],
    queryFn: async () => {
      const res = await api.get<{ data: PlanOption[] }>(`/api/staff/support-plans/individual?student_id=${selectedStudentId}`);
      return res.data.data;
    },
    enabled: !!selectedStudentId,
  });

  // Fetch monitoring records
  const { data: records = [], isLoading: loadingRecords } = useQuery({
    queryKey: ['staff', 'monitoring', selectedStudentId, selectedPlanId],
    queryFn: async () => {
      let url = `/api/staff/monitoring?student_id=${selectedStudentId}`;
      if (selectedPlanId) url += `&plan_id=${selectedPlanId}`;
      const res = await api.get<{ data: MonitoringRecord[] }>(url);
      return res.data.data;
    },
    enabled: !!selectedStudentId,
  });

  // Create mutation
  const createMutation = useMutation({
    mutationFn: (data: MonitoringForm & { student_id: number }) =>
      api.post('/api/staff/monitoring', data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['staff', 'monitoring'] });
      toast.success('モニタリングを作成しました');
      setEditModal(false);
      setForm(emptyForm);
    },
    onError: () => toast.error('作成に失敗しました'),
  });

  // Update mutation
  const updateMutation = useMutation({
    mutationFn: ({ id, ...data }: MonitoringForm & { id: number; student_id: number }) =>
      api.put(`/api/staff/monitoring/${id}`, data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['staff', 'monitoring'] });
      toast.success('モニタリングを更新しました');
      setEditModal(false);
      setEditingRecord(null);
      setForm(emptyForm);
    },
    onError: () => toast.error('更新に失敗しました'),
  });

  // Delete mutation
  const deleteMutation = useMutation({
    mutationFn: (id: number) => api.delete(`/api/staff/monitoring/${id}`),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['staff', 'monitoring'] });
      toast.success('モニタリングを削除しました');
    },
    onError: () => toast.error('削除に失敗しました'),
  });

  // Sign mutation
  const signMutation = useMutation({
    mutationFn: (data: { id: number; staff_signature: string; staff_signer_name: string; guardian_signature?: string }) =>
      api.post(`/api/staff/monitoring/${data.id}/sign`, data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['staff', 'monitoring'] });
      toast.success('署名を保存しました');
      setSignModal(false);
      setSigningRecord(null);
    },
    onError: () => toast.error('署名の保存に失敗しました'),
  });

  const openSignModal = (record: MonitoringRecord) => {
    setSigningRecord(record);
    setStaffSignerName('');
    setSignModal(true);
  };

  const handleSign = () => {
    if (!signingRecord) return;
    if (!staffSigRef.current || staffSigRef.current.isEmpty()) {
      toast.error('職員の署名を記入してください');
      return;
    }
    if (!staffSignerName.trim()) {
      toast.error('署名者名を入力してください');
      return;
    }
    const payload: { id: number; staff_signature: string; staff_signer_name: string; guardian_signature?: string } = {
      id: signingRecord.id,
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
    if (!selectedStudentId || !form.plan_id) {
      toast.error('生徒と計画を選択してください');
      return;
    }
    setGenerating(true);
    try {
      const res = await api.post<{ data: { details: MonitoringDetail[]; overall_assessment: string; next_plan_direction: string } }>(
        '/api/staff/monitoring/generate',
        { student_id: selectedStudentId, plan_id: form.plan_id }
      );
      setForm((prev) => ({
        ...prev,
        details: res.data.data.details,
        overall_assessment: res.data.data.overall_assessment,
        next_plan_direction: res.data.data.next_plan_direction,
      }));
      toast.success('AI生成が完了しました');
    } catch {
      toast.error('AI生成に失敗しました');
    } finally {
      setGenerating(false);
    }
  };

  // PDF download
  const handlePdfDownload = async (recordId: number) => {
    try {
      const res = await api.get(`/api/staff/monitoring/${recordId}/pdf`, { responseType: 'blob' });
      const url = window.URL.createObjectURL(new Blob([res.data]));
      const link = document.createElement('a');
      link.href = url;
      link.download = `monitoring_${recordId}.pdf`;
      link.click();
      window.URL.revokeObjectURL(url);
    } catch {
      toast.error('PDF生成に失敗しました');
    }
  };

  const openCreateModal = () => {
    setEditingRecord(null);
    setForm({ ...emptyForm, plan_id: selectedPlanId ?? '' });
    setEditModal(true);
  };

  const openEditModal = (record: MonitoringRecord) => {
    setEditingRecord(record);
    setForm({
      plan_id: record.plan_id,
      monitoring_date: record.monitoring_date,
      overall_assessment: record.overall_assessment || '',
      next_plan_direction: record.next_plan_direction || '',
      details: record.details,
    });
    setEditModal(true);
  };

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    if (!selectedStudentId) return;
    if (editingRecord) {
      updateMutation.mutate({ id: editingRecord.id, student_id: selectedStudentId, ...form });
    } else {
      createMutation.mutate({ student_id: selectedStudentId, ...form });
    }
  };

  const updateDetail = (index: number, field: keyof MonitoringDetail, value: string) => {
    setForm((prev) => ({
      ...prev,
      details: prev.details.map((d, i) => (i === index ? { ...d, [field]: value } : d)),
    }));
  };

  return (
    <div className="space-y-6">
      <h1 className="text-2xl font-bold text-[var(--neutral-foreground-1)]">モニタリング</h1>

      {/* Student selector */}
      <Card>
        <CardBody>
          <div className="grid gap-4 md:grid-cols-2">
            <div>
              <label className="mb-2 block text-sm font-medium text-[var(--neutral-foreground-2)]">生徒を選択</label>
              <select
                className="block w-full rounded-lg border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-1)] px-3 py-2 text-sm text-[var(--neutral-foreground-1)]"
                value={selectedStudentId ?? ''}
                onChange={(e) => {
                  setSelectedStudentId(e.target.value ? Number(e.target.value) : null);
                  setSelectedPlanId(null);
                }}
              >
                <option value="">-- 生徒を選択してください --</option>
                {students.map((s) => (
                  <option key={s.id} value={s.id}>{s.student_name}</option>
                ))}
              </select>
            </div>
            {selectedStudentId && planOptions.length > 0 && (
              <div>
                <label className="mb-2 block text-sm font-medium text-[var(--neutral-foreground-2)]">計画で絞り込み</label>
                <select
                  className="block w-full rounded-lg border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-1)] px-3 py-2 text-sm text-[var(--neutral-foreground-1)]"
                  value={selectedPlanId ?? ''}
                  onChange={(e) => setSelectedPlanId(e.target.value ? Number(e.target.value) : null)}
                >
                  <option value="">すべての計画</option>
                  {planOptions.map((p) => (
                    <option key={p.id} value={p.id}>
                      {format(new Date(p.created_date), 'yyyy/MM/dd')} 作成
                      {p.plan_start_date && p.plan_end_date
                        ? ` (${format(new Date(p.plan_start_date), 'MM/dd')}~${format(new Date(p.plan_end_date), 'MM/dd')})`
                        : ''}
                    </option>
                  ))}
                </select>
              </div>
            )}
          </div>
        </CardBody>
      </Card>

      {/* Records */}
      {selectedStudentId && (
        <>
          <div className="flex justify-end">
            <Button leftIcon={<Plus className="h-4 w-4" />} onClick={openCreateModal}>
              新規作成
            </Button>
          </div>

          {loadingRecords ? (
            <SkeletonList items={3} />
          ) : records.length === 0 ? (
            <Card>
              <CardBody>
                <p className="py-8 text-center text-sm text-[var(--neutral-foreground-3)]">モニタリング記録がありません</p>
              </CardBody>
            </Card>
          ) : (
            <div className="space-y-4">
              {records.map((record) => (
                <Card key={record.id}>
                  <CardHeader>
                    <div className="flex items-center justify-between">
                      <div className="flex items-center gap-3">
                        <ClipboardCheck className="h-5 w-5 text-[var(--brand-80)]" />
                        <CardTitle className="text-base">
                          {format(new Date(record.monitoring_date), 'yyyy年MM月dd日')}
                        </CardTitle>
                        <Badge variant={record.is_confirmed ? 'success' : 'warning'}>
                          {record.is_confirmed ? '確認済み' : '未確認'}
                        </Badge>
                      </div>
                      <div className="flex gap-1">
                        <Button variant="outline" size="sm" onClick={() => openSignModal(record)} title="署名">
                          <PenLine className="h-4 w-4" />
                        </Button>
                        <Button variant="outline" size="sm" onClick={() => handlePdfDownload(record.id)}>
                          <Download className="h-4 w-4" />
                        </Button>
                        <Button variant="outline" size="sm" onClick={() => openEditModal(record)}>
                          <Pencil className="h-4 w-4" />
                        </Button>
                        <Button
                          variant="ghost"
                          size="sm"
                          onClick={() => { if (confirm('削除しますか？')) deleteMutation.mutate(record.id); }}
                        >
                          <Trash2 className="h-4 w-4 text-[var(--status-danger-fg)]" />
                        </Button>
                      </div>
                    </div>
                  </CardHeader>
                  <CardBody>
                    {record.overall_assessment && (
                      <div className="mb-3">
                        <p className="text-xs font-medium text-[var(--neutral-foreground-3)]">総合評価</p>
                        <p className="text-sm text-[var(--neutral-foreground-1)] whitespace-pre-wrap">{record.overall_assessment}</p>
                      </div>
                    )}

                    {record.details.length > 0 && (
                      <div className="space-y-2">
                        {record.details.map((detail, idx) => (
                          <div key={idx} className="flex items-start gap-3 rounded bg-[var(--neutral-background-2)] p-2">
                            <Badge variant={ACHIEVEMENT_STATUS[detail.achievement_status]?.variant ?? 'default'}>
                              {ACHIEVEMENT_STATUS[detail.achievement_status]?.label ?? detail.achievement_status}
                            </Badge>
                            <div className="flex-1">
                              <p className="text-xs font-medium text-[var(--neutral-foreground-3)]">{detail.sub_category}</p>
                              <p className="text-sm text-[var(--neutral-foreground-1)]">{detail.assessment || '-'}</p>
                            </div>
                          </div>
                        ))}
                      </div>
                    )}

                    {/* Existing signatures */}
                    {(record.staff_signature_image || record.guardian_signature_image) && (
                      <div className="mt-3 flex flex-wrap gap-4 border-t border-[var(--neutral-stroke-2)] pt-3">
                        {record.staff_signature_image && (
                          <SignaturePad
                            readOnly
                            initialValue={record.staff_signature_image}
                            label={`職員署名${record.staff_signer_name ? ` (${record.staff_signer_name})` : ''}${record.staff_signature_date ? ` - ${record.staff_signature_date}` : ''}`}
                            width={200}
                            height={80}
                          />
                        )}
                        {record.guardian_signature_image && (
                          <SignaturePad
                            readOnly
                            initialValue={record.guardian_signature_image}
                            label={`保護者署名${record.guardian_signature_date ? ` - ${record.guardian_signature_date}` : ''}`}
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
        onClose={() => { setSignModal(false); setSigningRecord(null); }}
        title="電子署名 - モニタリング"
        size="xl"
      >
        <div className="space-y-6">
          <p className="text-sm text-[var(--neutral-foreground-3)]">
            職員の署名が入力されると「確定して保存」ボタンが有効になります。保護者署名は任意です。
          </p>

          <Input
            label="署名者名（職員）"
            value={staffSignerName}
            onChange={(e) => setStaffSignerName(e.target.value)}
            placeholder="職員氏名を入力..."
            required
          />

          <SignaturePad
            ref={staffSigRef}
            label="職員署名（必須）"
            width={400}
            height={150}
            initialValue={signingRecord?.staff_signature_image ?? undefined}
          />

          <SignaturePad
            ref={guardianSigRef}
            label="保護者署名（任意）"
            width={400}
            height={150}
            initialValue={signingRecord?.guardian_signature_image ?? undefined}
          />

          {signingRecord?.staff_signature_image && (
            <div className="rounded-lg bg-[var(--neutral-background-2)] p-3">
              <p className="mb-1 text-xs font-medium text-[var(--neutral-foreground-3)]">
                保存済みの職員署名 ({signingRecord.staff_signer_name} / {signingRecord.staff_signature_date})
              </p>
              <img src={signingRecord.staff_signature_image} alt="職員署名" className="max-h-20 rounded border border-[var(--neutral-stroke-2)] bg-white" />
            </div>
          )}

          <div className="flex justify-end gap-2 border-t border-[var(--neutral-stroke-2)] pt-4">
            <Button variant="secondary" onClick={() => { setSignModal(false); setSigningRecord(null); }}>
              キャンセル
            </Button>
            <Button onClick={handleSign} isLoading={signMutation.isPending}>
              確定して保存
            </Button>
          </div>
        </div>
      </Modal>

      {/* Create / Edit Modal */}
      <Modal
        isOpen={editModal}
        onClose={() => { setEditModal(false); setEditingRecord(null); setForm(emptyForm); }}
        title={editingRecord ? 'モニタリングを編集' : 'モニタリングを作成'}
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
            <div>
              <label className="mb-1 block text-sm font-medium text-[var(--neutral-foreground-2)]">対象計画</label>
              <select
                className="block w-full rounded-lg border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-1)] px-3 py-2 text-sm text-[var(--neutral-foreground-1)]"
                value={form.plan_id}
                onChange={(e) => setForm({ ...form, plan_id: e.target.value ? Number(e.target.value) : '' })}
                required
              >
                <option value="">計画を選択</option>
                {planOptions.map((p) => (
                  <option key={p.id} value={p.id}>
                    {format(new Date(p.created_date), 'yyyy/MM/dd')} 作成
                  </option>
                ))}
              </select>
            </div>
            <Input
              label="モニタリング日"
              type="date"
              value={form.monitoring_date}
              onChange={(e) => setForm({ ...form, monitoring_date: e.target.value })}
              required
            />
          </div>

          <div>
            <label className="mb-1 block text-sm font-medium text-[var(--neutral-foreground-2)]">総合評価</label>
            <textarea
              value={form.overall_assessment}
              onChange={(e) => setForm({ ...form, overall_assessment: e.target.value })}
              className="block w-full rounded-lg border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-1)] px-3 py-2 text-sm text-[var(--neutral-foreground-1)]"
              rows={4}
            />
          </div>

          <div>
            <label className="mb-1 block text-sm font-medium text-[var(--neutral-foreground-2)]">今後の計画の方向性</label>
            <textarea
              value={form.next_plan_direction}
              onChange={(e) => setForm({ ...form, next_plan_direction: e.target.value })}
              className="block w-full rounded-lg border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-1)] px-3 py-2 text-sm text-[var(--neutral-foreground-1)]"
              rows={3}
            />
          </div>

          {/* Detail items */}
          {form.details.length > 0 && (
            <div>
              <h3 className="mb-3 text-lg font-semibold text-[var(--neutral-foreground-1)]">明細項目</h3>
              <div className="space-y-4">
                {form.details.map((detail, index) => (
                  <div key={index} className="rounded-lg border border-[var(--neutral-stroke-2)] p-4">
                    <p className="mb-2 text-sm font-medium text-[var(--neutral-foreground-2)]">
                      {detail.category} / {detail.sub_category}
                    </p>
                    {detail.original_goal && (
                      <div className="mb-2 rounded bg-[var(--neutral-background-2)] p-2">
                        <p className="text-xs text-[var(--neutral-foreground-3)]">元の目標</p>
                        <p className="text-sm text-[var(--neutral-foreground-1)]">{detail.original_goal}</p>
                      </div>
                    )}
                    <div className="grid gap-3 md:grid-cols-2">
                      <div>
                        <label className="mb-1 block text-xs font-medium text-[var(--neutral-foreground-3)]">達成状況</label>
                        <select
                          className="block w-full rounded-lg border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-1)] px-3 py-2 text-sm text-[var(--neutral-foreground-1)]"
                          value={detail.achievement_status}
                          onChange={(e) => updateDetail(index, 'achievement_status', e.target.value)}
                        >
                          {Object.entries(ACHIEVEMENT_STATUS).map(([key, { label }]) => (
                            <option key={key} value={key}>{label}</option>
                          ))}
                        </select>
                      </div>
                      <div>
                        <label className="mb-1 block text-xs font-medium text-[var(--neutral-foreground-3)]">今後の方針</label>
                        <textarea
                          value={detail.future_direction}
                          onChange={(e) => updateDetail(index, 'future_direction', e.target.value)}
                          className="block w-full rounded-lg border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-1)] px-3 py-2 text-sm text-[var(--neutral-foreground-1)]"
                          rows={2}
                        />
                      </div>
                      <div className="md:col-span-2">
                        <label className="mb-1 block text-xs font-medium text-[var(--neutral-foreground-3)]">評価・コメント</label>
                        <textarea
                          value={detail.assessment}
                          onChange={(e) => updateDetail(index, 'assessment', e.target.value)}
                          className="block w-full rounded-lg border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-1)] px-3 py-2 text-sm text-[var(--neutral-foreground-1)]"
                          rows={2}
                        />
                      </div>
                    </div>
                  </div>
                ))}
              </div>
            </div>
          )}

          <div className="flex justify-end gap-2 pt-4">
            <Button variant="secondary" type="button" onClick={() => { setEditModal(false); setEditingRecord(null); }}>
              キャンセル
            </Button>
            <Button type="submit" isLoading={createMutation.isPending || updateMutation.isPending}>
              {editingRecord ? '更新' : '作成'}
            </Button>
          </div>
        </form>
      </Modal>
    </div>
  );
}
