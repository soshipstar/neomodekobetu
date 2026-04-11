'use client';

import { useState, useEffect } from 'react';
import api from '@/lib/api';
import { Modal } from '@/components/ui/Modal';
import { Button } from '@/components/ui/Button';
import { Badge } from '@/components/ui/Badge';
import { useToast } from '@/components/ui/Toast';
import { MaterialIcon } from '@/components/ui/MaterialIcon';

interface LinkedItem {
  id: number;
  student_name: string;
  classroom_id: number;
  status: string;
  classroom?: { id: number; classroom_name: string } | null;
}

interface Props {
  student: {
    id: number;
    student_name: string;
    person_id?: string | null;
    classroom?: { id: number; classroom_name: string } | null;
  };
  onClose: () => void;
  onSynced?: () => void;
}

const statusLabels: Record<string, string> = {
  active: '在籍', trial: '体験', short_term: '短期', withdrawn: '退所', waiting: '待機',
};

export function StudentLinkedSyncModal({ student, onClose, onSynced }: Props) {
  const { toast } = useToast();
  const [linked, setLinked] = useState<LinkedItem[]>([]);
  const [loading, setLoading] = useState(true);
  const [syncing, setSyncing] = useState(false);

  useEffect(() => {
    (async () => {
      try {
        const res = await api.get(`/api/admin/students/${student.id}/linked`);
        setLinked(res.data.data.linked || []);
      } catch {
        toast('リンク情報の取得に失敗しました', 'error');
      } finally {
        setLoading(false);
      }
    })();
  }, [student.id, toast]);

  const handleSync = async () => {
    if (!window.confirm(
      `この児童の氏名・生年月日・学年・保護者・メモを、他の ${linked.length} 件のレコードに反映します。よろしいですか？`
    )) return;
    setSyncing(true);
    try {
      const res = await api.post(`/api/admin/students/${student.id}/sync-linked`);
      const count = res.data?.data?.updated_count ?? 0;
      toast(`${count} 件のレコードに同期しました`, 'success');
      onSynced?.();
      onClose();
    } catch (err: unknown) {
      const msg =
        (err as { response?: { data?: { message?: string } } })?.response?.data?.message ||
        '同期に失敗しました';
      toast(msg, 'error');
    } finally {
      setSyncing(false);
    }
  };

  return (
    <Modal isOpen={true} onClose={onClose} title={`${student.student_name} のリンク`} size="md">
      {loading ? (
        <p className="text-sm text-[var(--neutral-foreground-3)]">読み込み中...</p>
      ) : (
        <div className="space-y-4">
          <div className="rounded border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-3)] p-3 text-xs">
            <div className="flex items-center gap-2">
              <MaterialIcon name="link" size={14} className="text-[var(--brand-80)]" />
              <span className="font-medium text-[var(--neutral-foreground-1)]">
                同一人物としてリンクされているレコード
              </span>
            </div>
            <p className="mt-1 text-[var(--neutral-foreground-3)]">
              複製元: {student.classroom?.classroom_name ?? '不明'}（このレコード）
            </p>
          </div>

          {linked.length === 0 ? (
            <p className="py-4 text-center text-sm text-[var(--neutral-foreground-4)]">
              リンク先のレコードがありません。
            </p>
          ) : (
            <>
              <div className="max-h-[40vh] space-y-2 overflow-y-auto">
                {linked.map((item) => (
                  <div
                    key={item.id}
                    className="flex items-center justify-between rounded border border-[var(--neutral-stroke-2)] p-2 text-sm"
                  >
                    <div>
                      <p className="font-medium text-[var(--neutral-foreground-1)]">
                        {item.student_name}
                      </p>
                      <p className="text-xs text-[var(--neutral-foreground-3)]">
                        {item.classroom?.classroom_name ?? `教室 #${item.classroom_id}`}
                      </p>
                    </div>
                    <Badge variant={item.status === 'active' ? 'success' : 'default'}>
                      {statusLabels[item.status] ?? item.status}
                    </Badge>
                  </div>
                ))}
              </div>

              <div className="rounded border border-[var(--status-warning-fg)]/20 bg-[var(--status-warning-bg)] p-2 text-xs text-[var(--status-warning-fg)]">
                「同期する」を実行すると、このレコードの <strong>氏名・生年月日・学年・学年調整・保護者・メモ</strong> が他のレコードにも反映されます。
                <br />
                教室・ユーザー名・スケジュール・支援開始日・ステータスは各教室で個別管理されるため同期されません。
              </div>
            </>
          )}

          <div className="flex justify-end gap-2 pt-2">
            <Button variant="outline" onClick={onClose}>
              閉じる
            </Button>
            {linked.length > 0 && (
              <Button variant="primary" isLoading={syncing} onClick={handleSync}>
                このレコードの内容を同期する
              </Button>
            )}
          </div>
        </div>
      )}
    </Modal>
  );
}
