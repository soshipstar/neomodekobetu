'use client';

import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import api from '@/lib/api';
import { Card, CardBody } from '@/components/ui/Card';
import { Button } from '@/components/ui/Button';
import { Badge } from '@/components/ui/Badge';
import { Modal } from '@/components/ui/Modal';
import { SkeletonList } from '@/components/ui/Skeleton';
import { useToast } from '@/components/ui/Toast';
import { formatDateTime } from '@/lib/utils';
import { Plus, Pencil, Trash2, Send, EyeOff, Eye } from 'lucide-react';

// ---------------------------------------------------------------------------
// Types
// ---------------------------------------------------------------------------

interface Announcement {
  id: number;
  title: string;
  content: string;
  priority: 'normal' | 'important' | 'urgent';
  target_type: 'all' | 'selected';
  is_published: boolean;
  published_at: string | null;
  created_at: string;
  reads_count: number;
  creator?: { id: number; full_name: string };
  target_students?: { id: number; student_name: string }[];
}

interface Student {
  id: number;
  student_name: string;
}

interface AnnouncementForm {
  title: string;
  content: string;
  priority: 'normal' | 'important' | 'urgent';
  target_type: 'all' | 'selected';
  target_student_ids: number[];
}

const emptyForm = (): AnnouncementForm => ({
  title: '',
  content: '',
  priority: 'normal',
  target_type: 'all',
  target_student_ids: [],
});

const PRIORITY_LABELS: Record<string, string> = {
  normal: '通常',
  important: '重要',
  urgent: '緊急',
};

const PRIORITY_VARIANT: Record<string, 'info' | 'warning' | 'danger'> = {
  normal: 'info',
  important: 'warning',
  urgent: 'danger',
};

// ---------------------------------------------------------------------------
// Page
// ---------------------------------------------------------------------------

export default function StaffAnnouncementsPage() {
  const queryClient = useQueryClient();
  const toast = useToast();
  const [showForm, setShowForm] = useState(false);
  const [editingId, setEditingId] = useState<number | null>(null);
  const [form, setForm] = useState<AnnouncementForm>(emptyForm());
  const [deleteTarget, setDeleteTarget] = useState<Announcement | null>(null);

  // Fetch announcements
  const { data: announcements, isLoading } = useQuery({
    queryKey: ['staff', 'announcements'],
    queryFn: async () => {
      const res = await api.get<{ data: { data: Announcement[] } }>('/api/staff/announcements');
      return res.data.data.data;
    },
  });

  // Fetch students for target selection
  const { data: students } = useQuery({
    queryKey: ['staff', 'students-list'],
    queryFn: async () => {
      const res = await api.get<{ data: Student[] }>('/api/staff/students');
      return res.data.data;
    },
  });

  // Create
  const createMutation = useMutation({
    mutationFn: (data: AnnouncementForm) => api.post('/api/staff/announcements', data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['staff', 'announcements'] });
      toast.success('お知らせを作成しました');
      resetForm();
    },
    onError: () => toast.error('作成に失敗しました'),
  });

  // Update
  const updateMutation = useMutation({
    mutationFn: ({ id, data }: { id: number; data: AnnouncementForm }) =>
      api.put(`/api/staff/announcements/${id}`, data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['staff', 'announcements'] });
      toast.success('お知らせを更新しました');
      resetForm();
    },
    onError: () => toast.error('更新に失敗しました'),
  });

  // Delete
  const deleteMutation = useMutation({
    mutationFn: (id: number) => api.delete(`/api/staff/announcements/${id}`),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['staff', 'announcements'] });
      toast.success('お知らせを削除しました');
      setDeleteTarget(null);
    },
    onError: () => toast.error('削除に失敗しました'),
  });

  // Publish
  const publishMutation = useMutation({
    mutationFn: (id: number) => api.post(`/api/staff/announcements/${id}/publish`),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['staff', 'announcements'] });
      toast.success('お知らせを公開しました');
    },
    onError: () => toast.error('公開に失敗しました'),
  });

  // Unpublish
  const unpublishMutation = useMutation({
    mutationFn: (id: number) => api.post(`/api/staff/announcements/${id}/unpublish`),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['staff', 'announcements'] });
      toast.success('お知らせを非公開にしました');
    },
    onError: () => toast.error('非公開に失敗しました'),
  });

  function resetForm() {
    setForm(emptyForm());
    setEditingId(null);
    setShowForm(false);
  }

  function startEdit(a: Announcement) {
    setForm({
      title: a.title,
      content: a.content,
      priority: a.priority,
      target_type: a.target_type,
      target_student_ids: a.target_students?.map((s) => s.id) ?? [],
    });
    setEditingId(a.id);
    setShowForm(true);
  }

  function handleSubmit(e: React.FormEvent) {
    e.preventDefault();
    if (editingId) {
      updateMutation.mutate({ id: editingId, data: form });
    } else {
      createMutation.mutate(form);
    }
  }

  function toggleStudentTarget(studentId: number) {
    setForm((prev) => ({
      ...prev,
      target_student_ids: prev.target_student_ids.includes(studentId)
        ? prev.target_student_ids.filter((id) => id !== studentId)
        : [...prev.target_student_ids, studentId],
    }));
  }

  const isSaving = createMutation.isPending || updateMutation.isPending;

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold text-[var(--neutral-foreground-1)]">お知らせ管理</h1>
          <p className="text-sm text-[var(--neutral-foreground-3)]">保護者向けのお知らせを管理します</p>
        </div>
        <Button
          onClick={() => {
            resetForm();
            setShowForm(true);
          }}
        >
          <Plus className="mr-1 h-4 w-4" />
          新規作成
        </Button>
      </div>

      {/* Create/Edit Modal */}
      <Modal isOpen={showForm} onClose={resetForm} title={editingId ? 'お知らせを編集' : 'お知らせを作成'} size="lg">
        <form onSubmit={handleSubmit} className="space-y-4">
          {/* Title */}
          <div>
            <label className="mb-1 block text-sm font-medium text-[var(--neutral-foreground-2)]">タイトル</label>
            <input
              type="text"
              required
              value={form.title}
              onChange={(e) => setForm((f) => ({ ...f, title: e.target.value }))}
              placeholder="お知らせのタイトルを入力"
              className="w-full rounded-md border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-1)] px-3 py-2 text-sm text-[var(--neutral-foreground-1)] focus:border-[var(--brand-80)] focus:outline-none focus:ring-1 focus:ring-[var(--brand-80)]"
            />
          </div>

          {/* Priority */}
          <div>
            <label className="mb-1 block text-sm font-medium text-[var(--neutral-foreground-2)]">重要度</label>
            <select
              value={form.priority}
              onChange={(e) => setForm((f) => ({ ...f, priority: e.target.value as AnnouncementForm['priority'] }))}
              className="w-full rounded-md border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-1)] px-3 py-2 text-sm text-[var(--neutral-foreground-1)] focus:border-[var(--brand-80)] focus:outline-none focus:ring-1 focus:ring-[var(--brand-80)]"
            >
              <option value="normal">通常</option>
              <option value="important">重要</option>
              <option value="urgent">緊急</option>
            </select>
          </div>

          {/* Content */}
          <div>
            <label className="mb-1 block text-sm font-medium text-[var(--neutral-foreground-2)]">内容</label>
            <textarea
              required
              rows={6}
              value={form.content}
              onChange={(e) => setForm((f) => ({ ...f, content: e.target.value }))}
              placeholder="お知らせの内容を入力"
              className="w-full rounded-md border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-1)] px-3 py-2 text-sm text-[var(--neutral-foreground-1)] focus:border-[var(--brand-80)] focus:outline-none focus:ring-1 focus:ring-[var(--brand-80)]"
            />
          </div>

          {/* Target */}
          <div>
            <label className="mb-1 block text-sm font-medium text-[var(--neutral-foreground-2)]">配信対象</label>
            <div className="flex gap-4">
              <label className="flex items-center gap-1.5 text-sm cursor-pointer">
                <input
                  type="radio"
                  name="target_type"
                  value="all"
                  checked={form.target_type === 'all'}
                  onChange={() => setForm((f) => ({ ...f, target_type: 'all', target_student_ids: [] }))}
                />
                全保護者
              </label>
              <label className="flex items-center gap-1.5 text-sm cursor-pointer">
                <input
                  type="radio"
                  name="target_type"
                  value="selected"
                  checked={form.target_type === 'selected'}
                  onChange={() => setForm((f) => ({ ...f, target_type: 'selected' }))}
                />
                個別選択
              </label>
            </div>
          </div>

          {/* Target student selection */}
          {form.target_type === 'selected' && (
            <div>
              <div className="mb-2 flex items-center justify-between">
                <label className="text-sm font-medium text-[var(--neutral-foreground-2)]">対象の生徒を選択</label>
                <div className="flex gap-2">
                  <button
                    type="button"
                    onClick={() => setForm((f) => ({ ...f, target_student_ids: students?.map((s) => s.id) ?? [] }))}
                    className="text-xs text-[var(--brand-80)] hover:underline"
                  >
                    全選択
                  </button>
                  <button
                    type="button"
                    onClick={() => setForm((f) => ({ ...f, target_student_ids: [] }))}
                    className="text-xs text-[var(--neutral-foreground-3)] hover:underline"
                  >
                    全解除
                  </button>
                </div>
              </div>
              <div className="max-h-48 space-y-1 overflow-y-auto rounded-md border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-2)] p-3">
                {students?.map((s) => (
                  <label key={s.id} className="flex items-center gap-2 text-sm cursor-pointer py-0.5">
                    <input
                      type="checkbox"
                      checked={form.target_student_ids.includes(s.id)}
                      onChange={() => toggleStudentTarget(s.id)}
                    />
                    {s.student_name}
                  </label>
                ))}
                {(!students || students.length === 0) && (
                  <p className="text-xs text-[var(--neutral-foreground-4)]">生徒が登録されていません</p>
                )}
              </div>
            </div>
          )}

          {/* Actions */}
          <div className="flex justify-end gap-2 pt-2">
            <Button type="button" variant="secondary" onClick={resetForm}>
              キャンセル
            </Button>
            <Button type="submit" disabled={isSaving}>
              {isSaving ? '保存中...' : editingId ? '更新する' : '作成する'}
            </Button>
          </div>
        </form>
      </Modal>

      {/* Delete confirmation modal */}
      <Modal isOpen={!!deleteTarget} onClose={() => setDeleteTarget(null)} title="お知らせの削除" size="sm">
        <p className="mb-4 text-sm text-[var(--neutral-foreground-2)]">
          「{deleteTarget?.title}」を削除してもよろしいですか？
        </p>
        <div className="flex justify-end gap-2">
          <Button variant="secondary" onClick={() => setDeleteTarget(null)}>
            キャンセル
          </Button>
          <Button
            variant="danger"
            onClick={() => deleteTarget && deleteMutation.mutate(deleteTarget.id)}
            disabled={deleteMutation.isPending}
          >
            {deleteMutation.isPending ? '削除中...' : '削除する'}
          </Button>
        </div>
      </Modal>

      {/* List */}
      {isLoading ? (
        <SkeletonList items={4} />
      ) : announcements && announcements.length > 0 ? (
        <div className="space-y-3">
          {announcements.map((a) => (
            <Card key={a.id}>
              <CardBody>
                {/* Header row */}
                <div className="mb-2 flex flex-wrap items-start justify-between gap-2">
                  <div className="flex flex-wrap items-center gap-2">
                    <Badge variant={PRIORITY_VARIANT[a.priority]}>{PRIORITY_LABELS[a.priority]}</Badge>
                    <span className="text-base font-semibold text-[var(--neutral-foreground-1)]">{a.title}</span>
                    {a.is_published ? (
                      <Badge variant="success" dot>公開中</Badge>
                    ) : (
                      <Badge variant="default">下書き</Badge>
                    )}
                    {a.target_type === 'selected' && (
                      <Badge variant="primary">個別配信</Badge>
                    )}
                  </div>
                  <span className="text-xs text-[var(--neutral-foreground-4)]">
                    <Eye className="mr-0.5 inline h-3.5 w-3.5" />
                    既読: {a.reads_count ?? 0}人
                  </span>
                </div>

                {/* Meta */}
                <div className="mb-2 flex flex-wrap gap-3 text-xs text-[var(--neutral-foreground-3)]">
                  <span>作成者: {a.creator?.full_name ?? '不明'}</span>
                  <span>作成日: {formatDateTime(a.created_at)}</span>
                  {a.published_at && <span>公開日: {formatDateTime(a.published_at)}</span>}
                </div>

                {/* Target students */}
                {a.target_type === 'selected' && a.target_students && a.target_students.length > 0 && (
                  <div className="mb-2 rounded bg-[var(--neutral-background-3)] px-3 py-1.5 text-xs text-[var(--neutral-foreground-3)]">
                    対象: {a.target_students.map((s) => s.student_name).join('、')}
                  </div>
                )}

                {/* Content preview */}
                <p className="mb-3 whitespace-pre-wrap text-sm text-[var(--neutral-foreground-2)] line-clamp-3">
                  {a.content}
                </p>

                {/* Actions */}
                <div className="flex flex-wrap gap-2">
                  {a.is_published ? (
                    <Button
                      size="sm"
                      variant="secondary"
                      onClick={() => unpublishMutation.mutate(a.id)}
                      disabled={unpublishMutation.isPending}
                    >
                      <EyeOff className="mr-1 h-3.5 w-3.5" />
                      非公開にする
                    </Button>
                  ) : (
                    <Button
                      size="sm"
                      variant="primary"
                      onClick={() => publishMutation.mutate(a.id)}
                      disabled={publishMutation.isPending}
                    >
                      <Send className="mr-1 h-3.5 w-3.5" />
                      公開する
                    </Button>
                  )}
                  <Button size="sm" variant="secondary" onClick={() => startEdit(a)}>
                    <Pencil className="mr-1 h-3.5 w-3.5" />
                    編集
                  </Button>
                  <Button size="sm" variant="danger" onClick={() => setDeleteTarget(a)}>
                    <Trash2 className="mr-1 h-3.5 w-3.5" />
                    削除
                  </Button>
                </div>
              </CardBody>
            </Card>
          ))}
        </div>
      ) : (
        <Card>
          <CardBody>
            <p className="py-8 text-center text-sm text-[var(--neutral-foreground-3)]">
              お知らせはまだありません。上の「新規作成」ボタンからお知らせを作成してください。
            </p>
          </CardBody>
        </Card>
      )}
    </div>
  );
}
