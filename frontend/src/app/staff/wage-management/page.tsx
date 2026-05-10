'use client';

import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import api from '@/lib/api';
import { Card, CardHeader, CardTitle, CardBody } from '@/components/ui/Card';
import { Button } from '@/components/ui/Button';
import { Badge } from '@/components/ui/Badge';
import { useToast } from '@/components/ui/Toast';
import { MaterialIcon } from '@/components/ui/MaterialIcon';
import { useWorkspace } from '@/hooks/useWorkspace';

interface WagePeriodSummary {
  id: number;
  year_month: string;
  status: 'draft' | 'finalized' | 'paid';
  settlement_date: string | null;
  payment_date: string | null;
  finalized_at: string | null;
  paid_at: string | null;
  total_wage: number;
  average_wage: number;
  student_count: number;
}

interface WageRecord {
  id: number;
  wage_period_id: number;
  student_id: number;
  attendance_days: number;
  total_work_minutes: number;
  wage_eligible_hours: string | number;
  calculation_type: string;
  hourly_rate: string | number;
  piece_rate_amount: string | number;
  base_wage: string | number;
  overtime_minutes: number;
  overtime_wage: string | number;
  bonus: string | number;
  deductions: string | number;
  net_wage: string | number;
  notes: string | null;
  calculated_at: string | null;
  student?: {
    id: number;
    student_name: string;
    wage_calculation_type: string | null;
    hourly_rate: string | null;
    piece_rate_unit: string | null;
    piece_rate_amount: string | null;
    employment_status: string | null;
  };
}

interface WagePeriodDetail {
  id: number;
  classroom_id: number;
  year_month: string;
  status: 'draft' | 'finalized' | 'paid';
  settlement_date: string | null;
  payment_date: string | null;
  finalized_at: string | null;
  paid_at: string | null;
  notes: string | null;
}

const STATUS_VARIANT: Record<'draft' | 'finalized' | 'paid', 'default' | 'warning' | 'success'> = {
  draft: 'default',
  finalized: 'warning',
  paid: 'success',
};

const STATUS_LABEL: Record<'draft' | 'finalized' | 'paid', string> = {
  draft: '下書き',
  finalized: '確定',
  paid: '支払い済',
};

function fmtYen(value: string | number | null | undefined): string {
  const n = typeof value === 'string' ? Number(value) : value ?? 0;
  return `¥${Number(n).toLocaleString('ja-JP', { maximumFractionDigits: 0 })}`;
}

function currentYearMonth(): string {
  const d = new Date();
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`;
}

export default function WageManagementPage() {
  const { serviceType, terms } = useWorkspace();
  const queryClient = useQueryClient();
  const toast = useToast();
  const [yearMonth, setYearMonth] = useState(currentYearMonth());
  const [selectedPeriodId, setSelectedPeriodId] = useState<number | null>(null);

  const isEmployment = serviceType === 'employment_a' || serviceType === 'employment_b';

  const { data: periods = [], isLoading: loadingPeriods } = useQuery({
    queryKey: ['staff', 'wage-periods'],
    queryFn: async () => {
      const res = await api.get<{ data: WagePeriodSummary[] }>('/api/staff/wage-periods');
      return res.data.data;
    },
    enabled: isEmployment,
  });

  const { data: detail } = useQuery({
    queryKey: ['staff', 'wage-period', selectedPeriodId],
    queryFn: async () => {
      const res = await api.get<{ data: { period: WagePeriodDetail; records: WageRecord[] } }>(`/api/staff/wage-periods/${selectedPeriodId}`);
      return res.data.data;
    },
    enabled: !!selectedPeriodId && isEmployment,
  });

  const calculateMutation = useMutation({
    mutationFn: async (ym: string) => {
      const res = await api.post<{ data: { period: WagePeriodDetail; records: WageRecord[] } }>('/api/staff/wage-periods/calculate', { year_month: ym });
      return res.data.data;
    },
    onSuccess: (data) => {
      toast.success(`${data.period.year_month} の工賃を計算しました`);
      queryClient.invalidateQueries({ queryKey: ['staff', 'wage-periods'] });
      setSelectedPeriodId(data.period.id);
    },
    onError: () => toast.error('計算に失敗しました'),
  });

  const finalizeMutation = useMutation({
    mutationFn: async (id: number) => api.post(`/api/staff/wage-periods/${id}/finalize`),
    onSuccess: () => {
      toast.success('確定しました');
      queryClient.invalidateQueries({ queryKey: ['staff', 'wage-periods'] });
      queryClient.invalidateQueries({ queryKey: ['staff', 'wage-period', selectedPeriodId] });
    },
    onError: () => toast.error('確定に失敗しました'),
  });

  const markPaidMutation = useMutation({
    mutationFn: async ({ id, paymentDate }: { id: number; paymentDate: string }) =>
      api.post(`/api/staff/wage-periods/${id}/mark-paid`, { payment_date: paymentDate }),
    onSuccess: () => {
      toast.success('支払い済に変更しました');
      queryClient.invalidateQueries({ queryKey: ['staff', 'wage-periods'] });
      queryClient.invalidateQueries({ queryKey: ['staff', 'wage-period', selectedPeriodId] });
    },
    onError: () => toast.error('変更に失敗しました'),
  });

  if (!isEmployment) {
    return (
      <Card>
        <CardBody>
          <div className="flex items-center gap-3 p-4">
            <MaterialIcon name="info" size={24} className="text-[var(--brand-80)]" />
            <p className="text-sm text-[var(--neutral-foreground-2)]">
              工賃管理は就労継続支援 A型・B型 でのみ利用できます。現在のサービス種別: <strong>{terms.client}</strong>
            </p>
          </div>
        </CardBody>
      </Card>
    );
  }

  return (
    <div className="space-y-6">
      <div className="flex items-end justify-between">
        <div>
          <h1 className="text-2xl font-bold text-[var(--neutral-foreground-1)]">工賃管理</h1>
          <p className="mt-1 text-sm text-[var(--neutral-foreground-3)]">
            月次の工賃を計算・確定・支払い管理します。連絡帳で記録された出退勤・作業内容から自動集計されます。
          </p>
        </div>
      </div>

      {/* 計算実行 */}
      <Card>
        <CardHeader>
          <CardTitle>新規計算 / 再計算</CardTitle>
        </CardHeader>
        <CardBody>
          <div className="flex flex-wrap items-end gap-3">
            <div>
              <label className="mb-1 block text-xs font-medium text-[var(--neutral-foreground-3)]">
                対象月
              </label>
              <input
                type="month"
                value={yearMonth}
                onChange={(e) => setYearMonth(e.target.value)}
                className="rounded-md border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-1)] px-3 py-2 text-sm"
              />
            </div>
            <Button
              onClick={() => calculateMutation.mutate(yearMonth)}
              isLoading={calculateMutation.isPending}
              leftIcon={<MaterialIcon name="calculate" size={16} />}
            >
              工賃を計算
            </Button>
          </div>
          <p className="mt-3 text-xs text-[var(--neutral-foreground-4)]">
            ※ 確定済みの月は再計算されません。下書き状態のみ最新値で再計算されます。
          </p>
        </CardBody>
      </Card>

      {/* 期間一覧 */}
      <Card>
        <CardHeader>
          <CardTitle>過去 12 ヶ月の工賃台帳</CardTitle>
        </CardHeader>
        <CardBody>
          {loadingPeriods ? (
            <p className="text-sm text-[var(--neutral-foreground-3)]">読み込み中...</p>
          ) : periods.length === 0 ? (
            <p className="text-sm text-[var(--neutral-foreground-4)]">
              まだ計算された月がありません。上の「工賃を計算」から実行してください。
            </p>
          ) : (
            <div className="overflow-x-auto">
              <table className="w-full text-sm">
                <thead>
                  <tr className="border-b border-[var(--neutral-stroke-2)] text-xs text-[var(--neutral-foreground-3)]">
                    <th className="px-3 py-2 text-left font-medium">対象月</th>
                    <th className="px-3 py-2 text-right font-medium">対象人数</th>
                    <th className="px-3 py-2 text-right font-medium">工賃合計</th>
                    <th className="px-3 py-2 text-right font-medium">平均工賃</th>
                    <th className="px-3 py-2 text-left font-medium">状態</th>
                    <th className="px-3 py-2 text-left font-medium">支払日</th>
                    <th className="px-3 py-2 text-left font-medium"></th>
                  </tr>
                </thead>
                <tbody>
                  {periods.map((p) => (
                    <tr
                      key={p.id}
                      className={`border-b border-[var(--neutral-stroke-3)] cursor-pointer hover:bg-[var(--neutral-background-3)] ${
                        selectedPeriodId === p.id ? 'bg-[var(--brand-160)]' : ''
                      }`}
                      onClick={() => setSelectedPeriodId(p.id)}
                    >
                      <td className="px-3 py-2 font-medium">{p.year_month}</td>
                      <td className="px-3 py-2 text-right">{p.student_count} 名</td>
                      <td className="px-3 py-2 text-right font-mono">{fmtYen(p.total_wage)}</td>
                      <td className="px-3 py-2 text-right font-mono">{fmtYen(p.average_wage)}</td>
                      <td className="px-3 py-2">
                        <Badge variant={STATUS_VARIANT[p.status]}>{STATUS_LABEL[p.status]}</Badge>
                      </td>
                      <td className="px-3 py-2 text-xs">{p.payment_date ?? '-'}</td>
                      <td className="px-3 py-2 text-right">
                        <button onClick={(e) => { e.stopPropagation(); setSelectedPeriodId(p.id); }} className="text-xs text-[var(--brand-80)] hover:underline">
                          詳細
                        </button>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </CardBody>
      </Card>

      {/* 詳細 */}
      {detail && (
        <Card>
          <CardHeader>
            <div className="flex items-center justify-between">
              <CardTitle>{detail.period.year_month} の明細</CardTitle>
              <div className="flex gap-2">
                {detail.period.status === 'draft' && (
                  <Button
                    variant="primary"
                    size="sm"
                    onClick={() => finalizeMutation.mutate(detail.period.id)}
                    isLoading={finalizeMutation.isPending}
                    leftIcon={<MaterialIcon name="lock" size={14} />}
                  >
                    確定
                  </Button>
                )}
                {detail.period.status === 'finalized' && (
                  <Button
                    variant="primary"
                    size="sm"
                    onClick={() => {
                      const today = new Date().toISOString().split('T')[0];
                      const paymentDate = window.prompt('支払日 (YYYY-MM-DD):', today);
                      if (paymentDate) {
                        markPaidMutation.mutate({ id: detail.period.id, paymentDate });
                      }
                    }}
                    leftIcon={<MaterialIcon name="payments" size={14} />}
                  >
                    支払い済に変更
                  </Button>
                )}
              </div>
            </div>
          </CardHeader>
          <CardBody>
            <div className="overflow-x-auto">
              <table className="w-full text-sm">
                <thead>
                  <tr className="border-b border-[var(--neutral-stroke-2)] text-xs text-[var(--neutral-foreground-3)]">
                    <th className="px-3 py-2 text-left font-medium">{terms.client}</th>
                    <th className="px-3 py-2 text-center font-medium">計算方式</th>
                    <th className="px-3 py-2 text-right font-medium">出勤日</th>
                    <th className="px-3 py-2 text-right font-medium">対象時間</th>
                    <th className="px-3 py-2 text-right font-medium">時給/単価</th>
                    <th className="px-3 py-2 text-right font-medium">基本工賃</th>
                    <th className="px-3 py-2 text-right font-medium">時間外</th>
                    <th className="px-3 py-2 text-right font-medium">賞与</th>
                    <th className="px-3 py-2 text-right font-medium">控除</th>
                    <th className="px-3 py-2 text-right font-medium font-bold">支給額</th>
                  </tr>
                </thead>
                <tbody>
                  {detail.records.map((r) => (
                    <tr key={r.id} className="border-b border-[var(--neutral-stroke-3)]">
                      <td className="px-3 py-2">
                        {r.student?.student_name ?? `#${r.student_id}`}
                      </td>
                      <td className="px-3 py-2 text-center text-xs">
                        {r.calculation_type === 'hourly' ? '時給' : r.calculation_type === 'piece_rate' ? '出来高' : '固定'}
                      </td>
                      <td className="px-3 py-2 text-right">{r.attendance_days} 日</td>
                      <td className="px-3 py-2 text-right">{Number(r.wage_eligible_hours).toFixed(1)} h</td>
                      <td className="px-3 py-2 text-right text-xs">
                        {r.calculation_type === 'hourly'
                          ? `¥${Number(r.hourly_rate).toLocaleString()}/h`
                          : r.calculation_type === 'piece_rate'
                            ? `¥${Number(r.piece_rate_amount).toLocaleString()}/${r.student?.piece_rate_unit ?? '回'}`
                            : '-'}
                      </td>
                      <td className="px-3 py-2 text-right font-mono">{fmtYen(r.base_wage)}</td>
                      <td className="px-3 py-2 text-right font-mono text-xs">{fmtYen(r.overtime_wage)}</td>
                      <td className="px-3 py-2 text-right font-mono text-xs">{fmtYen(r.bonus)}</td>
                      <td className="px-3 py-2 text-right font-mono text-xs">{fmtYen(r.deductions)}</td>
                      <td className="px-3 py-2 text-right font-mono font-bold">{fmtYen(r.net_wage)}</td>
                    </tr>
                  ))}
                </tbody>
                <tfoot>
                  <tr className="border-t-2 border-[var(--neutral-stroke-1)] font-bold">
                    <td className="px-3 py-2" colSpan={9}>合計</td>
                    <td className="px-3 py-2 text-right font-mono">
                      {fmtYen(detail.records.reduce((s, r) => s + Number(r.net_wage), 0))}
                    </td>
                  </tr>
                </tfoot>
              </table>
            </div>
          </CardBody>
        </Card>
      )}
    </div>
  );
}
