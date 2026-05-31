'use client';

/**
 * /admin/audit-logs (マスター管理者専用)
 *
 * API アクセス監査ログの閲覧。流出/不正解析の後追い調査用。
 * URL クエリ ?user_id=N で特定ユーザーに絞った状態で開ける
 * (セキュリティアラートの「ログ調査」リンクから遷移)。
 */

import { useEffect, useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import api from '@/lib/api';
import { Card, CardBody, CardHeader, CardTitle } from '@/components/ui/Card';
import { Input } from '@/components/ui/Input';
import { Badge } from '@/components/ui/Badge';
import { Button } from '@/components/ui/Button';
import { SkeletonTable } from '@/components/ui/Skeleton';
import { MaterialIcon } from '@/components/ui/MaterialIcon';
import { useMasterGuard } from '@/hooks/useMasterGuard';

interface AuditLog {
  id: number;
  user_id: number | null;
  user_type: string | null;
  method: string;
  path: string;
  status_code: number;
  duration_ms: number | null;
  ip_address: string | null;
  response_bytes: number | null;
  created_at: string;
  user?: { id: number; full_name: string; user_type: string } | null;
}

function statusVariant(code: number): 'success' | 'warning' | 'danger' | 'default' {
  if (code >= 500) return 'danger';
  if (code === 403 || code === 401) return 'warning';
  if (code >= 400) return 'danger';
  if (code >= 200 && code < 300) return 'success';
  return 'default';
}

export default function AuditLogsPage() {
  const { isMaster, isReady } = useMasterGuard();
  const [userId, setUserId] = useState('');
  const [path, setPath] = useState('');
  const [minStatus, setMinStatus] = useState('');
  const [appliedFilters, setAppliedFilters] = useState<Record<string, string>>({});

  // URL の ?user_id= を初期フィルタに反映 (security-alerts からの遷移)
  useEffect(() => {
    if (typeof window === 'undefined') return;
    const sp = new URLSearchParams(window.location.search);
    const uid = sp.get('user_id');
    if (uid) {
      setUserId(uid);
      setAppliedFilters({ user_id: uid });
    }
  }, []);

  const { data: stats } = useQuery({
    queryKey: ['admin', 'audit-logs', 'stats'],
    queryFn: async () => (await api.get('/api/admin/audit-logs/stats')).data?.data,
    enabled: isReady && isMaster,
  });

  const { data, isLoading } = useQuery({
    queryKey: ['admin', 'audit-logs', appliedFilters],
    queryFn: async () => {
      const params: Record<string, unknown> = { per_page: 100, ...appliedFilters };
      return (await api.get('/api/admin/audit-logs', { params })).data;
    },
    enabled: isReady && isMaster,
  });

  if (!isReady || !isMaster) return null;

  const logs: AuditLog[] = data?.data?.data ?? [];

  const applyFilters = () => {
    const f: Record<string, string> = {};
    if (userId) f.user_id = userId;
    if (path) f.path = path;
    if (minStatus) f.min_status = minStatus;
    setAppliedFilters(f);
  };

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-bold text-[var(--neutral-foreground-1)]">アクセスログ</h1>
        <p className="mt-1 text-sm text-[var(--neutral-foreground-3)]">
          全 API リクエストの監査ログです。不審なアクセスの後追い調査に使用します (90 日保存)。
        </p>
      </div>

      {/* 直近 24h サマリ */}
      {stats && (
        <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
          <Card>
            <CardBody>
              <div className="text-xs text-[var(--neutral-foreground-3)]">直近 24h リクエスト</div>
              <div className="mt-1 text-2xl font-bold text-[var(--neutral-foreground-1)]">
                {stats.total_requests?.toLocaleString()}
              </div>
            </CardBody>
          </Card>
          <Card className="lg:col-span-3">
            <CardBody>
              <div className="mb-1 text-xs text-[var(--neutral-foreground-3)]">アクセス上位ユーザー (24h)</div>
              <div className="flex flex-wrap gap-2">
                {(stats.top_users ?? []).slice(0, 8).map((u: { user_id: number; full_name: string; count: number }) => (
                  <button
                    key={u.user_id}
                    onClick={() => { setUserId(String(u.user_id)); setAppliedFilters({ user_id: String(u.user_id) }); }}
                    className="rounded-full bg-[var(--neutral-background-3)] px-2.5 py-1 text-xs hover:bg-[var(--neutral-background-4)]"
                    title="このユーザーで絞り込む"
                  >
                    {u.full_name ?? `uid=${u.user_id}`}: {u.count.toLocaleString()}
                  </button>
                ))}
              </div>
            </CardBody>
          </Card>
        </div>
      )}

      {/* フィルタ */}
      <Card>
        <CardHeader><CardTitle>フィルタ</CardTitle></CardHeader>
        <CardBody>
          <div className="grid gap-3 sm:grid-cols-4">
            <Input label="ユーザーID" value={userId} onChange={(e) => setUserId(e.target.value)} placeholder="例: 159" />
            <Input label="パス (部分一致)" value={path} onChange={(e) => setPath(e.target.value)} placeholder="例: students" />
            <div>
              <label className="mb-1 block text-sm font-medium text-[var(--neutral-foreground-2)]">最小ステータス</label>
              <select
                value={minStatus}
                onChange={(e) => setMinStatus(e.target.value)}
                className="block w-full rounded-lg border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-1)] px-3 py-2 text-sm"
              >
                <option value="">すべて</option>
                <option value="400">4xx/5xx (エラーのみ)</option>
                <option value="500">5xx のみ</option>
              </select>
            </div>
            <div className="flex items-end">
              <Button onClick={applyFilters} leftIcon={<MaterialIcon name="search" size={16} />}>絞り込む</Button>
            </div>
          </div>
        </CardBody>
      </Card>

      {/* ログ一覧 */}
      {isLoading ? (
        <SkeletonTable rows={10} cols={6} />
      ) : (
        <Card>
          <CardBody>
            <div className="overflow-x-auto">
              <table className="w-full text-sm">
                <thead>
                  <tr className="border-b border-[var(--neutral-stroke-2)] text-left text-xs text-[var(--neutral-foreground-3)]">
                    <th className="px-2 py-2">日時</th>
                    <th className="px-2 py-2">ユーザー</th>
                    <th className="px-2 py-2">メソッド</th>
                    <th className="px-2 py-2">パス</th>
                    <th className="px-2 py-2">状態</th>
                    <th className="px-2 py-2">ms</th>
                    <th className="px-2 py-2">IP</th>
                  </tr>
                </thead>
                <tbody>
                  {logs.length === 0 ? (
                    <tr><td colSpan={7} className="py-6 text-center text-sm text-[var(--neutral-foreground-4)]">該当するログがありません</td></tr>
                  ) : logs.map((l) => (
                    <tr key={l.id} className="border-b border-[var(--neutral-stroke-3)] last:border-b-0">
                      <td className="px-2 py-1.5 whitespace-nowrap text-xs">{l.created_at?.slice(0, 19).replace('T', ' ')}</td>
                      <td className="px-2 py-1.5 whitespace-nowrap text-xs">
                        {l.user?.full_name ?? (l.user_id ? `uid=${l.user_id}` : '匿名')}
                      </td>
                      <td className="px-2 py-1.5"><Badge variant="default">{l.method}</Badge></td>
                      <td className="px-2 py-1.5 font-mono text-xs break-all">{l.path}</td>
                      <td className="px-2 py-1.5"><Badge variant={statusVariant(l.status_code)}>{l.status_code}</Badge></td>
                      <td className="px-2 py-1.5 text-right text-xs">{l.duration_ms ?? '-'}</td>
                      <td className="px-2 py-1.5 font-mono text-xs">{l.ip_address ?? '-'}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
            {data?.data?.last_page > 1 && (
              <p className="mt-3 text-center text-xs text-[var(--neutral-foreground-4)]">
                {data.data.total?.toLocaleString()} 件中 最新 100 件を表示。フィルタで絞り込んでください。
              </p>
            )}
          </CardBody>
        </Card>
      )}
    </div>
  );
}
