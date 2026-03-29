'use client';

import { useParams } from 'next/navigation';
import { useQuery } from '@tanstack/react-query';
import api from '@/lib/api';
import { Card, CardHeader, CardTitle, CardBody } from '@/components/ui/Card';
import { Button } from '@/components/ui/Button';
import { Badge } from '@/components/ui/Badge';
import { SkeletonCard } from '@/components/ui/Skeleton';
import { formatDate } from '@/lib/utils';
import { MaterialIcon } from '@/components/ui/MaterialIcon';
import Link from 'next/link';

// ---------------------------------------------------------------------------
// Types (matching actual API response)
// ---------------------------------------------------------------------------

interface KakehashiStaffEntry {
  id: number;
  period_id: number;
  student_id: number;
  staff_id: number | null;
  student_wish: string | null;
  short_term_goal: string | null;
  long_term_goal: string | null;
  health_life: string | null;
  motor_sensory: string | null;
  cognitive_behavior: string | null;
  language_communication: string | null;
  social_relations: string | null;
  other_challenges: string | null;
  is_submitted: boolean;
  submitted_at: string | null;
  guardian_confirmed: boolean;
  guardian_confirmed_at: string | null;
  is_hidden: boolean;
}

interface KakehashiGuardianEntry {
  id: number;
  period_id: number;
  student_id: number;
  guardian_id: number | null;
  student_wish: string | null;
  home_challenges: string | null;
  short_term_goal: string | null;
  long_term_goal: string | null;
  domain_health_life: string | null;
  domain_motor_sensory: string | null;
  domain_cognitive_behavior: string | null;
  domain_language_communication: string | null;
  domain_social_relations: string | null;
  other_challenges: string | null;
  home_situation: string | null;
  concerns: string | null;
  requests: string | null;
  is_submitted: boolean;
  submitted_at: string | null;
  is_hidden: boolean;
  guardian?: { id: number; full_name: string } | null;
}

interface KakehashiPeriod {
  id: number;
  student_id: number;
  period_name: string | null;
  start_date: string;
  end_date: string;
  submission_deadline: string | null;
  is_active: boolean;
  staff_entries: KakehashiStaffEntry[];
  guardian_entries: KakehashiGuardianEntry[];
}

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function truncate(text: string | null, maxLen: number): string {
  if (!text) return '';
  return text.length > maxLen ? text.slice(0, maxLen) + '...' : text;
}

function hasContent(entry: KakehashiStaffEntry): boolean {
  return !!(
    entry.student_wish ||
    entry.short_term_goal ||
    entry.long_term_goal ||
    entry.health_life ||
    entry.motor_sensory ||
    entry.cognitive_behavior ||
    entry.language_communication ||
    entry.social_relations ||
    entry.other_challenges
  );
}

function hasGuardianContent(entry: KakehashiGuardianEntry): boolean {
  return !!(
    entry.student_wish ||
    entry.home_challenges ||
    entry.short_term_goal ||
    entry.long_term_goal ||
    entry.domain_health_life ||
    entry.domain_motor_sensory ||
    entry.domain_cognitive_behavior ||
    entry.domain_language_communication ||
    entry.domain_social_relations ||
    entry.other_challenges ||
    entry.home_situation ||
    entry.concerns ||
    entry.requests
  );
}

// ---------------------------------------------------------------------------
// Component
// ---------------------------------------------------------------------------

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
      </div>

      {periods && periods.length > 0 ? (
        <div className="space-y-4">
          {periods.map((period) => {
            const staffEntry = period.staff_entries?.[0] ?? null;
            const guardianEntry = period.guardian_entries?.[0] ?? null;
            const staffHasContent = staffEntry ? hasContent(staffEntry) : false;
            const guardianHasContent = guardianEntry ? hasGuardianContent(guardianEntry) : false;

            return (
              <Card key={period.id}>
                <CardHeader>
                  <div className="flex items-center justify-between flex-wrap gap-2">
                    <div className="flex items-center gap-2">
                      <CardTitle>
                        {period.period_name || `${formatDate(period.start_date)} 〜 ${formatDate(period.end_date)}`}
                      </CardTitle>
                      {period.is_active && <Badge variant="success">有効</Badge>}
                    </div>
                    <div className="flex items-center gap-2">
                      <Link href={`/staff/kakehashi-staff`}>
                        <Button variant="outline" size="sm" leftIcon={<MaterialIcon name="edit" size={14} />}>
                          編集
                        </Button>
                      </Link>
                    </div>
                  </div>
                </CardHeader>
                <CardBody>
                  <div className="grid gap-4 sm:grid-cols-2">
                    {/* Staff Entry */}
                    <div className="rounded-lg border border-[var(--neutral-stroke-2)] p-4">
                      <div className="flex items-center gap-2 mb-3">
                        <MaterialIcon name="badge" size={18} className="text-[var(--brand-80)]" />
                        <span className="text-sm font-semibold text-[var(--neutral-foreground-1)]">スタッフ回答</span>
                        {staffEntry?.is_submitted ? (
                          <Badge variant="success">提出済</Badge>
                        ) : staffHasContent ? (
                          <Badge variant="warning">下書き</Badge>
                        ) : (
                          <Badge variant="default">未記入</Badge>
                        )}
                      </div>

                      {staffHasContent && staffEntry ? (
                        <div className="space-y-2 text-xs text-[var(--neutral-foreground-2)]">
                          {staffEntry.short_term_goal && (
                            <div>
                              <span className="font-medium text-[var(--neutral-foreground-3)]">短期目標: </span>
                              {truncate(staffEntry.short_term_goal, 80)}
                            </div>
                          )}
                          {staffEntry.long_term_goal && (
                            <div>
                              <span className="font-medium text-[var(--neutral-foreground-3)]">長期目標: </span>
                              {truncate(staffEntry.long_term_goal, 80)}
                            </div>
                          )}
                          {staffEntry.student_wish && (
                            <div>
                              <span className="font-medium text-[var(--neutral-foreground-3)]">本人の願い: </span>
                              {truncate(staffEntry.student_wish, 80)}
                            </div>
                          )}
                          {/* Domain count */}
                          {(() => {
                            const domains = [
                              staffEntry.health_life,
                              staffEntry.motor_sensory,
                              staffEntry.cognitive_behavior,
                              staffEntry.language_communication,
                              staffEntry.social_relations,
                            ].filter(Boolean);
                            return domains.length > 0 ? (
                              <div className="text-[var(--neutral-foreground-3)]">
                                五領域: {domains.length}/5 記入済
                              </div>
                            ) : null;
                          })()}
                          {staffEntry.submitted_at && (
                            <div className="text-[var(--neutral-foreground-4)]">
                              提出日: {formatDate(staffEntry.submitted_at)}
                            </div>
                          )}
                        </div>
                      ) : (
                        <p className="text-xs text-[var(--neutral-foreground-4)]">
                          まだ記入されていません
                        </p>
                      )}
                    </div>

                    {/* Guardian Entry */}
                    <div className="rounded-lg border border-[var(--neutral-stroke-2)] p-4">
                      <div className="flex items-center gap-2 mb-3">
                        <MaterialIcon name="family_restroom" size={18} className="text-[var(--brand-80)]" />
                        <span className="text-sm font-semibold text-[var(--neutral-foreground-1)]">保護者回答</span>
                        {guardianEntry?.is_submitted ? (
                          <Badge variant="success">提出済</Badge>
                        ) : guardianHasContent ? (
                          <Badge variant="warning">下書き</Badge>
                        ) : (
                          <Badge variant="default">未記入</Badge>
                        )}
                        {guardianEntry?.is_hidden && (
                          <Badge variant="default">非表示</Badge>
                        )}
                      </div>

                      {guardianHasContent && guardianEntry ? (
                        <div className="space-y-2 text-xs text-[var(--neutral-foreground-2)]">
                          {guardianEntry.guardian && (
                            <div>
                              <span className="font-medium text-[var(--neutral-foreground-3)]">保護者: </span>
                              {guardianEntry.guardian.full_name}
                            </div>
                          )}
                          {guardianEntry.home_situation && (
                            <div>
                              <span className="font-medium text-[var(--neutral-foreground-3)]">家庭の様子: </span>
                              {truncate(guardianEntry.home_situation, 80)}
                            </div>
                          )}
                          {guardianEntry.concerns && (
                            <div>
                              <span className="font-medium text-[var(--neutral-foreground-3)]">心配事: </span>
                              {truncate(guardianEntry.concerns, 80)}
                            </div>
                          )}
                          {guardianEntry.requests && (
                            <div>
                              <span className="font-medium text-[var(--neutral-foreground-3)]">要望: </span>
                              {truncate(guardianEntry.requests, 80)}
                            </div>
                          )}
                          {guardianEntry.student_wish && (
                            <div>
                              <span className="font-medium text-[var(--neutral-foreground-3)]">本人の願い: </span>
                              {truncate(guardianEntry.student_wish, 80)}
                            </div>
                          )}
                          {guardianEntry.short_term_goal && (
                            <div>
                              <span className="font-medium text-[var(--neutral-foreground-3)]">短期目標: </span>
                              {truncate(guardianEntry.short_term_goal, 80)}
                            </div>
                          )}
                          {guardianEntry.submitted_at && (
                            <div className="text-[var(--neutral-foreground-4)]">
                              提出日: {formatDate(guardianEntry.submitted_at)}
                            </div>
                          )}
                        </div>
                      ) : (
                        <p className="text-xs text-[var(--neutral-foreground-4)]">
                          まだ記入されていません
                        </p>
                      )}
                    </div>
                  </div>
                </CardBody>
              </Card>
            );
          })}
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
