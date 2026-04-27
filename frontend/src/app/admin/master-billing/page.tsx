'use client';

import { useCallback, useEffect, useMemo, useState } from 'react';
import Link from 'next/link';
import api from '@/lib/api';
import { Card, CardBody } from '@/components/ui/Card';
import { Badge } from '@/components/ui/Badge';
import { SkeletonList } from '@/components/ui/Skeleton';
import { useToast } from '@/components/ui/Toast';
import { MaterialIcon } from '@/components/ui/MaterialIcon';

interface CompanyRow {
  id: number;
  name: string;
  code: string | null;
  stripe_id: string | null;
  subscription_status: string | null;
  current_price_id: string | null;
  custom_amount: number | null;
  is_custom_pricing: boolean;
  current_period_end: string | null;
  cancel_at_period_end: boolean;
  trial_ends_at: string | null;
  contract_started_at: string | null;
  open_invoice_total: number | null;
  paid_mtd_total: number | null;
}

const STATUS_FILTERS: { value: string; label: string }[] = [
  { value: 'all', label: 'すべて' },
  { value: 'active', label: '有効' },
  { value: 'trialing', label: 'トライアル中' },
  { value: 'past_due', label: '支払い遅延' },
  { value: 'canceled', label: '解約済み' },
  { value: 'none', label: '契約なし' },
];

const STATUS_BADGE: Record<string, { label: string; variant: 'success' | 'warning' | 'danger' | 'default' }> = {
  active: { label: '有効', variant: 'success' },
  trialing: { label: 'トライアル', variant: 'warning' },
  past_due: { label: '支払い遅延', variant: 'danger' },
  canceled: { label: '解約済み', variant: 'default' },
  incomplete: { label: '未完了', variant: 'warning' },
  unpaid: { label: '未払い', variant: 'danger' },
};

function formatJpy(value: number | null | undefined): string {
  if (value === null || value === undefined) return '—';
  return `¥${value.toLocaleString('ja-JP')}`;
}

function formatDate(value: string | null | undefined): string {
  if (!value) return '—';
  try {
    return new Date(value).toLocaleDateString('ja-JP');
  } catch {
    return '—';
  }
}

export default function MasterBillingPage() {
  const toast = useToast();
  const [companies, setCompanies] = useState<CompanyRow[]>([]);
  const [statusCounts, setStatusCounts] = useState<Record<string, number>>({});
  const [loading, setLoading] = useState(true);
  const [filter, setFilter] = useState<string>('all');

  const fetchOverview = useCallback(async () => {
    setLoading(true);
    try {
      const res = await api.get('/api/admin/master/billing/overview');
      setCompanies(res.data?.data?.companies || []);
      setStatusCounts(res.data?.data?.status_counts || {});
    } catch (err: unknown) {
      const msg = (err as { response?: { data?: { message?: string } } })?.response?.data?.message || '一覧の取得に失敗しました';
      toast.error(msg);
    } finally {
      setLoading(false);
    }
  }, [toast]);

  useEffect(() => {
    fetchOverview();
  }, [fetchOverview]);

  const filtered = useMemo(() => {
    if (filter === 'all') return companies;
    if (filter === 'none') return companies.filter((c) => !c.subscription_status);
    return companies.filter((c) => c.subscription_status === filter);
  }, [companies, filter]);

  const mrr = useMemo(() => {
    return companies.reduce((sum, c) => {
      if (c.subscription_status !== 'active' && c.subscription_status !== 'trialing') return sum;
      return sum + (c.custom_amount ?? 0);
    }, 0);
  }, [companies]);

  // 今月の売上（月額契約の月次分 + スポット請求の支払済 + open=確定済未払い）。
  // open は確定済みなので売上計上対象とみなす。
  const revenueMtd = useMemo(() => {
    return companies.reduce((sum, c) => {
      return sum + (c.paid_mtd_total ?? 0) + (c.open_invoice_total ?? 0);
    }, 0);
  }, [companies]);

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-bold text-[var(--neutral-foreground-1)]">企業課金管理</h1>
        <p className="mt-1 text-sm text-[var(--neutral-foreground-3)]">
          全企業の契約状態・カスタム価格・表示設定を管理します。
        </p>
      </div>

      <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
        <Card>
          <CardBody>
            <p className="text-xs text-[var(--neutral-foreground-3)]">企業数</p>
            <p className="mt-1 text-2xl font-bold text-[var(--neutral-foreground-1)]">{companies.length}</p>
          </CardBody>
        </Card>
        <Card>
          <CardBody>
            <p className="text-xs text-[var(--neutral-foreground-3)]">契約中（active+trial）</p>
            <p className="mt-1 text-2xl font-bold text-[var(--neutral-foreground-1)]">
              {(statusCounts.active || 0) + (statusCounts.trialing || 0)}
            </p>
          </CardBody>
        </Card>
        <Card>
          <CardBody>
            <p className="text-xs text-[var(--neutral-foreground-3)]">支払い遅延</p>
            <p className="mt-1 text-2xl font-bold text-[var(--status-danger-fg)]">{statusCounts.past_due || 0}</p>
          </CardBody>
        </Card>
        <Card>
          <CardBody>
            <p className="text-xs text-[var(--neutral-foreground-3)]">今月の売上（月額+スポット）</p>
            <p className="mt-1 text-2xl font-bold text-[var(--neutral-foreground-1)]">{formatJpy(revenueMtd)}</p>
            <p className="mt-1 text-[10px] text-[var(--neutral-foreground-4)]">支払済+確定未払いの合計</p>
          </CardBody>
        </Card>
        <Card>
          <CardBody>
            <p className="text-xs text-[var(--neutral-foreground-3)]">月額契約合計（MRR）</p>
            <p className="mt-1 text-2xl font-bold text-[var(--neutral-foreground-1)]">{formatJpy(mrr)}</p>
            <p className="mt-1 text-[10px] text-[var(--neutral-foreground-4)]">スポット請求は含まない</p>
          </CardBody>
        </Card>
      </div>

      <div className="flex flex-wrap gap-2">
        {STATUS_FILTERS.map((f) => (
          <button
            key={f.value}
            onClick={() => setFilter(f.value)}
            className={
              filter === f.value
                ? 'rounded-full bg-[var(--brand-80)] px-3 py-1 text-sm font-medium text-white'
                : 'rounded-full bg-[var(--neutral-background-3)] px-3 py-1 text-sm text-[var(--neutral-foreground-2)] hover:bg-[var(--neutral-background-4)]'
            }
          >
            {f.label}
          </button>
        ))}
      </div>

      {loading ? (
        <SkeletonList items={5} />
      ) : filtered.length === 0 ? (
        <Card>
          <CardBody>
            <p className="text-[var(--neutral-foreground-3)]">該当する企業はありません。</p>
          </CardBody>
        </Card>
      ) : (
        <Card>
          <CardBody>
            <div className="overflow-x-auto">
              <table className="min-w-full text-sm">
                <thead>
                  <tr className="border-b border-[var(--neutral-stroke-2)] text-left text-xs text-[var(--neutral-foreground-3)]">
                    <th className="py-2 pr-4 font-normal">企業名</th>
                    <th className="py-2 pr-4 font-normal">状態</th>
                    <th className="py-2 pr-4 font-normal">プラン</th>
                    <th className="py-2 pr-4 font-normal text-right">月額契約</th>
                    <th className="py-2 pr-4 font-normal text-right">今月の売上</th>
                    <th className="py-2 pr-4 font-normal">次回請求日</th>
                    <th className="py-2 pr-4 font-normal">契約開始</th>
                    <th className="py-2 font-normal" />
                  </tr>
                </thead>
                <tbody>
                  {filtered.map((c) => {
                    const s = c.subscription_status ? STATUS_BADGE[c.subscription_status] : null;
                    return (
                      <tr key={c.id} className="border-b border-[var(--neutral-stroke-3)] hover:bg-[var(--neutral-background-2)]">
                        <td className="py-3 pr-4">
                          <Link href={`/admin/master-billing/${c.id}`} className="font-medium text-[var(--neutral-foreground-1)] hover:underline">
                            {c.name}
                          </Link>
                          {c.code && <p className="text-xs text-[var(--neutral-foreground-4)]">{c.code}</p>}
                        </td>
                        <td className="py-3 pr-4">
                          {s ? <Badge variant={s.variant}>{s.label}</Badge> : <Badge variant="default">未契約</Badge>}
                          {c.cancel_at_period_end && <Badge variant="warning" className="ml-1">解約予約</Badge>}
                        </td>
                        <td className="py-3 pr-4 text-[var(--neutral-foreground-2)]">
                          {c.is_custom_pricing ? 'カスタム' : c.current_price_id ? '標準' : '—'}
                        </td>
                        <td className="py-3 pr-4 text-right text-[var(--neutral-foreground-2)]">{formatJpy(c.custom_amount)}</td>
                        <td className="py-3 pr-4 text-right text-[var(--neutral-foreground-1)] font-semibold">
                          {formatJpy((c.paid_mtd_total ?? 0) + (c.open_invoice_total ?? 0))}
                        </td>
                        <td className="py-3 pr-4 text-[var(--neutral-foreground-2)]">{formatDate(c.current_period_end)}</td>
                        <td className="py-3 pr-4 text-[var(--neutral-foreground-2)]">{formatDate(c.contract_started_at)}</td>
                        <td className="py-3 text-right">
                          <Link href={`/admin/master-billing/${c.id}`} className="inline-flex items-center text-[var(--brand-80)] hover:underline">
                            <MaterialIcon name="chevron_right" size={20} />
                          </Link>
                        </td>
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
