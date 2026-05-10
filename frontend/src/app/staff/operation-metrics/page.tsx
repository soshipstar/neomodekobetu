'use client';

import { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import api from '@/lib/api';
import { Card, CardHeader, CardTitle, CardBody } from '@/components/ui/Card';
import { Badge } from '@/components/ui/Badge';
import { MaterialIcon } from '@/components/ui/MaterialIcon';
import { useWorkspace } from '@/hooks/useWorkspace';

interface MonthlyMetrics {
  classroom_id: number;
  classroom_name: string;
  service_type: string;
  capacity: number;
  year_month: string;
  opening_days: number;
  active_students: number;
  total_usage_days: number;
  avg_daily_users: number;
  utilization: number;
  over_cap_students: { student_id: number; student_name: string; days: number; cap: number }[];
  trend_6_months: { year_month: string; opening_days: number; total_usage: number; avg_daily_users: number; utilization: number }[];
  minimum_capacity_recommended: number;
}

function currentYearMonth(): string {
  const d = new Date();
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`;
}

export default function OperationMetricsPage() {
  const { terms } = useWorkspace();
  const [yearMonth, setYearMonth] = useState(currentYearMonth());

  const { data, isLoading } = useQuery({
    queryKey: ['staff', 'operation-metrics', yearMonth],
    queryFn: async () => {
      const res = await api.get<{ data: MonthlyMetrics }>('/api/staff/operation-metrics/monthly', {
        params: { year_month: yearMonth },
      });
      return res.data.data;
    },
  });

  const utilColor = (u: number) =>
    u >= 90 ? 'var(--status-success-fg)' :
    u >= 70 ? 'var(--brand-80)' :
    u >= 50 ? 'var(--status-warning-fg)' :
              'var(--status-danger-fg)';

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-bold text-[var(--neutral-foreground-1)]">運営指標ダッシュボード</h1>
        <p className="mt-1 text-sm text-[var(--neutral-foreground-3)]">
          稼働率・開所日数・延べ利用日数を月次で確認できます。
        </p>
      </div>

      <Card>
        <CardBody>
          <div className="flex flex-wrap items-end gap-3">
            <div>
              <label className="mb-1 block text-xs font-medium text-[var(--neutral-foreground-3)]">対象月</label>
              <input
                type="month"
                value={yearMonth}
                onChange={(e) => setYearMonth(e.target.value)}
                className="rounded-md border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-1)] px-3 py-2 text-sm"
              />
            </div>
          </div>
        </CardBody>
      </Card>

      {isLoading ? <p>読み込み中...</p> : !data ? null : (
        <>
          {/* メイン KPI */}
          <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
            <KpiCard label="稼働率" value={`${data.utilization}%`} color={utilColor(data.utilization)} />
            <KpiCard label="開所日数" value={`${data.opening_days} 日`} />
            <KpiCard label="延べ利用日数" value={`${data.total_usage_days} 日`} />
            <KpiCard label="1 日平均利用者" value={`${data.avg_daily_users} 名`} />
          </div>

          <div className="grid gap-3 lg:grid-cols-2">
            <Card>
              <CardHeader><CardTitle>定員と在籍状況</CardTitle></CardHeader>
              <CardBody>
                <div className="space-y-3 text-sm">
                  <RowKv label="事業所定員" value={data.capacity > 0 ? `${data.capacity} 名` : '未設定'} />
                  <RowKv label="制度上の最低定員" value={`${data.minimum_capacity_recommended} 名`} />
                  <RowKv label="在籍利用者数" value={`${data.active_students} 名`} />
                  <RowKv label="サービス種別" value={data.service_type} />
                </div>
                {data.capacity === 0 && (
                  <div className="mt-3 rounded border border-[var(--status-warning-fg)]/30 bg-[var(--status-warning-bg)] p-2 text-xs text-[var(--status-warning-fg)]">
                    定員が未設定です。事業所マスタで設定すると稼働率が算出されます。
                  </div>
                )}
              </CardBody>
            </Card>

            <Card>
              <CardHeader><CardTitle>{terms.client_plural}の月利用日数上限超過</CardTitle></CardHeader>
              <CardBody>
                {data.over_cap_students.length === 0 ? (
                  <p className="text-sm text-[var(--neutral-foreground-4)]">超過している{terms.client_plural}はいません。</p>
                ) : (
                  <table className="w-full text-sm">
                    <thead>
                      <tr className="border-b border-[var(--neutral-stroke-2)] text-xs text-[var(--neutral-foreground-3)]">
                        <th className="px-2 py-1 text-left">{terms.client}</th>
                        <th className="px-2 py-1 text-right">利用日数</th>
                        <th className="px-2 py-1 text-right">上限</th>
                      </tr>
                    </thead>
                    <tbody>
                      {data.over_cap_students.map((s) => (
                        <tr key={s.student_id} className="border-b border-[var(--neutral-stroke-3)]">
                          <td className="px-2 py-1">{s.student_name}</td>
                          <td className="px-2 py-1 text-right text-[var(--status-danger-fg)] font-bold">{s.days} 日</td>
                          <td className="px-2 py-1 text-right">{s.cap} 日</td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                )}
              </CardBody>
            </Card>
          </div>

          {/* 6 ヶ月推移 */}
          <Card>
            <CardHeader><CardTitle>過去 6 ヶ月の稼働推移</CardTitle></CardHeader>
            <CardBody>
              <div className="overflow-x-auto">
                <table className="w-full text-sm">
                  <thead>
                    <tr className="border-b border-[var(--neutral-stroke-2)] text-xs text-[var(--neutral-foreground-3)]">
                      <th className="px-3 py-2 text-left">月</th>
                      <th className="px-3 py-2 text-right">開所日</th>
                      <th className="px-3 py-2 text-right">延べ利用日</th>
                      <th className="px-3 py-2 text-right">1日平均</th>
                      <th className="px-3 py-2 text-right">稼働率</th>
                      <th className="px-3 py-2 text-left">バー</th>
                    </tr>
                  </thead>
                  <tbody>
                    {data.trend_6_months.map((t) => (
                      <tr key={t.year_month} className="border-b border-[var(--neutral-stroke-3)]">
                        <td className="px-3 py-2 font-mono">{t.year_month}</td>
                        <td className="px-3 py-2 text-right">{t.opening_days} 日</td>
                        <td className="px-3 py-2 text-right">{t.total_usage} 日</td>
                        <td className="px-3 py-2 text-right">{t.avg_daily_users}</td>
                        <td className="px-3 py-2 text-right font-bold" style={{ color: utilColor(t.utilization) }}>
                          {t.utilization}%
                        </td>
                        <td className="px-3 py-2">
                          <div className="h-2 w-32 rounded bg-[var(--neutral-background-3)]">
                            <div
                              className="h-2 rounded"
                              style={{ width: `${Math.min(t.utilization, 100)}%`, background: utilColor(t.utilization) }}
                            />
                          </div>
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            </CardBody>
          </Card>
        </>
      )}
    </div>
  );
}

function KpiCard({ label, value, color }: { label: string; value: string; color?: string }) {
  return (
    <div className="rounded-lg border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-1)] p-4">
      <div className="text-xs text-[var(--neutral-foreground-3)]">{label}</div>
      <div className="mt-1 text-2xl font-bold" style={{ color: color ?? 'var(--neutral-foreground-1)' }}>
        {value}
      </div>
    </div>
  );
}

function RowKv({ label, value }: { label: string; value: string }) {
  return (
    <div className="flex justify-between border-b border-[var(--neutral-stroke-3)] pb-2">
      <span className="text-[var(--neutral-foreground-3)]">{label}</span>
      <span className="font-medium text-[var(--neutral-foreground-1)]">{value}</span>
    </div>
  );
}
