'use client';

import { useState, useRef, useCallback } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import api from '@/lib/api';
import { Card, CardHeader, CardTitle } from '@/components/ui/Card';
import { Button } from '@/components/ui/Button';
import { Input } from '@/components/ui/Input';
import { Badge } from '@/components/ui/Badge';
import { Modal } from '@/components/ui/Modal';
import { SkeletonList } from '@/components/ui/Skeleton';
import { useToast } from '@/components/ui/Toast';
import { Plus, Pencil, Trash2, GripVertical } from 'lucide-react';

interface Tag {
  id: number;
  name: string;
  color: string;
  sort_order: number;
  usage_count: number;
}

const colorOptions = [
  { value: '#3B82F6', label: '青' },
  { value: '#10B981', label: '緑' },
  { value: '#F59E0B', label: '黄' },
  { value: '#EF4444', label: '赤' },
  { value: '#8B5CF6', label: '紫' },
  { value: '#EC4899', label: 'ピンク' },
  { value: '#6B7280', label: 'グレー' },
  { value: '#F97316', label: 'オレンジ' },
];

export default function TagSettingsPage() {
  const queryClient = useQueryClient();
  const toast = useToast();
  const [modalOpen, setModalOpen] = useState(false);
  const [editingTag, setEditingTag] = useState<Tag | null>(null);
  const [form, setForm] = useState({ name: '', color: '#3B82F6' });
  const dragItem = useRef<number | null>(null);
  const dragOverItem = useRef<number | null>(null);

  const { data: tags = [], isLoading } = useQuery({
    queryKey: ['staff', 'tags'],
    queryFn: async () => {
      const res = await api.get<{ data: Tag[] }>('/api/staff/tag-settings');
      return res.data.data;
    },
  });

  const saveMutation = useMutation({
    mutationFn: async (data: typeof form) => {
      if (editingTag) {
        return api.put(`/api/staff/tag-settings/${editingTag.id}`, data);
      }
      return api.post('/api/staff/tag-settings', data);
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['staff', 'tags'] });
      toast.success(editingTag ? 'タグを更新しました' : 'タグを追加しました');
      closeModal();
    },
    onError: () => toast.error('保存に失敗しました'),
  });

  const deleteMutation = useMutation({
    mutationFn: (id: number) => api.delete(`/api/staff/tag-settings/${id}`),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['staff', 'tags'] });
      toast.success('タグを削除しました');
    },
    onError: () => toast.error('削除に失敗しました'),
  });

  const reorderMutation = useMutation({
    mutationFn: (tagIds: number[]) => api.post('/api/staff/tag-settings/reorder', { tag_ids: tagIds }),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['staff', 'tags'] });
    },
    onError: () => toast.error('並び替えに失敗しました'),
  });

  const closeModal = () => {
    setModalOpen(false);
    setEditingTag(null);
    setForm({ name: '', color: '#3B82F6' });
  };

  const openEdit = (tag: Tag) => {
    setEditingTag(tag);
    setForm({ name: tag.name, color: tag.color });
    setModalOpen(true);
  };

  const handleDragStart = (index: number) => {
    dragItem.current = index;
  };

  const handleDragEnter = (index: number) => {
    dragOverItem.current = index;
  };

  const handleDragEnd = useCallback(() => {
    if (dragItem.current === null || dragOverItem.current === null) return;
    const reordered = [...tags];
    const [removed] = reordered.splice(dragItem.current, 1);
    reordered.splice(dragOverItem.current, 0, removed);
    dragItem.current = null;
    dragOverItem.current = null;
    reorderMutation.mutate(reordered.map((t) => t.id));
  }, [tags, reorderMutation]);

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-bold text-[var(--neutral-foreground-1)]">タグ設定</h1>
        <Button onClick={() => { setEditingTag(null); setForm({ name: '', color: '#3B82F6' }); setModalOpen(true); }} leftIcon={<Plus className="h-4 w-4" />}>
          タグを追加
        </Button>
      </div>

      <Card>
        <CardHeader>
          <CardTitle>タグ一覧</CardTitle>
          <p className="text-sm text-[var(--neutral-foreground-3)]">ドラッグして並び替えができます</p>
        </CardHeader>

        {isLoading ? (
          <SkeletonList items={5} />
        ) : tags.length === 0 ? (
          <p className="py-8 text-center text-sm text-[var(--neutral-foreground-3)]">タグがありません</p>
        ) : (
          <div className="space-y-2">
            {tags.map((tag, index) => (
              <div
                key={tag.id}
                draggable
                onDragStart={() => handleDragStart(index)}
                onDragEnter={() => handleDragEnter(index)}
                onDragEnd={handleDragEnd}
                onDragOver={(e) => e.preventDefault()}
                className="flex items-center gap-3 rounded-lg border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-1)] p-3 hover:shadow-[var(--shadow-4)] transition-shadow cursor-move"
              >
                <GripVertical className="h-5 w-5 text-[var(--neutral-foreground-4)] shrink-0" />
                <div
                  className="h-4 w-4 rounded-full shrink-0"
                  style={{ backgroundColor: tag.color }}
                />
                <span className="flex-1 font-medium text-[var(--neutral-foreground-1)]">{tag.name}</span>
                <Badge variant="default">{tag.usage_count}件使用</Badge>
                <Button variant="ghost" size="sm" onClick={() => openEdit(tag)}>
                  <Pencil className="h-4 w-4" />
                </Button>
                <Button
                  variant="ghost"
                  size="sm"
                  onClick={() => { if (confirm(`タグ「${tag.name}」を削除しますか？`)) deleteMutation.mutate(tag.id); }}
                >
                  <Trash2 className="h-4 w-4 text-[var(--status-danger-fg)]" />
                </Button>
              </div>
            ))}
          </div>
        )}
      </Card>

      <Modal isOpen={modalOpen} onClose={closeModal} title={editingTag ? 'タグを編集' : 'タグを追加'}>
        <form onSubmit={(e) => { e.preventDefault(); saveMutation.mutate(form); }} className="space-y-4">
          <Input label="タグ名" value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} required />
          <div>
            <label className="mb-1 block text-sm font-medium text-[var(--neutral-foreground-2)]">カラー</label>
            <div className="flex flex-wrap gap-2">
              {colorOptions.map((opt) => (
                <button
                  key={opt.value}
                  type="button"
                  onClick={() => setForm({ ...form, color: opt.value })}
                  className={`h-8 w-8 rounded-full border-2 transition-transform ${form.color === opt.value ? 'border-[var(--neutral-foreground-1)] scale-110' : 'border-transparent'}`}
                  style={{ backgroundColor: opt.value }}
                  title={opt.label}
                />
              ))}
            </div>
          </div>
          <div className="flex items-center gap-2 mt-2">
            <span className="text-sm text-[var(--neutral-foreground-2)]">プレビュー:</span>
            <span
              className="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium text-white"
              style={{ backgroundColor: form.color }}
            >
              {form.name || 'サンプル'}
            </span>
          </div>
          <div className="flex justify-end gap-2 pt-2">
            <Button variant="secondary" type="button" onClick={closeModal}>キャンセル</Button>
            <Button type="submit" isLoading={saveMutation.isPending}>保存</Button>
          </div>
        </form>
      </Modal>
    </div>
  );
}
