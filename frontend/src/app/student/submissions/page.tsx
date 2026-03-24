'use client';

import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import api from '@/lib/api';
import { nl } from '@/lib/utils';
import { Card } from '@/components/ui/Card';
import { Button } from '@/components/ui/Button';
import { Input } from '@/components/ui/Input';
import { Badge } from '@/components/ui/Badge';
import { Modal } from '@/components/ui/Modal';
import { SkeletonList } from '@/components/ui/Skeleton';
import { useToast } from '@/components/ui/Toast';
import { format, differenceInDays, isPast } from 'date-fns';
import { ja } from 'date-fns/locale';
import { MaterialIcon } from '@/components/ui/MaterialIcon';

interface Submission {
  id: number;
  title: string;
  description: string;
  due_date: string | null;
  is_completed: boolean;
  completed_at: string | null;
  source: 'weekly_plan' | 'guardian_chat' | 'student';
  attachment_path?: string | null;
  attachment_original_name?: string | null;
  attachment_size?: number | null;
}

const sourceLabels: Record<string, string> = {
  weekly_plan: '週間計画表',
  guardian_chat: '保護者チャット',
  student: '自分で登録',
};

export default function StudentSubmissionsPage() {
  const queryClient = useQueryClient();
  const toast = useToast();
  const [addModal, setAddModal] = useState(false);
  const [editId, setEditId] = useState<number | null>(null);
  const [form, setForm] = useState({ title: '', description: '', due_date: '' });

  const { data: submissions = [], isLoading } = useQuery({
    queryKey: ['student', 'submissions'],
    queryFn: async () => {
      const res = await api.get<{ data: Submission[] }>('/api/student/submissions');
      return res.data.data;
    },
  });

  const saveMutation = useMutation({
    mutationFn: (data: { id?: number; title: string; description: string; due_date: string }) =>
      api.post('/api/student/submissions', data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['student', 'submissions'] });
      toast.success('保存しました');
      closeModal();
    },
    onError: () => toast.error('保存に失敗しました'),
  });

  const completeMutation = useMutation({
    mutationFn: ({ id, source }: { id: number; source: string }) =>
      api.post('/api/student/submissions/complete', { id, source }),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['student', 'submissions'] });
      toast.success('完了にしました');
    },
    onError: () => toast.error('更新に失敗しました'),
  });

  const uncompleteMutation = useMutation({
    mutationFn: ({ id, source }: { id: number; source: string }) =>
      api.post('/api/student/submissions/uncomplete', { id, source }),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['student', 'submissions'] });
      toast.success('未完了に戻しました');
    },
    onError: () => toast.error('更新に失敗しました'),
  });

  const deleteMutation = useMutation({
    mutationFn: (id: number) => api.delete(`/api/student/submissions/${id}`),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['student', 'submissions'] });
      toast.success('削除しました');
    },
    onError: () => toast.error('削除に失敗しました'),
  });

  const pending = submissions.filter((s) => !s.is_completed);
  const completed = submissions.filter((s) => s.is_completed);

  const today = new Date();
  const urgentCount = pending.filter((s) => {
    if (!s.due_date) return false;
    const daysLeft = differenceInDays(new Date(s.due_date), today);
    return daysLeft <= 3;
  }).length;

  const openAddModal = () => {
    setEditId(null);
    setForm({ title: '', description: '', due_date: '' });
    setAddModal(true);
  };

  const openEditModal = (sub: Submission) => {
    setEditId(sub.id);
    setForm({ title: sub.title, description: sub.description || '', due_date: sub.due_date || '' });
    setAddModal(true);
  };

  const closeModal = () => {
    setAddModal(false);
    setEditId(null);
    setForm({ title: '', description: '', due_date: '' });
  };

  const handleSave = () => {
    const data = editId ? { id: editId, ...form } : form;
    saveMutation.mutate(data);
  };

  const renderSubmissionCard = (sub: Submission) => {
    const dueDate = sub.due_date ? new Date(sub.due_date) : null;
    const daysLeft = dueDate ? differenceInDays(dueDate, today) : null;
    const isOverdue = dueDate && !sub.is_completed && isPast(dueDate) && daysLeft !== null && daysLeft < 0;
    const isUrgent = dueDate && !sub.is_completed && !isOverdue && daysLeft !== null && daysLeft <= 3;

    let cardClass = '';
    if (sub.is_completed) cardClass = 'border-l-4 border-l-[var(--status-success-fg)] bg-green-50/30';
    else if (isOverdue) cardClass = 'border-l-4 border-l-[var(--status-danger-fg)] bg-red-50/30';
    else if (isUrgent) cardClass = 'border-l-4 border-l-[var(--status-warning-fg)]';
    else cardClass = 'border-l-4 border-l-[var(--brand-80)]';

    let badgeVariant: 'danger' | 'warning' | 'default' | 'success' = 'default';
    let badgeText = '未提出';
    if (sub.is_completed) { badgeVariant = 'success'; badgeText = '提出済み'; }
    else if (isOverdue) { badgeVariant = 'danger'; badgeText = '期限切れ'; }
    else if (isUrgent) { badgeVariant = 'warning'; badgeText = '期限間近'; }

    return (
      <Card key={`${sub.source}-${sub.id}`} className={cardClass}>
        <div className="p-4">
          <div className="flex items-start justify-between gap-2 flex-wrap">
            <div className="flex-1 min-w-0">
              <h3 className="font-semibold text-[var(--neutral-foreground-1)]">{sub.title}</h3>
            </div>
            <div className="flex items-center gap-2 flex-shrink-0">
              <Badge variant={badgeVariant}>{badgeText}</Badge>
              <Badge variant="default">{sourceLabels[sub.source]}</Badge>
            </div>
          </div>

          {dueDate && (
            <p className={`mt-1 text-sm ${isOverdue ? 'text-[var(--status-danger-fg)] font-medium' : 'text-[var(--neutral-foreground-2)]'}`}>
              <MaterialIcon name="schedule" size={14} className="mr-1 inline" />
              提出期限: {format(dueDate, 'yyyy年M月d日', { locale: ja })}
              {!sub.is_completed && daysLeft !== null && (
                daysLeft >= 0
                  ? <span>（あと{daysLeft}日）</span>
                  : <span>（{Math.abs(daysLeft)}日超過）</span>
              )}
            </p>
          )}

          {sub.description && (
            <p className="mt-2 text-sm text-[var(--neutral-foreground-2)] leading-relaxed whitespace-pre-wrap">
              {nl(sub.description)}
            </p>
          )}

          <div className="mt-3 flex flex-wrap gap-2">
            {!sub.is_completed ? (
              <Button
                size="sm"
                variant="outline"
                onClick={() => completeMutation.mutate({ id: sub.id, source: sub.source })}
                leftIcon={<MaterialIcon name="check_circle" size={14} />}
              >
                完了にする
              </Button>
            ) : (
              <Button
                size="sm"
                variant="outline"
                onClick={() => uncompleteMutation.mutate({ id: sub.id, source: sub.source })}
                leftIcon={<MaterialIcon name="undo" size={14} />}
              >
                未完了に戻す
              </Button>
            )}
            {sub.source === 'student' && (
              <>
                {!sub.is_completed && (
                  <Button
                    size="sm"
                    variant="outline"
                    onClick={() => openEditModal(sub)}
                    leftIcon={<MaterialIcon name="edit" size={14} />}
                  >
                    編集
                  </Button>
                )}
                <Button
                  size="sm"
                  variant="ghost"
                  onClick={() => {
                    if (confirm('この提出物を削除しますか？')) deleteMutation.mutate(sub.id);
                  }}
                >
                  <MaterialIcon name="delete" size={14} className="text-[var(--status-danger-fg)]" />
                </Button>
              </>
            )}
          </div>
        </div>
      </Card>
    );
  };

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-bold text-[var(--neutral-foreground-1)]">提出物管理</h1>
        <Button onClick={openAddModal} leftIcon={<MaterialIcon name="add" size={16} />}>
          提出物を追加
        </Button>
      </div>

      {/* Summary Cards */}
      <div className="grid grid-cols-3 gap-3">
        <Card className={urgentCount > 0 ? 'bg-red-50/50' : ''}>
          <div className="p-4 text-center">
            <p className={`text-2xl font-bold ${urgentCount > 0 ? 'text-[var(--status-danger-fg)]' : 'text-[var(--neutral-foreground-1)]'}`}>
              {urgentCount}
            </p>
            <p className="text-xs text-[var(--neutral-foreground-3)]">期限間近</p>
          </div>
        </Card>
        <Card>
          <div className="p-4 text-center">
            <p className="text-2xl font-bold text-[var(--neutral-foreground-1)]">{pending.length}</p>
            <p className="text-xs text-[var(--neutral-foreground-3)]">未提出</p>
          </div>
        </Card>
        <Card className="bg-green-50/30">
          <div className="p-4 text-center">
            <p className="text-2xl font-bold text-[var(--status-success-fg)]">{completed.length}</p>
            <p className="text-xs text-[var(--neutral-foreground-3)]">提出済み</p>
          </div>
        </Card>
      </div>

      {isLoading ? (
        <SkeletonList items={4} />
      ) : (
        <>
          {/* Pending */}
          <div>
            <h2 className="mb-3 text-lg font-semibold text-[var(--brand-80)]">未提出の提出物</h2>
            {pending.length === 0 ? (
              <Card>
                <div className="py-8 text-center">
                  <MaterialIcon name="description" size={40} className="mx-auto text-[var(--neutral-foreground-3)]" />
                  <p className="mt-2 text-sm text-[var(--neutral-foreground-3)]">未提出の提出物はありません</p>
                </div>
              </Card>
            ) : (
              <div className="space-y-3">
                {pending.map(renderSubmissionCard)}
              </div>
            )}
          </div>

          {/* Completed */}
          {completed.length > 0 && (
            <div>
              <h2 className="mb-3 text-lg font-semibold text-[var(--status-success-fg)]">提出済みの提出物</h2>
              <div className="space-y-3">
                {completed.map(renderSubmissionCard)}
              </div>
            </div>
          )}
        </>
      )}

      {/* Add/Edit Modal */}
      <Modal
        isOpen={addModal}
        onClose={closeModal}
        title={editId ? '提出物を編集' : '提出物を追加'}
        size="md"
      >
        <form
          onSubmit={(e) => { e.preventDefault(); handleSave(); }}
          className="space-y-4"
        >
          <Input
            label="提出物名 *"
            value={form.title}
            onChange={(e) => setForm({ ...form, title: e.target.value })}
            required
          />
          <div>
            <label className="mb-1 block text-sm font-medium text-[var(--neutral-foreground-2)]">
              詳細説明
            </label>
            <textarea
              value={form.description}
              onChange={(e) => setForm({ ...form, description: e.target.value })}
              className="block w-full rounded-lg border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-1)] px-3 py-2 text-sm text-[var(--neutral-foreground-1)]"
              rows={3}
            />
          </div>
          <Input
            label="提出期限 *"
            type="date"
            value={form.due_date}
            onChange={(e) => setForm({ ...form, due_date: e.target.value })}
            required
          />
          <div className="flex justify-end gap-2 pt-2">
            <Button variant="secondary" type="button" onClick={closeModal}>
              キャンセル
            </Button>
            <Button type="submit" isLoading={saveMutation.isPending}>
              保存
            </Button>
          </div>
        </form>
      </Modal>
    </div>
  );
}
