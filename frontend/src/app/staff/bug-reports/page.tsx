'use client';

import { useState, useRef, useCallback } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import api from '@/lib/api';
import { Card, CardHeader, CardTitle, CardBody } from '@/components/ui/Card';
import { Button } from '@/components/ui/Button';
import { Badge } from '@/components/ui/Badge';
import { Input } from '@/components/ui/Input';
import { Modal } from '@/components/ui/Modal';
import { SkeletonList } from '@/components/ui/Skeleton';
import { useToast } from '@/components/ui/Toast';
import { MaterialIcon } from '@/components/ui/MaterialIcon';
import { useAuthStore } from '@/stores/authStore';
import { format } from 'date-fns';
import { ja } from 'date-fns/locale';

interface BugReport {
  id: number;
  page_url: string;
  description: string;
  console_log: string | null;
  screenshot_path: string | null;
  screenshot_url?: string;
  status: string;
  priority: string;
  created_at: string;
  replies_count?: number;
  reporter?: { id: number; full_name: string; classroom?: { id: number; classroom_name: string } };
  replies?: { id: number; message: string; created_at: string; user?: { id: number; full_name: string } }[];
}

const STATUS_MAP: Record<string, { label: string; variant: 'danger' | 'warning' | 'success' | 'default' }> = {
  open: { label: '未対応', variant: 'danger' },
  in_progress: { label: '対応中', variant: 'warning' },
  resolved: { label: '解決済み', variant: 'success' },
};

const PRIORITY_MAP: Record<string, { label: string; color: string }> = {
  low: { label: '低', color: 'text-gray-500' },
  normal: { label: '中', color: 'text-blue-600' },
  high: { label: '高', color: 'text-orange-600' },
  critical: { label: '緊急', color: 'text-red-600 font-bold' },
};

export default function BugReportsPage() {
  const { user } = useAuthStore();
  const toast = useToast();
  const queryClient = useQueryClient();
  const fileRef = useRef<HTMLInputElement>(null);
  const isAdmin = user?.is_master || user?.is_company_admin;

  // State
  const [showNewReport, setShowNewReport] = useState(false);
  const [selectedReport, setSelectedReport] = useState<BugReport | null>(null);
  const [statusFilter, setStatusFilter] = useState('open');
  const [replyText, setReplyText] = useState('');

  // Form
  const [form, setForm] = useState({
    page_url: '',
    description: '',
    console_log: '',
    priority: 'normal',
  });
  const [screenshot, setScreenshot] = useState<File | null>(null);

  // Fetch reports
  const { data: reportsData, isLoading } = useQuery({
    queryKey: ['bug-reports', statusFilter],
    queryFn: async () => {
      const params: Record<string, string> = { per_page: '50' };
      if (statusFilter) params.status = statusFilter;
      const res = await api.get('/api/staff/bug-reports', { params });
      return res.data.data;
    },
  });
  const reports: BugReport[] = reportsData?.data || [];

  // Fetch detail
  const { data: detail } = useQuery({
    queryKey: ['bug-report-detail', selectedReport?.id],
    queryFn: async () => {
      const res = await api.get(`/api/staff/bug-reports/${selectedReport!.id}`);
      return res.data.data as BugReport;
    },
    enabled: !!selectedReport,
  });

  // Submit report
  const submitMutation = useMutation({
    mutationFn: async () => {
      const formData = new FormData();
      formData.append('page_url', form.page_url);
      formData.append('description', form.description);
      if (form.console_log) formData.append('console_log', form.console_log);
      formData.append('priority', form.priority);
      if (screenshot) formData.append('screenshot', screenshot);
      return api.post('/api/staff/bug-reports', formData);
    },
    onSuccess: () => {
      toast.success('バグ報告を送信しました');
      setShowNewReport(false);
      setForm({ page_url: '', description: '', console_log: '', priority: 'normal' });
      setScreenshot(null);
      queryClient.invalidateQueries({ queryKey: ['bug-reports'] });
    },
    onError: () => toast.error('送信に失敗しました'),
  });

  // Reply
  const replyMutation = useMutation({
    mutationFn: async () => {
      return api.post(`/api/staff/bug-reports/${selectedReport!.id}/reply`, { message: replyText });
    },
    onSuccess: () => {
      setReplyText('');
      queryClient.invalidateQueries({ queryKey: ['bug-report-detail', selectedReport?.id] });
      queryClient.invalidateQueries({ queryKey: ['bug-reports'] });
    },
    onError: () => toast.error('返信に失敗しました'),
  });

  // Status change
  const statusMutation = useMutation({
    mutationFn: async (status: string) => {
      return api.patch(`/api/staff/bug-reports/${selectedReport!.id}/status`, { status });
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['bug-report-detail', selectedReport?.id] });
      queryClient.invalidateQueries({ queryKey: ['bug-reports'] });
      toast.success('ステータスを更新しました');
    },
  });

  // Auto-fill current URL
  const openNewReport = useCallback(() => {
    setForm({
      page_url: window.location.href,
      description: '',
      console_log: '',
      priority: 'normal',
    });
    setScreenshot(null);
    setShowNewReport(true);
  }, []);

  return (
    <div className="space-y-4 p-4">
      <div className="flex items-center justify-between flex-wrap gap-3">
        <div>
          <h1 className="text-xl font-bold text-[var(--neutral-foreground-1)]">
            <MaterialIcon name="bug_report" size={24} className="inline-block mr-2 align-text-bottom" />
            バグ報告
          </h1>
          <p className="text-xs text-[var(--neutral-foreground-3)] mt-1">
            システムの不具合をシステム管理者に報告できます
          </p>
        </div>
        <Button variant="primary" onClick={openNewReport} leftIcon={<MaterialIcon name="add" size={16} />}>
          新しい報告
        </Button>
      </div>

      {/* Filter */}
      <div className="flex gap-2 flex-wrap">
        {[
          { value: 'open', label: '未対応' },
          { value: 'in_progress', label: '対応中' },
          { value: 'resolved', label: '解決済み' },
          { value: '', label: 'すべて' },
        ].map((f) => (
          <button
            key={f.value}
            onClick={() => setStatusFilter(f.value)}
            className={`rounded-full px-3 py-1 text-xs font-medium transition-colors ${
              statusFilter === f.value
                ? 'bg-purple-600 text-white'
                : 'bg-[var(--neutral-background-3)] text-[var(--neutral-foreground-2)] hover:bg-[var(--neutral-background-4)]'
            }`}
          >
            {f.label}
          </button>
        ))}
      </div>

      {/* Report List */}
      {isLoading ? (
        <SkeletonList items={3} />
      ) : reports.length === 0 ? (
        <Card>
          <CardBody>
            <div className="py-12 text-center">
              <MaterialIcon name="check_circle" size={48} className="mx-auto text-green-400" />
              <p className="mt-2 text-sm text-[var(--neutral-foreground-3)]">報告はありません</p>
            </div>
          </CardBody>
        </Card>
      ) : (
        <div className="space-y-2">
          {reports.map((r) => {
            const st = STATUS_MAP[r.status] || STATUS_MAP.open;
            const pr = PRIORITY_MAP[r.priority] || PRIORITY_MAP.normal;
            return (
              <button
                key={r.id}
                onClick={() => setSelectedReport(r)}
                className="block w-full rounded-lg border border-[var(--neutral-stroke-2)] bg-white p-4 text-left transition-all hover:shadow-md"
              >
                <div className="flex items-start justify-between gap-2">
                  <div className="flex-1 min-w-0">
                    <p className="text-sm font-medium text-[var(--neutral-foreground-1)] line-clamp-1">{r.description}</p>
                    <p className="mt-1 text-xs text-[var(--neutral-foreground-3)] truncate">
                      <MaterialIcon name="link" size={12} className="inline mr-1" />
                      {r.page_url}
                    </p>
                    <div className="mt-2 flex items-center gap-3 text-xs text-[var(--neutral-foreground-4)]">
                      {isAdmin && r.reporter && (
                        <span>
                          <MaterialIcon name="person" size={12} className="inline mr-0.5" />
                          {r.reporter.full_name}
                          {r.reporter.classroom && ` (${r.reporter.classroom.classroom_name})`}
                        </span>
                      )}
                      <span>{format(new Date(r.created_at), 'M/d HH:mm', { locale: ja })}</span>
                      {(r.replies_count ?? 0) > 0 && (
                        <span><MaterialIcon name="chat" size={12} className="inline mr-0.5" />{r.replies_count}</span>
                      )}
                    </div>
                  </div>
                  <div className="flex flex-col items-end gap-1">
                    <Badge variant={st.variant}>{st.label}</Badge>
                    <span className={`text-xs ${pr.color}`}>{pr.label}</span>
                  </div>
                </div>
              </button>
            );
          })}
        </div>
      )}

      {/* New Report Modal */}
      <Modal isOpen={showNewReport} onClose={() => setShowNewReport(false)} title="バグ報告を作成" size="lg">
        <div className="space-y-4">
          <Input
            label="発生したページのURL"
            value={form.page_url}
            onChange={(e) => setForm({ ...form, page_url: e.target.value })}
            placeholder="https://kiduri.xyz/staff/..."
          />

          <div>
            <label className="mb-1 block text-sm font-medium">エラー内容 *</label>
            <textarea
              className="block w-full rounded-lg border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-1)] px-3 py-2 text-sm"
              rows={4}
              value={form.description}
              onChange={(e) => setForm({ ...form, description: e.target.value })}
              placeholder="どのような操作をしたときに、何が起きましたか？&#10;期待される動作と実際の動作を記載してください。"
            />
          </div>

          <div>
            <label className="mb-1 block text-sm font-medium">
              コンソールログ（F12 → Console）
            </label>
            <textarea
              className="block w-full rounded-lg border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-1)] px-3 py-2 text-sm font-mono text-xs"
              rows={4}
              value={form.console_log}
              onChange={(e) => setForm({ ...form, console_log: e.target.value })}
              placeholder="F12キーを押して「Console」タブの赤いエラーメッセージをコピー＆ペーストしてください"
            />
            <p className="mt-1 text-xs text-[var(--neutral-foreground-4)]">
              ブラウザでF12キーを押し、「Console」タブに表示される赤いエラーメッセージを貼り付けてください
            </p>
          </div>

          <div>
            <label className="mb-1 block text-sm font-medium">エラー画面のスクリーンショット</label>
            {screenshot ? (
              <div className="flex items-center gap-2 rounded border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-3)] p-2">
                <MaterialIcon name="image" size={18} />
                <span className="flex-1 truncate text-sm">{screenshot.name}</span>
                <button onClick={() => setScreenshot(null)} className="text-[var(--status-danger-fg)]">
                  <MaterialIcon name="close" size={16} />
                </button>
              </div>
            ) : (
              <button
                onClick={() => fileRef.current?.click()}
                className="flex w-full items-center justify-center gap-2 rounded border border-dashed border-[var(--neutral-stroke-2)] py-4 text-sm text-[var(--neutral-foreground-3)] hover:bg-[var(--neutral-background-3)]"
              >
                <MaterialIcon name="screenshot_monitor" size={20} />
                スクリーンショットを添付
              </button>
            )}
            <input
              ref={fileRef}
              type="file"
              accept="image/*"
              className="hidden"
              onChange={(e) => setScreenshot(e.target.files?.[0] ?? null)}
            />
          </div>

          <div>
            <label className="mb-1 block text-sm font-medium">重要度</label>
            <select
              value={form.priority}
              onChange={(e) => setForm({ ...form, priority: e.target.value })}
              className="block w-full rounded-lg border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-1)] px-3 py-2 text-sm"
            >
              <option value="low">低 - 軽微な問題</option>
              <option value="normal">中 - 通常の不具合</option>
              <option value="high">高 - 業務に支障あり</option>
              <option value="critical">緊急 - 業務が停止</option>
            </select>
          </div>

          <div className="flex justify-end gap-2 pt-2">
            <Button variant="outline" onClick={() => setShowNewReport(false)}>キャンセル</Button>
            <Button
              variant="primary"
              onClick={() => submitMutation.mutate()}
              isLoading={submitMutation.isPending}
              disabled={!form.description.trim() || !form.page_url.trim()}
              leftIcon={<MaterialIcon name="send" size={16} />}
            >
              送信
            </Button>
          </div>
        </div>
      </Modal>

      {/* Detail Modal */}
      {selectedReport && (
        <Modal isOpen={true} onClose={() => { setSelectedReport(null); setReplyText(''); }} title="報告詳細" size="lg">
          <div className="space-y-4 max-h-[80vh] overflow-y-auto">
            {detail ? (
              <>
                {/* Header */}
                <div className="flex items-center justify-between">
                  <div className="flex items-center gap-2">
                    <Badge variant={STATUS_MAP[detail.status]?.variant || 'default'}>
                      {STATUS_MAP[detail.status]?.label || detail.status}
                    </Badge>
                    <span className={`text-sm ${PRIORITY_MAP[detail.priority]?.color || ''}`}>
                      {PRIORITY_MAP[detail.priority]?.label || detail.priority}
                    </span>
                  </div>
                  <span className="text-xs text-[var(--neutral-foreground-4)]">
                    {format(new Date(detail.created_at), 'yyyy/M/d HH:mm', { locale: ja })}
                  </span>
                </div>

                {/* Reporter */}
                {detail.reporter && (
                  <p className="text-sm text-[var(--neutral-foreground-3)]">
                    報告者: {detail.reporter.full_name}
                    {detail.reporter.classroom && ` (${detail.reporter.classroom.classroom_name})`}
                  </p>
                )}

                {/* URL */}
                <div className="rounded-lg bg-[var(--neutral-background-3)] p-3">
                  <p className="text-xs font-medium text-[var(--neutral-foreground-3)] mb-1">発生ページ</p>
                  <a href={detail.page_url} target="_blank" rel="noopener noreferrer"
                    className="text-sm text-blue-600 underline break-all">{detail.page_url}</a>
                </div>

                {/* Description */}
                <div>
                  <p className="text-xs font-medium text-[var(--neutral-foreground-3)] mb-1">エラー内容</p>
                  <p className="text-sm whitespace-pre-wrap">{detail.description}</p>
                </div>

                {/* Screenshot */}
                {detail.screenshot_url && (
                  <div>
                    <p className="text-xs font-medium text-[var(--neutral-foreground-3)] mb-1">スクリーンショット</p>
                    <a href={detail.screenshot_url} target="_blank" rel="noopener noreferrer">
                      {/* eslint-disable-next-line @next/next/no-img-element */}
                      <img src={detail.screenshot_url} alt="スクリーンショット"
                        className="max-w-full max-h-[300px] rounded-lg border border-[var(--neutral-stroke-2)] object-contain" />
                    </a>
                  </div>
                )}

                {/* Console Log */}
                {detail.console_log && (
                  <div>
                    <p className="text-xs font-medium text-[var(--neutral-foreground-3)] mb-1">コンソールログ</p>
                    <pre className="rounded-lg bg-gray-900 p-3 text-xs text-green-400 overflow-x-auto max-h-[200px] overflow-y-auto whitespace-pre-wrap">
                      {detail.console_log}
                    </pre>
                  </div>
                )}

                {/* Admin: Status Change */}
                {isAdmin && (
                  <div className="flex items-center gap-2 border-t border-[var(--neutral-stroke-2)] pt-3">
                    <span className="text-xs text-[var(--neutral-foreground-3)]">ステータス:</span>
                    {['open', 'in_progress', 'resolved'].map((s) => (
                      <button
                        key={s}
                        onClick={() => statusMutation.mutate(s)}
                        disabled={detail.status === s}
                        className={`rounded-full px-3 py-1 text-xs font-medium transition-colors ${
                          detail.status === s
                            ? 'bg-purple-600 text-white'
                            : 'bg-[var(--neutral-background-3)] text-[var(--neutral-foreground-3)] hover:bg-[var(--neutral-background-4)]'
                        }`}
                      >
                        {STATUS_MAP[s]?.label || s}
                      </button>
                    ))}
                  </div>
                )}

                {/* Replies */}
                <div className="border-t border-[var(--neutral-stroke-2)] pt-3">
                  <h3 className="text-sm font-semibold mb-3">
                    <MaterialIcon name="chat" size={16} className="inline mr-1" />
                    やり取り ({detail.replies?.length || 0})
                  </h3>
                  {detail.replies && detail.replies.length > 0 ? (
                    <div className="space-y-2 mb-3">
                      {detail.replies.map((r) => (
                        <div key={r.id} className="rounded-lg bg-[var(--neutral-background-3)] p-3">
                          <div className="flex items-center justify-between mb-1">
                            <span className="text-sm font-medium">{r.user?.full_name ?? '不明'}</span>
                            <span className="text-xs text-[var(--neutral-foreground-4)]">
                              {format(new Date(r.created_at), 'M/d HH:mm', { locale: ja })}
                            </span>
                          </div>
                          <p className="text-sm whitespace-pre-wrap">{r.message}</p>
                        </div>
                      ))}
                    </div>
                  ) : (
                    <p className="text-xs text-[var(--neutral-foreground-4)] mb-3">まだ返信はありません</p>
                  )}

                  {/* Reply form */}
                  <div className="flex gap-2">
                    <textarea
                      className="flex-1 rounded-lg border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-1)] px-3 py-2 text-sm resize-y"
                      rows={2}
                      value={replyText}
                      onChange={(e) => setReplyText(e.target.value)}
                      placeholder="返信を入力..."
                    />
                    <Button
                      variant="primary"
                      size="sm"
                      onClick={() => replyMutation.mutate()}
                      disabled={!replyText.trim() || replyMutation.isPending}
                    >
                      送信
                    </Button>
                  </div>
                </div>
              </>
            ) : (
              <SkeletonList items={3} />
            )}
          </div>
        </Modal>
      )}
    </div>
  );
}
