'use client';

import { useState, useRef } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import api from '@/lib/api';
import { Card, CardHeader, CardTitle } from '@/components/ui/Card';
import { Button } from '@/components/ui/Button';
import { Badge } from '@/components/ui/Badge';
import { Modal } from '@/components/ui/Modal';
import { SkeletonList } from '@/components/ui/Skeleton';
import { useToast } from '@/components/ui/Toast';
import { SignaturePad, type SignaturePadRef } from '@/components/ui/SignaturePad';
import { format } from 'date-fns';
import { ja } from 'date-fns/locale';
import { ClipboardList, CheckCircle, ChevronDown, ChevronUp, PenLine } from 'lucide-react';

interface MonitoringRecord {
  id: number;
  student_name: string;
  period_start: string;
  period_end: string;
  staff_name: string;
  status: 'draft' | 'pending_confirmation' | 'confirmed';
  goals: MonitoringGoal[];
  overall_comment: string;
  guardian_confirmed_at: string | null;
  guardian_signature: string | null;
  guardian_signature_image: string | null;
  guardian_signature_date: string | null;
  staff_signature_image: string | null;
  staff_signer_name: string | null;
  created_at: string;
}

interface MonitoringGoal {
  id: number;
  goal_text: string;
  achievement_level: string;
  staff_comment: string;
}

interface StudentOption {
  id: number;
  student_name: string;
}

const achievementLabels: Record<string, { text: string; variant: 'success' | 'warning' | 'default' | 'danger' }> = {
  achieved: { text: '達成', variant: 'success' },
  mostly_achieved: { text: 'ほぼ達成', variant: 'success' },
  in_progress: { text: '取組中', variant: 'warning' },
  not_started: { text: '未着手', variant: 'default' },
  needs_revision: { text: '見直し要', variant: 'danger' },
};

export default function GuardianMonitoringPage() {
  const queryClient = useQueryClient();
  const toast = useToast();
  const [selectedStudent, setSelectedStudent] = useState('');
  const [expandedId, setExpandedId] = useState<number | null>(null);
  const [confirmModal, setConfirmModal] = useState(false);
  const [confirmingRecord, setConfirmingRecord] = useState<MonitoringRecord | null>(null);
  const guardianSigRef = useRef<SignaturePadRef>(null);

  const { data: students = [] } = useQuery({
    queryKey: ['guardian', 'students'],
    queryFn: async () => {
      const res = await api.get<{ data: StudentOption[] }>('/api/guardian/students');
      return res.data.data;
    },
  });

  const studentId = selectedStudent || students[0]?.id?.toString() || '';

  const { data: records = [], isLoading } = useQuery({
    queryKey: ['guardian', 'monitoring', studentId],
    queryFn: async () => {
      const res = await api.get<{ data: MonitoringRecord[] }>(`/api/guardian/students/${studentId}/monitoring`);
      return res.data.data;
    },
    enabled: !!studentId,
  });

  const confirmMutation = useMutation({
    mutationFn: async ({ id, guardian_signature_image }: { id: number; guardian_signature_image?: string }) => {
      return api.post(`/api/guardian/monitoring/${id}/confirm`, { guardian_signature_image });
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['guardian', 'monitoring'] });
      toast.success('確認しました');
      setConfirmModal(false);
      setConfirmingRecord(null);
    },
    onError: () => toast.error('確認に失敗しました'),
  });

  const openConfirm = (record: MonitoringRecord) => {
    setConfirmingRecord(record);
    setConfirmModal(true);
  };

  const handleConfirm = () => {
    if (!confirmingRecord) return;
    const payload: { id: number; guardian_signature_image?: string } = { id: confirmingRecord.id };
    if (guardianSigRef.current && !guardianSigRef.current.isEmpty()) {
      payload.guardian_signature_image = guardianSigRef.current.toDataURL();
    }
    confirmMutation.mutate(payload);
  };

  const statusConfig: Record<string, { text: string; variant: 'default' | 'warning' | 'success' }> = {
    draft: { text: '作成中', variant: 'default' },
    pending_confirmation: { text: '確認待ち', variant: 'warning' },
    confirmed: { text: '確認済み', variant: 'success' },
  };

  return (
    <div className="space-y-6">
      <h1 className="text-2xl font-bold text-gray-900">モニタリング記録</h1>

      {/* Student selector */}
      {students.length > 1 && (
        <select
          value={selectedStudent || students[0]?.id}
          onChange={(e) => setSelectedStudent(e.target.value)}
          className="rounded-lg border border-gray-300 px-3 py-2 text-sm"
        >
          {students.map((s) => (
            <option key={s.id} value={s.id}>{s.student_name}</option>
          ))}
        </select>
      )}

      {/* Pending confirmation banner */}
      {records.some((r) => r.status === 'pending_confirmation') && (
        <div className="rounded-lg bg-yellow-50 border border-yellow-200 p-3 text-sm text-yellow-700">
          確認待ちのモニタリング記録があります。内容をご確認のうえ、確認ボタンを押してください。
        </div>
      )}

      {isLoading ? (
        <SkeletonList items={3} />
      ) : records.length === 0 ? (
        <Card>
          <div className="py-12 text-center">
            <ClipboardList className="mx-auto h-12 w-12 text-gray-300" />
            <p className="mt-2 text-sm text-gray-500">モニタリング記録はありません</p>
          </div>
        </Card>
      ) : (
        <div className="space-y-4">
          {records.map((record) => {
            const isExpanded = expandedId === record.id;
            const config = statusConfig[record.status] || statusConfig.draft;
            return (
              <Card key={record.id}>
                <button
                  onClick={() => setExpandedId(isExpanded ? null : record.id)}
                  className="flex w-full items-center justify-between text-left"
                >
                  <div className="flex items-center gap-3">
                    <div className={`flex h-10 w-10 items-center justify-center rounded-full ${record.status === 'confirmed' ? 'bg-green-100' : 'bg-blue-100'}`}>
                      {record.status === 'confirmed' ? (
                        <CheckCircle className="h-5 w-5 text-green-600" />
                      ) : (
                        <ClipboardList className="h-5 w-5 text-blue-600" />
                      )}
                    </div>
                    <div>
                      <p className="font-medium text-gray-900">
                        {format(new Date(record.period_start), 'yyyy年M月d日', { locale: ja })} - {format(new Date(record.period_end), 'M月d日', { locale: ja })}
                      </p>
                      <div className="flex items-center gap-2 mt-0.5">
                        <Badge variant={config.variant}>{config.text}</Badge>
                        <span className="text-xs text-gray-500">担当: {record.staff_name}</span>
                      </div>
                    </div>
                  </div>
                  {isExpanded ? <ChevronUp className="h-5 w-5 text-gray-400" /> : <ChevronDown className="h-5 w-5 text-gray-400" />}
                </button>

                {isExpanded && (
                  <div className="mt-4 space-y-4 border-t border-gray-200 pt-4">
                    {/* Goals */}
                    <div className="space-y-3">
                      {record.goals.map((goal) => {
                        const aConfig = achievementLabels[goal.achievement_level] || achievementLabels.not_started;
                        return (
                          <div key={goal.id} className="rounded-lg border border-gray-200 p-3">
                            <div className="flex items-start justify-between gap-2">
                              <p className="text-sm font-medium text-gray-900">{goal.goal_text}</p>
                              <Badge variant={aConfig.variant} className="shrink-0">{aConfig.text}</Badge>
                            </div>
                            {goal.staff_comment && (
                              <p className="mt-2 text-sm text-gray-600">{goal.staff_comment}</p>
                            )}
                          </div>
                        );
                      })}
                    </div>

                    {/* Overall comment */}
                    {record.overall_comment && (
                      <div className="rounded-lg bg-blue-50 p-3">
                        <p className="text-xs font-medium text-blue-600 mb-1">総合コメント</p>
                        <p className="text-sm text-gray-700 whitespace-pre-wrap">{record.overall_comment}</p>
                      </div>
                    )}

                    {/* Confirmation */}
                    {record.status === 'confirmed' && record.guardian_confirmed_at && (
                      <div className="space-y-3">
                        <div className="rounded-lg bg-green-50 p-3 flex items-center gap-2">
                          <CheckCircle className="h-4 w-4 text-green-600" />
                          <span className="text-sm text-green-700">
                            {format(new Date(record.guardian_confirmed_at), 'yyyy年M月d日 HH:mm', { locale: ja })} に確認済み
                          </span>
                        </div>
                        {/* Signature images */}
                        {(record.staff_signature_image || record.guardian_signature_image) && (
                          <div className="flex flex-wrap gap-4">
                            {record.staff_signature_image && (
                              <SignaturePad
                                readOnly
                                initialValue={record.staff_signature_image}
                                label={`職員署名${record.staff_signer_name ? ` (${record.staff_signer_name})` : ''}`}
                                width={180}
                                height={70}
                              />
                            )}
                            {record.guardian_signature_image && (
                              <SignaturePad
                                readOnly
                                initialValue={record.guardian_signature_image}
                                label={`保護者署名${record.guardian_signature_date ? ` - ${record.guardian_signature_date}` : ''}`}
                                width={180}
                                height={70}
                              />
                            )}
                          </div>
                        )}
                      </div>
                    )}

                    {record.status === 'pending_confirmation' && (
                      <div className="flex justify-end">
                        <Button onClick={() => openConfirm(record)} leftIcon={<PenLine className="h-4 w-4" />}>
                          内容を確認する
                        </Button>
                      </div>
                    )}
                  </div>
                )}
              </Card>
            );
          })}
        </div>
      )}

      {/* Confirm Modal */}
      <Modal isOpen={confirmModal} onClose={() => setConfirmModal(false)} title="モニタリング記録の確認" size="lg">
        <div className="space-y-5">
          <p className="text-sm text-gray-600">
            モニタリング記録の内容を確認しました。署名を記入して確認ボタンを押してください。
          </p>

          <SignaturePad
            ref={guardianSigRef}
            label="保護者署名"
            width={400}
            height={150}
          />

          <div className="flex justify-end gap-2 border-t border-gray-200 pt-4">
            <Button variant="secondary" onClick={() => setConfirmModal(false)}>キャンセル</Button>
            <Button
              onClick={handleConfirm}
              isLoading={confirmMutation.isPending}
              leftIcon={<CheckCircle className="h-4 w-4" />}
            >
              内容を確認しました
            </Button>
          </div>
        </div>
      </Modal>
    </div>
  );
}
