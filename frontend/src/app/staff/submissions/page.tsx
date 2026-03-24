'use client';

import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import api from '@/lib/api';
import { Card, CardHeader, CardTitle } from '@/components/ui/Card';
import { Button } from '@/components/ui/Button';
import { Input } from '@/components/ui/Input';
import { Badge } from '@/components/ui/Badge';
import { Modal } from '@/components/ui/Modal';
import { Table, type Column } from '@/components/ui/Table';
import { Tabs } from '@/components/ui/Tabs';
import { SkeletonTable } from '@/components/ui/Skeleton';
import { useToast } from '@/components/ui/Toast';
import { format } from 'date-fns';
import { MaterialIcon } from '@/components/ui/MaterialIcon';

interface SubmissionRequest {
  id: number;
  title: string;
  description: string;
  due_date: string | null;
  status: 'open' | 'closed';
  total_students: number;
  submitted_count: number;
  created_at: string;
}

interface StudentSubmission {
  id: number;
  student_id: number;
  student_name: string;
  file_url: string | null;
  file_name: string | null;
  comment: string | null;
  submitted_at: string | null;
  status: 'pending' | 'submitted' | 'reviewed';
}

export default function SubmissionsPage() {
  const queryClient = useQueryClient();
  const toast = useToast();
  const [createModal, setCreateModal] = useState(false);
  const [detailModal, setDetailModal] = useState(false);
  const [selectedRequest, setSelectedRequest] = useState<SubmissionRequest | null>(null);
  const [form, setForm] = useState({ title: '', description: '', due_date: '' });

  const { data: requests = [], isLoading } = useQuery({
    queryKey: ['staff', 'submissions'],
    queryFn: async () => {
      const res = await api.get('/api/staff/submissions?per_page=200');
      const payload = res.data?.data;
      if (Array.isArray(payload)) return payload as SubmissionRequest[];
      if (payload?.data && Array.isArray(payload.data)) return payload.data as SubmissionRequest[];
      return [] as SubmissionRequest[];
    },
  });

  const { data: submissions = [], isLoading: loadingSubmissions } = useQuery({
    queryKey: ['staff', 'submissions', selectedRequest?.id, 'detail'],
    queryFn: async () => {
      const res = await api.get<{ data: StudentSubmission[] }>(`/api/staff/submissions/${selectedRequest!.id}/students`);
      return res.data.data;
    },
    enabled: !!selectedRequest,
  });

  const createMutation = useMutation({
    mutationFn: (data: typeof form) => api.post('/api/staff/submissions', data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['staff', 'submissions'] });
      toast.success('提出依頼を作成しました');
      setCreateModal(false);
      setForm({ title: '', description: '', due_date: '' });
    },
    onError: () => toast.error('作成に失敗しました'),
  });

  const toggleStatusMutation = useMutation({
    mutationFn: ({ id, status }: { id: number; status: string }) =>
      api.put(`/api/staff/submissions/${id}`, { status }),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['staff', 'submissions'] });
      toast.success('ステータスを更新しました');
    },
    onError: () => toast.error('更新に失敗しました'),
  });

  const deleteMutation = useMutation({
    mutationFn: (id: number) => api.delete(`/api/staff/submissions/${id}`),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['staff', 'submissions'] });
      toast.success('提出依頼を削除しました');
    },
    onError: () => toast.error('削除に失敗しました'),
  });

  const openRequests = requests.filter((r) => r.status === 'open');
  const closedRequests = requests.filter((r) => r.status === 'closed');

  const requestColumns: Column<SubmissionRequest>[] = [
    {
      key: 'title',
      label: 'タイトル',
      render: (req) => (
        <button
          className="font-medium text-[var(--brand-80)] hover:text-[var(--brand-70)] text-left"
          onClick={() => { setSelectedRequest(req); setDetailModal(true); }}
        >
          {req.title}
        </button>
      ),
    },
    {
      key: 'due_date',
      label: '期限',
      render: (req) => req.due_date ? format(new Date(req.due_date), 'yyyy/MM/dd') : '期限なし',
    },
    {
      key: 'progress',
      label: '提出状況',
      render: (req) => (
        <div className="flex items-center gap-2">
          <div className="h-2 flex-1 rounded-full bg-[var(--neutral-background-4)] max-w-[120px]">
            <div
              className="h-2 rounded-full bg-[var(--status-success-fg)]"
              style={{ width: `${req.total_students > 0 ? (req.submitted_count / req.total_students) * 100 : 0}%` }}
            />
          </div>
          <span className="text-sm text-[var(--neutral-foreground-2)]">{req.submitted_count}/{req.total_students}</span>
        </div>
      ),
    },
    {
      key: 'status',
      label: 'ステータス',
      render: (req) => (
        <Badge variant={req.status === 'open' ? 'success' : 'default'}>
          {req.status === 'open' ? '受付中' : '締切'}
        </Badge>
      ),
    },
    {
      key: 'actions',
      label: '操作',
      render: (req) => (
        <div className="flex gap-1">
          <Button
            variant="outline"
            size="sm"
            onClick={() => toggleStatusMutation.mutate({ id: req.id, status: req.status === 'open' ? 'closed' : 'open' })}
          >
            {req.status === 'open' ? '締切る' : '再開'}
          </Button>
          <Button
            variant="ghost"
            size="sm"
            onClick={() => { if (confirm('削除しますか？')) deleteMutation.mutate(req.id); }}
          >
            <MaterialIcon name="delete" size={16} className="text-[var(--status-danger-fg)]" />
          </Button>
        </div>
      ),
    },
  ];

  const submissionColumns: Column<StudentSubmission>[] = [
    { key: 'student_name', label: '生徒名' },
    {
      key: 'status',
      label: 'ステータス',
      render: (sub) => {
        const labels: Record<string, { text: string; variant: 'default' | 'success' | 'warning' }> = {
          pending: { text: '未提出', variant: 'default' },
          submitted: { text: '提出済み', variant: 'success' },
          reviewed: { text: '確認済み', variant: 'warning' },
        };
        const config = labels[sub.status] || labels.pending;
        return <Badge variant={config.variant}>{config.text}</Badge>;
      },
    },
    {
      key: 'submitted_at',
      label: '提出日時',
      render: (sub) => sub.submitted_at ? format(new Date(sub.submitted_at), 'yyyy/MM/dd HH:mm') : '-',
    },
    {
      key: 'file_url',
      label: 'ファイル',
      render: (sub) => sub.file_url ? (
        <a href={sub.file_url} target="_blank" rel="noopener noreferrer" className="flex items-center gap-1 text-sm text-[var(--brand-80)] hover:underline">
          <MaterialIcon name="download" size={12} />
          {sub.file_name || 'ダウンロード'}
        </a>
      ) : '-',
    },
    {
      key: 'comment',
      label: 'コメント',
      render: (sub) => <span className="text-sm text-[var(--neutral-foreground-2)]">{sub.comment || '-'}</span>,
    },
  ];

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-bold text-[var(--neutral-foreground-1)]">提出物管理</h1>
        <Button onClick={() => setCreateModal(true)} leftIcon={<MaterialIcon name="add" size={16} />}>
          新規依頼作成
        </Button>
      </div>

      <Tabs
        items={[
          {
            key: 'open',
            label: '受付中',
            badge: openRequests.length,
            content: isLoading ? (
              <SkeletonTable rows={5} cols={5} />
            ) : (
              <Table columns={requestColumns} data={openRequests} keyExtractor={(r) => r.id} emptyMessage="受付中の提出依頼はありません" />
            ),
          },
          {
            key: 'closed',
            label: '締切済み',
            badge: closedRequests.length,
            content: isLoading ? (
              <SkeletonTable rows={5} cols={5} />
            ) : (
              <Table columns={requestColumns} data={closedRequests} keyExtractor={(r) => r.id} emptyMessage="締切済みの提出依頼はありません" />
            ),
          },
        ]}
      />

      {/* Create Modal */}
      <Modal isOpen={createModal} onClose={() => setCreateModal(false)} title="提出依頼を作成" size="lg">
        <form onSubmit={(e) => { e.preventDefault(); createMutation.mutate(form); }} className="space-y-4">
          <Input label="タイトル" value={form.title} onChange={(e) => setForm({ ...form, title: e.target.value })} required />
          <div>
            <label className="mb-1 block text-sm font-medium text-[var(--neutral-foreground-2)]">説明</label>
            <textarea
              value={form.description}
              onChange={(e) => setForm({ ...form, description: e.target.value })}
              className="block w-full rounded-lg border border-[var(--neutral-stroke-2)] px-3 py-2 text-sm"
              rows={3}
            />
          </div>
          <Input label="期限（任意）" type="date" value={form.due_date} onChange={(e) => setForm({ ...form, due_date: e.target.value })} />
          <div className="flex justify-end gap-2 pt-2">
            <Button variant="secondary" type="button" onClick={() => setCreateModal(false)}>キャンセル</Button>
            <Button type="submit" isLoading={createMutation.isPending}>作成</Button>
          </div>
        </form>
      </Modal>

      {/* Detail Modal */}
      <Modal isOpen={detailModal} onClose={() => { setDetailModal(false); setSelectedRequest(null); }} title={selectedRequest?.title || '提出物詳細'} size="full">
        {loadingSubmissions ? (
          <SkeletonTable rows={5} cols={5} />
        ) : (
          <Table columns={submissionColumns} data={submissions} keyExtractor={(s) => s.id} emptyMessage="提出データがありません" />
        )}
      </Modal>
    </div>
  );
}
