'use client';

import { useState } from 'react';
import { useParams } from 'next/navigation';
import { useQuery } from '@tanstack/react-query';
import api from '@/lib/api';
import { Card, CardHeader, CardTitle, CardBody } from '@/components/ui/Card';
import { Button } from '@/components/ui/Button';
import { Badge } from '@/components/ui/Badge';
import { SkeletonCard } from '@/components/ui/Skeleton';
import { formatDate } from '@/lib/utils';
import { Plus } from 'lucide-react';
import type { MonitoringRecord } from '@/types/monitoring';
import { ACHIEVEMENT_LABELS } from '@/types/monitoring';

export default function MonitoringPage() {
  const params = useParams();
  const studentId = Number(params.id);

  const { data: records, isLoading } = useQuery({
    queryKey: ['staff', 'student', studentId, 'monitoring'],
    queryFn: async () => {
      const response = await api.get<{ data: MonitoringRecord[] }>(
        `/api/staff/students/${studentId}/monitoring`
      );
      return response.data.data;
    },
    enabled: !!studentId,
  });

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
        <h1 className="text-2xl font-bold text-[var(--neutral-foreground-1)]">モニタリング記録</h1>
        <Button leftIcon={<Plus className="h-4 w-4" />}>
          新規作成
        </Button>
      </div>

      {records && records.length > 0 ? (
        <div className="space-y-4">
          {records.map((record) => (
            <Card key={record.id}>
              <CardHeader>
                <CardTitle>{formatDate(record.monitoring_date)} モニタリング</CardTitle>
              </CardHeader>
              <CardBody>
                {record.overall_comment && (
                  <p className="mb-3 text-sm text-[var(--neutral-foreground-2)]">{record.overall_comment}</p>
                )}
                {record.details && record.details.length > 0 && (
                  <div className="space-y-2">
                    {record.details.map((detail) => (
                      <div
                        key={detail.id}
                        className="flex items-center justify-between rounded-lg border border-[var(--neutral-stroke-3)] p-3"
                      >
                        <div>
                          <span className="text-xs font-medium text-[var(--neutral-foreground-3)]">
                            {detail.domain}
                          </span>
                          {detail.comment && (
                            <p className="text-sm text-[var(--neutral-foreground-2)]">{detail.comment}</p>
                          )}
                        </div>
                        <Badge
                          variant={
                            detail.achievement_level >= 4
                              ? 'success'
                              : detail.achievement_level >= 3
                              ? 'primary'
                              : detail.achievement_level >= 2
                              ? 'warning'
                              : 'danger'
                          }
                        >
                          {ACHIEVEMENT_LABELS[detail.achievement_level]}
                        </Badge>
                      </div>
                    ))}
                  </div>
                )}
                {(record.short_term_goal_achievement || record.long_term_goal_achievement) && (
                  <div className="mt-3 space-y-2">
                    {record.short_term_goal_achievement && (
                      <div className="rounded-lg bg-[var(--brand-160)] p-3">
                        <p className="text-xs font-medium text-[var(--brand-80)]">短期目標達成度</p>
                        <p className="text-sm text-[var(--brand-80)]">{record.short_term_goal_achievement}</p>
                      </div>
                    )}
                    {record.long_term_goal_achievement && (
                      <div className="rounded-lg bg-[var(--brand-160)] p-3">
                        <p className="text-xs font-medium text-[var(--brand-80)]">長期目標達成度</p>
                        <p className="text-sm text-[var(--brand-80)]">{record.long_term_goal_achievement}</p>
                      </div>
                    )}
                  </div>
                )}
              </CardBody>
            </Card>
          ))}
        </div>
      ) : (
        <Card>
          <CardBody>
            <p className="py-8 text-center text-sm text-[var(--neutral-foreground-3)]">
              モニタリング記録がありません
            </p>
          </CardBody>
        </Card>
      )}
    </div>
  );
}
