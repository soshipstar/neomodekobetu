'use client';

import { useState, useRef } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import api from '@/lib/api';
import { Card, CardHeader, CardTitle, CardBody } from '@/components/ui/Card';
import { Button } from '@/components/ui/Button';
import { Badge } from '@/components/ui/Badge';
import { Modal } from '@/components/ui/Modal';
import { SkeletonCard } from '@/components/ui/Skeleton';
import { useToast } from '@/components/ui/Toast';
import { SignaturePad, type SignaturePadRef } from '@/components/ui/SignaturePad';
import { formatDate } from '@/lib/utils';
import { DOMAIN_LABELS, type Domain, type SupportPlan } from '@/types/support-plan';
import { CheckCircle, PenLine, MessageSquare } from 'lucide-react';

interface ExtendedSupportPlan extends SupportPlan {
  guardian_confirmed?: boolean;
  guardian_confirmed_at?: string | null;
  guardian_signature_image?: string | null;
  guardian_signature_date?: string | null;
  staff_signature_image?: string | null;
  staff_signer_name?: string | null;
  staff_signature_date?: string | null;
  guardian_review_comment?: string | null;
  is_official?: boolean;
  is_draft?: boolean;
}

const statusLabels: Record<string, string> = {
  draft: '下書き', review: 'レビュー中', approved: '承認済', active: '有効', archived: 'アーカイブ',
};

export default function GuardianSupportPlanPage() {
  const queryClient = useQueryClient();
  const toast = useToast();

  const [confirmModal, setConfirmModal] = useState(false);
  const [confirmingPlan, setConfirmingPlan] = useState<ExtendedSupportPlan | null>(null);
  const [reviewComment, setReviewComment] = useState('');
  const [commentMode, setCommentMode] = useState(false);
  const guardianSigRef = useRef<SignaturePadRef>(null);

  const { data: plans, isLoading } = useQuery({
    queryKey: ['guardian', 'support-plans'],
    queryFn: async () => {
      const response = await api.get<{ data: ExtendedSupportPlan[] }>('/api/guardian/support-plans');
      return response.data.data;
    },
  });

  // Confirm with signature mutation
  const confirmMutation = useMutation({
    mutationFn: async (data: { id: number; guardian_signature?: string }) =>
      api.post(`/api/guardian/support-plans/${data.id}/confirm`, data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['guardian', 'support-plans'] });
      toast.success('確認・署名が完了しました');
      setConfirmModal(false);
      setConfirmingPlan(null);
    },
    onError: () => toast.error('確認に失敗しました'),
  });

  // Submit review comment mutation
  const commentMutation = useMutation({
    mutationFn: async (data: { id: number; review_comment: string }) =>
      api.post(`/api/guardian/support-plans/${data.id}/review-comment`, data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['guardian', 'support-plans'] });
      toast.success('コメントを送信しました');
      setConfirmModal(false);
      setConfirmingPlan(null);
      setReviewComment('');
      setCommentMode(false);
    },
    onError: () => toast.error('コメントの送信に失敗しました'),
  });

  const openConfirmModal = (plan: ExtendedSupportPlan) => {
    setConfirmingPlan(plan);
    setReviewComment('');
    setCommentMode(false);
    setConfirmModal(true);
  };

  const handleConfirm = () => {
    if (!confirmingPlan) return;
    const payload: { id: number; guardian_signature?: string } = { id: confirmingPlan.id };
    if (guardianSigRef.current && !guardianSigRef.current.isEmpty()) {
      payload.guardian_signature = guardianSigRef.current.toDataURL();
    }
    confirmMutation.mutate(payload);
  };

  const handleSubmitComment = () => {
    if (!confirmingPlan || !reviewComment.trim()) {
      toast.error('コメントを入力してください');
      return;
    }
    commentMutation.mutate({ id: confirmingPlan.id, review_comment: reviewComment });
  };

  if (isLoading) {
    return <div className="space-y-4"><h1 className="text-2xl font-bold text-gray-900">個別支援計画</h1><SkeletonCard /><SkeletonCard /></div>;
  }

  return (
    <div className="space-y-6">
      <h1 className="text-2xl font-bold text-gray-900">個別支援計画</h1>

      {plans && plans.length > 0 ? (
        <div className="space-y-4">
          {plans.map((plan) => {
            const ext = plan as ExtendedSupportPlan;
            const canConfirm = !ext.is_draft && !ext.guardian_confirmed;
            return (
              <Card key={plan.id}>
                <CardHeader>
                  <div className="flex items-center justify-between">
                    <div className="flex items-center gap-2">
                      <CardTitle>
                        {plan.student?.student_name} - {formatDate(plan.plan_period_start ?? '')} ~ {formatDate(plan.plan_period_end ?? '')}
                      </CardTitle>
                      <Badge variant={plan.status === 'active' ? 'primary' : plan.status === 'approved' ? 'success' : 'default'}>
                        {statusLabels[plan.status]}
                      </Badge>
                      {ext.guardian_confirmed && (
                        <Badge variant="success">確認済み</Badge>
                      )}
                    </div>
                    {canConfirm && (
                      <Button size="sm" onClick={() => openConfirmModal(ext)} leftIcon={<PenLine className="h-4 w-4" />}>
                        内容を確認する
                      </Button>
                    )}
                  </div>
                </CardHeader>
                <CardBody>
                  {plan.overall_policy && <p className="mb-3 text-sm text-gray-600">{plan.overall_policy}</p>}
                  {plan.guardian_wish && (
                    <div className="mb-3 rounded-lg bg-blue-50 p-3">
                      <p className="text-xs font-medium text-blue-700">保護者の願い</p>
                      <p className="text-sm text-blue-900">{plan.guardian_wish}</p>
                    </div>
                  )}
                  {plan.details && plan.details.length > 0 && (
                    <div className="space-y-2">
                      {plan.details.map((detail) => (
                        <div key={detail.id} className="rounded-lg border border-gray-100 p-3">
                          <p className="text-xs font-medium text-gray-500">
                            {DOMAIN_LABELS[detail.domain as Domain] || detail.domain}
                          </p>
                          <p className="text-sm text-gray-700">{detail.short_term_goal}</p>
                          <p className="mt-1 text-xs text-gray-500">{detail.support_content}</p>
                        </div>
                      ))}
                    </div>
                  )}

                  {/* Signature display */}
                  {(ext.staff_signature_image || ext.guardian_signature_image) && (
                    <div className="mt-4 flex flex-wrap gap-4 border-t border-gray-200 pt-4">
                      {ext.staff_signature_image && (
                        <SignaturePad
                          readOnly
                          initialValue={ext.staff_signature_image}
                          label={`職員署名${ext.staff_signer_name ? ` (${ext.staff_signer_name})` : ''}`}
                          width={200}
                          height={80}
                        />
                      )}
                      {ext.guardian_signature_image && (
                        <SignaturePad
                          readOnly
                          initialValue={ext.guardian_signature_image}
                          label={`保護者署名${ext.guardian_signature_date ? ` - ${ext.guardian_signature_date}` : ''}`}
                          width={200}
                          height={80}
                        />
                      )}
                    </div>
                  )}

                  {/* Confirmed status */}
                  {ext.guardian_confirmed && ext.guardian_confirmed_at && (
                    <div className="mt-3 flex items-center gap-2 rounded-lg bg-green-50 p-3">
                      <CheckCircle className="h-4 w-4 text-green-600" />
                      <span className="text-sm text-green-700">
                        {formatDate(ext.guardian_confirmed_at)} に確認済み
                      </span>
                    </div>
                  )}
                </CardBody>
              </Card>
            );
          })}
        </div>
      ) : (
        <Card><CardBody><p className="py-8 text-center text-sm text-gray-500">個別支援計画がありません</p></CardBody></Card>
      )}

      {/* Confirm + Signature Modal */}
      <Modal
        isOpen={confirmModal}
        onClose={() => { setConfirmModal(false); setConfirmingPlan(null); setCommentMode(false); }}
        title="個別支援計画の確認"
        size="lg"
      >
        <div className="space-y-5">
          {!commentMode ? (
            <>
              <p className="text-sm text-gray-600">
                計画の内容をご確認いただき、問題がなければ署名のうえ確認ボタンを押してください。
                変更を希望される場合は「変更希望コメントを送る」を選択してください。
              </p>

              {/* Guardian electronic signature */}
              <SignaturePad
                ref={guardianSigRef}
                label="保護者署名"
                width={400}
                height={150}
              />

              <div className="flex flex-col gap-2 border-t border-gray-200 pt-4 sm:flex-row sm:justify-between">
                <Button
                  variant="outline"
                  size="sm"
                  onClick={() => setCommentMode(true)}
                  leftIcon={<MessageSquare className="h-4 w-4" />}
                >
                  変更希望コメントを送る
                </Button>
                <div className="flex gap-2">
                  <Button variant="secondary" onClick={() => { setConfirmModal(false); setConfirmingPlan(null); }}>
                    キャンセル
                  </Button>
                  <Button
                    onClick={handleConfirm}
                    isLoading={confirmMutation.isPending}
                    leftIcon={<CheckCircle className="h-4 w-4" />}
                  >
                    内容を確認しました
                  </Button>
                </div>
              </div>
            </>
          ) : (
            <>
              <p className="text-sm text-gray-600">
                変更を希望される箇所やコメントをご入力ください。
              </p>
              <div>
                <label className="mb-1 block text-sm font-medium text-gray-700">変更希望コメント</label>
                <textarea
                  value={reviewComment}
                  onChange={(e) => setReviewComment(e.target.value)}
                  className="block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm"
                  rows={5}
                  placeholder="変更を希望する内容を入力してください..."
                />
              </div>
              <div className="flex justify-end gap-2 border-t border-gray-200 pt-4">
                <Button variant="secondary" onClick={() => setCommentMode(false)}>
                  戻る
                </Button>
                <Button
                  onClick={handleSubmitComment}
                  isLoading={commentMutation.isPending}
                  disabled={!reviewComment.trim()}
                >
                  コメントを送信
                </Button>
              </div>
            </>
          )}
        </div>
      </Modal>
    </div>
  );
}
