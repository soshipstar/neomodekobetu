'use client';

import { useState } from 'react';
import { useParams } from 'next/navigation';
import { useQuery } from '@tanstack/react-query';
import api from '@/lib/api';
import { Card, CardHeader, CardTitle, CardBody } from '@/components/ui/Card';
import { Button } from '@/components/ui/Button';
import { Badge } from '@/components/ui/Badge';
import { Modal } from '@/components/ui/Modal';
import { SkeletonCard } from '@/components/ui/Skeleton';
import { PlanForm } from '@/components/support-plan/PlanForm';
import { PlanPreview } from '@/components/support-plan/PlanPreview';
import { AiGenerateButton } from '@/components/support-plan/AiGenerateButton';
import { formatDate } from '@/lib/utils';
import { useToast } from '@/components/ui/Toast';
import type { SupportPlan } from '@/types/support-plan';
import { MaterialIcon } from '@/components/ui/MaterialIcon';

const statusLabels: Record<string, string> = {
  draft: '下書き', submitted: '提出済', official: '正式',
};

const statusVariants: Record<string, 'default' | 'warning' | 'success' | 'primary' | 'info'> = {
  draft: 'default', submitted: 'warning', official: 'success',
};

export default function SupportPlanPage() {
  const params = useParams();
  const studentId = Number(params.id);
  const [showForm, setShowForm] = useState(false);
  const [previewPlan, setPreviewPlan] = useState<SupportPlan | null>(null);
  const [editPlan, setEditPlan] = useState<SupportPlan | null>(null);
  const toast = useToast();

  const { data: plans, isLoading, refetch } = useQuery({
    queryKey: ['staff', 'student', studentId, 'support-plans'],
    queryFn: async () => {
      const response = await api.get<{ data: SupportPlan[] }>(
        `/api/staff/students/${studentId}/support-plans`
      );
      return response.data.data;
    },
    enabled: !!studentId,
  });

  const handleFormSuccess = () => {
    setShowForm(false);
    setEditPlan(null);
    refetch();
  };

  const handleRequestSignature = async (planId: number) => {
    try {
      await api.post(`/api/staff/support-plans/${planId}/request-signature`);
      toast.success('署名要求を送信しました');
      refetch();
    } catch {
      toast.error('署名要求の送信に失敗しました');
    }
  };

  if (isLoading) {
    return (
      <div className="space-y-4">
        <SkeletonCard />
        <SkeletonCard />
      </div>
    );
  }

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-bold text-[var(--neutral-foreground-1)]">個別支援計画</h1>
        <div className="flex items-center gap-2">
          <AiGenerateButton studentId={studentId} onGenerated={() => refetch()} />
          <Button
            leftIcon={<MaterialIcon name="add" size={16} />}
            onClick={() => setShowForm(true)}
          >
            新規作成
          </Button>
        </div>
      </div>

      {/* Plan list */}
      {plans && plans.length > 0 ? (
        <div className="space-y-4">
          {plans.map((plan) => (
            <Card key={plan.id}>
              <CardHeader>
                <div className="flex items-center gap-2">
                  <CardTitle>
                    {formatDate(plan.created_date)} {plan.plan_type ? `(${plan.plan_type})` : ''}
                  </CardTitle>
                  <Badge variant={statusVariants[plan.status] || 'default'}>
                    {statusLabels[plan.status] || plan.status}
                  </Badge>
                  {(plan.status === 'official' || plan.status === 'submitted') && !plan.staff_signature_image && !(plan as any).staff_signature && (
                    <Badge variant="danger">職員署名なし</Badge>
                  )}
                  {(plan.status === 'official' || plan.status === 'submitted') && !plan.guardian_signature_image && !plan.guardian_signature && (
                    <Badge variant="danger">保護者署名なし</Badge>
                  )}
                </div>
                <div className="flex items-center gap-2">
                  <Button
                    variant="ghost"
                    size="sm"
                    leftIcon={<MaterialIcon name="visibility" size={16} />}
                    onClick={() => setPreviewPlan(plan)}
                  >
                    プレビュー
                  </Button>
                  {(plan.status === 'draft' || plan.status === 'submitted') && (
                    <Button
                      variant="ghost"
                      size="sm"
                      leftIcon={<MaterialIcon name="edit" size={16} />}
                      onClick={() => { setEditPlan(plan); setShowForm(true); }}
                    >
                      編集
                    </Button>
                  )}
                </div>
              </CardHeader>
              <CardBody>
                {plan.life_intention && (
                  <p className="text-sm text-[var(--neutral-foreground-2)]">{plan.life_intention}</p>
                )}
                {plan.details && (
                  <p className="mt-2 text-xs text-[var(--neutral-foreground-4)]">
                    {plan.details.length}件の支援領域
                  </p>
                )}
              </CardBody>
            </Card>
          ))}
        </div>
      ) : (
        <Card>
          <CardBody>
            <p className="py-8 text-center text-sm text-[var(--neutral-foreground-3)]">
              個別支援計画がまだ作成されていません
            </p>
          </CardBody>
        </Card>
      )}

      {/* Create/Edit Modal */}
      <Modal
        isOpen={showForm}
        onClose={() => { setShowForm(false); setEditPlan(null); }}
        title={editPlan ? '個別支援計画を編集' : '個別支援計画を作成'}
        size="full"
      >
        <PlanForm
          studentId={studentId}
          existingPlan={editPlan}
          onSuccess={handleFormSuccess}
          onCancel={() => { setShowForm(false); setEditPlan(null); }}
        />
      </Modal>

      {/* Preview Modal */}
      <Modal
        isOpen={!!previewPlan}
        onClose={() => setPreviewPlan(null)}
        title="個別支援計画プレビュー"
        size="full"
      >
        {previewPlan && <PlanPreview plan={previewPlan} onRequestSignature={handleRequestSignature} />}
      </Modal>
    </div>
  );
}
