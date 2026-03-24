'use client';

import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import api from '@/lib/api';
import { Card, CardBody } from '@/components/ui/Card';
import { Button } from '@/components/ui/Button';
import { Input } from '@/components/ui/Input';
import { Badge } from '@/components/ui/Badge';
import { Modal } from '@/components/ui/Modal';
import { Table, type Column } from '@/components/ui/Table';
import { Tabs } from '@/components/ui/Tabs';
import { SkeletonTable } from '@/components/ui/Skeleton';
import { useToast } from '@/components/ui/Toast';
import { format, isPast, differenceInDays } from 'date-fns';
import { MaterialIcon } from '@/components/ui/MaterialIcon';

// ---------------------------------------------------------------------------
// Types
// ---------------------------------------------------------------------------

interface Student {
  id: number;
  student_name: string;
  guardian_name: string;
}

interface SubmissionRequest {
  id: number;
  student_id: number;
  student_name: string;
  guardian_name: string;
  title: string;
  description: string | null;
  due_date: string | null;
  is_completed: boolean;
  completed_at: string | null;
  completed_note: string | null;
  attachment_path: string | null;
  attachment_original_name: string | null;
  attachment_size: number | null;
  created_by_name: string;
  created_at: string;
}

interface Stats {
  total: number;
  pending: number;
  completed: number;
  overdue: number;
}

interface SubmissionForm {
  student_id: number | '';
  title: string;
  description: string;
  due_date: string;
}

const emptyForm: SubmissionForm = {
  student_id: '',
  title: '',
  description: '',
  due_date: '',
};

export default function SubmissionManagementPage() {
  const queryClient = useQueryClient();
  const toast = useToast();

  const [createModal, setCreateModal] = useState(false);
  const [completeModal, setCompleteModal] = useState(false);
  const [completingId, setCompletingId] = useState<number | null>(null);
  const [completingTitle, setCompletingTitle] = useState('');
  const [completedNote, setCompletedNote] = useState('');
  const [form, setForm] = useState<SubmissionForm>(emptyForm);

  // Fetch students
  const { data: students = [] } = useQuery({
    queryKey: ['staff', 'students'],
    queryFn: async () => {
      const res = await api.get<{ data: Student[] }>('/api/staff/students');
      return res.data.data;
    },
  });

  // Fetch submission requests
  const { data: responseData, isLoading } = useQuery({
    queryKey: ['staff', 'submission-management'],
    queryFn: async () => {
      const res = await api.get('/api/staff/submissions?per_page=200');
      const payload = res.data?.data;
      let requests: SubmissionRequest[] = [];
      if (Array.isArray(payload)) requests = payload;
      else if (payload?.data && Array.isArray(payload.data)) requests = payload.data;
      const stats: Stats = res.data?.stats ?? { total: 0, pending: 0, completed: 0, overdue: 0 };
      return { requests, stats };
    },
  });

  const requests = responseData?.requests ?? [];
  const stats = responseData?.stats ?? { total: 0, pending: 0, completed: 0, overdue: 0 };

  // Create mutation
  const createMutation = useMutation({
    mutationFn: (data: SubmissionForm) => api.post('/api/staff/submissions', data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['staff', 'submission-management'] });
      toast.success('提出依頼を作成しました');
      setCreateModal(false);
      setForm(emptyForm);
    },
    onError: () => toast.error('作成に失敗しました'),
  });

  // Complete mutation
  const completeMutation = useMutation({
    mutationFn: ({ id, note }: { id: number; note: string }) =>
      api.put(`/api/staff/submissions/${id}`, { is_completed: true, completed_note: note }),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['staff', 'submission-management'] });
      toast.success('提出完了にしました');
      setCompleteModal(false);
      setCompletingId(null);
      setCompletedNote('');
    },
    onError: () => toast.error('更新に失敗しました'),
  });

  // Incomplete mutation
  const incompleteMutation = useMutation({
    mutationFn: (id: number) =>
      api.put(`/api/staff/submissions/${id}`, { is_completed: false }),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['staff', 'submission-management'] });
      toast.success('未提出に戻しました');
    },
    onError: () => toast.error('更新に失敗しました'),
  });

  // Delete mutation
  const deleteMutation = useMutation({
    mutationFn: (id: number) => api.delete(`/api/staff/submissions/${id}`),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['staff', 'submission-management'] });
      toast.success('提出依頼を削除しました');
    },
    onError: () => toast.error('削除に失敗しました'),
  });

  const pendingRequests = requests.filter((r) => !r.is_completed);
  const completedRequests = requests.filter((r) => r.is_completed);

  const openCompleteModal = (id: number, title: string) => {
    setCompletingId(id);
    setCompletingTitle(title);
    setCompletedNote('');
    setCompleteModal(true);
  };

  const columns: Column<SubmissionRequest>[] = [
    {
      key: 'student_name',
      label: '生徒名',
      render: (req) => (
        <div>
          <p className="font-medium text-[var(--neutral-foreground-1)]">{req.student_name}</p>
          <p className="text-xs text-[var(--neutral-foreground-3)]">{req.guardian_name}</p>
        </div>
      ),
    },
    { key: 'title', label: 'タイトル' },
    {
      key: 'description',
      label: '詳細',
      render: (req) => (
        <span className="text-sm text-[var(--neutral-foreground-2)] line-clamp-2">
          {req.description || '-'}
        </span>
      ),
    },
    {
      key: 'due_date',
      label: '期限',
      render: (req) => {
        if (!req.due_date) return <span className="text-[var(--neutral-foreground-3)]">期限なし</span>;
        const dueDate = new Date(req.due_date);
        const isOverdue = !req.is_completed && isPast(dueDate);
        const daysLeft = differenceInDays(dueDate, new Date());
        const isSoon = !req.is_completed && !isOverdue && daysLeft <= 3;
        return (
          <div>
            <span className={isOverdue ? 'font-medium text-[var(--status-danger-fg)]' : isSoon ? 'font-medium text-[var(--status-warning-fg)]' : ''}>
              {isOverdue && <MaterialIcon name="warning" size={12} className="mr-1 inline" />}
              {format(dueDate, 'yyyy/MM/dd')}
            </span>
            {isOverdue && (
              <p className="text-xs text-[var(--status-danger-fg)]">{Math.abs(daysLeft)}日超過</p>
            )}
            {isSoon && (
              <p className="text-xs text-[var(--status-warning-fg)]">残り{daysLeft}日</p>
            )}
          </div>
        );
      },
    },
    {
      key: 'status',
      label: 'ステータス',
      render: (req) => {
        if (req.is_completed) {
          return (
            <div>
              <Badge variant="success">提出済み</Badge>
              {req.completed_at && (
                <p className="mt-1 text-xs text-[var(--neutral-foreground-3)]">
                  {format(new Date(req.completed_at), 'yyyy/MM/dd HH:mm')}
                </p>
              )}
              {req.completed_note && (
                <p className="mt-1 text-xs text-[var(--neutral-foreground-2)] italic">
                  {req.completed_note}
                </p>
              )}
            </div>
          );
        }
        return <Badge variant="warning">未提出</Badge>;
      },
    },
    {
      key: 'attachment',
      label: '添付',
      render: (req) => req.attachment_path ? (
        <a
          href={`${process.env.NEXT_PUBLIC_BACKEND_URL || 'http://localhost:8000'}${req.attachment_path}`}
          target="_blank"
          rel="noopener noreferrer"
          className="flex items-center gap-1 text-sm text-[var(--brand-80)] hover:underline"
        >
          <MaterialIcon name="download" size={12} />
          {req.attachment_original_name || 'ファイル'}
        </a>
      ) : (
        <span className="text-[var(--neutral-foreground-3)]">-</span>
      ),
    },
    {
      key: 'actions',
      label: '操作',
      render: (req) => (
        <div className="flex gap-1">
          {!req.is_completed ? (
            <Button
              variant="outline"
              size="sm"
              onClick={() => openCompleteModal(req.id, req.title)}
            >
              <MaterialIcon name="check_circle" size={12} className="mr-1" />
              完了
            </Button>
          ) : (
            <Button
              variant="outline"
              size="sm"
              onClick={() => {
                if (confirm('未提出に戻しますか？')) incompleteMutation.mutate(req.id);
              }}
            >
              <MaterialIcon name="undo" size={12} className="mr-1" />
              未提出に戻す
            </Button>
          )}
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

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-bold text-[var(--neutral-foreground-1)]">提出物管理</h1>
        <Button leftIcon={<MaterialIcon name="add" size={16} />} onClick={() => { setForm(emptyForm); setCreateModal(true); }}>
          新規依頼作成
        </Button>
      </div>

      {/* Summary cards */}
      <div className="grid gap-4 md:grid-cols-4">
        <Card>
          <CardBody>
            <div className="flex items-center gap-3">
              <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-[var(--neutral-background-4)]">
                <MaterialIcon name="schedule" size={20} className="text-[var(--neutral-foreground-2)]" />
              </div>
              <div>
                <p className="text-2xl font-bold text-[var(--neutral-foreground-1)]">{stats.total}</p>
                <p className="text-xs text-[var(--neutral-foreground-3)]">全体</p>
              </div>
            </div>
          </CardBody>
        </Card>
        <Card>
          <CardBody>
            <div className="flex items-center gap-3">
              <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-amber-50">
                <MaterialIcon name="schedule" size={20} className="text-[var(--status-warning-fg)]" />
              </div>
              <div>
                <p className="text-2xl font-bold text-[var(--neutral-foreground-1)]">{stats.pending}</p>
                <p className="text-xs text-[var(--neutral-foreground-3)]">未提出</p>
              </div>
            </div>
          </CardBody>
        </Card>
        <Card>
          <CardBody>
            <div className="flex items-center gap-3">
              <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-red-50">
                <MaterialIcon name="warning" size={20} className="text-[var(--status-danger-fg)]" />
              </div>
              <div>
                <p className="text-2xl font-bold text-[var(--neutral-foreground-1)]">{stats.overdue}</p>
                <p className="text-xs text-[var(--neutral-foreground-3)]">期限超過</p>
              </div>
            </div>
          </CardBody>
        </Card>
        <Card>
          <CardBody>
            <div className="flex items-center gap-3">
              <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-green-50">
                <MaterialIcon name="check_circle" size={20} className="text-[var(--status-success-fg)]" />
              </div>
              <div>
                <p className="text-2xl font-bold text-[var(--neutral-foreground-1)]">{stats.completed}</p>
                <p className="text-xs text-[var(--neutral-foreground-3)]">提出済み</p>
              </div>
            </div>
          </CardBody>
        </Card>
      </div>

      <Tabs
        items={[
          {
            key: 'pending',
            label: '未提出',
            badge: pendingRequests.length,
            content: isLoading ? (
              <SkeletonTable rows={5} cols={7} />
            ) : (
              <Table columns={columns} data={pendingRequests} keyExtractor={(r) => r.id} emptyMessage="未提出の依頼はありません" />
            ),
          },
          {
            key: 'completed',
            label: '提出済み',
            badge: completedRequests.length,
            content: isLoading ? (
              <SkeletonTable rows={5} cols={7} />
            ) : (
              <Table columns={columns} data={completedRequests} keyExtractor={(r) => r.id} emptyMessage="提出済みの依頼はありません" />
            ),
          },
          {
            key: 'all',
            label: 'すべて',
            badge: requests.length,
            content: isLoading ? (
              <SkeletonTable rows={5} cols={7} />
            ) : (
              <Table columns={columns} data={requests} keyExtractor={(r) => r.id} emptyMessage="提出依頼はありません" />
            ),
          },
        ]}
      />

      {/* Create Modal */}
      <Modal isOpen={createModal} onClose={() => setCreateModal(false)} title="提出依頼を作成" size="lg">
        <form onSubmit={(e) => { e.preventDefault(); createMutation.mutate(form); }} className="space-y-4">
          <div>
            <label className="mb-1 block text-sm font-medium text-[var(--neutral-foreground-2)]">生徒</label>
            <select
              className="block w-full rounded-lg border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-1)] px-3 py-2 text-sm text-[var(--neutral-foreground-1)]"
              value={form.student_id}
              onChange={(e) => setForm({ ...form, student_id: e.target.value ? Number(e.target.value) : '' })}
              required
            >
              <option value="">生徒を選択</option>
              {students.map((s) => (
                <option key={s.id} value={s.id}>{s.student_name}</option>
              ))}
            </select>
          </div>
          <Input label="タイトル" value={form.title} onChange={(e) => setForm({ ...form, title: e.target.value })} required />
          <div>
            <label className="mb-1 block text-sm font-medium text-[var(--neutral-foreground-2)]">説明</label>
            <textarea
              value={form.description}
              onChange={(e) => setForm({ ...form, description: e.target.value })}
              className="block w-full rounded-lg border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-1)] px-3 py-2 text-sm text-[var(--neutral-foreground-1)]"
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

      {/* Complete Modal */}
      <Modal isOpen={completeModal} onClose={() => setCompleteModal(false)} title="提出完了の確認" size="md">
        <form
          onSubmit={(e) => {
            e.preventDefault();
            if (completingId !== null) completeMutation.mutate({ id: completingId, note: completedNote });
          }}
          className="space-y-4"
        >
          <p className="text-sm text-[var(--neutral-foreground-1)]">
            <span className="font-semibold">{completingTitle}</span> を提出完了にしますか？
          </p>
          <div>
            <label className="mb-1 block text-sm font-medium text-[var(--neutral-foreground-2)]">
              完了メモ（任意）
            </label>
            <textarea
              value={completedNote}
              onChange={(e) => setCompletedNote(e.target.value)}
              placeholder="メモがあれば入力してください"
              className="block w-full rounded-lg border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-1)] px-3 py-2 text-sm text-[var(--neutral-foreground-1)]"
              rows={3}
            />
          </div>
          <div className="flex justify-end gap-2 pt-2">
            <Button variant="secondary" type="button" onClick={() => setCompleteModal(false)}>
              キャンセル
            </Button>
            <Button type="submit" isLoading={completeMutation.isPending}>
              完了にする
            </Button>
          </div>
        </form>
      </Modal>
    </div>
  );
}
