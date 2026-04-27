'use client';

import { useCallback, useEffect, useMemo, useState } from 'react';
import api from '@/lib/api';
import { Card, CardBody } from '@/components/ui/Card';
import { Button } from '@/components/ui/Button';
import { Input } from '@/components/ui/Input';
import { Badge } from '@/components/ui/Badge';
import { SkeletonList } from '@/components/ui/Skeleton';
import { useToast } from '@/components/ui/Toast';
import { MaterialIcon } from '@/components/ui/MaterialIcon';

interface Payout {
  id: number;
  agent_id: number;
  agent: { id: number; name: string };
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
  notes: string | null;
}

interface AgentLite { id: number; name: string }

const STATUS_FILTERS = [
  { value: 'all', label: 'すべて' },
  { value: 'draft', label: '集計中' },
  { value: 'finalized', label: '確定（未払）' },
  { value: 'paid', label: '支払い済み' },
  { value: 'canceled', label: '取消' },
];

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
  try {
    return new Date(d).toLocaleDateString('ja-JP');
  } catch { return '—'; }
}

function currentMonthYM(): string {
  const d = new Date();
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`;
}

function lastMonthYM(): string {
  const d = new Date();
  d.setMonth(d.getMonth() - 1);
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`;
}

export default function AgentPayoutsPage() {
  const toast = useToast();
  const [payouts, setPayouts] = useState<Payout[]>([]);
  const [agents, setAgents] = useState<AgentLite[]>([]);
  const [loading, setLoading] = useState(true);
  const [filter, setFilter] = useState<string>('all');
  const [agentFilter, setAgentFilter] = useState<string>('');

  const [calcPeriod, setCalcPeriod] = useState<string>(lastMonthYM());
  const [calcAgent, setCalcAgent] = useState<string>('');
  const [calculating, setCalculating] = useState(false);

  const fetchAll = useCallback(async () => {
    setLoading(true);
    try {
      const params = new URLSearchParams();
      if (filter !== 'all') params.set('status', filter);
      if (agentFilter) params.set('agent_id', agentFilter);
      const [pRes, aRes] = await Promise.all([
        api.get(`/api/admin/master/agent-payouts?${params.toString()}`),
        api.get('/api/admin/master/agents'),
      ]);
      setPayouts(pRes.data?.data ?? []);
      setAgents((aRes.data?.data ?? []).map((a: AgentLite) => ({ id: a.id, name: a.name })));
    } catch (err: unknown) {
      const msg = (err as { response?: { data?: { message?: string } } })?.response?.data?.message || '取得に失敗しました';
      toast.error(msg);
    } finally {
      setLoading(false);
    }
  }, [filter, agentFilter, toast]);

  useEffect(() => { fetchAll(); }, [fetchAll]);

  // URLクエリ ?agent_id= を初期反映
  useEffect(() => {
    if (typeof window === 'undefined') return;
    const sp = new URLSearchParams(window.location.search);
    const aid = sp.get('agent_id');
    if (aid) setAgentFilter(aid);
  }, []);

  const submitCalculate = async () => {
    if (!calcPeriod) {
      toast.error('集計対象月を入力してください');
      return;
    }
    setCalculating(true);
    try {
      const body: Record<string, string | number> = { period: calcPeriod };
      if (calcAgent) body.agent_id = parseInt(calcAgent, 10);
      const res = await api.post('/api/admin/master/agent-payouts/calculate', body);
      toast.success(res.data?.message || '集計完了');
      fetchAll();
    } catch (err: unknown) {
      const msg = (err as { response?: { data?: { message?: string } } })?.response?.data?.message || '集計に失敗しました';
      toast.error(msg);
    } finally {
      setCalculating(false);
    }
  };

  const handleFinalize = async (id: number) => {
    if (!confirm('この集計結果を確定します（金額が固定されます）。よろしいですか？')) return;
    try {
      await api.post(`/api/admin/master/agent-payouts/${id}/finalize`);
      toast.success('確定しました');
      fetchAll();
    } catch (err: unknown) {
      const msg = (err as { response?: { data?: { message?: string } } })?.response?.data?.message || '確定に失敗しました';
      toast.error(msg);
    }
  };

  const handleMarkPaid = async (id: number) => {
    const ref = window.prompt('振込番号や参照（任意）を入力してください', '');
    if (ref === null) return;
    try {
      await api.post(`/api/admin/master/agent-payouts/${id}/mark-paid`, {
        transaction_ref: ref || null,
      });
      toast.success('支払い済みとしてマークしました');
      fetchAll();
    } catch (err: unknown) {
      const msg = (err as { response?: { data?: { message?: string } } })?.response?.data?.message || '更新に失敗しました';
      toast.error(msg);
    }
  };

  const handleCancel = async (id: number) => {
    if (!confirm('この集計を取消します（draft または finalized のみ可）。よろしいですか？')) return;
    try {
      await api.post(`/api/admin/master/agent-payouts/${id}/cancel`);
      toast.success('取消しました');
      fetchAll();
    } catch (err: unknown) {
      const msg = (err as { response?: { data?: { message?: string } } })?.response?.data?.message || '取消に失敗しました';
      toast.error(msg);
    }
  };

  const totals = useMemo(() => {
    return payouts.reduce(
      (acc, p) => {
        if (p.status === 'finalized') acc.unpaid += p.commission_amount;
        if (p.status === 'paid') acc.paid += p.commission_amount;
        return acc;
      },
      { unpaid: 0, paid: 0 }
    );
  }, [payouts]);

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-bold text-[var(--neutral-foreground-1)]">代理店手数料</h1>
        <p className="mt-1 text-sm text-[var(--neutral-foreground-3)]">
          月次の代理店手数料を集計・確定し、銀行振込後に「支払い済み」マークを付けます。
          手数料は <strong>(売上 − Stripe手数料) × 手数料率</strong> で計算されます。
        </p>
      </div>

      <div className="grid grid-cols-2 gap-3 sm:grid-cols-3">
        <Card><CardBody>
          <p className="text-xs text-[var(--neutral-foreground-3)]">未払い手数料 合計</p>
          <p className="mt-1 text-2xl font-bold text-[var(--status-warning-fg)]">{formatJpy(totals.unpaid)}</p>
        </CardBody></Card>
        <Card><CardBody>
          <p className="text-xs text-[var(--neutral-foreground-3)]">支払い済み 合計（表示中）</p>
          <p className="mt-1 text-2xl font-bold text-[var(--neutral-foreground-1)]">{formatJpy(totals.paid)}</p>
        </CardBody></Card>
        <Card><CardBody>
          <p className="text-xs text-[var(--neutral-foreground-3)]">表示件数</p>
          <p className="mt-1 text-2xl font-bold text-[var(--neutral-foreground-1)]">{payouts.length}</p>
        </CardBody></Card>
      </div>

      <Card>
        <CardBody>
          <h2 className="text-base font-semibold text-[var(--neutral-foreground-1)]">月次集計の実行</h2>
          <p className="mt-1 text-xs text-[var(--neutral-foreground-3)]">
            指定月の代理店手数料を集計します。draft 状態で作成され、後から確定・支払いマークしてください。
            既存の draft があれば再計算で上書きされます（finalized 以降は触りません）。
          </p>
          <div className="mt-3 flex flex-wrap items-end gap-3">
            <div>
              <label className="block text-xs text-[var(--neutral-foreground-3)]">対象月（YYYY-MM）</label>
              <Input type="text" value={calcPeriod} onChange={(e) => setCalcPeriod(e.target.value)} placeholder={lastMonthYM()} className="w-32" />
              <p className="mt-1 text-[10px] text-[var(--neutral-foreground-4)]">前月: {lastMonthYM()} / 当月: {currentMonthYM()}</p>
            </div>
            <div>
              <label className="block text-xs text-[var(--neutral-foreground-3)]">代理店（任意・全代理店なら空）</label>
              <select
                value={calcAgent}
                onChange={(e) => setCalcAgent(e.target.value)}
                className="mt-1 rounded-md border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-1)] px-3 py-2 text-sm w-48"
              >
                <option value="">すべての代理店</option>
                {agents.map((a) => (<option key={a.id} value={a.id}>{a.name}</option>))}
              </select>
            </div>
            <Button onClick={submitCalculate} disabled={calculating}>
              <MaterialIcon name="calculate" size={18} />
              <span className="ml-1">集計実行</span>
            </Button>
          </div>
        </CardBody>
      </Card>

      <div className="flex flex-wrap gap-2 items-end">
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
        <div>
          <label className="block text-xs text-[var(--neutral-foreground-3)]">代理店フィルタ</label>
          <select
            value={agentFilter}
            onChange={(e) => setAgentFilter(e.target.value)}
            className="mt-1 rounded-md border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-1)] px-3 py-2 text-sm w-48"
          >
            <option value="">すべての代理店</option>
            {agents.map((a) => (<option key={a.id} value={a.id}>{a.name}</option>))}
          </select>
        </div>
      </div>

      {loading ? (
        <SkeletonList items={5} />
      ) : payouts.length === 0 ? (
        <Card><CardBody><p className="text-sm text-[var(--neutral-foreground-3)]">該当する手数料レコードはありません。</p></CardBody></Card>
      ) : (
        <Card>
          <CardBody>
            <div className="overflow-x-auto">
              <table className="min-w-full text-sm">
                <thead>
                  <tr className="border-b border-[var(--neutral-stroke-2)] text-left text-xs text-[var(--neutral-foreground-3)]">
                    <th className="py-2 pr-4 font-normal">代理店</th>
                    <th className="py-2 pr-4 font-normal">対象月</th>
                    <th className="py-2 pr-4 font-normal">状態</th>
                    <th className="py-2 pr-4 font-normal text-right">売上</th>
                    <th className="py-2 pr-4 font-normal text-right">Stripe手数料</th>
                    <th className="py-2 pr-4 font-normal text-right">利益</th>
                    <th className="py-2 pr-4 font-normal text-right">レート</th>
                    <th className="py-2 pr-4 font-normal text-right">手数料</th>
                    <th className="py-2 pr-4 font-normal">支払期日</th>
                    <th className="py-2 pr-4 font-normal">支払日</th>
                    <th className="py-2 font-normal" />
                  </tr>
                </thead>
                <tbody>
                  {payouts.map((p) => {
                    const s = STATUS_LABEL[p.status] ?? { label: p.status, variant: 'default' as const };
                    return (
                      <tr key={p.id} className="border-b border-[var(--neutral-stroke-3)]">
                        <td className="py-3 pr-4 font-medium text-[var(--neutral-foreground-1)]">{p.agent?.name || `agent#${p.agent_id}`}</td>
                        <td className="py-3 pr-4 text-[var(--neutral-foreground-2)]">{formatDate(p.period_start)} – {formatDate(p.period_end)}</td>
                        <td className="py-3 pr-4"><Badge variant={s.variant}>{s.label}</Badge></td>
                        <td className="py-3 pr-4 text-right">{formatJpy(p.gross_revenue)}</td>
                        <td className="py-3 pr-4 text-right">{formatJpy(p.stripe_fees)}</td>
                        <td className="py-3 pr-4 text-right">{formatJpy(p.net_profit)}</td>
                        <td className="py-3 pr-4 text-right text-[var(--neutral-foreground-2)]">{formatRate(p.commission_rate)}</td>
                        <td className="py-3 pr-4 text-right font-semibold">{formatJpy(p.commission_amount)}</td>
                        <td className="py-3 pr-4 text-[var(--neutral-foreground-2)]">{formatDate(p.due_date)}</td>
                        <td className="py-3 pr-4 text-[var(--neutral-foreground-2)]">{formatDate(p.paid_at)}</td>
                        <td className="py-3 text-right whitespace-nowrap">
                          {p.status === 'draft' && (
                            <>
                              <Button variant="ghost" onClick={() => handleFinalize(p.id)} title="確定">
                                <MaterialIcon name="check_circle" size={18} />
                              </Button>
                              <Button variant="ghost" onClick={() => handleCancel(p.id)} title="取消">
                                <MaterialIcon name="cancel" size={18} />
                              </Button>
                            </>
                          )}
                          {p.status === 'finalized' && (
                            <>
                              <Button variant="ghost" onClick={() => handleMarkPaid(p.id)} title="支払い済みマーク">
                                <MaterialIcon name="paid" size={18} />
                              </Button>
                              <Button variant="ghost" onClick={() => handleCancel(p.id)} title="取消">
                                <MaterialIcon name="cancel" size={18} />
                              </Button>
                            </>
                          )}
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
