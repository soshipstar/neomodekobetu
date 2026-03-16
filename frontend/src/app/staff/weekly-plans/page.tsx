'use client';

import { useState, useMemo } from 'react';
import { useQuery } from '@tanstack/react-query';
import api from '@/lib/api';
import { Card, CardHeader, CardTitle, CardBody } from '@/components/ui/Card';
import { Button } from '@/components/ui/Button';
import { Badge } from '@/components/ui/Badge';
import { Skeleton } from '@/components/ui/Skeleton';
import {
  ChevronLeft,
  ChevronRight,
  Calendar,
  FileText,
} from 'lucide-react';
import { format, addWeeks, startOfWeek } from 'date-fns';
import { ja } from 'date-fns/locale';

// ---------------------------------------------------------------------------
// Types
// ---------------------------------------------------------------------------

interface WeeklyPlan {
  id: number;
  classroom_id: number;
  week_start_date: string;
  plan_content: Record<string, unknown> | null;
  status: string;
  created_by: number | null;
  creator?: { id: number; full_name: string } | null;
  created_at: string;
  updated_at: string;
}

function nl(t: string | null | undefined): string {
  if (!t) return '';
  return t.replace(/\\r\\n|\\n|\\r/g, '\n').replace(/\r\n|\r/g, '\n');
}

function getWeekStart(date: Date): Date {
  return startOfWeek(date, { weekStartsOn: 1 });
}

// ---------------------------------------------------------------------------
// Main
// ---------------------------------------------------------------------------

export default function WeeklyPlansPage() {
  const [weekOffset, setWeekOffset] = useState(0);

  const currentWeekStart = useMemo(() => {
    const base = getWeekStart(new Date());
    return addWeeks(base, weekOffset);
  }, [weekOffset]);

  const weekStartStr = format(currentWeekStart, 'yyyy-MM-dd');
  const weekEndStr = format(addWeeks(currentWeekStart, 1), 'yyyy-MM-dd');
  const weekLabel = `${format(currentWeekStart, 'yyyy年M月d日', { locale: ja })} 〜 ${format(addWeeks(currentWeekStart, 0), 'M月d日', { locale: ja })}週`;

  // Fetch all plans (recent)
  const { data: plans = [], isLoading } = useQuery({
    queryKey: ['staff', 'weekly-plans', weekStartStr],
    queryFn: async () => {
      const res = await api.get('/api/staff/weekly-plans', {
        params: { per_page: 50 },
      });
      const payload = res.data?.data;
      if (Array.isArray(payload)) return payload as WeeklyPlan[];
      if (payload?.data && Array.isArray(payload.data)) return payload.data as WeeklyPlan[];
      return [] as WeeklyPlan[];
    },
  });

  // Group plans by week
  const plansByWeek = useMemo(() => {
    const map: Record<string, WeeklyPlan[]> = {};
    plans.forEach((p) => {
      const week = p.week_start_date?.split('T')[0] || '';
      if (!map[week]) map[week] = [];
      map[week].push(p);
    });
    return map;
  }, [plans]);

  const sortedWeeks = Object.keys(plansByWeek).sort().reverse();

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-bold text-[var(--neutral-foreground-1)]">週間計画</h1>
      </div>

      <p className="text-sm text-[var(--neutral-foreground-3)]">
        教室の週間計画一覧（{plans.length}件）
      </p>

      {/* Plan list */}
      {isLoading ? (
        <div className="space-y-3">{[...Array(5)].map((_, i) => <Skeleton key={i} className="h-20 rounded-lg" />)}</div>
      ) : plans.length === 0 ? (
        <Card>
          <CardBody>
            <div className="py-12 text-center text-[var(--neutral-foreground-4)]">
              <FileText className="mx-auto mb-3 h-12 w-12" />
              <p className="text-sm">週間計画がありません</p>
            </div>
          </CardBody>
        </Card>
      ) : (
        <div className="overflow-x-auto rounded-lg border border-[var(--neutral-stroke-2)]">
          <table className="w-full text-sm">
            <thead>
              <tr className="border-b border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-3)]">
                <th className="px-3 py-2 text-left text-xs font-semibold text-[var(--neutral-foreground-3)]">ID</th>
                <th className="px-3 py-2 text-left text-xs font-semibold text-[var(--neutral-foreground-3)]">対象週</th>
                <th className="px-3 py-2 text-left text-xs font-semibold text-[var(--neutral-foreground-3)]">状態</th>
                <th className="px-3 py-2 text-left text-xs font-semibold text-[var(--neutral-foreground-3)]">内容</th>
                <th className="px-3 py-2 text-left text-xs font-semibold text-[var(--neutral-foreground-3)]">更新日</th>
              </tr>
            </thead>
            <tbody>
              {plans.map((plan) => {
                const weekDate = plan.week_start_date?.split('T')[0] || '';
                const hasContent = plan.plan_content && Object.keys(plan.plan_content).length > 0;

                return (
                  <tr key={plan.id} className="border-b border-[var(--neutral-stroke-3)] hover:bg-[var(--neutral-background-3)]">
                    <td className="px-3 py-2 text-[var(--neutral-foreground-4)]">{plan.id}</td>
                    <td className="px-3 py-2 font-medium text-[var(--neutral-foreground-1)]">
                      {weekDate ? format(new Date(weekDate), 'yyyy/MM/dd', { locale: ja }) : '-'}〜
                    </td>
                    <td className="px-3 py-2">
                      <Badge variant={plan.status === 'published' ? 'success' : 'warning'}>
                        {plan.status === 'published' ? '公開済み' : '下書き'}
                      </Badge>
                    </td>
                    <td className="px-3 py-2 text-xs text-[var(--neutral-foreground-3)]">
                      {hasContent ? '内容あり' : '未入力'}
                    </td>
                    <td className="px-3 py-2 text-xs text-[var(--neutral-foreground-3)]">
                      {plan.updated_at ? format(new Date(plan.updated_at), 'yyyy/MM/dd HH:mm') : '-'}
                    </td>
                  </tr>
                );
              })}
            </tbody>
          </table>
        </div>
      )}
    </div>
  );
}
