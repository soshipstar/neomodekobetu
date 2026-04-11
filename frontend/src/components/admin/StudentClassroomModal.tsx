'use client';

import { useState, useEffect } from 'react';
import api from '@/lib/api';
import { Modal } from '@/components/ui/Modal';
import { Button } from '@/components/ui/Button';
import { useToast } from '@/components/ui/Toast';

interface ClassroomOption {
  id: number;
  classroom_name: string;
  company_id: number | null;
}

interface Props {
  student: { id: number; student_name: string };
  onClose: () => void;
}

export function StudentClassroomModal({ student, onClose }: Props) {
  const { toast } = useToast();
  const [classrooms, setClassrooms] = useState<ClassroomOption[]>([]);
  const [selectedIds, setSelectedIds] = useState<number[]>([]);
  const [primaryId, setPrimaryId] = useState<number | null>(null);
  const [studentCompanyId, setStudentCompanyId] = useState<number | null>(null);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);

  useEffect(() => {
    (async () => {
      try {
        const [allRes, stuRes] = await Promise.all([
          api.get('/api/admin/classrooms'),
          api.get(`/api/admin/students/${student.id}/classrooms`),
        ]);
        const allData = allRes.data.data ?? allRes.data;
        const list: ClassroomOption[] = Array.isArray(allData) ? allData : allData?.data || [];
        setClassrooms(list);
        setSelectedIds(stuRes.data.data.classroom_ids || []);
        setPrimaryId(stuRes.data.data.primary_classroom_id ?? null);
        setStudentCompanyId(stuRes.data.data.company_id ?? null);
      } catch {
        toast('教室情報の取得に失敗しました', 'error');
      } finally {
        setLoading(false);
      }
    })();
  }, [student.id, toast]);

  // 児童の主教室と同じ企業に属する教室のみ候補にする。
  // company_id が null の教室は常に除外。
  const filteredClassrooms =
    studentCompanyId !== null
      ? classrooms.filter((c) => c.company_id === studentCompanyId)
      : [];

  const toggleId = (id: number) => {
    // 主教室は外せない（サーバ側でも拒否するが UX のため先に禁止）
    if (id === primaryId) return;
    setSelectedIds((prev) => (prev.includes(id) ? prev.filter((i) => i !== id) : [...prev, id]));
  };

  const handleSave = async () => {
    setSaving(true);
    try {
      await api.put(`/api/admin/students/${student.id}/classrooms`, { classroom_ids: selectedIds });
      toast('所属教室を更新しました', 'success');
      onClose();
    } catch {
      toast('保存に失敗しました', 'error');
    } finally {
      setSaving(false);
    }
  };

  return (
    <Modal isOpen={true} onClose={onClose} title={`${student.student_name} の所属教室`} size="md">
      {loading ? (
        <p className="text-sm text-[var(--neutral-foreground-3)]">読み込み中...</p>
      ) : (
        <div className="space-y-3">
          <p className="text-xs text-[var(--neutral-foreground-3)]">
            この児童が在籍する教室を選択してください。複数選択可能です。
            主教室（背景グレー）は外せません。主教室の変更は児童編集画面で行ってください。
          </p>
          {studentCompanyId === null && (
            <p className="rounded border border-[var(--status-warning-fg)]/20 bg-[var(--status-warning-bg)] p-2 text-xs text-[var(--status-warning-fg)]">
              この児童の主教室に所属企業が設定されていません。先に主教室を企業に所属させてください。
            </p>
          )}
          <div className="max-h-[50vh] space-y-1 overflow-y-auto rounded border border-[var(--neutral-stroke-2)] p-2">
            {filteredClassrooms.length === 0 ? (
              <p className="py-4 text-center text-sm text-[var(--neutral-foreground-4)]">
                所属企業内に選択可能な教室がありません
              </p>
            ) : (
              filteredClassrooms.map((c) => {
                const isPrimary = c.id === primaryId;
                return (
                  <label
                    key={c.id}
                    className={`flex items-center gap-2 rounded px-2 py-1.5 text-sm ${
                      isPrimary
                        ? 'cursor-not-allowed bg-[var(--neutral-background-3)]'
                        : 'cursor-pointer hover:bg-[var(--neutral-background-3)]'
                    }`}
                  >
                    <input
                      type="checkbox"
                      checked={selectedIds.includes(c.id)}
                      onChange={() => toggleId(c.id)}
                      disabled={isPrimary}
                      className="h-4 w-4"
                    />
                    <span>{c.classroom_name}</span>
                    {isPrimary && (
                      <span className="ml-auto text-xs text-[var(--neutral-foreground-4)]">主教室</span>
                    )}
                  </label>
                );
              })
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
