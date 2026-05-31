'use client';

/**
 * /admin/security-alerts (マスター管理者専用)
 *
 * 異常検知 (ApiAnomalyDetectionService) が検出したセキュリティアラートの
 * 一覧と対処管理。過大リクエスト / 403連発 / PDF連射 / 404連発 を表示する。
 */

import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import api, { formatApiError } from '@/lib/api';
import { Card, CardBody } from '@/components/ui/Card';
import { Badge } from '@/components/ui/Badge';
import { Button } from '@/components/ui/Button';
import { SkeletonTable } from '@/components/ui/Skeleton';
import { useToast } from '@/components/ui/Toast';
import { MaterialIcon } from '@/components/ui/MaterialIcon';
import { useMasterGuard } from '@/hooks/useMasterGuard';

const RULE_LABELS: Record<string, string> = {
  A_excessive_requests: '過大リクエスト',
  B_excessive_forbidden: '権限外アクセス連発',
  C_excessive_exports: 'PDF/CSV 連射',
  D_excessive_not_found: '404 連発 (列挙疑い)',
  E_honeypot: 'ハニーポット作動',
};

interface SecurityAlert {
  id: number;
  rule: string;
  user_id: number | null;
  user_name: string | null;
  user_type: string | null;
  count: number;
  title: string;
  body: string;
  detected_hour: string;
  is_resolved: boolean;
  resolved_note: string | null;
  resolved_at: string | null;
  created_at: string;
  user?: { id: number; full_name: string; user_type: string } | null;
  resolved_by?: { id: number; full_name: string } | null;
}

export default function SecurityAlertsPage() {
  const { isMaster, isReady } = useMasterGuard();
  const queryClient = useQueryClient();
  const { toast } = useToast();
  const [filter, setFilter] = useState<'unresolved' | 'all'>('unresolved');

  const { data, isLoading } = useQuery({
    queryKey: ['admin', 'security-alerts', filter],
    queryFn: async () => {
      const params: Record<string, unknown> = { per_page: 100 };
      if (filter === 'unresolved') params.is_resolved = false;
      const res = await api.get('/api/admin/security-alerts', { params });
      return res.data;
    },
    enabled: isReady && isMaster,
  });

  const resolveMutation = useMutation({
    mutationFn: ({ id, is_resolved, note }: { id: number; is_resolved: boolean; note?: string }) =>
      api.patch(`/api/admin/security-alerts/${id}/resolve`, { is_resolved, resolved_note: note }),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['admin', 'security-alerts'] });
      toast('更新しました', 'success');
    },
    onError: (err: unknown) => toast(formatApiError(err, '更新に失敗しました'), 'error'),
  });

  if (!isReady || !isMaster) return null;

  const alerts: SecurityAlert[] = data?.data ?? [];

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between flex-wrap gap-3">
        <div>
          <h1 className="text-2xl font-bold text-[var(--neutral-foreground-1)]">セキュリティアラート</h1>
          <p className="mt-1 text-sm text-[var(--neutral-foreground-3)]">
            異常なアクセスパターン (大量取得・権限外探索・一括ダウンロード等) を毎時自動検出します。
          </p>
        </div>
        <div className="flex gap-2">
          <button
            onClick={() => setFilter('unresolved')}
            className={`rounded-md px-3 py-1.5 text-sm font-medium ${filter === 'unresolved' ? 'bg-[var(--brand-80)] text-white' : 'text-[var(--neutral-foreground-2)] hover:bg-[var(--neutral-background-3)]'}`}
          >
            未対処
          </button>
          <button
            onClick={() => setFilter('all')}
            className={`rounded-md px-3 py-1.5 text-sm font-medium ${filter === 'all' ? 'bg-[var(--brand-80)] text-white' : 'text-[var(--neutral-foreground-2)] hover:bg-[var(--neutral-background-3)]'}`}
          >
            すべて
          </button>
        </div>
      </div>

      {isLoading ? (
        <SkeletonTable rows={6} cols={4} />
      ) : alerts.length === 0 ? (
        <Card>
          <CardBody>
            <div className="py-10 text-center">
              <MaterialIcon name="verified_user" size={36} className="mb-2 text-[var(--status-success-fg)]" />
              <p className="text-sm text-[var(--neutral-foreground-2)]">
                {filter === 'unresolved' ? '未対処のアラートはありません。' : 'アラートはまだありません。'}
              </p>
              <p className="mt-1 text-xs text-[var(--neutral-foreground-4)]">
                不審なアクセスが検出されると、こことマスター管理者の通知に表示されます。
              </p>
            </div>
          </CardBody>
        </Card>
      ) : (
        <div className="space-y-3">
          {alerts.map((a) => (
            <Card key={a.id}>
              <CardBody>
                <div className="flex items-start justify-between gap-3 flex-wrap">
                  <div className="flex-1 min-w-0">
                    <div className="flex flex-wrap items-center gap-2">
                      <Badge variant="danger">{RULE_LABELS[a.rule] ?? a.rule}</Badge>
                      <Badge variant="default">{a.count} 件/時</Badge>
                      {a.is_resolved && <Badge variant="success">対処済</Badge>}
                      <span className="text-xs text-[var(--neutral-foreground-4)]">
                        {a.created_at?.slice(0, 16).replace('T', ' ')}
                      </span>
                    </div>
                    <p className="mt-2 text-sm font-medium text-[var(--neutral-foreground-1)]">{a.title}</p>
                    <p className="mt-1 text-sm text-[var(--neutral-foreground-2)] whitespace-pre-wrap">{a.body}</p>
                    <div className="mt-2 text-xs text-[var(--neutral-foreground-3)]">
                      対象ユーザー: {a.user?.full_name ?? a.user_name ?? '-'}
                      {a.user_type && ` (${a.user_type})`}
                      {a.user_id != null && ` / uid=${a.user_id}`}
                    </div>
                    {a.is_resolved && a.resolved_note && (
                      <div className="mt-2 rounded bg-[var(--neutral-background-3)] p-2 text-xs text-[var(--neutral-foreground-2)]">
                        対処メモ: {a.resolved_note}
                        {a.resolved_by?.full_name && ` (${a.resolved_by.full_name})`}
                      </div>
                    )}
                  </div>
                  <div className="flex flex-col gap-2">
                    {a.user_id != null && (
                      <a
                        href={`/admin/audit-logs?user_id=${a.user_id}`}
                        className="inline-flex items-center gap-1 rounded border border-[var(--neutral-stroke-2)] px-2 py-1 text-xs hover:bg-[var(--neutral-background-3)]"
                      >
                        <MaterialIcon name="manage_search" size={12} /> ログ調査
                      </a>
                    )}
                    {a.is_resolved ? (
                      <Button size="sm" variant="ghost" onClick={() => resolveMutation.mutate({ id: a.id, is_resolved: false })}>
                        未対処に戻す
                      </Button>
                    ) : (
                      <Button
                        size="sm"
                        onClick={() => {
                          const note = window.prompt('対処メモ (任意) を入力してください:', '');
                          if (note === null) return;
                          resolveMutation.mutate({ id: a.id, is_resolved: true, note: note || undefined });
                        }}
                        isLoading={resolveMutation.isPending}
                      >
                        対処済みにする
                      </Button>
                    )}
                  </div>
                </div>
              </CardBody>
            </Card>
          ))}
        </div>
      )}
    </div>
  );
}
