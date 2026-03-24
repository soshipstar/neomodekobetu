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
import { formatDate, nl } from '@/lib/utils';
import { DOMAIN_LABELS, type Domain, type SupportPlan } from '@/types/support-plan';
import { CheckCircle, PenLine, MessageSquare } from 'lucide-react';

interface ExtendedSupportPlan extends SupportPlan {
  guardian_confirmed_at?: string | null;
  guardian_signature_date?: string | null;
  staff_signature_date?: string | null;
  guardian_review_comment?: string | null;
  guardian_reviewed_at?: string | null;
  is_official?: boolean;
  is_draft?: boolean;
}

const statusLabels: Record<string, string> = {
  draft: '下書き', submitted: '提出済み', review: 'レビュー中', approved: '承認済', active: '有効', archived: 'アーカイブ',
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

  // Sign (confirm with signature) mutation - calls POST /api/guardian/support-plans/{id}/sign
  const signMutation = useMutation({
    mutationFn: async (data: { id: number; signature: string }) =>
      api.post(`/api/guardian/support-plans/${data.id}/sign`, { signature: data.signature }),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['guardian', 'support-plans'] });
      toast.success('確認・署名が完了しました');
      setConfirmModal(false);
      setConfirmingPlan(null);
    },
    onError: () => toast.error('署名に失敗しました'),
  });

  // Review (approve without changes) mutation - calls POST /api/guardian/support-plans/{id}/review
  const approveMutation = useMutation({
    mutationFn: async (data: { id: number; comment: string }) =>
      api.post(`/api/guardian/support-plans/${data.id}/review`, { comment: data.comment }),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['guardian', 'support-plans'] });
      toast.success('承認しました');
      setConfirmModal(false);
      setConfirmingPlan(null);
    },
    onError: () => toast.error('承認に失敗しました'),
  });

  // Submit review comment mutation - calls POST /api/guardian/support-plans/{id}/review
  const commentMutation = useMutation({
    mutationFn: async (data: { id: number; comment: string }) =>
      api.post(`/api/guardian/support-plans/${data.id}/review`, { comment: data.comment }),
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

  const handleSign = () => {
    if (!confirmingPlan) return;
    if (!guardianSigRef.current || guardianSigRef.current.isEmpty()) {
      toast.error('署名を記入してください');
      return;
    }
    signMutation.mutate({ id: confirmingPlan.id, signature: guardianSigRef.current.toDataURL() });
  };

  const handleApprove = () => {
    if (!confirmingPlan) return;
    approveMutation.mutate({ id: confirmingPlan.id, comment: '変更なし（承認）' });
  };

  const handleSubmitComment = () => {
    if (!confirmingPlan || !reviewComment.trim()) {
      toast.error('コメントを入力してください');
      return;
    }
    commentMutation.mutate({ id: confirmingPlan.id, comment: reviewComment });
  };

  if (isLoading) {
    return <div className="space-y-4"><h1 className="text-2xl font-bold text-[var(--neutral-foreground-1)]">個別支援計画書</h1><SkeletonCard /><SkeletonCard /></div>;
  }

  return (
    <div className="space-y-6">
      <h1 className="text-2xl font-bold text-[var(--neutral-foreground-1)]">個別支援計画書</h1>

      {/* Pending review banner */}
      {plans && plans.some((p) => !p.is_draft && !p.guardian_review_comment && !p.guardian_signature) && (
        <div className="rounded-lg bg-yellow-50 border border-yellow-200 p-3 text-sm text-yellow-700">
          確認待ちの個別支援計画書があります。内容をご確認のうえ、承認またはコメントの送信をお願いします。
        </div>
      )}

      {plans && plans.length > 0 ? (
        <div className="space-y-4">
          {plans.map((plan) => {
            const ext = plan as ExtendedSupportPlan;
            const hasReviewed = !!ext.guardian_review_comment || !!ext.guardian_reviewed_at;
            const hasSigned = !!ext.guardian_signature || !!ext.guardian_signature_image;
            const canReview = !ext.is_draft && !hasReviewed && !hasSigned;
            return (
              <Card key={plan.id}>
                <CardHeader>
                  <div className="flex items-center justify-between">
                    <div className="flex items-center gap-2 flex-wrap">
                      <CardTitle>
                        {plan.student?.student_name} {plan.plan_period_start ? `- ${formatDate(plan.plan_period_start)}` : ''} {plan.plan_period_end ? `~ ${formatDate(plan.plan_period_end)}` : ''}
                      </CardTitle>
                      <Badge variant={plan.status === 'active' ? 'primary' : plan.status === 'approved' ? 'success' : 'default'}>
                        {statusLabels[plan.status] || plan.status}
                      </Badge>
                      {hasSigned && (
                        <Badge variant="success" dot>署名済み</Badge>
                      )}
                      {hasReviewed && !hasSigned && (
                        <Badge variant="info" dot>レビュー済み</Badge>
                      )}
                      {canReview && (
                        <Badge variant="warning" dot>確認待ち</Badge>
                      )}
                    </div>
                    {canReview && (
                      <Button size="sm" onClick={() => openConfirmModal(ext)} leftIcon={<PenLine className="h-4 w-4" />}>
                        内容を確認する
                      </Button>
                    )}
                  </div>
                </CardHeader>
                <CardBody>
                  {/* Plan details in read-only mode */}
                  {plan.life_intention && (
                    <div className="mb-3 rounded-lg bg-[var(--brand-160)] p-3">
                      <p className="text-xs font-medium text-[var(--brand-60)]">本人の生活に対する意向</p>
                      <p className="text-sm text-[var(--brand-40)]">{plan.life_intention}</p>
                    </div>
                  )}
                  {plan.overall_policy && (
                    <div className="mb-3 rounded-lg bg-[var(--neutral-background-3)] p-3">
                      <p className="text-xs font-medium text-[var(--neutral-foreground-3)]">総合的な援助の方針</p>
                      <p className="text-sm text-[var(--neutral-foreground-1)]">{plan.overall_policy}</p>
                    </div>
                  )}
                  {plan.guardian_wish && (
                    <div className="mb-3 rounded-lg bg-[var(--brand-160)] p-3">
                      <p className="text-xs font-medium text-[var(--brand-70)]">保護者の願い</p>
                      <p className="text-sm text-blue-900">{plan.guardian_wish}</p>
                    </div>
                  )}
                  {plan.long_term_goal && (
                    <div className="mb-3 rounded-lg bg-green-50 p-3">
                      <p className="text-xs font-medium text-green-700">長期目標</p>
                      <p className="text-sm text-green-900">{plan.long_term_goal}</p>
                    </div>
                  )}
                  {plan.short_term_goal && (
                    <div className="mb-3 rounded-lg bg-amber-50 p-3">
                      <p className="text-xs font-medium text-amber-700">短期目標</p>
                      <p className="text-sm text-amber-900">{plan.short_term_goal}</p>
                    </div>
                  )}
                  {plan.details && plan.details.length > 0 && (
                    <div className="space-y-2">
                      <p className="text-xs font-medium text-[var(--neutral-foreground-3)]">支援内容</p>
                      {plan.details.map((detail) => (
                        <div key={detail.id} className="rounded-lg border border-[var(--neutral-stroke-3)] p-3">
                          <p className="text-xs font-medium text-[var(--neutral-foreground-3)]">
                            {DOMAIN_LABELS[detail.domain as Domain] || detail.domain}
                          </p>
                          {detail.current_status && (
                            <p className="text-xs text-[var(--neutral-foreground-3)] mt-1">現状: {detail.current_status}</p>
                          )}
                          {detail.short_term_goal && (
                            <p className="text-sm text-[var(--neutral-foreground-2)] mt-1">短期目標: {detail.short_term_goal}</p>
                          )}
                          {detail.support_content && (
                            <p className="mt-1 text-xs text-[var(--neutral-foreground-3)]">支援内容: {detail.support_content}</p>
                          )}
                          {detail.achievement_criteria && (
                            <p className="mt-1 text-xs text-[var(--neutral-foreground-4)]">達成基準: {detail.achievement_criteria}</p>
                          )}
                        </div>
                      ))}
                    </div>
                  )}

                  {/* Review comment display */}
                  {ext.guardian_review_comment && (
                    <div className="mt-3 rounded-lg bg-orange-50 p-3">
                      <p className="text-xs font-medium text-orange-700">保護者コメント</p>
                      <p className="text-sm text-orange-900 whitespace-pre-wrap">{nl(ext.guardian_review_comment)}</p>
                      {ext.guardian_reviewed_at && (
                        <p className="mt-1 text-xs text-orange-500">{formatDate(ext.guardian_reviewed_at)} に送信</p>
                      )}
                    </div>
                  )}

                  {/* Signature display */}
                  {(ext.staff_signature_image || ext.guardian_signature_image || ext.guardian_signature) && (
                    <div className="mt-4 flex flex-wrap gap-4 border-t border-[var(--neutral-stroke-2)] pt-4">
                      {ext.staff_signature_image && (
                        <SignaturePad
                          readOnly
                          initialValue={ext.staff_signature_image}
                          label={`職員署名${ext.staff_signer_name ? ` (${ext.staff_signer_name})` : ''}`}
                          width={200}
                          height={80}
                        />
                      )}
                      {(ext.guardian_signature_image || ext.guardian_signature) && (
                        <SignaturePad
                          readOnly
                          initialValue={ext.guardian_signature_image || ext.guardian_signature || ''}
                          label={`保護者署名${ext.guardian_signature_date ? ` - ${ext.guardian_signature_date}` : ''}`}
                          width={200}
                          height={80}
                        />
                      )}
                    </div>
                  )}

                  {/* Reviewed / signed status */}
                  {hasSigned && ext.guardian_signature_date && (
                    <div className="mt-3 flex items-center gap-2 rounded-lg bg-green-50 p-3">
                      <CheckCircle className="h-4 w-4 text-green-600" />
                      <span className="text-sm text-green-700">
                        {ext.guardian_signature_date} に署名済み
                      </span>
                    </div>
                  )}
                  {hasReviewed && !hasSigned && ext.guardian_reviewed_at && (
                    <div className="mt-3 flex items-center gap-2 rounded-lg bg-[var(--brand-160)] p-3">
                      <CheckCircle className="h-4 w-4 text-[var(--brand-80)]" />
                      <span className="text-sm text-[var(--brand-70)]">
                        {formatDate(ext.guardian_reviewed_at)} にレビュー済み
                      </span>
                    </div>
                  )}
                </CardBody>
              </Card>
            );
          })}
        </div>
      ) : (
        <Card><CardBody><p className="py-8 text-center text-sm text-[var(--neutral-foreground-3)]">個別支援計画書がありません</p></CardBody></Card>
      )}

      {/* Confirm + Review Modal */}
      <Modal
        isOpen={confirmModal}
        onClose={() => { setConfirmModal(false); setConfirmingPlan(null); setCommentMode(false); }}
        title="個別支援計画書の確認"
        size="lg"
      >
        <div className="space-y-5">
          {/* Plan details in read-only mode within the modal */}
          {confirmingPlan && (
            <div className="max-h-[40vh] overflow-y-auto space-y-3 rounded-lg border border-[var(--neutral-stroke-2)] p-4 bg-[var(--neutral-background-3)]">
              <h3 className="text-sm font-semibold text-[var(--neutral-foreground-2)]">
                {confirmingPlan.student?.student_name} - {formatDate(confirmingPlan.plan_period_start ?? '')} ~ {formatDate(confirmingPlan.plan_period_end ?? '')}
              </h3>
              {confirmingPlan.life_intention && (
                <div>
                  <p className="text-xs font-medium text-[var(--brand-60)]">本人の生活に対する意向</p>
                  <p className="text-sm text-[var(--neutral-foreground-1)] whitespace-pre-wrap">{nl(confirmingPlan.life_intention)}</p>
                </div>
              )}
              {confirmingPlan.overall_policy && (
                <div>
                  <p className="text-xs font-medium text-[var(--neutral-foreground-3)]">総合的な援助の方針</p>
                  <p className="text-sm text-[var(--neutral-foreground-1)] whitespace-pre-wrap">{nl(confirmingPlan.overall_policy)}</p>
                </div>
              )}
              {confirmingPlan.guardian_wish && (
                <div>
                  <p className="text-xs font-medium text-[var(--brand-70)]">保護者の願い</p>
                  <p className="text-sm text-[var(--neutral-foreground-1)] whitespace-pre-wrap">{nl(confirmingPlan.guardian_wish)}</p>
                </div>
              )}
              {confirmingPlan.long_term_goal && (
                <div>
                  <p className="text-xs font-medium text-green-700">長期目標</p>
                  <p className="text-sm text-[var(--neutral-foreground-1)] whitespace-pre-wrap">{nl(confirmingPlan.long_term_goal)}</p>
                </div>
              )}
              {confirmingPlan.short_term_goal && (
                <div>
                  <p className="text-xs font-medium text-amber-700">短期目標</p>
                  <p className="text-sm text-[var(--neutral-foreground-1)] whitespace-pre-wrap">{nl(confirmingPlan.short_term_goal)}</p>
                </div>
              )}
              {confirmingPlan.details && confirmingPlan.details.length > 0 && (
                <div className="space-y-2">
                  <p className="text-xs font-medium text-[var(--neutral-foreground-3)]">支援内容</p>
                  {confirmingPlan.details.map((detail) => (
                    <div key={detail.id} className="rounded border border-[var(--neutral-stroke-2)] bg-white p-2">
                      <p className="text-xs font-medium text-[var(--neutral-foreground-3)]">
                        {DOMAIN_LABELS[detail.domain as Domain] || detail.domain}
                      </p>
                      {detail.current_status && <p className="text-xs text-[var(--neutral-foreground-3)]">現状: {detail.current_status}</p>}
                      {detail.short_term_goal && <p className="text-sm text-[var(--neutral-foreground-2)]">目標: {detail.short_term_goal}</p>}
                      {detail.support_content && <p className="text-xs text-[var(--neutral-foreground-3)]">支援: {detail.support_content}</p>}
                    </div>
                  ))}
                </div>
              )}
            </div>
          )}

          {!commentMode ? (
            <>
              <p className="text-sm text-[var(--neutral-foreground-3)]">
                計画の内容をご確認ください。問題がなければ「変更なし（承認）」を押してください。
                署名をご希望の場合は署名欄に記入のうえ「署名して確認」を押してください。
                変更を希望される場合は「コメントを送る」を選択してください。
              </p>

              {/* Guardian electronic signature (optional) */}
              <SignaturePad
                ref={guardianSigRef}
                label="保護者署名（任意）"
                width={400}
                height={150}
              />

              <div className="flex flex-col gap-2 border-t border-[var(--neutral-stroke-2)] pt-4 sm:flex-row sm:justify-between">
                <Button
                  variant="outline"
                  size="sm"
                  onClick={() => setCommentMode(true)}
                  leftIcon={<MessageSquare className="h-4 w-4" />}
                >
                  コメントを送る
                </Button>
                <div className="flex gap-2">
                  <Button variant="secondary" onClick={() => { setConfirmModal(false); setConfirmingPlan(null); }}>
                    キャンセル
                  </Button>
                  <Button
                    variant="outline"
                    onClick={handleApprove}
                    isLoading={approveMutation.isPending}
                    leftIcon={<CheckCircle className="h-4 w-4" />}
                  >
                    変更なし（承認）
                  </Button>
                  <Button
                    onClick={handleSign}
                    isLoading={signMutation.isPending}
                    leftIcon={<PenLine className="h-4 w-4" />}
                  >
                    署名して確認
                  </Button>
                </div>
              </div>
            </>
          ) : (
            <>
              <p className="text-sm text-[var(--neutral-foreground-3)]">
                変更を希望される箇所やコメントをご入力ください。
              </p>
              <div>
                <label className="mb-1 block text-sm font-medium text-[var(--neutral-foreground-2)]">変更希望コメント</label>
                <textarea
                  value={reviewComment}
                  onChange={(e) => setReviewComment(e.target.value)}
                  className="block w-full rounded-lg border border-[var(--neutral-stroke-1)] px-3 py-2 text-sm"
                  rows={5}
                  placeholder="変更を希望する内容を入力してください..."
                />
              </div>
              <div className="flex justify-end gap-2 border-t border-[var(--neutral-stroke-2)] pt-4">
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
