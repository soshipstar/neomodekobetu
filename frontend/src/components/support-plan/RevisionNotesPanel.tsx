'use client';

/**
 * 本案画面に表示する「原案からの変更説明」パネル。
 *
 * 仕様 (2026-05-17 — 原案/本案 分離):
 *  - revision_notes (text) を表示する
 *  - 「AI で再生成」ボタンで gpt-5.4-mini に原案・本案を渡して再生成
 *  - 印刷物 (PDF/export) には含めない (これは backend 側で除外。本 UI は print: hidden)
 *  - 原案または本案が未保存だと backend が 422 を返す可能性があるため、エラー
 *    トーストで案内する
 */

import { useState } from 'react';
import { useMutation } from '@tanstack/react-query';
import api from '@/lib/api';
import { Button } from '@/components/ui/Button';
import { MaterialIcon } from '@/components/ui/MaterialIcon';
import { useToast } from '@/components/ui/Toast';
import type { SupportPlan } from '@/types/support-plan';

interface Props {
  plan: SupportPlan;
  onUpdated: (next: { revision_notes: string | null; revision_notes_generated_at: string | null }) => void;
}

export function RevisionNotesPanel({ plan, onUpdated }: Props) {
  const toast = useToast();
  const [content, setContent] = useState<string | null>(plan.revision_notes);
  const [generatedAt, setGeneratedAt] = useState<string | null>(plan.revision_notes_generated_at);

  const generateMutation = useMutation({
    mutationFn: async () => {
      const res = await api.post<{
        data: { revision_notes: string | null; revision_notes_generated_at: string | null };
      }>(`/api/staff/support-plans/${plan.id}/generate-revision-notes`);
      return res.data.data;
    },
    onSuccess: (data) => {
      setContent(data.revision_notes);
      setGeneratedAt(data.revision_notes_generated_at);
      onUpdated(data);
      toast.success('変更説明を生成しました');
    },
    onError: (err: unknown) => {
      const msg =
        (err as { response?: { data?: { message?: string } } })?.response?.data?.message
        || 'AI 生成に失敗しました';
      toast.error(msg);
    },
  });

  return (
    // print:hidden で印刷物には出力しない (PDF 側でも除外している、二重防御)
    <div className="print:hidden rounded-md border border-dashed border-[var(--brand-80)] bg-[var(--brand-160)] p-3">
      <div className="mb-2 flex items-center justify-between gap-2">
        <div className="flex items-center gap-2">
          <MaterialIcon name="track_changes" size={16} className="text-[var(--brand-80)]" />
          <p className="text-xs font-semibold text-[var(--brand-80)]">
            原案からの変更説明 (スタッフ確認用)
          </p>
        </div>
        <div className="flex items-center gap-1.5">
          <span className="rounded bg-[var(--brand-80)] px-1.5 py-0.5 text-[10px] font-bold text-white">
            印刷物には含まれません
          </span>
          <Button
            type="button"
            size="sm"
            variant="outline"
            onClick={() => generateMutation.mutate()}
            isLoading={generateMutation.isPending}
            leftIcon={<MaterialIcon name="auto_awesome" size={14} />}
          >
            {content ? 'AI で再生成' : 'AI で生成'}
          </Button>
        </div>
      </div>

      {content ? (
        <div className="space-y-1">
          <p className="whitespace-pre-wrap rounded bg-[var(--neutral-background-1)] p-2 text-xs leading-relaxed text-[var(--neutral-foreground-1)]">
            {content}
          </p>
          {generatedAt && (
            <p className="text-right text-[10px] text-[var(--neutral-foreground-4)]">
              生成: {new Date(generatedAt).toLocaleString('ja-JP')}
            </p>
          )}
        </div>
      ) : (
        <p className="text-xs text-[var(--neutral-foreground-3)]">
          原案と本案の両方を保存した後、「AI で生成」を押すと、原案からの変更内容の説明文が自動生成されます。
        </p>
      )}
    </div>
  );
}
