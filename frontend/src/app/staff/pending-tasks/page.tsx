'use client';

import { useState, useMemo } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import Link from 'next/link';
import api from '@/lib/api';
import { Card, CardBody } from '@/components/ui/Card';
import { Button } from '@/components/ui/Button';
import { Badge } from '@/components/ui/Badge';
import { SkeletonList } from '@/components/ui/Skeleton';
import { useToast } from '@/components/ui/Toast';
import { MaterialIcon } from '@/components/ui/MaterialIcon';

// --- Types ---
interface PlanTask {
  student_id: number;
  student_name: string;
  support_start_date: string | null;
  plan_id: number | null;
  latest_plan_date: string | null;
  days_since_plan: number | null;
  status_code: 'none' | 'draft' | 'needs_confirm' | 'outdated';
  has_newer: boolean;
  is_hidden: boolean;
  target_period_start: string | null;
  target_period_end: string | null;
  plan_number: number | null;
}

interface MonitoringTask {
  student_id: number;
  student_name: string;
  support_start_date: string;
  monitoring_id: number | null;
  monitoring_deadline: string;
  days_since_monitoring: number | null;
  status_code: 'none' | 'draft' | 'needs_confirm' | 'outdated' | 'urgent';
  has_newer: boolean;
  is_hidden: boolean;
  guardian_confirmed: boolean;
  next_plan_deadline: string;
  days_left: number;
}

interface KakehashiTask {
  student_id: number;
  student_name: string;
  period_id: number;
  period_name: string;
  submission_deadline: string;
  start_date: string;
  end_date: string;
  days_left: number;
  kakehashi_id: number | null;
  status_code: 'overdue' | 'urgent' | 'warning' | 'draft' | 'needs_confirm';
  is_submitted?: boolean;
  guardian_confirmed?: boolean;
}

interface PendingTasksData {
  plans: PlanTask[];
  monitoring: MonitoringTask[];
  guardian_kakehashi: KakehashiTask[];
  staff_kakehashi: KakehashiTask[];
}

interface ApiResponse {
  success: boolean;
  data: PendingTasksData;
  summary: {
    plans: number;
    monitoring: number;
    guardian_kakehashi: number;
    staff_kakehashi: number;
  };
  total_count: number;
}

// --- Helpers ---
function formatDate(dateStr: string | null | undefined): string {
  if (!dateStr) return '-';
  const d = new Date(dateStr);
  return `${d.getFullYear()}年${d.getMonth() + 1}月${d.getDate()}日`;
}

function formatPeriod(start: string | null, end: string | null): string {
  if (!start || !end) return '-';
  const s = new Date(start);
  const e = new Date(end);
  return `${s.getFullYear()}/${String(s.getMonth() + 1).padStart(2, '0')}/${String(s.getDate()).padStart(2, '0')} ~ ${e.getFullYear()}/${String(e.getMonth() + 1).padStart(2, '0')}/${String(e.getDate()).padStart(2, '0')}`;
}

function formatTargetPeriod(start: string | null, end: string | null, number: number | null): string {
  if (!start || !end) return '-';
  const s = new Date(start);
  const e = new Date(end);
  const prefix = number ? `${number}回目: ` : '';
  return `${prefix}${s.getFullYear()}年${s.getMonth() + 1}月~${e.getFullYear()}年${e.getMonth() + 1}月`;
}

type StatusVariant = 'danger' | 'warning' | 'info' | 'success' | 'primary' | 'default';

function getStatusBadge(statusCode: string, daysLeft?: number, daysSincePlan?: number | null): { label: string; variant: StatusVariant } {
  switch (statusCode) {
    case 'none':
      return { label: '未作成', variant: 'danger' };
    case 'draft':
      return { label: '下書き', variant: 'info' };
    case 'needs_confirm':
      return { label: '要保護者確認', variant: 'primary' };
    case 'outdated':
      if (daysSincePlan !== undefined && daysSincePlan !== null && daysSincePlan >= 180) {
        return { label: `期限切れ（${Math.floor(daysSincePlan / 30)}ヶ月経過）`, variant: 'default' };
      }
      if (daysLeft !== undefined && daysLeft < 0) {
        return { label: `期限切れ（${Math.abs(daysLeft)}日超過）`, variant: 'default' };
      }
      return { label: '期限切れ', variant: 'default' };
    case 'urgent':
      if (daysLeft !== undefined) {
        return { label: `緊急（残り${daysLeft}日）`, variant: 'danger' };
      }
      return { label: '緊急', variant: 'danger' };
    case 'overdue':
      if (daysLeft !== undefined && daysLeft < 0) {
        return { label: `期限切れ（${Math.abs(daysLeft)}日経過）`, variant: 'default' };
      }
      return { label: '期限切れ', variant: 'default' };
    case 'warning':
      if (daysLeft !== undefined) {
        return { label: `未提出（残り${daysLeft}日）`, variant: 'warning' };
      }
      return { label: '未提出', variant: 'warning' };
    default:
      return { label: statusCode, variant: 'default' };
  }
}

type CategoryFilter = '' | 'plan' | 'monitoring' | 'guardian_kakehashi' | 'staff_kakehashi';
type SortType = 'deadline_asc' | 'deadline_desc' | 'name_asc';

export default function PendingTasksPage() {
  const queryClient = useQueryClient();
  const toast = useToast();
  const [nameFilter, setNameFilter] = useState('');
  const [categoryFilter, setCategoryFilter] = useState<CategoryFilter>('');
  const [sortType, setSortType] = useState<SortType>('deadline_asc');

  const { data, isLoading } = useQuery({
    queryKey: ['staff', 'pending-tasks'],
    queryFn: async () => {
      const response = await api.get<ApiResponse>('/api/staff/pending-tasks');
      return response.data;
    },
  });

  const hideMutation = useMutation({
    mutationFn: async (params: Record<string, string | number>) => {
      await api.post('/api/staff/pending-tasks/toggle-hide', params);
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['staff', 'pending-tasks'] });
      toast.success('非表示にしました');
    },
    onError: () => toast.error('エラーが発生しました'),
  });

  // Filter logic
  const filteredData = useMemo(() => {
    if (!data?.data) return null;
    const d = data.data;
    const lowerName = nameFilter.toLowerCase();

    const filterByName = <T extends { student_name: string }>(items: T[]): T[] => {
      if (!lowerName) return items;
      return items.filter(i => i.student_name.toLowerCase().includes(lowerName));
    };

    return {
      plans: categoryFilter === '' || categoryFilter === 'plan' ? filterByName(d.plans) : [],
      monitoring: categoryFilter === '' || categoryFilter === 'monitoring' ? filterByName(d.monitoring) : [],
      guardian_kakehashi: categoryFilter === '' || categoryFilter === 'guardian_kakehashi' ? filterByName(d.guardian_kakehashi) : [],
      staff_kakehashi: categoryFilter === '' || categoryFilter === 'staff_kakehashi' ? filterByName(d.staff_kakehashi) : [],
    };
  }, [data, nameFilter, categoryFilter]);

  const totalFiltered = filteredData
    ? filteredData.plans.length + filteredData.monitoring.length + filteredData.guardian_kakehashi.length + filteredData.staff_kakehashi.length
    : 0;

  const totalCount = data?.total_count ?? 0;
  const hasFilter = nameFilter || categoryFilter;

  return (
    <div className="space-y-6">
      {/* Page Header */}
      <div className="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h1 className="text-2xl font-bold text-[var(--neutral-foreground-1)]">未作成タスク一覧</h1>
          <p className="text-sm text-[var(--neutral-foreground-3)]">個別支援計画書・モニタリング・かけはしの未作成タスク</p>
        </div>
      </div>

      {/* Filters */}
      <Card>
        <CardBody>
          <div className="flex flex-wrap items-end gap-3">
            <div className="flex flex-col gap-1">
              <label className="text-xs font-semibold text-[var(--neutral-foreground-3)]">生徒名で検索</label>
              <div className="relative">
                <MaterialIcon name="search" size={16} className="absolute left-2.5 top-2 text-[var(--neutral-foreground-4)]" />
                <input
                  type="text"
                  value={nameFilter}
                  onChange={e => setNameFilter(e.target.value)}
                  placeholder="生徒名を入力..."
                  className="rounded-md border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-1)] px-3 py-1.5 pl-8 text-sm text-[var(--neutral-foreground-1)] outline-none focus:border-[var(--brand-80)] focus:ring-2 focus:ring-[var(--brand-160)]"
                />
              </div>
            </div>
            <div className="flex flex-col gap-1">
              <label className="text-xs font-semibold text-[var(--neutral-foreground-3)]">カテゴリ</label>
              <select
                value={categoryFilter}
                onChange={e => setCategoryFilter(e.target.value as CategoryFilter)}
                className="rounded-md border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-1)] px-3 py-1.5 text-sm text-[var(--neutral-foreground-1)] outline-none focus:border-[var(--brand-80)]"
              >
                <option value="">全て</option>
                <option value="plan">個別支援計画書</option>
                <option value="monitoring">モニタリング</option>
                <option value="guardian_kakehashi">かけはし（保護者）</option>
                <option value="staff_kakehashi">かけはし（職員）</option>
              </select>
            </div>
            <div className="flex flex-col gap-1">
              <label className="text-xs font-semibold text-[var(--neutral-foreground-3)]">並び替え</label>
              <select
                value={sortType}
                onChange={e => setSortType(e.target.value as SortType)}
                className="rounded-md border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-1)] px-3 py-1.5 text-sm text-[var(--neutral-foreground-1)] outline-none focus:border-[var(--brand-80)]"
              >
                <option value="deadline_asc">期限が近い順</option>
                <option value="deadline_desc">期限が遠い順</option>
                <option value="name_asc">生徒名順</option>
              </select>
            </div>
          </div>
          <div className="mt-3 border-t border-[var(--neutral-stroke-2)] pt-3 text-xs text-[var(--neutral-foreground-3)]">
            {hasFilter
              ? `${totalFiltered}件の未作成タスク（フィルター適用中）`
              : `${totalCount}件の未作成タスク`}
          </div>
        </CardBody>
      </Card>

      {isLoading ? (
        <SkeletonList items={5} />
      ) : filteredData ? (
        <>
          {/* Summary Cards */}
          <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
            <SummaryCard
              title="個別支援計画書"
              count={data?.summary.plans ?? 0}
              variant={data?.summary.plans ? 'danger' : 'success'}
            />
            <SummaryCard
              title="モニタリング"
              count={data?.summary.monitoring ?? 0}
              variant={data?.summary.monitoring ? 'warning' : 'success'}
            />
            <SummaryCard
              title="保護者かけはし"
              count={data?.summary.guardian_kakehashi ?? 0}
              variant={data?.summary.guardian_kakehashi ? 'warning' : 'success'}
            />
            <SummaryCard
              title="スタッフかけはし"
              count={data?.summary.staff_kakehashi ?? 0}
              variant={data?.summary.staff_kakehashi ? 'warning' : 'success'}
            />
          </div>

          {hasFilter && totalFiltered === 0 && (
            <Card>
              <CardBody>
                <p className="py-8 text-center text-sm text-[var(--neutral-foreground-3)]">該当するタスクはありません</p>
              </CardBody>
            </Card>
          )}

          {/* Plans Section */}
          {(categoryFilter === '' || categoryFilter === 'plan') && (
            <TaskSection
              title="個別支援計画書"
              icon={<MaterialIcon name="description" size={20} />}
              count={filteredData.plans.length}
              emptyMessage="すべての生徒の個別支援計画書が最新です"
              badgeText={filteredData.plans.length > 0 ? `${filteredData.plans.length}件の対応が必要です` : 'すべて最新です'}
              badgeSuccess={filteredData.plans.length === 0}
            >
              {filteredData.plans.length > 0 && (
                <div className="overflow-x-auto">
                  <table className="w-full text-sm">
                    <thead>
                      <tr className="border-b border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-3)]">
                        <th className="px-4 py-3 text-left font-semibold text-[var(--neutral-foreground-2)]">生徒名</th>
                        <th className="px-4 py-3 text-left font-semibold text-[var(--neutral-foreground-2)]">対象期間</th>
                        <th className="px-4 py-3 text-left font-semibold text-[var(--neutral-foreground-2)]">最新計画日</th>
                        <th className="px-4 py-3 text-left font-semibold text-[var(--neutral-foreground-2)]">状態</th>
                        <th className="px-4 py-3 text-left font-semibold text-[var(--neutral-foreground-2)]">アクション</th>
                      </tr>
                    </thead>
                    <tbody>
                      {sortItems(filteredData.plans, sortType, p => p.latest_plan_date ? new Date(p.latest_plan_date).getTime() + 180 * 86400000 : p.support_start_date ? new Date(p.support_start_date).getTime() : Infinity).map((plan) => {
                        const status = getPlanStatusDisplay(plan);
                        return (
                          <tr key={`plan-${plan.student_id}`} className="border-b border-[var(--neutral-stroke-2)] hover:bg-[var(--neutral-background-2)]">
                            <td className="px-4 py-3 font-medium text-[var(--neutral-foreground-1)]">{plan.student_name}</td>
                            <td className="px-4 py-3 text-[var(--neutral-foreground-2)]">{formatTargetPeriod(plan.target_period_start, plan.target_period_end, plan.plan_number)}</td>
                            <td className="px-4 py-3 text-[var(--neutral-foreground-2)]">{formatDate(plan.latest_plan_date)}</td>
                            <td className="px-4 py-3">
                              <div className="flex flex-wrap items-center gap-1">
                                <Badge variant={status.mainVariant}>{status.mainLabel}</Badge>
                                {status.deadlineLabel && <Badge variant={status.deadlineVariant!}>{status.deadlineLabel}</Badge>}
                                {plan.has_newer && <Badge variant="info">最新あり</Badge>}
                              </div>
                            </td>
                            <td className="px-4 py-3">
                              <div className="flex items-center gap-2">
                                <Link
                                  href={`/staff/kobetsu-plan?student_id=${plan.student_id}${plan.plan_id ? `&plan_id=${plan.plan_id}` : ''}`}
                                  className="rounded-md bg-[var(--neutral-background-3)] px-3 py-1.5 text-xs font-medium text-[var(--neutral-foreground-1)] hover:bg-[var(--neutral-background-4)]"
                                >
                                  {plan.status_code === 'needs_confirm' ? '確認依頼' : '計画書を作成'}
                                </Link>
                                {plan.plan_id && (
                                  <Button
                                    variant="ghost"
                                    size="sm"
                                    onClick={() => {
                                      if (confirm('この項目を非表示にしますか？')) {
                                        hideMutation.mutate({ type: 'plan', id: plan.plan_id!, action: 'hide' });
                                      }
                                    }}
                                    disabled={hideMutation.isPending}
                                  >
                                    非表示
                                  </Button>
                                )}
                              </div>
                            </td>
                          </tr>
                        );
                      })}
                    </tbody>
                  </table>
                </div>
              )}
            </TaskSection>
          )}

          {/* Monitoring Section */}
          {(categoryFilter === '' || categoryFilter === 'monitoring') && (
            <TaskSection
              title="モニタリング"
              icon={<MaterialIcon name="trending_up" size={20} />}
              count={filteredData.monitoring.length}
              emptyMessage="すべての生徒のモニタリングが最新です"
              badgeText={filteredData.monitoring.length > 0 ? `${filteredData.monitoring.length}件の対応が必要です` : 'すべて最新です'}
              badgeSuccess={filteredData.monitoring.length === 0}
            >
              {filteredData.monitoring.length > 0 && (
                <div className="overflow-x-auto">
                  <table className="w-full text-sm">
                    <thead>
                      <tr className="border-b border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-3)]">
                        <th className="px-4 py-3 text-left font-semibold text-[var(--neutral-foreground-2)]">生徒名</th>
                        <th className="px-4 py-3 text-left font-semibold text-[var(--neutral-foreground-2)]">支援開始日</th>
                        <th className="px-4 py-3 text-left font-semibold text-[var(--neutral-foreground-2)]">モニタリング期限</th>
                        <th className="px-4 py-3 text-left font-semibold text-[var(--neutral-foreground-2)]">状態</th>
                        <th className="px-4 py-3 text-left font-semibold text-[var(--neutral-foreground-2)]">アクション</th>
                      </tr>
                    </thead>
                    <tbody>
                      {sortItems(filteredData.monitoring, sortType, m => m.monitoring_deadline ? new Date(m.monitoring_deadline).getTime() : Infinity).map((mon) => {
                        const status = getMonitoringStatusDisplay(mon);
                        return (
                          <tr key={`mon-${mon.student_id}`} className="border-b border-[var(--neutral-stroke-2)] hover:bg-[var(--neutral-background-2)]">
                            <td className="px-4 py-3 font-medium text-[var(--neutral-foreground-1)]">{mon.student_name}</td>
                            <td className="px-4 py-3 text-[var(--neutral-foreground-2)]">{formatDate(mon.support_start_date)}</td>
                            <td className="px-4 py-3 text-[var(--neutral-foreground-2)]">{formatDate(mon.monitoring_deadline)}</td>
                            <td className="px-4 py-3">
                              <div className="flex flex-wrap items-center gap-1">
                                <Badge variant={status.mainVariant}>{status.mainLabel}</Badge>
                                {status.deadlineLabel && <Badge variant={status.deadlineVariant!}>{status.deadlineLabel}</Badge>}
                                {mon.has_newer && <Badge variant="info">最新あり</Badge>}
                              </div>
                            </td>
                            <td className="px-4 py-3">
                              <div className="flex items-center gap-2">
                                <Link
                                  href={`/staff/kobetsu-monitoring?student_id=${mon.student_id}`}
                                  className="rounded-md bg-[var(--neutral-background-3)] px-3 py-1.5 text-xs font-medium text-[var(--neutral-foreground-1)] hover:bg-[var(--neutral-background-4)]"
                                >
                                  {mon.status_code === 'needs_confirm' ? '確認依頼' : 'モニタリング作成'}
                                </Link>
                                {mon.monitoring_id ? (
                                  <Button
                                    variant="ghost"
                                    size="sm"
                                    onClick={() => {
                                      if (confirm('この項目を非表示にしますか？')) {
                                        hideMutation.mutate({ type: 'monitoring', id: mon.monitoring_id!, action: 'hide' });
                                      }
                                    }}
                                    disabled={hideMutation.isPending}
                                  >
                                    非表示
                                  </Button>
                                ) : (
                                  <Button
                                    variant="ghost"
                                    size="sm"
                                    onClick={() => {
                                      if (confirm('このモニタリングタスクを非表示にしますか？')) {
                                        hideMutation.mutate({ type: 'initial_monitoring', student_id: mon.student_id, action: 'hide' });
                                      }
                                    }}
                                    disabled={hideMutation.isPending}
                                  >
                                    非表示
                                  </Button>
                                )}
                              </div>
                            </td>
                          </tr>
                        );
                      })}
                    </tbody>
                  </table>
                </div>
              )}
            </TaskSection>
          )}

          {/* Guardian Kakehashi Section */}
          {(categoryFilter === '' || categoryFilter === 'guardian_kakehashi') && (
            <TaskSection
              title="保護者かけはし"
              icon={<MaterialIcon name="handshake" size={20} />}
              count={filteredData.guardian_kakehashi.length}
              emptyMessage="すべての保護者かけはしが提出済みです"
              badgeText={filteredData.guardian_kakehashi.length > 0 ? `${filteredData.guardian_kakehashi.length}件の未提出があります` : 'すべて提出済みです'}
              badgeSuccess={filteredData.guardian_kakehashi.length === 0}
            >
              {filteredData.guardian_kakehashi.length > 0 && (
                <div className="overflow-x-auto">
                  <table className="w-full text-sm">
                    <thead>
                      <tr className="border-b border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-3)]">
                        <th className="px-4 py-3 text-left font-semibold text-[var(--neutral-foreground-2)]">生徒名</th>
                        <th className="px-4 py-3 text-left font-semibold text-[var(--neutral-foreground-2)]">期間名</th>
                        <th className="px-4 py-3 text-left font-semibold text-[var(--neutral-foreground-2)]">対象期間</th>
                        <th className="px-4 py-3 text-left font-semibold text-[var(--neutral-foreground-2)]">提出期限</th>
                        <th className="px-4 py-3 text-left font-semibold text-[var(--neutral-foreground-2)]">状態</th>
                        <th className="px-4 py-3 text-left font-semibold text-[var(--neutral-foreground-2)]">アクション</th>
                      </tr>
                    </thead>
                    <tbody>
                      {sortItems(filteredData.guardian_kakehashi, sortType, k => k.submission_deadline ? new Date(k.submission_deadline).getTime() : Infinity).map((kak) => {
                        const status = getStatusBadge(kak.status_code, kak.days_left);
                        return (
                          <tr key={`gk-${kak.student_id}-${kak.period_id}`} className="border-b border-[var(--neutral-stroke-2)] hover:bg-[var(--neutral-background-2)]">
                            <td className="px-4 py-3 font-medium text-[var(--neutral-foreground-1)]">{kak.student_name}</td>
                            <td className="px-4 py-3 text-[var(--neutral-foreground-2)]">{kak.period_name}</td>
                            <td className="px-4 py-3 text-[var(--neutral-foreground-2)]">{formatPeriod(kak.start_date, kak.end_date)}</td>
                            <td className="px-4 py-3 text-[var(--neutral-foreground-2)]">{formatDate(kak.submission_deadline)}</td>
                            <td className="px-4 py-3">
                              <Badge variant={status.variant}>{status.label}</Badge>
                            </td>
                            <td className="px-4 py-3">
                              <div className="flex items-center gap-2">
                                <Link
                                  href={`/staff/kakehashi-guardian?student_id=${kak.student_id}&period_id=${kak.period_id}`}
                                  className="rounded-md bg-[var(--neutral-background-3)] px-3 py-1.5 text-xs font-medium text-[var(--neutral-foreground-1)] hover:bg-[var(--neutral-background-4)]"
                                >
                                  確認・催促
                                </Link>
                                <Button
                                  variant="ghost"
                                  size="sm"
                                  onClick={() => {
                                    if (confirm('このかけはしを非表示にしますか？')) {
                                      hideMutation.mutate({ type: 'guardian_kakehashi', period_id: kak.period_id, student_id: kak.student_id, action: 'hide' });
                                    }
                                  }}
                                  disabled={hideMutation.isPending}
                                >
                                  非表示
                                </Button>
                              </div>
                            </td>
                          </tr>
                        );
                      })}
                    </tbody>
                  </table>
                </div>
              )}
            </TaskSection>
          )}

          {/* Staff Kakehashi Section */}
          {(categoryFilter === '' || categoryFilter === 'staff_kakehashi') && (
            <TaskSection
              title="スタッフかけはし"
              icon={<MaterialIcon name="handshake" size={20} />}
              count={filteredData.staff_kakehashi.length}
              emptyMessage="すべてのスタッフかけはしが作成済みです"
              badgeText={filteredData.staff_kakehashi.length > 0 ? `${filteredData.staff_kakehashi.length}件の未作成があります` : 'すべて作成済みです'}
              badgeSuccess={filteredData.staff_kakehashi.length === 0}
            >
              {filteredData.staff_kakehashi.length > 0 && (
                <div className="overflow-x-auto">
                  <table className="w-full text-sm">
                    <thead>
                      <tr className="border-b border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-3)]">
                        <th className="px-4 py-3 text-left font-semibold text-[var(--neutral-foreground-2)]">生徒名</th>
                        <th className="px-4 py-3 text-left font-semibold text-[var(--neutral-foreground-2)]">期間名</th>
                        <th className="px-4 py-3 text-left font-semibold text-[var(--neutral-foreground-2)]">対象期間</th>
                        <th className="px-4 py-3 text-left font-semibold text-[var(--neutral-foreground-2)]">提出期限</th>
                        <th className="px-4 py-3 text-left font-semibold text-[var(--neutral-foreground-2)]">状態</th>
                        <th className="px-4 py-3 text-left font-semibold text-[var(--neutral-foreground-2)]">アクション</th>
                      </tr>
                    </thead>
                    <tbody>
                      {sortItems(filteredData.staff_kakehashi, sortType, k => k.submission_deadline ? new Date(k.submission_deadline).getTime() : Infinity).map((kak) => {
                        const statusDisplay = getStaffKakehashiStatusDisplay(kak);
                        return (
                          <tr key={`sk-${kak.student_id}-${kak.period_id}`} className="border-b border-[var(--neutral-stroke-2)] hover:bg-[var(--neutral-background-2)]">
                            <td className="px-4 py-3 font-medium text-[var(--neutral-foreground-1)]">{kak.student_name}</td>
                            <td className="px-4 py-3 text-[var(--neutral-foreground-2)]">{kak.period_name}</td>
                            <td className="px-4 py-3 text-[var(--neutral-foreground-2)]">{formatPeriod(kak.start_date, kak.end_date)}</td>
                            <td className="px-4 py-3 text-[var(--neutral-foreground-2)]">{formatDate(kak.submission_deadline)}</td>
                            <td className="px-4 py-3">
                              <div className="flex flex-wrap items-center gap-1">
                                <Badge variant={statusDisplay.mainVariant}>{statusDisplay.mainLabel}</Badge>
                                {statusDisplay.deadlineLabel && <Badge variant={statusDisplay.deadlineVariant!}>{statusDisplay.deadlineLabel}</Badge>}
                              </div>
                            </td>
                            <td className="px-4 py-3">
                              <div className="flex items-center gap-2">
                                <Link
                                  href={`/staff/kakehashi-staff?student_id=${kak.student_id}&period_id=${kak.period_id}`}
                                  className="rounded-md bg-[var(--neutral-background-3)] px-3 py-1.5 text-xs font-medium text-[var(--neutral-foreground-1)] hover:bg-[var(--neutral-background-4)]"
                                >
                                  {kak.status_code === 'needs_confirm' ? '確認依頼' : kak.status_code === 'draft' ? '編集する' : '作成する'}
                                </Link>
                                <Button
                                  variant="ghost"
                                  size="sm"
                                  onClick={() => {
                                    if (confirm('このかけはしを非表示にしますか？')) {
                                      hideMutation.mutate({ type: 'staff_kakehashi', period_id: kak.period_id, student_id: kak.student_id, action: 'hide' });
                                    }
                                  }}
                                  disabled={hideMutation.isPending}
                                >
                                  非表示
                                </Button>
                              </div>
                            </td>
                          </tr>
                        );
                      })}
                    </tbody>
                  </table>
                </div>
              )}
            </TaskSection>
          )}

          {/* All empty state */}
          {totalCount === 0 && (
            <Card>
              <CardBody>
                <div className="flex flex-col items-center py-12">
                  <MaterialIcon name="check_circle" size={48} className="mb-3 text-[var(--status-success-fg)]" />
                  <p className="text-sm font-medium text-[var(--neutral-foreground-2)]">すべてのタスクが完了しています</p>
                  <p className="text-xs text-[var(--neutral-foreground-3)]">お疲れさまです!</p>
                </div>
              </CardBody>
            </Card>
          )}
        </>
      ) : null}
    </div>
  );
}

// --- Sub-components ---

function SummaryCard({ title, count, variant }: { title: string; count: number; variant: 'danger' | 'warning' | 'success' }) {
  const borderColors = {
    danger: 'border-l-[var(--status-danger-fg)]',
    warning: 'border-l-[var(--status-warning-fg)]',
    success: 'border-l-[var(--status-success-fg)]',
  };
  const bgColors = {
    danger: 'bg-[var(--status-danger-bg)]',
    warning: 'bg-[var(--status-warning-bg)]',
    success: 'bg-[var(--status-success-bg)]',
  };

  return (
    <div className={`flex items-center gap-3 rounded-md border-l-[3px] ${borderColors[variant]} ${bgColors[variant]} px-4 py-3 shadow-sm`}>
      <span className="text-xs font-medium text-[var(--neutral-foreground-3)]">{title}</span>
      <span className="text-lg font-bold text-[var(--neutral-foreground-1)]">{count}件</span>
    </div>
  );
}

function TaskSection({
  title,
  icon,
  count,
  emptyMessage,
  badgeText,
  badgeSuccess,
  children,
}: {
  title: string;
  icon: React.ReactNode;
  count: number;
  emptyMessage: string;
  badgeText: string;
  badgeSuccess: boolean;
  children: React.ReactNode;
}) {
  return (
    <Card>
      <div className="flex items-center justify-between border-b-[3px] border-[var(--brand-80)] px-4 py-3">
        <h2 className="flex items-center gap-2 text-lg font-semibold text-[var(--brand-80)]">
          {icon}
          {title}
        </h2>
        <Badge variant={badgeSuccess ? 'success' : 'danger'}>{badgeText}</Badge>
      </div>
      {count > 0 ? (
        children
      ) : (
        <CardBody>
          <div className="flex flex-col items-center py-8">
            <MaterialIcon name="check_circle" size={32} className="mb-2 text-[var(--status-success-fg)]" />
            <p className="text-sm text-[var(--neutral-foreground-3)]">{emptyMessage}</p>
            <p className="text-xs text-[var(--neutral-foreground-4)]">対応が必要な項目はありません。</p>
          </div>
        </CardBody>
      )}
    </Card>
  );
}

// --- Status display helpers ---

function getPlanStatusDisplay(plan: PlanTask): { mainLabel: string; mainVariant: StatusVariant; deadlineLabel?: string; deadlineVariant?: StatusVariant } {
  const daysSince = plan.days_since_plan;

  // Deadline sub-badge for draft/needs_confirm
  let deadlineLabel: string | undefined;
  let deadlineVariant: StatusVariant | undefined;
  if (daysSince !== null && daysSince !== undefined) {
    if (daysSince >= 180) {
      deadlineLabel = `期限切れ（${Math.floor(daysSince / 30)}ヶ月経過）`;
      deadlineVariant = 'default';
    } else if (daysSince >= 150) {
      deadlineLabel = `残り${180 - daysSince}日`;
      deadlineVariant = 'danger';
    }
  }

  if (plan.status_code === 'none') {
    return { mainLabel: '未作成', mainVariant: 'danger' };
  } else if (plan.status_code === 'needs_confirm') {
    return { mainLabel: '要保護者確認', mainVariant: 'primary', deadlineLabel, deadlineVariant };
  } else if (plan.status_code === 'draft') {
    return { mainLabel: '下書き', mainVariant: 'info', deadlineLabel, deadlineVariant };
  } else if (daysSince !== null && daysSince >= 180) {
    return { mainLabel: `期限切れ（${Math.floor(daysSince / 30)}ヶ月経過）`, mainVariant: 'default' };
  } else if (daysSince !== null && daysSince >= 150) {
    return { mainLabel: `1か月以内（残り${180 - daysSince}日）`, mainVariant: 'danger' };
  }
  return { mainLabel: '対応が必要', mainVariant: 'warning' };
}

function getMonitoringStatusDisplay(mon: MonitoringTask): { mainLabel: string; mainVariant: StatusVariant; deadlineLabel?: string; deadlineVariant?: StatusVariant } {
  const daysLeft = mon.days_left;

  // Deadline sub-badge
  let deadlineLabel: string | undefined;
  let deadlineVariant: StatusVariant | undefined;
  if (daysLeft < 0) {
    deadlineLabel = `期限切れ（${Math.abs(daysLeft)}日超過）`;
    deadlineVariant = 'default';
  } else if (daysLeft <= 30) {
    deadlineLabel = `残り${daysLeft}日`;
    deadlineVariant = 'danger';
  }

  if (mon.status_code === 'none') {
    return { mainLabel: '初回モニタリング未作成', mainVariant: 'danger', deadlineLabel: undefined, deadlineVariant: undefined };
  } else if (mon.status_code === 'needs_confirm') {
    return { mainLabel: '要保護者確認', mainVariant: 'primary', deadlineLabel, deadlineVariant };
  } else if (mon.status_code === 'draft') {
    return { mainLabel: '下書き', mainVariant: 'info', deadlineLabel, deadlineVariant };
  } else if (daysLeft < 0) {
    return { mainLabel: `期限切れ（${Math.abs(daysLeft)}日超過）`, mainVariant: 'default' };
  } else if (daysLeft <= 30) {
    return { mainLabel: `1か月以内（残り${daysLeft}日）`, mainVariant: 'danger' };
  }
  return { mainLabel: '対応が必要', mainVariant: 'warning' };
}

function getStaffKakehashiStatusDisplay(kak: KakehashiTask): { mainLabel: string; mainVariant: StatusVariant; deadlineLabel?: string; deadlineVariant?: StatusVariant } {
  const daysLeft = kak.days_left;

  // Deadline sub-badge for draft/needs_confirm
  let deadlineLabel: string | undefined;
  let deadlineVariant: StatusVariant | undefined;
  if (daysLeft < 0) {
    deadlineLabel = `期限切れ（${Math.abs(daysLeft)}日経過）`;
    deadlineVariant = 'default';
  } else if (daysLeft <= 30) {
    deadlineLabel = `残り${daysLeft}日`;
    deadlineVariant = daysLeft <= 7 ? 'danger' : 'warning';
  }

  if (kak.status_code === 'needs_confirm') {
    return { mainLabel: '要保護者確認', mainVariant: 'primary', deadlineLabel, deadlineVariant };
  } else if (kak.status_code === 'draft') {
    return { mainLabel: '下書き', mainVariant: 'info', deadlineLabel, deadlineVariant };
  } else if (daysLeft < 0) {
    return { mainLabel: `期限切れ（${Math.abs(daysLeft)}日経過）`, mainVariant: 'default' };
  } else if (daysLeft <= 7) {
    return { mainLabel: `緊急（残り${daysLeft}日）`, mainVariant: 'danger' };
  }
  return { mainLabel: `未作成（残り${daysLeft}日）`, mainVariant: 'warning' };
}

// --- Sort helper ---
function sortItems<T extends { student_name: string }>(items: T[], sortType: SortType, getDeadline: (item: T) => number): T[] {
  const sorted = [...items];
  sorted.sort((a, b) => {
    if (sortType === 'name_asc') {
      return a.student_name.localeCompare(b.student_name, 'ja');
    } else if (sortType === 'deadline_asc') {
      return getDeadline(a) - getDeadline(b);
    } else if (sortType === 'deadline_desc') {
      return getDeadline(b) - getDeadline(a);
    }
    return 0;
  });
  return sorted;
}
