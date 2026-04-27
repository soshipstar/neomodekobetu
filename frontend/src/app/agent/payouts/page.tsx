'use client';

import { useCallback, useEffect, useState } from 'react';
import api from '@/lib/api';
import { Card, CardBody } from '@/components/ui/Card';
import { Badge } from '@/components/ui/Badge';
import { SkeletonList } from '@/components/ui/Skeleton';
import { useToast } from '@/components/ui/Toast';

interface Payout {
  id: number;
  period_start: string;
  period_end: string;
  due_date: string;
  gross_revenue: number;
  stripe_fees: number;
  net_profit: number;
  commission_rate: string | number;
  commission_amount: number;
  status: string;
  paid_at: string | null;
  transaction_ref: string | null;
}

const STATUS_LABEL: Record<string, { label: string; variant: 'success' | 'warning' | 'danger' | 'default' }> = {
  draft: { label: '集計中', variant: 'default' },
  finalized: { label: '確定（未払）', variant: 'warning' },
  paid: { label: '支払い済み', variant: 'success' },
  canceled: { label: '取消', variant: 'default' },
};

function formatJpy(v: number | null | undefined): string {
  if (v === null || v === undefined) return '—';
  return `¥${v.toLocaleString('ja-JP')}`;
}

function formatRate(r: number | string | null | undefined): string {
  if (r === null || r === undefined) return '—';
  return `${(parseFloat(String(r)) * 100).toFixed(1)}%`;
}

function formatDate(d: string | null | undefined): string {
  if (!d) return '—';
  try { return new Date(d).toLocaleDateString('ja-JP'); } catch { return '—'; }
}

export default function AgentPayoutsPage() {
  const toast = useToast();
  const [payouts, setPayouts] = useState<Payout[]>([]);
  const [loading, setLoading] = useState(true);

  const fetchAll = useCallback(async () => {
    try {
      const res = await api.get('/api/agent/payouts');
      setPayouts(res.data?.data ?? []);
    } catch (err: unknown) {
      const msg = (err as { response?: { data?: { message?: string } } })?.response?.data?.message || '取得に失敗しました';
      toast.error(msg);
    } finally {
      setLoading(false);
    }
  }, [toast]);

  useEffect(() => { fetchAll(); }, [fetchAll]);

  if (loading) {
    return (
      <div className="space-y-4">
        <h1 className="text-2xl font-bold text-[var(--neutral-foreground-1)]">手数料・支払い履歴</h1>
        <SkeletonList items={3} />
      </div>
    );
  }

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-bold text-[var(--neutral-foreground-1)]">手数料・支払い履歴</h1>
        <p className="mt-1 text-sm text-[var(--neutral-foreground-3)]">
          月次の手数料計算結果と、銀行振込の状況です。支払期日は集計対象月の翌月末日です。
        </p>
      </div>

      {payouts.length === 0 ? (
        <Card><CardBody><p className="text-sm text-[var(--neutral-foreground-3)]">手数料の履歴はまだありません。</p></CardBody></Card>
      ) : (
        <Card>
          <CardBody>
            <div className="overflow-x-auto">
              <table className="min-w-full text-sm">
                <thead>
                  <tr className="border-b border-[var(--neutral-stroke-2)] text-left text-xs text-[var(--neutral-foreground-3)]">
                    <th className="py-2 pr-4 font-normal">対象月</th>
                    <th className="py-2 pr-4 font-normal">状態</th>
                    <th className="py-2 pr-4 font-normal text-right">売上</th>
                    <th className="py-2 pr-4 font-normal text-right">Stripe手数料</th>
                    <th className="py-2 pr-4 font-normal text-right">利益</th>
                    <th className="py-2 pr-4 font-normal text-right">レート</th>
                    <th className="py-2 pr-4 font-normal text-right">手数料</th>
                    <th className="py-2 pr-4 font-normal">支払期日</th>
                    <th className="py-2 pr-4 font-normal">支払日</th>
                    <th className="py-2 pr-4 font-normal">参照</th>
                  </tr>
                </thead>
                <tbody>
                  {payouts.map((p) => {
                    const s = STATUS_LABEL[p.status] ?? { label: p.status, variant: 'default' as const };
                    return (
                      <tr key={p.id} className="border-b border-[var(--neutral-stroke-3)]">
                        <td className="py-3 pr-4 text-[var(--neutral-foreground-1)]">
                          {formatDate(p.period_start)} – {formatDate(p.period_end)}
                        </td>
                        <td className="py-3 pr-4"><Badge variant={s.variant}>{s.label}</Badge></td>
                        <td className="py-3 pr-4 text-right text-[var(--neutral-foreground-2)]">{formatJpy(p.gross_revenue)}</td>
                        <td className="py-3 pr-4 text-right text-[var(--neutral-foreground-2)]">{formatJpy(p.stripe_fees)}</td>
                        <td className="py-3 pr-4 text-right text-[var(--neutral-foreground-2)]">{formatJpy(p.net_profit)}</td>
                        <td className="py-3 pr-4 text-right text-[var(--neutral-foreground-2)]">{formatRate(p.commission_rate)}</td>
                        <td className="py-3 pr-4 text-right font-semibold text-[var(--neutral-foreground-1)]">{formatJpy(p.commission_amount)}</td>
                        <td className="py-3 pr-4 text-[var(--neutral-foreground-2)]">{formatDate(p.due_date)}</td>
                        <td className="py-3 pr-4 text-[var(--neutral-foreground-2)]">{formatDate(p.paid_at)}</td>
                        <td className="py-3 pr-4 text-[var(--neutral-foreground-3)]">{p.transaction_ref || '—'}</td>
                      </tr>
                    );
                  })}
                </tbody>
              </table>
            </div>
          </CardBody>
        </Card>
      )}
    </div>
  );
}
