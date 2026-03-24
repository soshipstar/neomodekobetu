'use client';

import { useState, useRef } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import api from '@/lib/api';
import { Card, CardBody } from '@/components/ui/Card';
import { Button } from '@/components/ui/Button';
import { Badge } from '@/components/ui/Badge';
import { Modal } from '@/components/ui/Modal';
import { SkeletonList } from '@/components/ui/Skeleton';
import { useToast } from '@/components/ui/Toast';
import { SignaturePad, type SignaturePadRef } from '@/components/ui/SignaturePad';
import { formatDate, nl } from '@/lib/utils';
import { format } from 'date-fns';
import { ja } from 'date-fns/locale';
import { MaterialIcon } from '@/components/ui/MaterialIcon';

interface MonitoringDetail {
  id: number;
  monitoring_id: number;
  plan_detail_id: number;
  achievement_status: string | null;
  monitoring_comment: string | null;
}

interface MonitoringRecord {
  id: number;
  plan_id: number;
  student_id: number;
  student_name: string;
  monitoring_date: string;
  overall_comment: string | null;
  short_term_goal_achievement: string | null;
  long_term_goal_achievement: string | null;
  short_term_goal_comment: string | null;
  long_term_goal_comment: string | null;
  is_official: boolean;
  is_draft: boolean;
  guardian_confirmed: boolean;
  guardian_confirmed_at: string | null;
  staff_signature: string | null;
  guardian_signature: string | null;
  guardian_signature_date: string | null;
  staff_signature_date: string | null;
  staff_signer_name: string | null;
  details: MonitoringDetail[];
  student?: { id: number; student_name: string };
  plan?: {
    id: number;
    created_date: string;
    long_term_goal_text: string | null;
    short_term_goal_text: string | null;
    details?: {
      id: number;
      main_category: string | null;
      sub_category: string | null;
      support_goal: string | null;
      support_content: string | null;
      achievement_date: string | null;
      row_order: number;
    }[];
  };
  created_at: string;
}

interface StudentOption {
  id: number;
  student_name: string;
}

const achievementBadge = (status: string | null) => {
  if (!status) return null;
  switch (status) {
    case '達成':
      return <Badge variant="success">達成</Badge>;
    case '一部達成':
      return <Badge variant="warning">一部達成</Badge>;
    case '未達成':
      return <Badge variant="danger">未達成</Badge>;
    default:
      return <Badge variant="default">{status}</Badge>;
  }
};

export default function GuardianMonitoringPage() {
  const queryClient = useQueryClient();
  const toast = useToast();
  const [selectedStudent, setSelectedStudent] = useState('');
  const [expandedId, setExpandedId] = useState<number | null>(null);
  const [confirmModal, setConfirmModal] = useState(false);
  const [confirmingRecord, setConfirmingRecord] = useState<MonitoringRecord | null>(null);
  const [signatureDate, setSignatureDate] = useState(format(new Date(), 'yyyy-MM-dd'));
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
      // Try student-specific route first, fallback to general
      try {
        const res = await api.get<{ data: MonitoringRecord[] }>(`/api/guardian/students/${studentId}/monitoring`);
        return res.data.data;
      } catch {
        const res = await api.get<{ data: MonitoringRecord[] }>('/api/guardian/monitoring');
        return res.data.data;
      }
    },
    enabled: !!studentId,
  });

  const confirmMutation = useMutation({
    mutationFn: async ({ id, guardian_signature_image, guardian_signature_date }: { id: number; guardian_signature_image?: string; guardian_signature_date?: string }) => {
      return api.post(`/api/guardian/monitoring/${id}/confirm`, { guardian_signature_image, guardian_signature_date });
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['guardian', 'monitoring'] });
      toast.success('署名が完了しました');
      setConfirmModal(false);
      setConfirmingRecord(null);
    },
    onError: () => toast.error('確認に失敗しました'),
  });

  const openConfirm = (record: MonitoringRecord) => {
    setConfirmingRecord(record);
    setSignatureDate(format(new Date(), 'yyyy-MM-dd'));
    setConfirmModal(true);
  };

  const handleConfirm = () => {
    if (!confirmingRecord) return;
    if (!guardianSigRef.current || guardianSigRef.current.isEmpty()) {
      toast.error('署名を入力してください');
      return;
    }
    confirmMutation.mutate({
      id: confirmingRecord.id,
      guardian_signature_image: guardianSigRef.current.toDataURL(),
      guardian_signature_date: signatureDate,
    });
  };

  // Build a lookup map from plan_detail_id to monitoring detail
  const getDetailMap = (record: MonitoringRecord) => {
    const map: Record<number, MonitoringDetail> = {};
    for (const d of record.details ?? []) {
      map[d.plan_detail_id] = d;
    }
    return map;
  };

  return (
    <div className="space-y-6">
      <h1 className="text-2xl font-bold text-[var(--neutral-foreground-1)]">モニタリング表</h1>

      {/* Student selector */}
      {students.length > 1 && (
        <select
          value={selectedStudent || students[0]?.id}
          onChange={(e) => setSelectedStudent(e.target.value)}
          className="w-full rounded-lg border border-[var(--neutral-stroke-1)] bg-white px-3 py-2 text-sm shadow-sm"
        >
          {students.map((s) => (
            <option key={s.id} value={s.id}>{s.student_name}</option>
          ))}
        </select>
      )}

      {/* Pending confirmation banner */}
      {records.some((r) => !r.guardian_confirmed && !r.is_draft) && (
        <div className="rounded-lg bg-yellow-50 border border-yellow-200 p-3 text-sm text-yellow-700">
          確認待ちのモニタリング表があります。内容をご確認のうえ、署名をお願いします。
        </div>
      )}

      {isLoading ? (
        <SkeletonList items={3} />
      ) : records.length === 0 ? (
        <Card>
          <div className="py-12 text-center">
            <MaterialIcon name="checklist" size={48} className="mx-auto text-[var(--neutral-foreground-disabled)]" />
            <p className="mt-2 text-sm text-[var(--neutral-foreground-3)]">提出済みのモニタリング表はまだありません</p>
            <p className="text-xs text-[var(--neutral-foreground-4)]">スタッフがモニタリング表を作成・提出すると、ここに表示されます。</p>
          </div>
        </Card>
      ) : (
        <div className="space-y-4">
          {records.map((record) => {
            const isExpanded = expandedId === record.id;
            const detailMap = getDetailMap(record);
            const planDetails = record.plan?.details ?? [];

            return (
              <Card key={record.id}>
                <button
                  onClick={() => setExpandedId(isExpanded ? null : record.id)}
                  className="flex w-full items-center justify-between text-left"
                >
                  <div className="flex items-center gap-3">
                    <div className={`flex h-10 w-10 items-center justify-center rounded-full ${record.guardian_confirmed ? 'bg-green-100' : 'bg-[var(--brand-160)]'}`}>
                      {record.guardian_confirmed ? (
                        <MaterialIcon name="check_circle" size={20} className="text-green-600" />
                      ) : (
                        <MaterialIcon name="checklist" size={20} className="text-[var(--brand-80)]" />
                      )}
                    </div>
                    <div>
                      <p className="font-medium text-[var(--neutral-foreground-1)]">
                        モニタリング（{formatDate(record.monitoring_date)}実施）
                      </p>
                      <div className="flex items-center gap-2 mt-0.5">
                        {record.guardian_confirmed ? (
                          <Badge variant="success">確認済み</Badge>
                        ) : (
                          <Badge variant="warning">確認待ち</Badge>
                        )}
                        {record.plan && (
                          <span className="text-xs text-[var(--neutral-foreground-3)]">
                            対象計画: {formatDate(record.plan.created_date)}作成
                          </span>
                        )}
                      </div>
                    </div>
                  </div>
                  {isExpanded ? <MaterialIcon name="expand_less" size={20} className="text-[var(--neutral-foreground-4)]" /> : <MaterialIcon name="expand_more" size={20} className="text-[var(--neutral-foreground-4)]" />}
                </button>

                {isExpanded && (
                  <div className="mt-4 space-y-4 border-t border-[var(--neutral-stroke-2)] pt-4">
                    {/* Basic info */}
                    <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
                      <div className="rounded-lg bg-[var(--neutral-background-3)] p-3">
                        <p className="text-xs font-medium text-[var(--neutral-foreground-3)]">お子様のお名前</p>
                        <p className="text-sm font-medium text-[var(--neutral-foreground-1)]">{record.student_name || record.student?.student_name}</p>
                      </div>
                      <div className="rounded-lg bg-[var(--neutral-background-3)] p-3">
                        <p className="text-xs font-medium text-[var(--neutral-foreground-3)]">実施日</p>
                        <p className="text-sm text-[var(--neutral-foreground-1)]">{formatDate(record.monitoring_date)}</p>
                      </div>
                      {record.plan && (
                        <div className="rounded-lg bg-[var(--neutral-background-3)] p-3">
                          <p className="text-xs font-medium text-[var(--neutral-foreground-3)]">対象計画書</p>
                          <p className="text-sm text-[var(--neutral-foreground-1)]">{formatDate(record.plan.created_date)}作成</p>
                        </div>
                      )}
                    </div>

                    {/* Plan details with achievement status */}
                    {planDetails.length > 0 && (
                      <div>
                        <h4 className="mb-2 text-sm font-semibold text-green-700 border-b-2 border-green-600 pb-1">
                          支援目標の達成状況
                        </h4>
                        <div className="overflow-x-auto">
                          <table className="w-full min-w-[600px] border-collapse text-sm">
                            <thead>
                              <tr className="bg-[var(--neutral-background-3)]">
                                <th className="border border-[var(--neutral-stroke-2)] px-3 py-2 text-left font-semibold">項目</th>
                                <th className="border border-[var(--neutral-stroke-2)] px-3 py-2 text-left font-semibold">支援目標</th>
                                <th className="border border-[var(--neutral-stroke-2)] px-3 py-2 text-left font-semibold">達成状況</th>
                                <th className="border border-[var(--neutral-stroke-2)] px-3 py-2 text-left font-semibold">コメント</th>
                              </tr>
                            </thead>
                            <tbody>
                              {planDetails.map((pd) => {
                                const md = detailMap[pd.id];
                                return (
                                  <tr key={pd.id}>
                                    <td className="border border-[var(--neutral-stroke-2)] px-3 py-2 align-top">
                                      {pd.main_category}
                                      {pd.sub_category && <><br /><span className="text-xs text-[var(--neutral-foreground-4)]">{pd.sub_category}</span></>}
                                    </td>
                                    <td className="border border-[var(--neutral-stroke-2)] px-3 py-2 align-top whitespace-pre-wrap">
                                      {nl(pd.support_goal)}
                                    </td>
                                    <td className="border border-[var(--neutral-stroke-2)] px-3 py-2 align-top">
                                      {md ? achievementBadge(md.achievement_status) : null}
                                    </td>
                                    <td className="border border-[var(--neutral-stroke-2)] px-3 py-2 align-top whitespace-pre-wrap">
                                      {nl(md?.monitoring_comment) || ''}
                                    </td>
                                  </tr>
                                );
                              })}
                            </tbody>
                          </table>
                        </div>
                      </div>
                    )}

                    {/* Long-term / Short-term goal achievement */}
                    {(record.long_term_goal_achievement || record.long_term_goal_comment ||
                      record.short_term_goal_achievement || record.short_term_goal_comment) && (
                      <div>
                        <h4 className="mb-2 text-sm font-semibold text-green-700 border-b-2 border-green-600 pb-1">
                          目標の達成状況
                        </h4>
                        <div className="space-y-3">
                          {(record.long_term_goal_achievement || record.long_term_goal_comment) && (
                            <div className="rounded-lg border-l-4 border-[var(--brand-90)] bg-[var(--neutral-background-3)] p-3">
                              <h5 className="text-sm font-semibold text-[var(--brand-60)] mb-2">長期目標</h5>
                              {record.plan?.long_term_goal_text && (
                                <p className="mb-2 rounded bg-white p-2 text-sm text-[var(--neutral-foreground-1)] whitespace-pre-wrap">{nl(record.plan.long_term_goal_text)}</p>
                              )}
                              {record.long_term_goal_achievement && (
                                <p className="text-sm"><span className="font-medium text-[var(--neutral-foreground-3)]">達成状況: </span><Badge variant="default">{record.long_term_goal_achievement}</Badge></p>
                              )}
                              {record.long_term_goal_comment && (
                                <p className="mt-1 text-sm text-[var(--neutral-foreground-2)] whitespace-pre-wrap">{nl(record.long_term_goal_comment)}</p>
                              )}
                            </div>
                          )}
                          {(record.short_term_goal_achievement || record.short_term_goal_comment) && (
                            <div className="rounded-lg border-l-4 border-green-500 bg-[var(--neutral-background-3)] p-3">
                              <h5 className="text-sm font-semibold text-green-700 mb-2">短期目標</h5>
                              {record.plan?.short_term_goal_text && (
                                <p className="mb-2 rounded bg-white p-2 text-sm text-[var(--neutral-foreground-1)] whitespace-pre-wrap">{nl(record.plan.short_term_goal_text)}</p>
                              )}
                              {record.short_term_goal_achievement && (
                                <p className="text-sm"><span className="font-medium text-[var(--neutral-foreground-3)]">達成状況: </span><Badge variant="default">{record.short_term_goal_achievement}</Badge></p>
                              )}
                              {record.short_term_goal_comment && (
                                <p className="mt-1 text-sm text-[var(--neutral-foreground-2)] whitespace-pre-wrap">{nl(record.short_term_goal_comment)}</p>
                              )}
                            </div>
                          )}
                        </div>
                      </div>
                    )}

                    {/* Overall comment */}
                    {record.overall_comment && (
                      <div>
                        <h4 className="mb-2 text-sm font-semibold text-green-700 border-b-2 border-green-600 pb-1">総合コメント</h4>
                        <div className="rounded-lg bg-[var(--brand-160)] p-3">
                          <p className="text-sm text-[var(--neutral-foreground-2)] whitespace-pre-wrap">{nl(record.overall_comment)}</p>
                        </div>
                      </div>
                    )}

                    {/* Signatures display */}
                    {(record.staff_signature || record.guardian_signature) && (
                      <div>
                        <h4 className="mb-2 text-sm font-semibold text-green-700 border-b-2 border-green-600 pb-1">電子署名</h4>
                        <div className="flex flex-wrap gap-4">
                          {record.staff_signature && (
                            <SignaturePad
                              readOnly
                              initialValue={record.staff_signature}
                              label={`職員署名${record.staff_signer_name ? ` (${record.staff_signer_name})` : ''}`}
                              width={200}
                              height={80}
                            />
                          )}
                          {record.guardian_signature && (
                            <SignaturePad
                              readOnly
                              initialValue={record.guardian_signature}
                              label={`保護者署名${record.guardian_signature_date ? ` - ${formatDate(record.guardian_signature_date)}` : ''}`}
                              width={200}
                              height={80}
                            />
                          )}
                        </div>
                      </div>
                    )}

                    {/* Confirmation status */}
                    {record.guardian_confirmed && record.guardian_confirmed_at && (
                      <div className="rounded-lg bg-green-50 p-3 flex items-center gap-2">
                        <MaterialIcon name="check_circle" size={20} className="text-green-600" />
                        <div>
                          <p className="text-sm font-semibold text-green-700">確認済み</p>
                          <p className="text-xs text-green-600">
                            確認日時: {format(new Date(record.guardian_confirmed_at), 'yyyy年M月d日 HH:mm', { locale: ja })}
                          </p>
                        </div>
                      </div>
                    )}

                    {/* Confirm button */}
                    {!record.guardian_confirmed && (
                      <div className="flex justify-end">
                        <Button onClick={() => openConfirm(record)} leftIcon={<MaterialIcon name="draw" size={16} />}>
                          署名して確認
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
      <Modal isOpen={confirmModal} onClose={() => setConfirmModal(false)} title="モニタリング表の確認" size="lg">
        <div className="space-y-5">
          <p className="text-sm text-[var(--neutral-foreground-3)]">
            このモニタリング表の内容を確認し、署名をお願いします。
          </p>

          <SignaturePad
            ref={guardianSigRef}
            label="保護者署名"
            width={400}
            height={150}
          />

          <div className="flex items-center gap-2">
            <label className="text-sm font-medium text-[var(--neutral-foreground-3)] whitespace-nowrap">署名日:</label>
            <input
              type="date"
              value={signatureDate}
              onChange={(e) => setSignatureDate(e.target.value)}
              className="rounded border border-[var(--neutral-stroke-1)] px-2 py-1 text-sm"
            />
          </div>

          <div className="flex justify-end gap-2 border-t border-[var(--neutral-stroke-2)] pt-4">
            <Button variant="secondary" onClick={() => setConfirmModal(false)}>キャンセル</Button>
            <Button
              onClick={handleConfirm}
              isLoading={confirmMutation.isPending}
              leftIcon={<MaterialIcon name="check_circle" size={16} />}
            >
              署名して確認
            </Button>
          </div>
        </div>
      </Modal>
    </div>
  );
}
