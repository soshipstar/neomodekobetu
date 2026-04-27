'use client';

import { useCallback, useEffect, useState } from 'react';
import Link from 'next/link';
import { useParams } from 'next/navigation';
import api from '@/lib/api';
import { Card, CardBody } from '@/components/ui/Card';
import { Badge } from '@/components/ui/Badge';
import { SkeletonList } from '@/components/ui/Skeleton';
import { useToast } from '@/components/ui/Toast';
import { MaterialIcon } from '@/components/ui/MaterialIcon';

interface AgentDetail {
  id: number;
  name: string;
  code: string | null;
  contact_name: string | null;
  contact_email: string | null;
  contact_phone: string | null;
  address: string | null;
  default_commission_rate: string | number;
  bank_info: {
    bank_name?: string;
    branch?: string;
    account_type?: string;
    account_number?: string;
    account_holder?: string;
  } | null;
  contract_terms: string | null;
  is_active: boolean;
  notes: string | null;
  companies_count: number;
  companies?: Array<{
    id: number;
    name: string;
    code: string | null;
    commission_rate_override: string | number | null;
    subscription_status: string | null;
    custom_amount: number | null;
    agent_assigned_at: string | null;
  }>;
}

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
}

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
  try {
    return new Date(d).toLocaleDateString('ja-JP');
  } catch { return '—'; }
}

const STATUS_LABEL: Record<string, { label: string; variant: 'success' | 'warning' | 'danger' | 'default' }> = {
  draft: { label: '集計中', variant: 'default' },
  finalized: { label: '確定（未払）', variant: 'warning' },
  paid: { label: '支払い済み', variant: 'success' },
  canceled: { label: '取消', variant: 'default' },
};

export default function AgentDetailPage() {
  const params = useParams<{ id: string }>();
  const agentId = params?.id;
  const toast = useToast();
  const [agent, setAgent] = useState<AgentDetail | null>(null);
  const [payouts, setPayouts] = useState<Payout[]>([]);
  const [loading, setLoading] = useState(true);

  const fetchAll = useCallback(async () => {
    if (!agentId) return;
    setLoading(true);
    try {
      const [aRes, pRes] = await Promise.all([
        api.get(`/api/admin/master/agents/${agentId}`),
        api.get(`/api/admin/master/agent-payouts?agent_id=${agentId}`),
      ]);
      setAgent(aRes.data?.data ?? null);
      setPayouts(pRes.data?.data ?? []);
    } catch (err: unknown) {
      const msg = (err as { response?: { data?: { message?: string } } })?.response?.data?.message || '取得に失敗しました';
      toast.error(msg);
    } finally {
      setLoading(false);
    }
  }, [agentId, toast]);

  useEffect(() => { fetchAll(); }, [fetchAll]);

  if (loading || !agent) {
    return (
      <div className="space-y-4">
        <Link href="/admin/agents" className="inline-flex items-center text-sm text-[var(--brand-80)] hover:underline">
          <MaterialIcon name="chevron_left" size={18} />
          代理店一覧に戻る
        </Link>
        <SkeletonList items={3} />
      </div>
    );
  }

  return (
    <div className="space-y-6">
      <Link href="/admin/agents" className="inline-flex items-center text-sm text-[var(--brand-80)] hover:underline">
        <MaterialIcon name="chevron_left" size={18} />
        代理店一覧に戻る
      </Link>

      <div className="flex items-center gap-3 flex-wrap">
        <h1 className="text-2xl font-bold text-[var(--neutral-foreground-1)]">{agent.name}</h1>
        {agent.is_active ? <Badge variant="success">有効</Badge> : <Badge variant="default">無効</Badge>}
        {agent.code && <span className="text-sm text-[var(--neutral-foreground-3)]">{agent.code}</span>}
      </div>

      <Card>
        <CardBody>
          <h2 className="text-base font-semibold text-[var(--neutral-foreground-1)]">基本情報</h2>
          <dl className="mt-3 grid grid-cols-1 gap-2 sm:grid-cols-2 text-sm">
            <div className="flex"><dt className="w-32 text-[var(--neutral-foreground-3)]">既定手数料率</dt><dd className="flex-1 font-semibold text-[var(--neutral-foreground-1)]">{formatRate(agent.default_commission_rate)}</dd></div>
            <div className="flex"><dt className="w-32 text-[var(--neutral-foreground-3)]">紹介企業数</dt><dd className="flex-1 text-[var(--neutral-foreground-1)]">{agent.companies_count} 社</dd></div>
            <div className="flex"><dt className="w-32 text-[var(--neutral-foreground-3)]">担当者</dt><dd className="flex-1 text-[var(--neutral-foreground-1)]">{agent.contact_name || '—'}</dd></div>
            <div className="flex"><dt className="w-32 text-[var(--neutral-foreground-3)]">メール</dt><dd className="flex-1 text-[var(--neutral-foreground-1)] break-all">{agent.contact_email || '—'}</dd></div>
            <div className="flex"><dt className="w-32 text-[var(--neutral-foreground-3)]">電話</dt><dd className="flex-1 text-[var(--neutral-foreground-1)]">{agent.contact_phone || '—'}</dd></div>
            <div className="flex sm:col-span-2"><dt className="w-32 text-[var(--neutral-foreground-3)]">住所</dt><dd className="flex-1 text-[var(--neutral-foreground-1)]">{agent.address || '—'}</dd></div>
          </dl>
        </CardBody>
      </Card>

      {agent.bank_info && Object.values(agent.bank_info).some(Boolean) && (
        <Card>
          <CardBody>
            <h2 className="text-base font-semibold text-[var(--neutral-foreground-1)]">振込先銀行情報（手数料の支払先）</h2>
            <dl className="mt-3 grid grid-cols-1 gap-2 sm:grid-cols-2 text-sm">
              <div className="flex"><dt className="w-32 text-[var(--neutral-foreground-3)]">銀行名</dt><dd className="flex-1 text-[var(--neutral-foreground-1)]">{agent.bank_info.bank_name || '—'}</dd></div>
              <div className="flex"><dt className="w-32 text-[var(--neutral-foreground-3)]">支店</dt><dd className="flex-1 text-[var(--neutral-foreground-1)]">{agent.bank_info.branch || '—'}</dd></div>
              <div className="flex"><dt className="w-32 text-[var(--neutral-foreground-3)]">口座種別</dt><dd className="flex-1 text-[var(--neutral-foreground-1)]">{agent.bank_info.account_type || '—'}</dd></div>
              <div className="flex"><dt className="w-32 text-[var(--neutral-foreground-3)]">口座番号</dt><dd className="flex-1 text-[var(--neutral-foreground-1)]">{agent.bank_info.account_number || '—'}</dd></div>
              <div className="flex sm:col-span-2"><dt className="w-32 text-[var(--neutral-foreground-3)]">口座名義</dt><dd className="flex-1 text-[var(--neutral-foreground-1)]">{agent.bank_info.account_holder || '—'}</dd></div>
            </dl>
          </CardBody>
        </Card>
      )}

      <Card>
        <CardBody>
          <h2 className="text-base font-semibold text-[var(--neutral-foreground-1)]">紹介企業（{agent.companies?.length || 0} 社）</h2>
          {!agent.companies || agent.companies.length === 0 ? (
            <p className="mt-3 text-sm text-[var(--neutral-foreground-3)]">紐付く企業はまだありません。</p>
          ) : (
            <div className="mt-3 overflow-x-auto">
              <table className="min-w-full text-sm">
                <thead>
                  <tr className="border-b border-[var(--neutral-stroke-2)] text-left text-xs text-[var(--neutral-foreground-3)]">
                    <th className="py-2 pr-4 font-normal">企業名</th>
                    <th className="py-2 pr-4 font-normal">状態</th>
                    <th className="py-2 pr-4 font-normal text-right">月額</th>
                    <th className="py-2 pr-4 font-normal text-right">手数料率</th>
                    <th className="py-2 pr-4 font-normal">紐付け開始</th>
                    <th className="py-2 font-normal" />
                  </tr>
                </thead>
                <tbody>
                  {agent.companies.map((c) => {
                    const rate = c.commission_rate_override ?? agent.default_commission_rate;
                    return (
                      <tr key={c.id} className="border-b border-[var(--neutral-stroke-3)]">
                        <td className="py-3 pr-4">
                          <Link href={`/admin/master-billing/${c.id}`} className="font-medium text-[var(--neutral-foreground-1)] hover:underline">
                            {c.name}
                          </Link>
                          {c.code && <p className="text-xs text-[var(--neutral-foreground-4)]">{c.code}</p>}
                        </td>
                        <td className="py-3 pr-4 text-[var(--neutral-foreground-2)]">{c.subscription_status || '未契約'}</td>
                        <td className="py-3 pr-4 text-right text-[var(--neutral-foreground-1)]">{formatJpy(c.custom_amount)}</td>
                        <td className="py-3 pr-4 text-right text-[var(--neutral-foreground-1)]">
                          {formatRate(rate)}
                          {c.commission_rate_override !== null && <span className="ml-1 text-[10px] text-[var(--neutral-foreground-4)]">(上書)</span>}
                        </td>
                        <td className="py-3 pr-4 text-[var(--neutral-foreground-2)]">{formatDate(c.agent_assigned_at)}</td>
                        <td className="py-3 text-right">
                          <Link href={`/admin/master-billing/${c.id}`} className="text-[var(--brand-80)] hover:underline inline-flex items-center">
                            <MaterialIcon name="chevron_right" size={20} />
                          </Link>
                        </td>
                      </tr>
                    );
                  })}
                </tbody>
              </table>
            </div>
          )}
        </CardBody>
      </Card>

      <Card>
        <CardBody>
          <div className="flex items-center justify-between flex-wrap gap-2">
            <h2 className="text-base font-semibold text-[var(--neutral-foreground-1)]">手数料支払い履歴</h2>
            <Link href={`/admin/agent-payouts?agent_id=${agent.id}`} className="text-sm text-[var(--brand-80)] hover:underline">
              手数料管理画面で操作
            </Link>
          </div>
          {payouts.length === 0 ? (
            <p className="mt-3 text-sm text-[var(--neutral-foreground-3)]">支払い履歴はまだありません。</p>
          ) : (
            <div className="mt-3 overflow-x-auto">
              <table className="min-w-full text-sm">
                <thead>
                  <tr className="border-b border-[var(--neutral-stroke-2)] text-left text-xs text-[var(--neutral-foreground-3)]">
                    <th className="py-2 pr-4 font-normal">期間</th>
                    <th className="py-2 pr-4 font-normal">状態</th>
                    <th className="py-2 pr-4 font-normal text-right">売上</th>
                    <th className="py-2 pr-4 font-normal text-right">利益</th>
                    <th className="py-2 pr-4 font-normal text-right">手数料</th>
                    <th className="py-2 pr-4 font-normal">支払期日</th>
                    <th className="py-2 pr-4 font-normal">支払日</th>
                  </tr>
                </thead>
                <tbody>
                  {payouts.map((p) => {
                    const s = STATUS_LABEL[p.status] ?? { label: p.status, variant: 'default' as const };
                    return (
                      <tr key={p.id} className="border-b border-[var(--neutral-stroke-3)]">
                        <td className="py-3 pr-4 text-[var(--neutral-foreground-1)]">{formatDate(p.period_start)} – {formatDate(p.period_end)}</td>
                        <td className="py-3 pr-4"><Badge variant={s.variant}>{s.label}</Badge></td>
                        <td className="py-3 pr-4 text-right">{formatJpy(p.gross_revenue)}</td>
                        <td className="py-3 pr-4 text-right">{formatJpy(p.net_profit)}</td>
                        <td className="py-3 pr-4 text-right font-semibold">{formatJpy(p.commission_amount)}</td>
                        <td className="py-3 pr-4 text-[var(--neutral-foreground-2)]">{formatDate(p.due_date)}</td>
                        <td className="py-3 pr-4 text-[var(--neutral-foreground-2)]">{formatDate(p.paid_at)}</td>
                      </tr>
                    );
                  })}
                </tbody>
              </table>
            </div>
          )}
        </CardBody>
      </Card>

      {(agent.contract_terms || agent.notes) && (
        <Card>
          <CardBody>
            {agent.contract_terms && (
              <>
                <h2 className="text-base font-semibold text-[var(--neutral-foreground-1)]">契約条件</h2>
                <p className="mt-2 whitespace-pre-wrap text-sm text-[var(--neutral-foreground-2)]">{agent.contract_terms}</p>
              </>
            )}
            {agent.notes && (
              <>
                <h3 className="mt-4 text-sm font-semibold text-[var(--neutral-foreground-2)]">社内メモ</h3>
                <p className="mt-1 whitespace-pre-wrap text-sm text-[var(--neutral-foreground-3)]">{agent.notes}</p>
              </>
            )}
          </CardBody>
        </Card>
      )}
    </div>
  );
}
