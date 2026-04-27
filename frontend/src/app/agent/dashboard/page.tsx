'use client';

import { useCallback, useEffect, useState } from 'react';
import Link from 'next/link';
import api from '@/lib/api';
import { Card, CardBody } from '@/components/ui/Card';
import { SkeletonList } from '@/components/ui/Skeleton';
import { useToast } from '@/components/ui/Toast';
import { MaterialIcon } from '@/components/ui/MaterialIcon';

interface Dashboard {
  agent: { id: number; name: string; code: string | null; default_commission_rate: string | number };
  company_count: number;
  current_month: {
    period_label: string;
    applicable_revenue: number;
    estimated_commission: number;
    invoice_count: number;
  };
  unpaid_total: number;
  recent_paid_payouts: Array<{
    id: number;
    period_start: string;
    period_end: string;
    commission_amount: number;
    paid_at: string | null;
  }>;
}

function formatJpy(v: number | null | undefined): string {
  if (v === null || v === undefined) return '—';
  return `¥${v.toLocaleString('ja-JP')}`;
}

function formatDate(d: string | null | undefined): string {
  if (!d) return '—';
  try { return new Date(d).toLocaleDateString('ja-JP'); } catch { return '—'; }
}

export default function AgentDashboardPage() {
  const toast = useToast();
  const [data, setData] = useState<Dashboard | null>(null);
  const [loading, setLoading] = useState(true);

  const fetchData = useCallback(async () => {
    try {
      const res = await api.get('/api/agent/dashboard');
      setData(res.data?.data ?? null);
    } catch (err: unknown) {
      const msg = (err as { response?: { data?: { message?: string } } })?.response?.data?.message || '取得に失敗しました';
      toast.error(msg);
    } finally {
      setLoading(false);
    }
  }, [toast]);

  useEffect(() => { fetchData(); }, [fetchData]);

  if (loading || !data) {
    return (
      <div className="space-y-4">
        <h1 className="text-2xl font-bold text-[var(--neutral-foreground-1)]">代理店ダッシュボード</h1>
        <SkeletonList items={3} />
      </div>
    );
  }

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-bold text-[var(--neutral-foreground-1)]">代理店ダッシュボード</h1>
        <p className="mt-1 text-sm text-[var(--neutral-foreground-3)]">
          {data.agent.name} さん（既定手数料率: {(parseFloat(String(data.agent.default_commission_rate)) * 100).toFixed(1)}%）
        </p>
      </div>

      <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
        <Card><CardBody>
          <p className="text-xs text-[var(--neutral-foreground-3)]">紹介企業数</p>
          <p className="mt-1 text-2xl font-bold text-[var(--neutral-foreground-1)]">{data.company_count}</p>
        </CardBody></Card>
        <Card><CardBody>
          <p className="text-xs text-[var(--neutral-foreground-3)]">{data.current_month.period_label} 売上</p>
          <p className="mt-1 text-2xl font-bold text-[var(--neutral-foreground-1)]">{formatJpy(data.current_month.applicable_revenue)}</p>
          <p className="mt-1 text-[10px] text-[var(--neutral-foreground-4)]">支払済 {data.current_month.invoice_count}件</p>
        </CardBody></Card>
        <Card><CardBody>
          <p className="text-xs text-[var(--neutral-foreground-3)]">{data.current_month.period_label} 見込み手数料</p>
          <p className="mt-1 text-2xl font-bold text-[var(--brand-80)]">{formatJpy(data.current_month.estimated_commission)}</p>
          <p className="mt-1 text-[10px] text-[var(--neutral-foreground-4)]">Stripe手数料控除前</p>
        </CardBody></Card>
        <Card><CardBody>
          <p className="text-xs text-[var(--neutral-foreground-3)]">未払い手数料合計</p>
          <p className="mt-1 text-2xl font-bold text-[var(--status-warning-fg)]">{formatJpy(data.unpaid_total)}</p>
        </CardBody></Card>
      </div>

      <Card>
        <CardBody>
          <div className="flex items-center justify-between flex-wrap gap-2">
            <h2 className="text-base font-semibold text-[var(--neutral-foreground-1)]">直近の支払い</h2>
            <Link href="/agent/payouts" className="text-sm text-[var(--brand-80)] hover:underline">
              すべて見る
            </Link>
          </div>
          {data.recent_paid_payouts.length === 0 ? (
            <p className="mt-3 text-sm text-[var(--neutral-foreground-3)]">支払い履歴はまだありません。</p>
          ) : (
            <ul className="mt-3 divide-y divide-[var(--neutral-stroke-3)]">
              {data.recent_paid_payouts.map((p) => (
                <li key={p.id} className="flex items-center justify-between py-2 text-sm">
                  <div>
                    <p className="text-[var(--neutral-foreground-1)]">{formatDate(p.period_start)} – {formatDate(p.period_end)} 分</p>
                    <p className="text-xs text-[var(--neutral-foreground-3)]">支払日: {formatDate(p.paid_at)}</p>
                  </div>
                  <span className="font-semibold text-[var(--neutral-foreground-1)]">{formatJpy(p.commission_amount)}</span>
                </li>
              ))}
            </ul>
          )}
        </CardBody>
      </Card>

      <Card>
        <CardBody>
          <p className="text-sm text-[var(--neutral-foreground-2)]">
            <MaterialIcon name="info" size={16} className="inline mr-1 align-middle" />
            手数料は <strong>(売上 − Stripe手数料) × 手数料率</strong> で月次に集計されます。
            集計確定（finalized）後に銀行振込が行われ、支払い済みマークが付きます。
            支払期日は集計対象月の翌月末日です。
          </p>
        </CardBody>
      </Card>
    </div>
  );
}
