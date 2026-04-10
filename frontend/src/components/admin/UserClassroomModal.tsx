'use client';

import { useState, useEffect } from 'react';
import api from '@/lib/api';
import { Modal } from '@/components/ui/Modal';
import { Button } from '@/components/ui/Button';
import { useToast } from '@/components/ui/Toast';

interface ClassroomOption {
  id: number;
  classroom_name: string;
}

interface Props {
  user: { id: number; full_name: string };
  onClose: () => void;
}

export function UserClassroomModal({ user, onClose }: Props) {
  const toast = useToast();
  const [classrooms, setClassrooms] = useState<ClassroomOption[]>([]);
  const [selectedIds, setSelectedIds] = useState<number[]>([]);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);

  useEffect(() => {
    (async () => {
      try {
        const [allRes, userRes] = await Promise.all([
          api.get('/api/admin/classrooms'),
          api.get(`/api/admin/users/${user.id}/classrooms`),
        ]);
        const allData = allRes.data.data ?? allRes.data;
        setClassrooms(Array.isArray(allData) ? allData : allData?.data || []);
        setSelectedIds(userRes.data.data.classroom_ids || []);
      } catch {
        toast.error('教室情報の取得に失敗しました');
      } finally {
        setLoading(false);
      }
    })();
  }, [user.id, toast]);

  const toggleId = (id: number) => {
    setSelectedIds((prev) => (prev.includes(id) ? prev.filter((i) => i !== id) : [...prev, id]));
  };

  const handleSave = async () => {
    setSaving(true);
    try {
      await api.put(`/api/admin/users/${user.id}/classrooms`, { classroom_ids: selectedIds });
      toast.success('所属教室を更新しました');
      onClose();
    } catch {
      toast.error('保存に失敗しました');
    } finally {
      setSaving(false);
    }
  };

  return (
    <Modal isOpen={true} onClose={onClose} title={`${user.full_name} の所属教室`} size="md">
      {loading ? (
        <p className="text-sm text-[var(--neutral-foreground-3)]">読み込み中...</p>
      ) : (
        <div className="space-y-3">
          <p className="text-xs text-[var(--neutral-foreground-3)]">
            所属する教室を選択してください。複数選択可能です。
          </p>
          <div className="max-h-[50vh] space-y-1 overflow-y-auto rounded border border-[var(--neutral-stroke-2)] p-2">
            {classrooms.length === 0 ? (
              <p className="py-4 text-center text-sm text-[var(--neutral-foreground-4)]">教室がありません</p>
            ) : (
              classrooms.map((c) => (
                <label key={c.id} className="flex cursor-pointer items-center gap-2 rounded px-2 py-1.5 text-sm hover:bg-[var(--neutral-background-3)]">
                  <input
                    type="checkbox"
                    checked={selectedIds.includes(c.id)}
                    onChange={() => toggleId(c.id)}
                    className="h-4 w-4"
                  />
                  <span>{c.classroom_name}</span>
                </label>
              ))
            )}
          </div>
          <div className="flex justify-end gap-2 pt-2">
            <Button variant="outline" onClick={onClose}>キャンセル</Button>
            <Button variant="primary" isLoading={saving} onClick={handleSave}>保存</Button>
          </div>
        </div>
      )}
    </Modal>
  );
}
