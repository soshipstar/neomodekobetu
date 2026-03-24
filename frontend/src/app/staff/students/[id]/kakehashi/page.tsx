'use client';

import { useParams } from 'next/navigation';
import { useQuery } from '@tanstack/react-query';
import api from '@/lib/api';
import { Card, CardHeader, CardTitle, CardBody } from '@/components/ui/Card';
import { Button } from '@/components/ui/Button';
import { Badge } from '@/components/ui/Badge';
import { SkeletonCard } from '@/components/ui/Skeleton';
import { formatDate } from '@/lib/utils';
import type { KakehashiPeriod } from '@/types/kakehashi';
import { MaterialIcon } from '@/components/ui/MaterialIcon';

const statusLabels: Record<string, string> = {
  draft: '下書き', in_progress: '実施中', completed: '完了',
};

const statusVariants: Record<string, 'default' | 'warning' | 'success'> = {
  draft: 'default', in_progress: 'warning', completed: 'success',
};

export default function KakehashiPage() {
  const params = useParams();
  const studentId = Number(params.id);

  const { data: periods, isLoading } = useQuery({
    queryKey: ['staff', 'student', studentId, 'kakehashi'],
    queryFn: async () => {
      const response = await api.get<{ data: KakehashiPeriod[] }>(
        `/api/staff/students/${studentId}/kakehashi`
      );
      return response.data.data;
    },
    enabled: !!studentId,
  });

  if (isLoading) {
    return <div className="space-y-4"><SkeletonCard /><SkeletonCard /></div>;
  }

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-bold text-[var(--neutral-foreground-1)]">かけはし</h1>
        <Button leftIcon={<MaterialIcon name="add" size={16} />}>新規作成</Button>
      </div>

      {periods && periods.length > 0 ? (
        <div className="space-y-4">
          {periods.map((period) => (
            <Card key={period.id}>
              <CardHeader>
                <div className="flex items-center gap-2">
                  <CardTitle>
                    {period.period_label || `${formatDate(period.start_date)} - ${formatDate(period.end_date)}`}
                  </CardTitle>
                  <Badge variant={statusVariants[period.status] || 'default'}>
                    {statusLabels[period.status] || period.status}
                  </Badge>
                </div>
              </CardHeader>
              <CardBody>
                <div className="grid gap-4 sm:grid-cols-2">
                  <div>
                    <p className="text-xs font-medium text-[var(--neutral-foreground-3)]">スタッフ回答</p>
                    <p className="text-sm text-[var(--neutral-foreground-2)]">
                      {period.staffEntries?.length || 0}件
                    </p>
                  </div>
                  <div>
                    <p className="text-xs font-medium text-[var(--neutral-foreground-3)]">保護者回答</p>
                    <p className="text-sm text-[var(--neutral-foreground-2)]">
                      {period.guardianEntries?.length || 0}件
                    </p>
                  </div>
                </div>
              </CardBody>
            </Card>
          ))}
        </div>
      ) : (
        <Card>
          <CardBody>
            <p className="py-8 text-center text-sm text-[var(--neutral-foreground-3)]">
              かけはし記録がありません
            </p>
          </CardBody>
        </Card>
      )}
    </div>
  );
}
