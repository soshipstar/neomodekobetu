'use client';

import { useCallback, useEffect, useState } from 'react';
import api, { formatApiError } from '@/lib/api';
import { Card, CardBody } from '@/components/ui/Card';
import { Badge } from '@/components/ui/Badge';
import { Button } from '@/components/ui/Button';
import { Skeleton } from '@/components/ui/Skeleton';
import { useToast } from '@/components/ui/Toast';
import { MaterialIcon } from '@/components/ui/MaterialIcon';

interface IndividualContract {
  id: number;
  agent_id: number;
  company_id: number;
  contract_date: string | null;
  start_date: string | null;
  end_date: string | null;
  monthly_fee: number | null;
  commission_rate: string | number | null;
  soship_signed: boolean;
  soship_signed_at: string | null;
  agent_signed: boolean;
  agent_signed_at: string | null;
  customer_signed: boolean;
  customer_signed_at: string | null;
  contract_document_path: string | null;
  agent?: { id: number; name: string; code?: string | null };
  company?: { id: number; name: string; code?: string | null };
}

interface AgentOption {
  id: number;
  name: string;
}

function fmtRate(r: string | number | null): string {
  if (r == null || r === '') return '—';
  return `${(parseFloat(String(r)) * 100).toFixed(1)}%`;
}

function fmtYen(n: number | null): string {
  if (n == null) return '—';
  return `¥${n.toLocaleString('ja-JP')}`;
}

export default function AdminIndividualContractsPage() {
  const toast = useToast();
  const [contracts, setContracts] = useState<IndividualContract[]>([]);
  const [agents, setAgents] = useState<AgentOption[]>([]);
  const [loading, setLoading] = useState(true);
  const [agentFilter, setAgentFilter] = useState<string>('');

  const fetchContracts = useCallback(async () => {
    try {
      setLoading(true);
      const params: Record<string, string | number> = { per_page: 100 };
      if (agentFilter) params.agent_id = Number(agentFilter);
      const res = await api.get('/api/admin/master/individual-contracts', { params });
      const payload = res.data?.data;
      const list: IndividualContract[] = Array.isArray(payload?.data) ? payload.data : (Array.isArray(payload) ? payload : []);
      setContracts(list);
    } catch (err) {
      toast.error(formatApiError(err, '個別契約書の取得に失敗しました'));
    } finally {
      setLoading(false);
    }
  }, [agentFilter, toast]);

  const fetchAgents = useCallback(async () => {
    try {
      const res = await api.get('/api/admin/agents');
      const payload = res.data?.data ?? [];
      const list = Array.isArray(payload) ? payload : (Array.isArray(payload?.data) ? payload.data : []);
      setAgents(list.map((a: AgentOption) => ({ id: a.id, name: a.name })));
    } catch {
      // ignore
    }
  }, []);

  useEffect(() => { fetchContracts(); }, [fetchContracts]);
  useEffect(() => { fetchAgents(); }, [fetchAgents]);

  const toggleSign = async (c: IndividualContract, party: 'soship_signed' | 'customer_signed') => {
    try {
      const newVal = !c[party];
      await api.put(`/api/admin/master/individual-contracts/${c.id}`, { [party]: newVal });
      toast.success(newVal ? '署名済としてマークしました' : '署名を解除しました');
      fetchContracts();
    } catch (err) {
      toast.error(formatApiError(err, '更新に失敗しました'));
    }
  };

  const handleOpenPdf = async (c: IndividualContract) => {
    try {
      const res = await api.get(`/api/admin/master/individual-contracts/${c.id}/document`, { responseType: 'blob' });
      const url = window.URL.createObjectURL(new Blob([res.data], { type: 'application/pdf' }));
      window.open(url, '_blank', 'noopener');
      setTimeout(() => window.URL.revokeObjectURL(url), 60000);
    } catch (err) {
      toast.error(formatApiError(err, 'PDFを開けませんでした'));
    }
  };

  const handleDelete = async (c: IndividualContract) => {
    if (!confirm(`${c.agent?.name ?? '?'} × ${c.company?.name ?? '?'} の契約書を削除しますか?`)) return;
    try {
      await api.delete(`/api/admin/master/individual-contracts/${c.id}`);
      toast.success('削除しました');
      fetchContracts();
    } catch (err) {
      toast.error(formatApiError(err, '削除に失敗しました'));
    }
  };

  return (
    <div className="space-y-4">
      <div>
        <h1 className="text-2xl font-bold text-[var(--neutral-foreground-1)]">個別契約書管理</h1>
        <p className="text-sm text-[var(--neutral-foreground-3)]">代理店が登録した3者間契約書を管理します。代理店側からは自社分のみ操作可能です。</p>
      </div>

      <Card>
        <CardBody>
          <div className="flex flex-wrap items-end gap-3">
            <div>
              <label className="mb-1 block text-xs font-semibold text-[var(--neutral-foreground-3)]">代理店で絞り込み</label>
              <select
                value={agentFilter}
                onChange={(e) => setAgentFilter(e.target.value)}
                className="block rounded-lg border border-[var(--neutral-stroke-1)] bg-[var(--neutral-background-1)] px-3 py-2 text-sm"
              >
                <option value="">全代理店</option>
                {agents.map((a) => (
                  <option key={a.id} value={a.id}>{a.name}</option>
                ))}
              </select>
            </div>
            <Button variant="outline" size="sm" onClick={fetchContracts} leftIcon={<MaterialIcon name="refresh" size={14} />}>
              更新
            </Button>
          </div>
        </CardBody>
      </Card>

      {loading ? (
        <div className="space-y-3">{[...Array(3)].map((_, i) => <Skeleton key={i} className="h-32 rounded-lg" />)}</div>
      ) : contracts.length === 0 ? (
        <Card>
          <CardBody>
            <div className="py-12 text-center text-[var(--neutral-foreground-4)]">
              <MaterialIcon name="description" size={48} className="mx-auto mb-3" />
              <p className="text-sm">個別契約書はまだありません</p>
            </div>
          </CardBody>
        </Card>
      ) : (
        <div className="space-y-3">
          {contracts.map((c) => {
            const fully = c.soship_signed && c.agent_signed && c.customer_signed;
            return (
              <Card key={c.id}>
                <CardBody>
                  <div className="flex items-start justify-between gap-2 flex-wrap mb-2">
                    <div>
                      <h3 className="text-base font-semibold text-[var(--neutral-foreground-1)]">
                        {c.agent?.name ?? `代理店ID:${c.agent_id}`} × {c.company?.name ?? `企業ID:${c.company_id}`}
                      </h3>
                      <p className="text-xs text-[var(--neutral-foreground-3)]">
                        契約日: {c.contract_date ?? '—'} / 期間: {c.start_date ?? '—'} 〜 {c.end_date ?? '—'}
                      </p>
                    </div>
                    <div className="flex items-center gap-2">
                      {fully ? <Badge variant="success" dot>3者署名完了</Badge> : <Badge variant="warning" dot>署名待ち</Badge>}
                      <Button size="sm" variant="ghost" onClick={() => handleDelete(c)} leftIcon={<MaterialIcon name="delete" size={14} />}>削除</Button>
                    </div>
                  </div>

                  <div className="grid grid-cols-1 gap-2 sm:grid-cols-3 text-sm">
                    <div><span className="text-[var(--neutral-foreground-3)]">月額料金:</span> {fmtYen(c.monthly_fee)}</div>
                    <div><span className="text-[var(--neutral-foreground-3)]">手数料率:</span> {fmtRate(c.commission_rate)}</div>
                    <div><span className="text-[var(--neutral-foreground-3)]">PDF:</span> {c.contract_document_path ? '登録済' : '未登録'}</div>
                  </div>

                  {/* 3者署名状態 + マスター操作 */}
                  <div className="mt-3 grid grid-cols-1 gap-2 sm:grid-cols-3 text-xs">
                    <SignedToggle label="株式会社ソーシップ" signed={c.soship_signed} at={c.soship_signed_at} onClick={() => toggleSign(c, 'soship_signed')} />
                    <SignedReadOnly label="代理店" signed={c.agent_signed} at={c.agent_signed_at} hint="代理店側で操作" />
                    <SignedToggle label="顧客企業" signed={c.customer_signed} at={c.customer_signed_at} onClick={() => toggleSign(c, 'customer_signed')} />
                  </div>

                  {c.contract_document_path && (
                    <div className="mt-3 pt-2 border-t border-[var(--neutral-stroke-3)]">
                      <Button size="sm" variant="outline" onClick={() => handleOpenPdf(c)} leftIcon={<MaterialIcon name="description" size={14} />}>
                        PDFを開く
                      </Button>
                    </div>
                  )}
                </CardBody>
              </Card>
            );
          })}
        </div>
      )}
    </div>
  );
}

function SignedToggle({ label, signed, at, onClick }: { label: string; signed: boolean; at: string | null; onClick: () => void }) {
  return (
    <button
      onClick={onClick}
      className={`text-left rounded border px-2 py-1.5 transition-colors ${signed ? 'border-[var(--status-success-fg)]/30 bg-[var(--status-success-bg)] text-[var(--status-success-fg)] hover:opacity-80' : 'border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-2)] text-[var(--neutral-foreground-3)] hover:bg-[var(--neutral-background-3)]'}`}
    >
      <div className="flex items-center gap-1 font-semibold">
        <MaterialIcon name={signed ? 'check_circle' : 'pending'} size={12} />
        {label}
      </div>
      <div className="text-[10px] opacity-80">{signed ? (at ? new Date(at).toLocaleDateString('ja-JP') : '署名済') : '未署名 (クリックで切替)'}</div>
    </button>
  );
}

function SignedReadOnly({ label, signed, at, hint }: { label: string; signed: boolean; at: string | null; hint: string }) {
  return (
    <div className={`rounded border px-2 py-1.5 ${signed ? 'border-[var(--status-success-fg)]/30 bg-[var(--status-success-bg)] text-[var(--status-success-fg)]' : 'border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-2)] text-[var(--neutral-foreground-3)]'}`}>
      <div className="flex items-center gap-1 font-semibold">
        <MaterialIcon name={signed ? 'check_circle' : 'pending'} size={12} />
        {label}
      </div>
      <div className="text-[10px] opacity-80">{signed ? (at ? new Date(at).toLocaleDateString('ja-JP') : '署名済') : `未署名 (${hint})`}</div>
    </div>
  );
}
