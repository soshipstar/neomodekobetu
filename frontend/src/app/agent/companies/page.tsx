'use client';

import { useCallback, useEffect, useState } from 'react';
import api from '@/lib/api';
import { Card, CardBody } from '@/components/ui/Card';
import { Badge } from '@/components/ui/Badge';
import { SkeletonList } from '@/components/ui/Skeleton';
import { useToast } from '@/components/ui/Toast';

interface CompanyRow {
  id: number;
  name: string;
  code: string | null;
  agent_assigned_at: string | null;
  commission_rate_override: string | number | null;
  effective_commission_rate: number;
  default_commission_rate: number;
  subscription_status: string | null;
  custom_amount: number | null;
  tax_inclusive: boolean;
  current_period_end: string | null;
  cancel_at_period_end: boolean;
  is_active: boolean;
  contact: {
    name: string | null;
    email: string | null;
    phone: string | null;
    address: { line1?: string | null; line2?: string | null; city?: string | null; state?: string | null; postal_code?: string | null; country?: string | null } | null;
  } | null;
}

const STATUS_LABEL: Record<string, { label: string; variant: 'success' | 'warning' | 'danger' | 'default' }> = {
  active: { label: '有効', variant: 'success' },
  trialing: { label: 'トライアル中', variant: 'warning' },
  past_due: { label: '支払い遅延', variant: 'danger' },
  canceled: { label: '解約済み', variant: 'default' },
  incomplete: { label: '未完了', variant: 'warning' },
  unpaid: { label: '未払い', variant: 'danger' },
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

function formatAddress(addr: CompanyRow['contact']): string {
  if (!addr?.address) return '—';
  const a = addr.address;
  const parts = [a.postal_code ? `〒${a.postal_code}` : null, a.state, a.city, a.line1, a.line2].filter(Boolean);
  return parts.length > 0 ? parts.join(' ') : '—';
}

export default function AgentCompaniesPage() {
  const toast = useToast();
  const [companies, setCompanies] = useState<CompanyRow[]>([]);
  const [loading, setLoading] = useState(true);

  const fetchAll = useCallback(async () => {
    try {
      const res = await api.get('/api/agent/companies');
      setCompanies(res.data?.data ?? []);
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
        <h1 className="text-2xl font-bold text-[var(--neutral-foreground-1)]">紹介企業</h1>
        <SkeletonList items={3} />
      </div>
    );
  }

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-bold text-[var(--neutral-foreground-1)]">紹介企業</h1>
        <p className="mt-1 text-sm text-[var(--neutral-foreground-3)]">
          あなたが紹介した企業の一覧です。問題やお問い合わせは、各企業の連絡先にご連絡ください。
        </p>
      </div>

      {companies.length === 0 ? (
        <Card><CardBody><p className="text-sm text-[var(--neutral-foreground-3)]">紹介企業はまだありません。</p></CardBody></Card>
      ) : (
        <div className="space-y-3">
          {companies.map((c) => {
            const s = c.subscription_status ? STATUS_LABEL[c.subscription_status] : null;
            return (
              <Card key={c.id}>
                <CardBody>
                  <div className="flex items-start justify-between flex-wrap gap-3">
                    <div>
                      <h2 className="text-lg font-semibold text-[var(--neutral-foreground-1)]">{c.name}</h2>
                      {c.code && <p className="text-xs text-[var(--neutral-foreground-4)]">{c.code}</p>}
                      <div className="mt-1 flex items-center gap-2 flex-wrap">
                        {s ? <Badge variant={s.variant}>{s.label}</Badge> : <Badge variant="default">未契約</Badge>}
                        {c.cancel_at_period_end && <Badge variant="warning">解約予約</Badge>}
                      </div>
                    </div>
                    <div className="text-right">
                      <p className="text-xs text-[var(--neutral-foreground-3)]">月額</p>
                      <p className="text-xl font-bold text-[var(--neutral-foreground-1)]">{formatJpy(c.custom_amount)}</p>
                      <p className="mt-1 text-xs text-[var(--neutral-foreground-3)]">
                        手数料率 <span className="font-semibold text-[var(--brand-80)]">{formatRate(c.effective_commission_rate)}</span>
                        {c.commission_rate_override !== null && <span className="ml-1 text-[10px] text-[var(--neutral-foreground-4)]">(企業上書)</span>}
                      </p>
                    </div>
                  </div>

                  <dl className="mt-4 grid grid-cols-1 gap-y-2 text-sm sm:grid-cols-2">
                    <div className="flex"><dt className="w-32 text-[var(--neutral-foreground-3)]">紐付け開始</dt><dd className="flex-1 text-[var(--neutral-foreground-1)]">{formatDate(c.agent_assigned_at)}</dd></div>
                    <div className="flex"><dt className="w-32 text-[var(--neutral-foreground-3)]">次回請求日</dt><dd className="flex-1 text-[var(--neutral-foreground-1)]">{formatDate(c.current_period_end)}</dd></div>
                    <div className="flex"><dt className="w-32 text-[var(--neutral-foreground-3)]">担当者名</dt><dd className="flex-1 text-[var(--neutral-foreground-1)]">{c.contact?.name || '—'}</dd></div>
                    <div className="flex"><dt className="w-32 text-[var(--neutral-foreground-3)]">メール</dt><dd className="flex-1 text-[var(--neutral-foreground-1)] break-all">{c.contact?.email || '—'}</dd></div>
                    <div className="flex"><dt className="w-32 text-[var(--neutral-foreground-3)]">電話</dt><dd className="flex-1 text-[var(--neutral-foreground-1)]">{c.contact?.phone || '—'}</dd></div>
                    <div className="flex sm:col-span-2"><dt className="w-32 text-[var(--neutral-foreground-3)]">住所</dt><dd className="flex-1 text-[var(--neutral-foreground-1)]">{formatAddress(c.contact)}</dd></div>
                  </dl>
                </CardBody>
              </Card>
            );
          })}
        </div>
      )}
    </div>
  );
}
