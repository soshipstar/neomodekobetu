'use client';

import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import api from '@/lib/api';
import { Card, CardHeader, CardTitle, CardBody } from '@/components/ui/Card';
import { Button } from '@/components/ui/Button';
import { Input } from '@/components/ui/Input';
import { Badge } from '@/components/ui/Badge';
import { Modal } from '@/components/ui/Modal';
import { Table, type Column } from '@/components/ui/Table';
import { Tabs } from '@/components/ui/Tabs';
import { SkeletonTable } from '@/components/ui/Skeleton';
import { useToast } from '@/components/ui/Toast';
import {
  Plus,
  Trash2,
  CheckCircle2,
  Clock,
  FileText,
  Download,
  Upload,
  AlertTriangle,
} from 'lucide-react';
import { format, isPast } from 'date-fns';

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
  created_by_name: string;
  created_at: string;
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

  const [filterStatus, setFilterStatus] = useState<'pending' | 'completed' | 'all'>('pending');
  const [createModal, setCreateModal] = useState(false);
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
  const { data: requests = [], isLoading } = useQuery({
    queryKey: ['staff', 'submission-management', filterStatus],
    queryFn: async () => {
      const res = await api.get(`/api/staff/submissions?status=${filterStatus}&per_page=200`);
      const payload = res.data?.data;
      if (Array.isArray(payload)) return payload as SubmissionRequest[];
      if (payload?.data && Array.isArray(payload.data)) return payload.data as SubmissionRequest[];
      return [] as SubmissionRequest[];
    },
  });

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
  const overdueRequests = pendingRequests.filter((r) => r.due_date && isPast(new Date(r.due_date)));

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
      key: 'due_date',
      label: '期限',
      render: (req) => {
        if (!req.due_date) return <span className="text-[var(--neutral-foreground-3)]">期限なし</span>;
        const isOverdue = !req.is_completed && isPast(new Date(req.due_date));
        return (
          <span className={isOverdue ? 'font-medium text-[var(--status-danger-fg)]' : ''}>
            {isOverdue && <AlertTriangle className="mr-1 inline h-3 w-3" />}
            {format(new Date(req.due_date), 'yyyy/MM/dd')}
          </span>
        );
      },
    },
    {
      key: 'status',
      label: 'ステータス',
      render: (req) => (
        <Badge variant={req.is_completed ? 'success' : 'warning'}>
          {req.is_completed ? '完了' : '未提出'}
        </Badge>
      ),
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
          <Download className="h-3 w-3" />
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
          {!req.is_completed && (
            <Button
              variant="outline"
              size="sm"
              onClick={() => {
                const note = prompt('完了メモ（任意）');
                if (note !== null) completeMutation.mutate({ id: req.id, note });
              }}
            >
              <CheckCircle2 className="mr-1 h-3 w-3" />
              完了
            </Button>
          )}
          <Button
            variant="ghost"
            size="sm"
            onClick={() => { if (confirm('削除しますか？')) deleteMutation.mutate(req.id); }}
          >
            <Trash2 className="h-4 w-4 text-[var(--status-danger-fg)]" />
          </Button>
        </div>
      ),
    },
  ];

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-bold text-[var(--neutral-foreground-1)]">提出物管理</h1>
        <Button leftIcon={<Plus className="h-4 w-4" />} onClick={() => { setForm(emptyForm); setCreateModal(true); }}>
          新規依頼作成
        </Button>
      </div>

      {/* Summary cards */}
      <div className="grid gap-4 md:grid-cols-3">
        <Card>
          <CardBody>
            <div className="flex items-center gap-3">
              <Clock className="h-8 w-8 text-[var(--status-warning-fg)]" />
              <div>
                <p className="text-2xl font-bold text-[var(--neutral-foreground-1)]">{pendingRequests.length}</p>
                <p className="text-xs text-[var(--neutral-foreground-3)]">未提出</p>
              </div>
            </div>
          </CardBody>
        </Card>
        <Card>
          <CardBody>
            <div className="flex items-center gap-3">
              <AlertTriangle className="h-8 w-8 text-[var(--status-danger-fg)]" />
              <div>
                <p className="text-2xl font-bold text-[var(--neutral-foreground-1)]">{overdueRequests.length}</p>
                <p className="text-xs text-[var(--neutral-foreground-3)]">期限超過</p>
              </div>
            </div>
          </CardBody>
        </Card>
        <Card>
          <CardBody>
            <div className="flex items-center gap-3">
              <CheckCircle2 className="h-8 w-8 text-[var(--status-success-fg)]" />
              <div>
                <p className="text-2xl font-bold text-[var(--neutral-foreground-1)]">{completedRequests.length}</p>
                <p className="text-xs text-[var(--neutral-foreground-3)]">完了</p>
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
              <SkeletonTable rows={5} cols={6} />
            ) : (
              <Table columns={columns} data={pendingRequests} keyExtractor={(r) => r.id} emptyMessage="未提出の依頼はありません" />
            ),
          },
          {
            key: 'completed',
            label: '完了',
            badge: completedRequests.length,
            content: isLoading ? (
              <SkeletonTable rows={5} cols={6} />
            ) : (
              <Table columns={columns} data={completedRequests} keyExtractor={(r) => r.id} emptyMessage="完了した依頼はありません" />
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
    </div>
  );
}
