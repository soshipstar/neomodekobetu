'use client';

import { useState } from 'react';
import { useMutation } from '@tanstack/react-query';
import api from '@/lib/api';
import { Button } from '@/components/ui/Button';
import { Modal } from '@/components/ui/Modal';
import { useToast } from '@/components/ui/Toast';
import { MaterialIcon } from '@/components/ui/MaterialIcon';

interface AiGenerateButtonProps {
  studentId: number;
  onGenerated: () => void;
}

export function AiGenerateButton({ studentId, onGenerated }: AiGenerateButtonProps) {
  const [showConfirm, setShowConfirm] = useState(false);
  const [progress, setProgress] = useState('');
  const toast = useToast();

  const mutation = useMutation({
    mutationFn: async () => {
      setProgress('AI生成を開始しています...');
      const response = await api.post<{ data: { job_id: string } }>(
        `/api/staff/students/${studentId}/support-plans/ai-generate`
      );
      setProgress('支援計画を生成中です。しばらくお待ちください...');

      // Poll for completion (the actual implementation would use WebSocket)
      // For now, we return after the POST succeeds (job queued)
      return response.data;
    },
    onSuccess: () => {
      setShowConfirm(false);
      setProgress('');
      toast.success('AI生成ジョブを開始しました。完了後に通知されます。');
      onGenerated();
    },
    onError: () => {
      setProgress('');
      toast.error('AI生成に失敗しました');
    },
  });

  return (
    <>
      <Button
        variant="outline"
        leftIcon={<MaterialIcon name="auto_awesome" size={16} />}
        onClick={() => setShowConfirm(true)}
      >
        AI生成
      </Button>

      <Modal
        isOpen={showConfirm}
        onClose={() => !mutation.isPending && setShowConfirm(false)}
        title="AI支援計画生成"
        size="sm"
      >
        {mutation.isPending ? (
          <div className="flex flex-col items-center gap-4 py-8">
            <MaterialIcon name="progress_activity" size={32} className="animate-spin text-[var(--brand-80)]" />
            <p className="text-sm text-[var(--neutral-foreground-3)]">{progress}</p>
            <p className="text-xs text-[var(--neutral-foreground-4)]">
              この処理には数分かかる場合があります
            </p>
          </div>
        ) : (
          <div className="space-y-4">
            <div className="rounded-lg bg-[var(--brand-160)] p-4">
              <p className="text-sm text-blue-800">
                AIが過去のデータに基づいて個別支援計画の素案を生成します。
                生成された内容は下書きとして保存され、確認・編集が可能です。
              </p>
            </div>

            <div className="rounded-lg bg-yellow-50 p-4">
              <p className="text-sm text-yellow-800">
                <strong>注意:</strong> AIの生成内容は参考情報です。必ず専門スタッフが内容を確認・修正してください。
              </p>
            </div>

            <div className="flex justify-end gap-2">
              <Button variant="ghost" onClick={() => setShowConfirm(false)}>
                キャンセル
              </Button>
              <Button
                leftIcon={<MaterialIcon name="auto_awesome" size={16} />}
                onClick={() => mutation.mutate()}
              >
                生成を開始
              </Button>
            </div>
          </div>
        )}
      </Modal>
    </>
  );
}

export default AiGenerateButton;
