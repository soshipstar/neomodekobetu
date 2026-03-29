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
import type { MonitoringRecord } from '@/types/monitoring';
import { ACHIEVEMENT_LABELS } from '@/types/monitoring';
import { MaterialIcon } from '@/components/ui/MaterialIcon';

export default function MonitoringPage() {
  const params = useParams();
  const studentId = Number(params.id);
  const [expandedIds, setExpandedIds] = useState<Set<number>>(new Set());

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

  const toggleExpand = (id: number) => {
    setExpandedIds((prev) => {
      const next = new Set(prev);
      if (next.has(id)) {
        next.delete(id);
      } else {
        next.add(id);
      }
      return next;
    });
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
        <h1 className="text-2xl font-bold text-[var(--neutral-foreground-1)]">モニタリング記録</h1>
        <Button leftIcon={<MaterialIcon name="add" size={16} />}>
          新規作成
        </Button>
      </div>

      {records && records.length > 0 ? (
        <div className="space-y-4">
          {records.map((record) => {
            const isExpanded = expandedIds.has(record.id);
            const detailCount = record.details?.length || 0;

            return (
              <Card key={record.id}>
                <CardHeader>
                  <div className="flex items-center justify-between flex-wrap gap-2">
                    <div className="flex items-center gap-2">
                      <CardTitle>{formatDate(record.monitoring_date)} モニタリング</CardTitle>
                      {detailCount > 0 && (
                        <Badge variant="info">{detailCount}領域</Badge>
                      )}
                    </div>
                    <Button
                      variant="outline"
                      size="sm"
                      onClick={() => toggleExpand(record.id)}
                      leftIcon={
                        <MaterialIcon
                          name={isExpanded ? 'expand_less' : 'expand_more'}
                          size={16}
                        />
                      }
                    >
                      {isExpanded ? '閉じる' : '詳細確認'}
                    </Button>
                  </div>
                </CardHeader>

                {/* Collapsed summary */}
                {!isExpanded && (
                  <CardBody>
                    <div className="flex flex-wrap gap-2">
                      {record.details && record.details.length > 0 && (
                        record.details.map((detail) => (
                          <Badge
                            key={detail.id}
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
                            {detail.domain}: {ACHIEVEMENT_LABELS[detail.achievement_level]}
                          </Badge>
                        ))
                      )}
                    </div>
                    {record.overall_comment && (
                      <p className="mt-2 text-xs text-[var(--neutral-foreground-3)] line-clamp-2">
                        {record.overall_comment}
                      </p>
                    )}
                  </CardBody>
                )}

                {/* Expanded details */}
                {isExpanded && (
                  <CardBody>
                    {record.overall_comment && (
                      <p className="mb-3 text-sm text-[var(--neutral-foreground-2)] whitespace-pre-wrap">
                        {record.overall_comment}
                      </p>
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
                )}
              </Card>
            );
          })}
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
