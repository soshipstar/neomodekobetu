'use client';

import { useCallback, useEffect, useState } from 'react';
import api from '@/lib/api';
import { Card, CardBody } from '@/components/ui/Card';
import { Button } from '@/components/ui/Button';
import { Input } from '@/components/ui/Input';
import { SkeletonList } from '@/components/ui/Skeleton';
import { useToast } from '@/components/ui/Toast';
import { MaterialIcon } from '@/components/ui/MaterialIcon';

interface AuditLog {
  id: number;
  master_user_id: number;
  master_user: { id: number; full_name: string; username: string } | null;
  company_id: number | null;
  company: { id: number; name: string; code: string | null } | null;
  action: string;
  before: Record<string, unknown> | unknown[] | null;
  after: Record<string, unknown> | unknown[] | null;
  context: { ip?: string; user_agent?: string } | null;
  created_at: string;
}

const ACTION_LABEL: Record<string, string> = {
  update_display_settings: '表示設定の更新',
  update_feature_flags: '機能フラグ更新',
  update_individual_terms: '個別条件書の更新',
  update_customer_info: '請求先情報の更新',
  update_price: 'カスタム価格の更新',
  subscribe: '月額契約の開始',
  cancel_subscription: '解約',
  spot_invoice: 'スポット請求の発行',
  update_sales_channel: '販売チャネルの更新',
  create_agent: '代理店の作成',
  update_agent: '代理店の更新',
  delete_agent: '代理店の削除',
  upload_agent_contract: '代理店契約書のアップロード',
  delete_agent_contract: '代理店契約書の削除',
  create_agent_user: '代理店ユーザーの作成',
  update_agent_user: '代理店ユーザーの更新',
  delete_agent_user: '代理店ユーザーの削除',
  calculate_payouts: '手数料の月次集計',
  finalize_payout: '手数料集計の確定',
  mark_payout_paid: '手数料の支払い済みマーク',
  cancel_payout: '手数料集計の取消',
};

function formatDateTime(d: string | null | undefined): string {
  if (!d) return '—';
  try {
    return new Date(d).toLocaleString('ja-JP', { hour12: false });
  } catch {
    return d;
  }
}

export default function MasterAuditLogsPage() {
  const toast = useToast();
  const [logs, setLogs] = useState<AuditLog[]>([]);
  const [actions, setActions] = useState<string[]>([]);
  const [loading, setLoading] = useState(true);
  const [expandedIds, setExpandedIds] = useState<Set<number>>(new Set());

  const [actionFilter, setActionFilter] = useState<string>('');
  const [from, setFrom] = useState<string>('');
  const [to, setTo] = useState<string>('');

  const fetchAll = useCallback(async () => {
    setLoading(true);
    try {
      const params = new URLSearchParams();
      if (actionFilter) params.set('action', actionFilter);
      if (from) params.set('from', from);
      if (to) params.set('to', to);
      const res = await api.get(`/api/admin/master/audit-logs?${params.toString()}`);
      setLogs(res.data?.data?.logs ?? []);
      setActions(res.data?.data?.available_actions ?? []);
    } catch (err: unknown) {
      const msg = (err as { response?: { data?: { message?: string } } })?.response?.data?.message || '取得に失敗しました';
      toast.error(msg);
    } finally {
      setLoading(false);
    }
  }, [actionFilter, from, to, toast]);

  useEffect(() => { fetchAll(); }, [fetchAll]);

  const toggleExpand = (id: number) => {
    setExpandedIds((prev) => {
      const next = new Set(prev);
      if (next.has(id)) next.delete(id);
      else next.add(id);
      return next;
    });
  };

  const formatJson = (v: unknown): string => {
    if (v === null || v === undefined) return '（なし）';
    try {
      return JSON.stringify(v, null, 2);
    } catch {
      return String(v);
    }
  };

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-bold text-[var(--neutral-foreground-1)]">マスター操作履歴</h1>
        <p className="mt-1 text-sm text-[var(--neutral-foreground-3)]">
          マスター管理者が課金・代理店関連で行った操作の監査ログ。append-only で改変できません。
        </p>
      </div>

      <Card>
        <CardBody>
          <div className="flex flex-wrap items-end gap-3">
            <div>
              <label className="block text-xs text-[var(--neutral-foreground-3)]">アクション</label>
              <select
                value={actionFilter}
                onChange={(e) => setActionFilter(e.target.value)}
                className="mt-1 rounded-md border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-1)] px-3 py-2 text-sm w-64"
              >
                <option value="">すべて</option>
                {actions.map((a) => (
                  <option key={a} value={a}>{ACTION_LABEL[a] || a}</option>
                ))}
              </select>
            </div>
            <div>
              <label className="block text-xs text-[var(--neutral-foreground-3)]">開始日</label>
              <Input type="date" value={from} onChange={(e) => setFrom(e.target.value)} />
            </div>
            <div>
              <label className="block text-xs text-[var(--neutral-foreground-3)]">終了日</label>
              <Input type="date" value={to} onChange={(e) => setTo(e.target.value)} />
            </div>
            <Button variant="ghost" onClick={() => { setActionFilter(''); setFrom(''); setTo(''); }}>
              フィルタクリア
            </Button>
          </div>
        </CardBody>
      </Card>

      {loading ? (
        <SkeletonList items={5} />
      ) : logs.length === 0 ? (
        <Card><CardBody><p className="text-sm text-[var(--neutral-foreground-3)]">該当する操作履歴はありません。</p></CardBody></Card>
      ) : (
        <Card>
          <CardBody>
            <div className="overflow-x-auto">
              <table className="min-w-full text-sm">
                <thead>
                  <tr className="border-b border-[var(--neutral-stroke-2)] text-left text-xs text-[var(--neutral-foreground-3)]">
                    <th className="py-2 pr-4 font-normal">日時</th>
                    <th className="py-2 pr-4 font-normal">アクション</th>
                    <th className="py-2 pr-4 font-normal">操作者</th>
                    <th className="py-2 pr-4 font-normal">対象企業</th>
                    <th className="py-2 pr-4 font-normal">IP</th>
                    <th className="py-2 font-normal" />
                  </tr>
                </thead>
                <tbody>
                  {logs.map((log) => {
                    const expanded = expandedIds.has(log.id);
                    return (
                      <>
                        <tr key={log.id} className="border-b border-[var(--neutral-stroke-3)] hover:bg-[var(--neutral-background-2)]">
                          <td className="py-3 pr-4 text-[var(--neutral-foreground-1)] whitespace-nowrap">{formatDateTime(log.created_at)}</td>
                          <td className="py-3 pr-4 text-[var(--neutral-foreground-1)]">
                            <span className="rounded bg-[var(--neutral-background-3)] px-2 py-0.5 text-xs font-mono">{log.action}</span>
                            <p className="mt-0.5 text-xs text-[var(--neutral-foreground-3)]">{ACTION_LABEL[log.action] || ''}</p>
                          </td>
                          <td className="py-3 pr-4 text-[var(--neutral-foreground-2)]">
                            {log.master_user?.full_name || `user#${log.master_user_id}`}
                            {log.master_user?.username && (
                              <p className="text-xs text-[var(--neutral-foreground-4)]">{log.master_user.username}</p>
                            )}
                          </td>
                          <td className="py-3 pr-4 text-[var(--neutral-foreground-2)]">
                            {log.company ? (
                              <>
                                {log.company.name}
                                {log.company.code && <span className="ml-1 text-xs text-[var(--neutral-foreground-4)]">({log.company.code})</span>}
                              </>
                            ) : <span className="text-[var(--neutral-foreground-4)]">—</span>}
                          </td>
                          <td className="py-3 pr-4 text-[var(--neutral-foreground-3)] text-xs font-mono">{log.context?.ip || '—'}</td>
                          <td className="py-3 text-right">
                            <Button variant="ghost" onClick={() => toggleExpand(log.id)}>
                              <MaterialIcon name={expanded ? 'expand_less' : 'expand_more'} size={18} />
                            </Button>
                          </td>
                        </tr>
                        {expanded && (
                          <tr key={`${log.id}-detail`} className="bg-[var(--neutral-background-2)] border-b border-[var(--neutral-stroke-3)]">
                            <td colSpan={6} className="py-3 px-4">
                              <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 text-xs">
                                <div>
                                  <p className="font-semibold text-[var(--neutral-foreground-2)]">変更前 (before)</p>
                                  <pre className="mt-1 max-h-64 overflow-auto rounded bg-[var(--neutral-background-1)] p-2 font-mono text-[11px] text-[var(--neutral-foreground-1)]">{formatJson(log.before)}</pre>
                                </div>
                                <div>
                                  <p className="font-semibold text-[var(--neutral-foreground-2)]">変更後 (after)</p>
                                  <pre className="mt-1 max-h-64 overflow-auto rounded bg-[var(--neutral-background-1)] p-2 font-mono text-[11px] text-[var(--neutral-foreground-1)]">{formatJson(log.after)}</pre>
                                </div>
                                {log.context && (
                                  <div className="sm:col-span-2">
                                    <p className="font-semibold text-[var(--neutral-foreground-2)]">コンテキスト</p>
                                    <pre className="mt-1 rounded bg-[var(--neutral-background-1)] p-2 font-mono text-[11px] text-[var(--neutral-foreground-3)]">{formatJson(log.context)}</pre>
                                  </div>
                                )}
                              </div>
                            </td>
                          </tr>
                        )}
                      </>
                    );
                  })}
                </tbody>
              </table>
            </div>
            <p className="mt-3 text-[10px] text-[var(--neutral-foreground-4)]">
              最大 200 件まで表示（最新順）。期間フィルタで絞り込んでください。
            </p>
          </CardBody>
        </Card>
      )}
    </div>
  );
}
