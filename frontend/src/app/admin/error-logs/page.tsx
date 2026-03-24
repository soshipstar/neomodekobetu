'use client';

import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import api from '@/lib/api';
import { Card, CardBody } from '@/components/ui/Card';
import { Button } from '@/components/ui/Button';
import { Input } from '@/components/ui/Input';
import { Badge } from '@/components/ui/Badge';
import { Modal } from '@/components/ui/Modal';
import { Skeleton } from '@/components/ui/Skeleton';
import { useToast } from '@/components/ui/Toast';
import { useDebounce } from '@/hooks/useDebounce';
import { format } from 'date-fns';
import { MaterialIcon } from '@/components/ui/MaterialIcon';

// ---------------------------------------------------------------------------
// Types
// ---------------------------------------------------------------------------

interface ErrorLogEntry {
  id: number;
  level: string;
  message: string;
  exception_class: string | null;
  file: string | null;
  line: number | null;
  trace: string[] | null;
  url: string | null;
  method: string | null;
  user_id: number | null;
  user?: { id: number; full_name: string; user_type: string } | null;
  ip_address: string | null;
  user_agent: string | null;
  request_data: Record<string, unknown> | null;
  created_at: string;
}

interface Summary {
  today: number;
  this_week: number;
  total: number;
  by_level: { error: number; warning: number; critical: number };
}

const LEVEL_CONFIG: Record<string, { icon: typeof AlertTriangle; color: string; bg: string; label: string }> = {
  critical: { icon: "cancel", color: 'text-red-700', bg: 'bg-red-100', label: '重大' },
  error:    { icon: "error", color: 'text-orange-700', bg: 'bg-orange-100', label: 'エラー' },
  warning:  { icon: "warning", color: 'text-yellow-700', bg: 'bg-yellow-100', label: '警告' },
};

// ---------------------------------------------------------------------------
// Main Component
// ---------------------------------------------------------------------------

export default function ErrorLogsPage() {
  const queryClient = useQueryClient();
  const toast = useToast();
  const [search, setSearch] = useState('');
  const [levelFilter, setLevelFilter] = useState('');
  const [dateFrom, setDateFrom] = useState('');
  const [dateTo, setDateTo] = useState('');
  const [selectedLog, setSelectedLog] = useState<ErrorLogEntry | null>(null);
  const [page, setPage] = useState(1);
  const debouncedSearch = useDebounce(search, 300);

  // Fetch summary
  const { data: summary } = useQuery({
    queryKey: ['admin', 'error-logs', 'summary'],
    queryFn: async () => {
      const res = await api.get<{ data: Summary }>('/api/admin/error-logs/summary');
      return res.data.data;
    },
  });

  // Fetch logs
  const { data, isLoading, refetch } = useQuery({
    queryKey: ['admin', 'error-logs', page, debouncedSearch, levelFilter, dateFrom, dateTo],
    queryFn: async () => {
      const params: Record<string, string | number> = { page, per_page: 30 };
      if (debouncedSearch) params.search = debouncedSearch;
      if (levelFilter) params.level = levelFilter;
      if (dateFrom) params.date_from = dateFrom;
      if (dateTo) params.date_to = dateTo;
      const res = await api.get('/api/admin/error-logs', { params });
      return {
        logs: (res.data?.data || []) as ErrorLogEntry[],
        meta: res.data?.meta as { current_page: number; last_page: number; total: number } | undefined,
      };
    },
  });

  const logs = data?.logs ?? [];
  const meta = data?.meta;

  // Cleanup mutation
  const cleanupMutation = useMutation({
    mutationFn: (days: number) => api.delete('/api/admin/error-logs/cleanup', { data: { days } }),
    onSuccess: (res: any) => {
      queryClient.invalidateQueries({ queryKey: ['admin', 'error-logs'] });
      toast.success(res.data?.message || '古いログを削除しました');
    },
    onError: () => toast.error('削除に失敗しました'),
  });

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold text-[var(--neutral-foreground-1)]">エラーログ</h1>
          <p className="text-sm text-[var(--neutral-foreground-3)]">システムエラーの記録・確認</p>
        </div>
        <div className="flex gap-2">
          <Button variant="outline" size="sm" leftIcon={<RefreshCw className="h-4 w-4" />} onClick={() => refetch()}>
            更新
          </Button>
          <Button variant="ghost" size="sm" leftIcon={<MaterialIcon name="delete" size={16} />}
            onClick={() => { if (confirm('30日以上前のログを削除しますか？')) cleanupMutation.mutate(30); }}>
            古いログを削除
          </Button>
        </div>
      </div>

      {/* Summary cards */}
      {summary && (
        <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
          <SummaryCard label="今日" count={summary.today} icon={<MaterialIcon name="schedule" size={20} className="text-[var(--brand-80)]" />} bg="bg-[var(--brand-160)]" />
          <SummaryCard label="今週" count={summary.this_week} icon={<MaterialIcon name="error" size={20} className="text-orange-600" />} bg="bg-orange-100" />
          <SummaryCard label="合計" count={summary.total} icon={<MaterialIcon name="warning" size={20} className="text-[var(--neutral-foreground-3)]" />} bg="bg-[var(--neutral-background-4)]" />
          <Card>
            <CardBody>
              <div className="flex items-center justify-between text-xs">
                <span className="text-red-600 font-bold">{summary.by_level.critical} 重大</span>
                <span className="text-orange-600 font-bold">{summary.by_level.error} エラー</span>
                <span className="text-yellow-600 font-bold">{summary.by_level.warning} 警告</span>
              </div>
              <p className="text-[9px] text-[var(--neutral-foreground-4)] mt-1">過去7日間のレベル別</p>
            </CardBody>
          </Card>
        </div>
      )}

      {/* Filters */}
      <Card>
        <CardBody>
          <div className="flex flex-wrap gap-3">
            <div className="relative flex-1 min-w-[200px]">
              <MaterialIcon name="search" size={16} className="absolute left-3 top-1/2 -translate-y-1/2 text-[var(--neutral-foreground-4)]" />
              <Input placeholder="メッセージ・URL・クラスで検索..." value={search}
                onChange={(e) => { setSearch(e.target.value); setPage(1); }} className="pl-10" />
            </div>
            <select value={levelFilter} onChange={(e) => { setLevelFilter(e.target.value); setPage(1); }}
              className="rounded-lg border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-1)] px-3 py-2 text-sm">
              <option value="">全レベル</option>
              <option value="critical">重大</option>
              <option value="error">エラー</option>
              <option value="warning">警告</option>
            </select>
            <input type="date" value={dateFrom} onChange={(e) => { setDateFrom(e.target.value); setPage(1); }}
              className="rounded-lg border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-1)] px-3 py-2 text-sm" />
            <span className="self-center text-sm text-[var(--neutral-foreground-4)]">〜</span>
            <input type="date" value={dateTo} onChange={(e) => { setDateTo(e.target.value); setPage(1); }}
              className="rounded-lg border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-1)] px-3 py-2 text-sm" />
          </div>
        </CardBody>
      </Card>

      {/* Log table */}
      {isLoading ? (
        <div className="space-y-2">{[...Array(8)].map((_, i) => <Skeleton key={i} className="h-12 rounded-lg" />)}</div>
      ) : logs.length === 0 ? (
        <Card>
          <CardBody>
            <div className="py-12 text-center text-[var(--neutral-foreground-4)]">
              <MaterialIcon name="error" size={48} className="mx-auto mb-3" />
              <p>エラーログはありません</p>
            </div>
          </CardBody>
        </Card>
      ) : (
        <>
          <div className="overflow-x-auto rounded-lg border border-[var(--neutral-stroke-2)]">
            <table className="w-full text-sm">
              <thead>
                <tr className="border-b border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-3)]">
                  <th className="px-3 py-2 text-left text-xs font-semibold text-[var(--neutral-foreground-3)]">レベル</th>
                  <th className="px-3 py-2 text-left text-xs font-semibold text-[var(--neutral-foreground-3)]">日時</th>
                  <th className="px-3 py-2 text-left text-xs font-semibold text-[var(--neutral-foreground-3)]">メッセージ</th>
                  <th className="px-3 py-2 text-left text-xs font-semibold text-[var(--neutral-foreground-3)]">URL</th>
                  <th className="px-3 py-2 text-left text-xs font-semibold text-[var(--neutral-foreground-3)]">ユーザー</th>
                  <th className="px-3 py-2 text-left text-xs font-semibold text-[var(--neutral-foreground-3)]">操作</th>
                </tr>
              </thead>
              <tbody>
                {logs.map((log) => {
                  const cfg = LEVEL_CONFIG[log.level] || LEVEL_CONFIG.error;
                  const Icon = cfg.icon;
                  return (
                    <tr key={log.id} className="border-b border-[var(--neutral-stroke-3)] hover:bg-[var(--neutral-background-3)]">
                      <td className="px-3 py-2">
                        <span className={`inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[10px] font-bold ${cfg.bg} ${cfg.color}`}>
                          <Icon className="h-3 w-3" /> {cfg.label}
                        </span>
                      </td>
                      <td className="px-3 py-2 text-xs text-[var(--neutral-foreground-3)] whitespace-nowrap">
                        {format(new Date(log.created_at), 'MM/dd HH:mm:ss')}
                      </td>
                      <td className="px-3 py-2 max-w-[300px]">
                        <p className="text-xs text-[var(--neutral-foreground-1)] truncate font-mono">{log.message}</p>
                        {log.exception_class && (
                          <p className="text-[10px] text-[var(--neutral-foreground-4)] truncate">{log.exception_class}</p>
                        )}
                      </td>
                      <td className="px-3 py-2 text-[10px] text-[var(--neutral-foreground-3)] max-w-[200px] truncate">
                        {log.method && <Badge variant="default" className="text-[8px] mr-1">{log.method}</Badge>}
                        {log.url?.replace(/https?:\/\/[^/]+/, '') || '-'}
                      </td>
                      <td className="px-3 py-2 text-xs text-[var(--neutral-foreground-3)]">
                        {log.user?.full_name || (log.user_id ? `ID:${log.user_id}` : '-')}
                      </td>
                      <td className="px-3 py-2">
                        <Button variant="ghost" size="sm" onClick={() => setSelectedLog(log)}>
                          <MaterialIcon name="visibility" size={14} />
                        </Button>
                      </td>
                    </tr>
                  );
                })}
              </tbody>
            </table>
          </div>

          {/* Pagination */}
          {meta && meta.last_page > 1 && (
            <div className="flex items-center justify-center gap-2">
              <Button variant="outline" size="sm" disabled={page <= 1} onClick={() => setPage(page - 1)}>前へ</Button>
              <span className="text-sm text-[var(--neutral-foreground-3)]">{meta.current_page} / {meta.last_page}（{meta.total}件）</span>
              <Button variant="outline" size="sm" disabled={page >= meta.last_page} onClick={() => setPage(page + 1)}>次へ</Button>
            </div>
          )}
        </>
      )}

      {/* Detail Modal */}
      <Modal isOpen={!!selectedLog} onClose={() => setSelectedLog(null)} title="エラーログ詳細" size="lg">
        {selectedLog && (
          <div className="space-y-4 text-sm">
            <div className="grid grid-cols-2 gap-3">
              <div><span className="text-xs text-[var(--neutral-foreground-3)]">レベル</span>
                <p className="font-mono">{selectedLog.level}</p></div>
              <div><span className="text-xs text-[var(--neutral-foreground-3)]">日時</span>
                <p>{format(new Date(selectedLog.created_at), 'yyyy/MM/dd HH:mm:ss')}</p></div>
              <div><span className="text-xs text-[var(--neutral-foreground-3)]">ユーザー</span>
                <p>{selectedLog.user?.full_name || '-'} ({selectedLog.user?.user_type || '-'})</p></div>
              <div><span className="text-xs text-[var(--neutral-foreground-3)]">IP</span>
                <p className="font-mono">{selectedLog.ip_address || '-'}</p></div>
              <div className="col-span-2"><span className="text-xs text-[var(--neutral-foreground-3)]">URL</span>
                <p className="font-mono text-xs break-all">{selectedLog.method} {selectedLog.url || '-'}</p></div>
            </div>

            <div>
              <span className="text-xs text-[var(--neutral-foreground-3)]">例外クラス</span>
              <p className="font-mono text-xs text-red-600 break-all">{selectedLog.exception_class || '-'}</p>
            </div>

            <div>
              <span className="text-xs text-[var(--neutral-foreground-3)]">メッセージ</span>
              <pre className="mt-1 rounded-lg bg-[var(--neutral-background-3)] p-3 text-xs font-mono whitespace-pre-wrap break-all max-h-40 overflow-y-auto">
                {selectedLog.message}
              </pre>
            </div>

            <div>
              <span className="text-xs text-[var(--neutral-foreground-3)]">ファイル</span>
              <p className="font-mono text-xs">{selectedLog.file}:{selectedLog.line}</p>
            </div>

            {selectedLog.trace && selectedLog.trace.length > 0 && (
              <div>
                <span className="text-xs text-[var(--neutral-foreground-3)]">スタックトレース</span>
                <pre className="mt-1 rounded-lg bg-gray-900 text-green-400 p-3 text-[10px] font-mono whitespace-pre-wrap max-h-48 overflow-y-auto">
                  {selectedLog.trace.join('\n')}
                </pre>
              </div>
            )}

            {selectedLog.request_data && Object.keys(selectedLog.request_data).length > 0 && (
              <div>
                <span className="text-xs text-[var(--neutral-foreground-3)]">リクエストデータ</span>
                <pre className="mt-1 rounded-lg bg-[var(--neutral-background-3)] p-3 text-[10px] font-mono whitespace-pre-wrap max-h-32 overflow-y-auto">
                  {JSON.stringify(selectedLog.request_data, null, 2)}
                </pre>
              </div>
            )}
          </div>
        )}
      </Modal>
    </div>
  );
}

// ---------------------------------------------------------------------------
// Summary Card
// ---------------------------------------------------------------------------

function SummaryCard({ label, count, icon, bg }: { label: string; count: number; icon: React.ReactNode; bg: string }) {
  return (
    <Card>
      <CardBody>
        <div className="flex items-center gap-3">
          <div className={`flex h-10 w-10 items-center justify-center rounded-lg ${bg}`}>{icon}</div>
          <div>
            <p className="text-2xl font-bold text-[var(--neutral-foreground-1)]">{count}</p>
            <p className="text-xs text-[var(--neutral-foreground-3)]">{label}</p>
          </div>
        </div>
      </CardBody>
    </Card>
  );
}
