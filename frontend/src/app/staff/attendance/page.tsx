'use client';

import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import api from '@/lib/api';
import { Card, CardHeader, CardTitle, CardBody } from '@/components/ui/Card';
import { Button } from '@/components/ui/Button';
import { Badge } from '@/components/ui/Badge';
import { Skeleton } from '@/components/ui/Skeleton';
import { useToast } from '@/components/ui/Toast';
import { format } from 'date-fns';
import { ja } from 'date-fns/locale';
import Link from 'next/link';
import { MaterialIcon } from '@/components/ui/MaterialIcon';

// ---------------------------------------------------------------------------
// Types
// ---------------------------------------------------------------------------

interface MakeupRequest {
  id: number;
  student_id: number;
  student?: { id: number; student_name: string };
  absence_date: string;
  reason: string | null;
  makeup_request_date: string | null;
  makeup_status: 'none' | 'pending' | 'approved' | 'rejected';
  makeup_approved_by: number | null;
  makeup_approved_at: string | null;
  makeup_note: string | null;
  approver?: { id: number; full_name: string } | null;
  created_at: string;
}

const STATUS_CONFIG = {
  pending:  { label: '承認待ち', variant: 'warning' as const, icon: "schedule" },
  approved: { label: '承認済み', variant: 'success' as const, icon: "check" },
  rejected: { label: '却下', variant: 'danger' as const, icon: "close" },
};

function nl(text: string | null | undefined): string {
  if (!text) return '';
  return text.replace(/\\r\\n|\\n|\\r/g, '\n').replace(/\r\n|\r/g, '\n');
}

// ---------------------------------------------------------------------------
// Main
// ---------------------------------------------------------------------------

export default function AttendancePage() {
  const queryClient = useQueryClient();
  const toast = useToast();
  const [statusFilter, setStatusFilter] = useState<string>('pending');
  const [searchDate, setSearchDate] = useState('');

  // Fetch makeup requests
  const { data: requests = [], isLoading } = useQuery({
    queryKey: ['staff', 'attendance', statusFilter, searchDate],
    queryFn: async () => {
      const params: Record<string, string> = {};
      if (statusFilter) params.makeup_status = statusFilter;
      if (searchDate) params.date = searchDate;
      const res = await api.get('/api/staff/attendance', { params });
      const payload = res.data?.data;
      if (Array.isArray(payload)) return payload as MakeupRequest[];
      if (payload?.data && Array.isArray(payload.data)) return payload.data as MakeupRequest[];
      return [] as MakeupRequest[];
    },
  });

  // Approve/Reject mutation
  const actionMutation = useMutation({
    mutationFn: ({ id, action, note }: { id: number; action: 'approve' | 'reject'; note?: string }) =>
      api.put(`/api/staff/absence/${id}/makeup`, { action, note }),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['staff', 'attendance'] });
      toast.success('処理しました');
    },
    onError: () => toast.error('処理に失敗しました'),
  });

  const filteredRequests = requests.filter((r) => {
    if (statusFilter === 'all') return r.makeup_status !== 'none';
    return r.makeup_status === statusFilter;
  });

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold text-[var(--neutral-foreground-1)]">振替依頼管理</h1>
          <p className="text-sm text-[var(--neutral-foreground-3)]">保護者からの振替依頼を確認・承認</p>
        </div>
        <Link href="/staff/dashboard">
          <Button variant="ghost" size="sm" leftIcon={<MaterialIcon name="chevron_left" size={16} />}>
            活動管理へ戻る
          </Button>
        </Link>
      </div>

      {/* Filters */}
      <Card>
        <CardBody>
          <div className="flex flex-wrap gap-3 items-end">
            <div>
              <label className="mb-1 block text-xs font-medium text-[var(--neutral-foreground-3)]">ステータス</label>
              <div className="flex gap-1">
                {[
                  { key: 'pending', label: '承認待ち' },
                  { key: 'approved', label: '承認済み' },
                  { key: 'rejected', label: '却下' },
                  { key: 'all', label: 'すべて' },
                ].map((opt) => (
                  <button
                    key={opt.key}
                    onClick={() => setStatusFilter(opt.key)}
                    className={`rounded-lg px-3 py-1.5 text-xs font-medium transition-colors ${
                      statusFilter === opt.key
                        ? 'bg-[var(--brand-80)] text-white'
                        : 'bg-[var(--neutral-background-3)] text-[var(--neutral-foreground-2)] hover:bg-[var(--neutral-background-4)]'
                    }`}
                  >
                    {opt.label}
                  </button>
                ))}
              </div>
            </div>
            <div>
              <label className="mb-1 block text-xs font-medium text-[var(--neutral-foreground-3)]">日付で検索</label>
              <input
                type="date"
                value={searchDate}
                onChange={(e) => setSearchDate(e.target.value)}
                className="rounded-lg border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-1)] px-3 py-1.5 text-sm"
              />
            </div>
            {searchDate && (
              <Button variant="ghost" size="sm" onClick={() => setSearchDate('')}>クリア</Button>
            )}
          </div>
        </CardBody>
      </Card>

      {/* Request list */}
      {isLoading ? (
        <div className="space-y-3">{[...Array(4)].map((_, i) => <Skeleton key={i} className="h-32 rounded-lg" />)}</div>
      ) : filteredRequests.length === 0 ? (
        <Card>
          <CardBody>
            <div className="py-12 text-center text-[var(--neutral-foreground-4)]">
              <MaterialIcon name="calendar_month" size={48} className="mx-auto mb-3" />
              <p className="text-sm">{statusFilter === 'pending' ? '承認待ちの振替依頼はありません' : '該当する振替依頼はありません'}</p>
            </div>
          </CardBody>
        </Card>
      ) : (
        <div className="space-y-3">
          {filteredRequests.map((req) => {
            const cfg = STATUS_CONFIG[req.makeup_status as keyof typeof STATUS_CONFIG] || STATUS_CONFIG.pending;
            

            return (
              <Card key={req.id}>
                <CardBody>
                  <div className="flex items-start justify-between mb-3">
                    <div className="flex items-center gap-3">
                      <div className="flex h-10 w-10 items-center justify-center rounded-full bg-[var(--brand-140)] text-sm font-bold text-[var(--brand-80)]">
                        {req.student?.student_name?.charAt(0) || '?'}
                      </div>
                      <div>
                        <p className="font-semibold text-[var(--neutral-foreground-1)]">{req.student?.student_name || '不明'}</p>
                      </div>
                    </div>
                    <Badge variant={cfg.variant}>
                      <MaterialIcon name={cfg.icon} size={12} className="mr-1" />
                      {cfg.label}
                    </Badge>
                  </div>

                  <div className="grid grid-cols-1 gap-2 sm:grid-cols-3 text-sm mb-3">
                    <div>
                      <p className="text-[10px] font-semibold text-[var(--neutral-foreground-3)] uppercase">欠席日</p>
                      <p className="text-[var(--neutral-foreground-1)]">
                        {format(new Date(req.absence_date), 'yyyy年M月d日 (E)', { locale: ja })}
                      </p>
                    </div>
                    {req.makeup_request_date && (
                      <div>
                        <p className="text-[10px] font-semibold text-[var(--neutral-foreground-3)] uppercase">振替希望日</p>
                        <p className="text-[var(--neutral-foreground-1)]">
                          {format(new Date(req.makeup_request_date), 'yyyy年M月d日 (E)', { locale: ja })}
                        </p>
                      </div>
                    )}
                    <div>
                      <p className="text-[10px] font-semibold text-[var(--neutral-foreground-3)] uppercase">依頼日時</p>
                      <p className="text-[var(--neutral-foreground-1)]">
                        {format(new Date(req.created_at), 'yyyy年M月d日 HH:mm', { locale: ja })}
                      </p>
                    </div>
                  </div>

                  {req.reason && (
                    <p className="text-sm text-[var(--neutral-foreground-2)] mb-3">
                      <span className="text-[var(--neutral-foreground-3)]">欠席理由:</span> {nl(req.reason)}
                    </p>
                  )}

                  {/* Approved/Rejected info */}
                  {req.makeup_status === 'approved' && (
                    <div className="rounded-lg bg-green-50 border border-green-200 p-3 text-sm">
                      <p className="text-green-800">
                        <MaterialIcon name="check" size={16} className="inline mr-1" />
                        承認済み
                        {req.approver && <span className="ml-2">承認者: {req.approver.full_name}</span>}
                        {req.makeup_approved_at && (
                          <span className="ml-2">承認日時: {format(new Date(req.makeup_approved_at), 'yyyy年M月d日 HH:mm')}</span>
                        )}
                      </p>
                      {req.makeup_note && <p className="mt-1 text-green-700">{nl(req.makeup_note)}</p>}
                    </div>
                  )}

                  {req.makeup_status === 'rejected' && (
                    <div className="rounded-lg bg-red-50 border border-red-200 p-3 text-sm">
                      <p className="text-red-800">
                        <MaterialIcon name="close" size={16} className="inline mr-1" />
                        却下
                        {req.approver && <span className="ml-2">担当: {req.approver.full_name}</span>}
                      </p>
                      {req.makeup_note && <p className="mt-1 text-red-700">{nl(req.makeup_note)}</p>}
                    </div>
                  )}

                  {/* Action buttons for pending */}
                  {req.makeup_status === 'pending' && (
                    <div className="flex gap-2 mt-3 pt-3 border-t border-[var(--neutral-stroke-3)]">
                      <Button
                        variant="primary"
                        size="sm"
                        leftIcon={<MaterialIcon name="check" size={16} />}
                        onClick={() => {
                          if (confirm('この振替依頼を承認しますか？'))
                            actionMutation.mutate({ id: req.id, action: 'approve' });
                        }}
                        isLoading={actionMutation.isPending}
                      >
                        承認
                      </Button>
                      <Button
                        variant="outline"
                        size="sm"
                        leftIcon={<MaterialIcon name="close" size={16} />}
                        onClick={() => {
                          const note = prompt('却下理由を入力してください（任意）');
                          if (note !== null)
                            actionMutation.mutate({ id: req.id, action: 'reject', note: note || undefined });
                        }}
                        isLoading={actionMutation.isPending}
                      >
                        却下
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
