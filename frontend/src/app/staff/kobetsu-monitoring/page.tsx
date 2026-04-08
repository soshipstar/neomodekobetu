'use client';

import { useState, useRef, useEffect, useCallback } from 'react';
import { useSearchParams } from 'next/navigation';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import api from '@/lib/api';
import { Card, CardBody } from '@/components/ui/Card';
import { Button } from '@/components/ui/Button';
import { Input } from '@/components/ui/Input';
import { Badge } from '@/components/ui/Badge';
import { useToast } from '@/components/ui/Toast';
import { nl } from '@/lib/utils';
import { format } from 'date-fns';
import { SignaturePad, type SignaturePadRef } from '@/components/ui/SignaturePad';
import { MaterialIcon } from '@/components/ui/MaterialIcon';

// ---------------------------------------------------------------------------
// Types
// ---------------------------------------------------------------------------

interface Student {
  id: number;
  student_name: string;
  support_plan_start_type?: string;
}

interface PlanDetail {
  id: number;
  category: string;
  sub_category: string;
  support_goal: string;
  support_content: string;
  achievement_date: string | null;
  sort_order: number;
  domain?: string;
}

interface PlanOption {
  id: number;
  student_name: string;
  created_date: string;
  long_term_goal_text?: string;
  long_term_goal_date?: string;
  short_term_goal_text?: string;
  short_term_goal_date?: string;
  long_term_goal?: string;
  short_term_goal?: string;
  details: PlanDetail[];
}

interface MonitoringDetailData {
  id?: number;
  plan_detail_id: number;
  achievement_level: string;
  comment: string;
}

interface MonitoringRecord {
  id: number;
  student_id: number;
  plan_id: number;
  student_name: string;
  monitoring_date: string;
  overall_comment: string | null;
  short_term_goal_achievement: string | null;
  short_term_goal_comment: string | null;
  long_term_goal_achievement: string | null;
  long_term_goal_comment: string | null;
  is_draft: boolean;
  is_official: boolean;
  guardian_confirmed: boolean;
  guardian_confirmed_at: string | null;
  staff_signature: string | null;
  guardian_signature: string | null;
  staff_signer_name: string | null;
  staff_signature_date: string | null;
  guardian_signature_date: string | null;
  created_at: string;
  updated_at: string;
  details: MonitoringDetailData[];
}

const ACHIEVEMENT_OPTIONS = ['', '未着手', '進行中', '達成', '継続中', '見直し必要'];

export default function KobetsuMonitoringPage() {
  const queryClient = useQueryClient();
  const toast = useToast();
  const searchParams = useSearchParams();

  const [selectedStudentId, setSelectedStudentId] = useState<number | null>(null);
  const [selectedPlanId, setSelectedPlanId] = useState<number | null>(null);
  const [selectedMonitoringId, setSelectedMonitoringId] = useState<number | null>(null);

  // Form state
  const [monitoringDate, setMonitoringDate] = useState(new Date().toISOString().split('T')[0]);
  const [overallComment, setOverallComment] = useState('');
  const [shortTermGoalAchievement, setShortTermGoalAchievement] = useState('');
  const [shortTermGoalComment, setShortTermGoalComment] = useState('');
  const [longTermGoalAchievement, setLongTermGoalAchievement] = useState('');
  const [longTermGoalComment, setLongTermGoalComment] = useState('');
  const [detailsMap, setDetailsMap] = useState<Record<number, { achievement_level: string; comment: string }>>({});
  const [staffSignatureDate, setStaffSignatureDate] = useState(new Date().toISOString().split('T')[0]);

  // AI generation state
  const [generating, setGenerating] = useState(false);
  const [generatingDetailId, setGeneratingDetailId] = useState<number | null>(null);
  const [generateProgress, setGenerateProgress] = useState({ visible: false, percent: 0, text: '' });

  // Signature
  const staffSigRef = useRef<SignaturePadRef>(null);

  // Fetch students
  const { data: students = [] } = useQuery({
    queryKey: ['staff', 'students'],
    queryFn: async () => {
      const res = await api.get<{ data: Student[] }>('/api/staff/students');
      return res.data.data;
    },
  });

  // Fetch plans for student
  const { data: studentPlans = [] } = useQuery({
    queryKey: ['staff', 'monitoring', 'plans', selectedStudentId],
    queryFn: async () => {
      const res = await api.get<{ data: PlanOption[] }>(`/api/staff/students/${selectedStudentId}/support-plans`);
      return res.data.data;
    },
    enabled: !!selectedStudentId,
  });

  // Fetch monitoring records for the selected plan
  const { data: existingMonitorings = [] } = useQuery({
    queryKey: ['staff', 'monitoring', selectedStudentId, selectedPlanId],
    queryFn: async () => {
      let url = `/api/staff/students/${selectedStudentId}/monitoring`;
      if (selectedPlanId) url += `?plan_id=${selectedPlanId}`;
      const res = await api.get<{ data: MonitoringRecord[] }>(url);
      return res.data.data;
    },
    enabled: !!selectedStudentId && !!selectedPlanId,
  });

  // Auto-select from URL params (e.g. from pending-tasks)
  const autoOpenRef = useRef(false);
  useEffect(() => {
    if (autoOpenRef.current) return;
    const sid = searchParams.get('student_id');
    if (sid) {
      autoOpenRef.current = true;
      setSelectedStudentId(Number(sid));
    }
  }, [searchParams]);

  // Auto-select first plan when student plans load from URL navigation
  useEffect(() => {
    if (autoOpenRef.current && selectedStudentId && studentPlans.length > 0 && !selectedPlanId) {
      setSelectedPlanId(studentPlans[0].id);
    }
  }, [selectedStudentId, studentPlans, selectedPlanId]);

  // Get the selected plan data
  const planData = studentPlans.find((p) => p.id === selectedPlanId) ?? null;
  const planDetails = planData?.details ?? [];

  // Get the selected monitoring data
  const monitoringData = existingMonitorings.find((m) => m.id === selectedMonitoringId) ?? null;

  // Populate form when monitoring data changes
  const populateForm = useCallback((data: MonitoringRecord | null) => {
    if (data) {
      setMonitoringDate(data.monitoring_date?.split('T')[0] ?? new Date().toISOString().split('T')[0]);
      setOverallComment(data.overall_comment ?? '');
      setShortTermGoalAchievement(data.short_term_goal_achievement ?? '');
      setShortTermGoalComment(data.short_term_goal_comment ?? '');
      setLongTermGoalAchievement(data.long_term_goal_achievement ?? '');
      setLongTermGoalComment(data.long_term_goal_comment ?? '');
      setStaffSignatureDate(data.staff_signature_date?.split('T')[0] ?? new Date().toISOString().split('T')[0]);

      const map: Record<number, { achievement_level: string; comment: string }> = {};
      for (const d of data.details) {
        if (d.plan_detail_id) {
          map[d.plan_detail_id] = { achievement_level: d.achievement_level ?? '', comment: d.comment ?? '' };
        }
      }
      setDetailsMap(map);
    } else {
      setMonitoringDate(new Date().toISOString().split('T')[0]);
      setOverallComment('');
      setShortTermGoalAchievement('');
      setShortTermGoalComment('');
      setLongTermGoalAchievement('');
      setLongTermGoalComment('');
      setStaffSignatureDate(new Date().toISOString().split('T')[0]);
      setDetailsMap({});
    }
  }, []);

  useEffect(() => {
    populateForm(monitoringData);
  }, [monitoringData, populateForm]);

  // Save mutation (create or update)
  const saveMutation = useMutation({
    mutationFn: async (isDraft: boolean) => {
      if (!selectedStudentId || !selectedPlanId) throw new Error('Missing student/plan');

      // Collect signature data
      let staffSignature: string | null = null;
      if (staffSigRef.current && !staffSigRef.current.isEmpty()) {
        staffSignature = staffSigRef.current.toDataURL();
      }

      const details = planDetails.map((pd, idx) => ({
        plan_detail_id: pd.id,
        achievement_level: detailsMap[pd.id]?.achievement_level ?? '',
        comment: detailsMap[pd.id]?.comment ?? '',
        domain: pd.category ?? pd.domain ?? '',
        sort_order: idx,
      }));

      const payload = {
        plan_id: selectedPlanId,
        monitoring_date: monitoringDate,
        overall_comment: overallComment,
        short_term_goal_achievement: shortTermGoalAchievement,
        short_term_goal_comment: shortTermGoalComment,
        long_term_goal_achievement: longTermGoalAchievement,
        long_term_goal_comment: longTermGoalComment,
        is_draft: isDraft,
        staff_signature: staffSignature,
        staff_signature_date: staffSignatureDate,
        staff_signer_name: '',
        details,
      };

      if (selectedMonitoringId) {
        return api.put(`/api/staff/monitoring/${selectedMonitoringId}`, payload);
      } else {
        return api.post(`/api/staff/students/${selectedStudentId}/monitoring`, payload);
      }
    },
    onSuccess: (res) => {
      queryClient.invalidateQueries({ queryKey: ['staff', 'monitoring'] });
      const data = res.data as { message?: string; data?: MonitoringRecord };
      toast.success(data.message ?? '保存しました');
      if (data.data?.id && !selectedMonitoringId) {
        setSelectedMonitoringId(data.data.id);
      }
    },
    onError: (err: unknown) => {
      const message = (err as { response?: { data?: { message?: string } } })?.response?.data?.message ?? '保存に失敗しました';
      toast.error(message);
    },
  });

  // Delete mutation
  const deleteMutation = useMutation({
    mutationFn: (id: number) => api.delete(`/api/staff/monitoring/${id}`),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['staff', 'monitoring'] });
      toast.success('モニタリング表を削除しました');
      if (selectedMonitoringId) {
        setSelectedMonitoringId(null);
        populateForm(null);
      }
    },
    onError: () => toast.error('削除に失敗しました'),
  });

  // AI generation - all details
  const handleAIGenerateAll = async () => {
    if (!selectedPlanId || !selectedStudentId) {
      toast.error('計画と生徒を選択してください');
      return;
    }

    if (!confirm('過去6ヶ月の連絡帳データを基に、AIで全ての目標の評価を自動生成します。\n既存の入力内容は上書きされます。続行しますか？')) {
      return;
    }

    setGenerating(true);
    setGenerateProgress({ visible: true, percent: 0, text: '過去の連絡帳データを分析中...' });

    try {
      setGenerateProgress({ visible: true, percent: 50, text: 'AIが評価を生成中...' });

      const res = await api.post<{ success: boolean; data: Record<string, { achievement_status: string; monitoring_comment: string }> }>(
        '/api/staff/monitoring/generate',
        { plan_id: selectedPlanId, student_id: selectedStudentId }
      );

      setGenerateProgress({ visible: true, percent: 80, text: 'フォームに反映中...' });

      if (res.data.success) {
        const evaluations = res.data.data;
        const newMap = { ...detailsMap };
        for (const [detailIdStr, evaluation] of Object.entries(evaluations)) {
          const detailId = Number(detailIdStr);
          newMap[detailId] = {
            achievement_level: evaluation.achievement_status ?? '',
            comment: evaluation.monitoring_comment ?? '',
          };
        }
        setDetailsMap(newMap);

        setGenerateProgress({ visible: true, percent: 100, text: '生成完了! 内容を確認し、必要に応じて編集してください。' });
        setTimeout(() => setGenerateProgress({ visible: false, percent: 0, text: '' }), 3000);
      } else {
        throw new Error('生成に失敗しました');
      }
    } catch (error) {
      toast.error('エラーが発生しました: ' + (error instanceof Error ? error.message : '不明なエラー'));
      setGenerateProgress({ visible: false, percent: 0, text: '' });
    } finally {
      setGenerating(false);
    }
  };

  // AI generation - single detail
  const handleAIGenerateSingle = async (detailId: number) => {
    if (!selectedPlanId || !selectedStudentId) {
      toast.error('計画と生徒を選択してください');
      return;
    }

    setGeneratingDetailId(detailId);

    try {
      const res = await api.post<{ success: boolean; data: Record<string, { achievement_status: string; monitoring_comment: string }> }>(
        '/api/staff/monitoring/generate',
        { plan_id: selectedPlanId, student_id: selectedStudentId, detail_id: detailId }
      );

      if (res.data.success && res.data.data[detailId]) {
        const evaluation = res.data.data[detailId];
        setDetailsMap((prev) => ({
          ...prev,
          [detailId]: {
            achievement_level: evaluation.achievement_status ?? prev[detailId]?.achievement_level ?? '',
            comment: evaluation.monitoring_comment ?? prev[detailId]?.comment ?? '',
          },
        }));
        toast.success('AI生成完了');
      } else {
        throw new Error('生成に失敗しました');
      }
    } catch (error) {
      toast.error('エラーが発生しました: ' + (error instanceof Error ? error.message : '不明なエラー'));
    } finally {
      setGeneratingDetailId(null);
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

  const handleStudentChange = (studentId: number | null) => {
    setSelectedStudentId(studentId);
    setSelectedPlanId(null);
    setSelectedMonitoringId(null);
    populateForm(null);
  };

  const handlePlanChange = (planId: number | null) => {
    setSelectedPlanId(planId);
    setSelectedMonitoringId(null);
    populateForm(null);
  };

  const handleNewMonitoring = () => {
    setSelectedMonitoringId(null);
    populateForm(null);
  };

  const selectedStudent = students.find((s) => s.id === selectedStudentId);
  const isReadOnly = !!monitoringData && monitoringData.is_official && !monitoringData.is_draft;

  return (
    <div className="space-y-6">
      {/* Page Header */}
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold text-[var(--neutral-foreground-1)]">モニタリング表作成</h1>
          <p className="text-sm text-[var(--neutral-foreground-3)]">
            支援目標の達成状況を評価
            {monitoringData?.guardian_confirmed && monitoringData.guardian_confirmed_at && (
              <Badge variant="success" className="ml-2">
                保護者確認済み（{format(new Date(monitoringData.guardian_confirmed_at), 'yyyy/MM/dd HH:mm')}）
              </Badge>
            )}
          </p>
        </div>
      </div>

      {/* Student & Plan Selection */}
      <Card>
        <CardBody>
          <div className="flex flex-wrap gap-5">
            <div className="min-w-[200px] flex-1">
              <label className="mb-2 block text-sm font-semibold text-[var(--neutral-foreground-2)]">生徒を選択 *</label>
              <select
                className="block w-full rounded-lg border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-1)] px-3 py-2 text-sm text-[var(--neutral-foreground-1)]"
                value={selectedStudentId ?? ''}
                onChange={(e) => handleStudentChange(e.target.value ? Number(e.target.value) : null)}
              >
                <option value="">-- 生徒を選択してください --</option>
                {students.map((s) => (
                  <option key={s.id} value={s.id}>{s.student_name}</option>
                ))}
              </select>
            </div>

            {selectedStudentId && studentPlans.length > 0 && (
              <div className="min-w-[200px] flex-1">
                <label className="mb-2 block text-sm font-semibold text-[var(--neutral-foreground-2)]">個別支援計画書を選択 *</label>
                <select
                  className="block w-full rounded-lg border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-1)] px-3 py-2 text-sm text-[var(--neutral-foreground-1)]"
                  value={selectedPlanId ?? ''}
                  onChange={(e) => handlePlanChange(e.target.value ? Number(e.target.value) : null)}
                >
                  <option value="">-- 計画書を選択してください --</option>
                  {studentPlans.map((p) => {
                    const plan = p as PlanOption & { is_official?: boolean; is_draft?: boolean; status?: string };
                    const label = plan.is_official ? '(提出済)' : plan.status === 'draft' || plan.is_draft ? '(下書き)' : '';
                    return (
                      <option key={p.id} value={p.id}>
                        {format(new Date(p.created_date), 'yyyy年MM月dd日')} 作成 {label}
                      </option>
                    );
                  })}
                </select>
              </div>
            )}
          </div>
        </CardBody>
      </Card>

      {selectedPlanId && planData ? (
        <>
          {/* Existing Monitorings List */}
          {existingMonitorings.length > 0 && (
            <div className="flex flex-wrap items-center gap-2">
              <span className="text-sm font-semibold text-[var(--neutral-foreground-2)]">既存のモニタリング:</span>
              {existingMonitorings.map((m) => (
                <div key={m.id} className="inline-flex items-center gap-1">
                  <button
                    onClick={() => setSelectedMonitoringId(m.id)}
                    className={`px-3 py-1.5 text-sm transition-colors ${
                      m.id === selectedMonitoringId
                        ? 'bg-[var(--brand-80)] text-white'
                        : 'bg-orange-100 text-orange-700 hover:bg-orange-500 hover:text-white dark:bg-orange-900/30 dark:text-orange-400'
                    }`}
                  >
                    {format(new Date(m.monitoring_date), 'yyyy/MM/dd')}
                    {m.is_draft && ' (下書き)'}
                  </button>
                  <button
                    onClick={() => { if (confirm('このモニタリング表を削除してもよろしいですか？')) deleteMutation.mutate(m.id); }}
                    className="rounded bg-red-500 p-1 text-white hover:bg-red-600"
                    title="削除"
                  >
                    <MaterialIcon name="delete" size={14} />
                  </button>
                  <button
                    onClick={() => handlePdfDownload(m.id)}
                    className="rounded bg-[var(--brand-80)] p-1 text-white hover:bg-[var(--brand-80)]"
                    title="PDF出力"
                  >
                    <MaterialIcon name="download" size={14} />
                  </button>
                </div>
              ))}
              <button
                onClick={handleNewMonitoring}
                className="px-3 py-1.5 text-sm bg-orange-100 text-orange-700 hover:bg-orange-500 hover:text-white dark:bg-orange-900/30 dark:text-orange-400"
              >
                + 新規作成
              </button>
            </div>
          )}

          {/* Plan Info */}
          <Card className="border-l-4 border-l-blue-500">
            <CardBody>
              <h3 className="mb-3 font-semibold text-[var(--brand-80)]">対象の個別支援計画書</h3>
              <div className="grid grid-cols-2 gap-4 md:grid-cols-4">
                <div>
                  <p className="text-xs font-semibold text-[var(--brand-80)]">生徒氏名</p>
                  <p className="text-sm text-[var(--neutral-foreground-1)]">{planData.student_name}</p>
                </div>
                <div>
                  <p className="text-xs font-semibold text-[var(--brand-80)]">作成年月日</p>
                  <p className="text-sm text-[var(--neutral-foreground-1)]">{format(new Date(planData.created_date), 'yyyy年MM月dd日')}</p>
                </div>
                <div>
                  <p className="text-xs font-semibold text-[var(--brand-80)]">長期目標達成時期</p>
                  <p className="text-sm text-[var(--neutral-foreground-1)]">
                    {planData.long_term_goal_date ? format(new Date(planData.long_term_goal_date), 'yyyy年MM月dd日') : '未設定'}
                  </p>
                </div>
                <div>
                  <p className="text-xs font-semibold text-[var(--brand-80)]">短期目標達成時期</p>
                  <p className="text-sm text-[var(--neutral-foreground-1)]">
                    {planData.short_term_goal_date ? format(new Date(planData.short_term_goal_date), 'yyyy年MM月dd日') : '未設定'}
                  </p>
                </div>
              </div>
            </CardBody>
          </Card>

          {/* Read-only banner */}
          {isReadOnly && (
            <div className="flex items-center gap-2 rounded-lg border border-green-300 bg-green-50 px-4 py-3 text-sm text-green-800 dark:border-green-700 dark:bg-green-900/20 dark:text-green-300">
              <MaterialIcon name="check_circle" size={20} />
              <span>このモニタリング表は提出済みのため、閲覧のみ可能です。</span>
            </div>
          )}

          {/* Monitoring Date */}
          <Card>
            <CardBody>
              <div className="max-w-xs">
                <Input
                  label="モニタリング実施日 *"
                  type="date"
                  value={monitoringDate}
                  onChange={(e) => setMonitoringDate(e.target.value)}
                  required
                  disabled={isReadOnly}
                />
              </div>
            </CardBody>
          </Card>

          {/* Support Goals Achievement Table */}
          <div>
            <div className="mb-4 flex flex-wrap items-center justify-between gap-3 border-b-2 border-blue-500 pb-2">
              <h2 className="text-lg font-semibold text-[var(--brand-80)]">支援目標の達成状況</h2>
              {!isReadOnly && (
                <Button
                  variant="primary"
                  size="sm"
                  leftIcon={<MaterialIcon name="auto_awesome" size={16} />}
                  onClick={handleAIGenerateAll}
                  isLoading={generating}
                  className="bg-purple-600 hover:bg-purple-700"
                >
                  AIで評価を自動生成
                </Button>
              )}
            </div>

            {/* Progress bar */}
            {generateProgress.visible && (
              <div className="mb-4 rounded-lg bg-[var(--neutral-background-2)] p-4">
                <div className="mb-2 h-2 w-full overflow-hidden rounded-full bg-[var(--neutral-stroke-2)]">
                  <div
                    className="h-full rounded-full bg-purple-600 transition-all duration-300"
                    style={{ width: `${generateProgress.percent}%` }}
                  />
                </div>
                <p className="text-center text-xs text-[var(--neutral-foreground-3)]">{generateProgress.text}</p>
              </div>
            )}

            {/* Table */}
            <div className="overflow-x-auto">
              <table className="w-full border-collapse shadow">
                <thead>
                  <tr>
                    <th className="border border-blue-500 bg-[var(--brand-80)] px-2 py-2 text-left text-xs font-semibold text-white" style={{ width: '100px' }}>項目</th>
                    <th className="border border-blue-500 bg-[var(--brand-80)] px-2 py-2 text-left text-xs font-semibold text-white" style={{ width: '196px' }}>支援目標</th>
                    <th className="border border-blue-500 bg-[var(--brand-80)] px-2 py-2 text-left text-xs font-semibold text-white" style={{ width: '245px' }}>支援内容</th>
                    <th className="border border-blue-500 bg-[var(--brand-80)] px-2 py-2 text-left text-xs font-semibold text-white" style={{ width: '64px' }}>達成時期</th>
                    <th className="border border-blue-500 bg-[var(--brand-80)] px-2 py-2 text-left text-xs font-semibold text-white" style={{ width: '95px' }}>達成状況</th>
                    <th className="border border-blue-500 bg-[var(--brand-80)] px-2 py-2 text-left text-xs font-semibold text-white" style={{ width: '300px' }}>モニタリングコメント</th>
                  </tr>
                </thead>
                <tbody>
                  {planDetails.map((detail) => (
                    <tr key={detail.id}>
                      <td className="border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-1)] p-2 align-top">
                        <div className="rounded bg-[var(--neutral-background-2)] p-1.5 text-xs text-[var(--neutral-foreground-3)]">
                          {detail.category}
                          {detail.sub_category && <><br />{detail.sub_category}</>}
                        </div>
                      </td>
                      <td className="border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-1)] p-2 align-top">
                        <div className="whitespace-pre-wrap rounded bg-[var(--neutral-background-2)] p-1.5 text-xs text-[var(--neutral-foreground-3)]">
                          {nl(detail.support_goal) || '（未設定）'}
                        </div>
                      </td>
                      <td className="border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-1)] p-2 align-top">
                        <div className="whitespace-pre-wrap rounded bg-[var(--neutral-background-2)] p-1.5 text-xs text-[var(--neutral-foreground-3)]">
                          {nl(detail.support_content) || '（未設定）'}
                        </div>
                      </td>
                      <td className="border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-1)] p-2 align-top">
                        <div className="rounded bg-[var(--neutral-background-2)] p-1.5 text-xs text-[var(--neutral-foreground-3)]">
                          {detail.achievement_date ? format(new Date(detail.achievement_date), 'yyyy/MM/dd') : '（未設定）'}
                        </div>
                      </td>
                      <td className="border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-1)] p-2 align-top">
                        <select
                          className="w-full rounded border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-1)] px-1.5 py-1.5 text-xs text-[var(--neutral-foreground-1)]"
                          value={detailsMap[detail.id]?.achievement_level ?? ''}
                          disabled={isReadOnly}
                          onChange={(e) =>
                            setDetailsMap((prev) => ({
                              ...prev,
                              [detail.id]: { ...prev[detail.id], achievement_level: e.target.value, comment: prev[detail.id]?.comment ?? '' },
                            }))
                          }
                        >
                          {ACHIEVEMENT_OPTIONS.map((opt) => (
                            <option key={opt} value={opt}>{opt || '-- 選択 --'}</option>
                          ))}
                        </select>
                      </td>
                      <td className="border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-1)] p-2 align-top">
                        <textarea
                          className="w-full resize-y rounded border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-1)] px-1.5 py-1 text-xs text-[var(--neutral-foreground-1)]"
                          rows={3}
                          readOnly={isReadOnly}
                          value={detailsMap[detail.id]?.comment ?? ''}
                          onChange={(e) =>
                            setDetailsMap((prev) => ({
                              ...prev,
                              [detail.id]: { ...prev[detail.id], comment: e.target.value, achievement_level: prev[detail.id]?.achievement_level ?? '' },
                            }))
                          }
                        />
                        {!isReadOnly && (
                          <button
                            type="button"
                            onClick={() => handleAIGenerateSingle(detail.id)}
                            disabled={generatingDetailId === detail.id}
                            className="mt-1 w-full rounded border border-blue-500 bg-[var(--neutral-background-1)] px-2 py-1 text-[10px] text-[var(--brand-80)] hover:bg-[var(--brand-80)] hover:text-white disabled:cursor-not-allowed disabled:border-gray-400 disabled:bg-gray-400 disabled:text-white"
                          >
                            {generatingDetailId === detail.id ? 'AI生成中...' : 'AI生成'}
                          </button>
                        )}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </div>

          {/* Goal Achievement Section */}
          <div>
            <h2 className="mb-4 border-b-2 border-blue-500 pb-2 text-lg font-semibold text-[var(--brand-80)]">
              目標の達成状況
            </h2>

            {/* Long-term goal */}
            <div className="mb-6 rounded-lg border-l-4 border-l-purple-500 bg-[var(--neutral-background-2)] p-4">
              <h4 className="mb-3 flex items-center gap-2 text-sm font-semibold text-[var(--brand-70)]">
                <MaterialIcon name="target" size={16} className="h-4 w-4" /> 長期目標
              </h4>
              {(planData.long_term_goal_text || planData.long_term_goal) ? (
                <div className="mb-3 whitespace-pre-wrap rounded-md bg-[var(--neutral-background-1)] p-3 text-sm leading-relaxed text-[var(--neutral-foreground-1)]">
                  {nl(planData.long_term_goal_text || planData.long_term_goal)}
                </div>
              ) : (
                <div className="mb-3 rounded-md bg-[var(--neutral-background-1)] p-3 text-sm italic text-[var(--neutral-foreground-3)]">
                  長期目標が設定されていません
                </div>
              )}
              <div className="mb-3">
                <label className="mb-1 block text-sm font-semibold text-[var(--neutral-foreground-1)]">達成状況</label>
                <select
                  className="w-full rounded border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-1)] px-3 py-2 text-sm text-[var(--neutral-foreground-1)]"
                  value={longTermGoalAchievement}
                  disabled={isReadOnly}
                  onChange={(e) => setLongTermGoalAchievement(e.target.value)}
                >
                  {ACHIEVEMENT_OPTIONS.map((opt) => (
                    <option key={opt} value={opt}>{opt || '-- 選択してください --'}</option>
                  ))}
                </select>
              </div>
              <div>
                <label className="mb-1 block text-sm font-semibold text-[var(--neutral-foreground-1)]">コメント</label>
                <textarea
                  className="w-full resize-y rounded border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-1)] px-3 py-2 text-sm text-[var(--neutral-foreground-1)]"
                  rows={4}
                  placeholder="長期目標に対する振り返りや意見を記入してください"
                  readOnly={isReadOnly}
                  value={longTermGoalComment}
                  onChange={(e) => setLongTermGoalComment(e.target.value)}
                />
              </div>
            </div>

            {/* Short-term goal */}
            <div className="mb-6 rounded-lg border-l-4 border-l-green-500 bg-[var(--neutral-background-2)] p-4">
              <h4 className="mb-3 flex items-center gap-2 text-sm font-semibold text-green-600">
                <MaterialIcon name="push_pin" size={16} /> 短期目標
              </h4>
              {(planData.short_term_goal_text || planData.short_term_goal) ? (
                <div className="mb-3 whitespace-pre-wrap rounded-md bg-[var(--neutral-background-1)] p-3 text-sm leading-relaxed text-[var(--neutral-foreground-1)]">
                  {nl(planData.short_term_goal_text || planData.short_term_goal)}
                </div>
              ) : (
                <div className="mb-3 rounded-md bg-[var(--neutral-background-1)] p-3 text-sm italic text-[var(--neutral-foreground-3)]">
                  短期目標が設定されていません
                </div>
              )}
              <div className="mb-3">
                <label className="mb-1 block text-sm font-semibold text-[var(--neutral-foreground-1)]">達成状況</label>
                <select
                  className="w-full rounded border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-1)] px-3 py-2 text-sm text-[var(--neutral-foreground-1)]"
                  value={shortTermGoalAchievement}
                  disabled={isReadOnly}
                  onChange={(e) => setShortTermGoalAchievement(e.target.value)}
                >
                  {ACHIEVEMENT_OPTIONS.map((opt) => (
                    <option key={opt} value={opt}>{opt || '-- 選択してください --'}</option>
                  ))}
                </select>
              </div>
              <div>
                <label className="mb-1 block text-sm font-semibold text-[var(--neutral-foreground-1)]">コメント</label>
                <textarea
                  className="w-full resize-y rounded border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-1)] px-3 py-2 text-sm text-[var(--neutral-foreground-1)]"
                  rows={4}
                  placeholder="短期目標に対する振り返りや意見を記入してください"
                  readOnly={isReadOnly}
                  value={shortTermGoalComment}
                  onChange={(e) => setShortTermGoalComment(e.target.value)}
                />
              </div>
            </div>
          </div>

          {/* Overall Comment */}
          <div>
            <h2 className="mb-4 border-b-2 border-blue-500 pb-2 text-lg font-semibold text-[var(--brand-80)]">総合所見</h2>
            <textarea
              className="w-full resize-y rounded-lg border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-1)] px-3 py-2 text-sm text-[var(--neutral-foreground-1)]"
              rows={6}
              readOnly={isReadOnly}
              value={overallComment}
              onChange={(e) => setOverallComment(e.target.value)}
            />
          </div>

          {/* Signature Section */}
          <div>
            <h2 className="mb-4 border-b-2 border-blue-500 pb-2 text-lg font-semibold text-[var(--brand-80)]">電子署名</h2>
            <Card>
              <CardBody>
                <div className="grid gap-8 md:grid-cols-2">
                  {/* Staff Signature */}
                  <div>
                    <p className="mb-2 text-sm font-semibold text-[var(--brand-80)]">職員署名</p>
                    <SignaturePad
                      ref={staffSigRef}
                      label=""
                      width={400}
                      height={120}
                      initialValue={monitoringData?.staff_signature ?? undefined}
                    />
                    <div className="mt-2 flex items-center gap-3">
                      <Button
                        variant="secondary"
                        size="sm"
                        onClick={() => staffSigRef.current?.clear()}
                      >
                        クリア
                      </Button>
                      <div className="flex-1">
                        <label className="mb-1 block text-xs text-[var(--neutral-foreground-3)]">署名日</label>
                        <input
                          type="date"
                          className="w-full rounded border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-1)] px-2 py-1 text-sm text-[var(--neutral-foreground-1)]"
                          value={staffSignatureDate}
                          onChange={(e) => setStaffSignatureDate(e.target.value)}
                        />
                      </div>
                    </div>
                    {monitoringData?.staff_signature && (
                      <div className="mt-3 rounded-lg bg-[var(--neutral-background-2)] p-3">
                        <p className="mb-1 text-xs text-[var(--neutral-foreground-3)]">
                          保存済みの署名（{monitoringData.staff_signer_name ?? ''}）:
                        </p>
                        <img
                          src={monitoringData.staff_signature}
                          alt="職員署名"
                          className="max-h-20 rounded border border-[var(--neutral-stroke-2)] bg-white"
                        />
                      </div>
                    )}
                  </div>

                  {/* Guardian Signature (display only) */}
                  <div>
                    <p className="mb-2 text-sm font-semibold text-[var(--brand-80)]">保護者署名</p>
                    {monitoringData?.guardian_signature ? (
                      <div className="rounded-lg bg-[var(--neutral-background-2)] p-3">
                        <p className="mb-1 text-xs text-[var(--neutral-foreground-3)]">
                          保護者署名日: {monitoringData.guardian_signature_date
                            ? format(new Date(monitoringData.guardian_signature_date), 'yyyy年MM月dd日')
                            : '未署名'}
                        </p>
                        <img
                          src={monitoringData.guardian_signature}
                          alt="保護者署名"
                          className="max-h-20 rounded border border-[var(--neutral-stroke-2)] bg-white"
                        />
                      </div>
                    ) : (
                      <div className="flex flex-col items-center justify-center rounded-lg bg-[var(--neutral-background-2)] p-6 text-center">
                        <MaterialIcon name="draw" size={32} className="mb-2 text-[var(--neutral-foreground-3)] opacity-50" />
                        <p className="text-sm text-[var(--neutral-foreground-3)]">保護者からの署名待ち</p>
                        <p className="mt-1 text-xs text-[var(--neutral-foreground-3)]">
                          モニタリング表提出後、保護者画面から署名できます
                        </p>
                      </div>
                    )}
                  </div>
                </div>
              </CardBody>
            </Card>
          </div>

          {/* Action Buttons */}
          <div className="flex flex-wrap justify-end gap-3">
            {!isReadOnly && (
              <>
                <Button
                  variant="secondary"
                  onClick={() => saveMutation.mutate(true)}
                  isLoading={saveMutation.isPending}
                  leftIcon={<MaterialIcon name="description" size={16} />}
                >
                  下書き保存（保護者非公開）
                </Button>
                <Button
                  variant="primary"
                  onClick={() => saveMutation.mutate(false)}
                  isLoading={saveMutation.isPending}
                  leftIcon={<MaterialIcon name="assignment_turned_in" size={16} className="h-4 w-4" />}
                  className="bg-green-600 hover:bg-green-700"
                >
                  作成・提出（保護者公開）
                </Button>
              </>
            )}
            {selectedMonitoringId && (
              <Button
                variant="primary"
                onClick={() => handlePdfDownload(selectedMonitoringId)}
                leftIcon={<MaterialIcon name="download" size={16} />}
              >
                PDF出力
              </Button>
            )}
          </div>
        </>
      ) : (
        /* No plan selected message */
        selectedStudentId ? (
          <Card>
            <CardBody>
              <p className="py-8 text-center text-sm text-[var(--neutral-foreground-3)]">
                {selectedStudent?.support_plan_start_type === 'next'
                  ? 'この生徒は「次回の期間から個別支援計画を作成する」設定になっています。現在は連絡帳のみ利用可能です。'
                  : studentPlans.length === 0
                    ? 'この生徒にはまだモニタリング対象の個別支援計画書がありません。個別支援計画書を作成してから5ヶ月後にモニタリングが可能になります。'
                    : '個別支援計画書を選択してください。'}
              </p>
            </CardBody>
          </Card>
        ) : (
          <Card>
            <CardBody>
              <p className="py-8 text-center text-sm text-[var(--neutral-foreground-3)]">
                生徒を選択し、個別支援計画書を選択してください。
              </p>
            </CardBody>
          </Card>
        )
      )}
    </div>
  );
}
