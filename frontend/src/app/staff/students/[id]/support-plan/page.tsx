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
import { Plus, Eye, Edit2 } from 'lucide-react';
import type { SupportPlan } from '@/types/support-plan';

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
            leftIcon={<Plus className="h-4 w-4" />}
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
                </div>
                <div className="flex items-center gap-2">
                  <Button
                    variant="ghost"
                    size="sm"
                    leftIcon={<Eye className="h-4 w-4" />}
                    onClick={() => setPreviewPlan(plan)}
                  >
                    プレビュー
                  </Button>
                  {(plan.status === 'draft' || plan.status === 'submitted') && (
                    <Button
                      variant="ghost"
                      size="sm"
                      leftIcon={<Edit2 className="h-4 w-4" />}
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
        {previewPlan && <PlanPreview plan={previewPlan} />}
      </Modal>
    </div>
  );
}
