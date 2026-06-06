'use client';

/**
 * 個別支援計画の「原案」編集 component。
 *
 * 仕様 (2026-05-17 — 原案/本案 分離):
 *  - 原案 (draft_xxx) の主要 4 項目を編集 + 保存
 *  - 保護者からのレビューコメント (guardian_review_comment) を読み取り表示
 *  - 個別支援計画に紐付く担当者会議録 (meetings) を読み取り表示
 *  - 上記 3 つを加味して、別画面で本案を加筆修正する想定
 *  - 本 component は本案フィールド (life_intention 等) には触らない
 *  - 本 component から AI 生成等のヘビーな操作は出さない (シンプル維持)
 */

import { useEffect, useState } from 'react';
import { useQuery, useMutation } from '@tanstack/react-query';
import api from '@/lib/api';
import { Button } from '@/components/ui/Button';
import { MaterialIcon } from '@/components/ui/MaterialIcon';
import { useToast } from '@/components/ui/Toast';
import { formatDate } from '@/lib/utils';
import type { SupportPlan, PlanMeeting } from '@/types/support-plan';

interface Props {
  plan: SupportPlan;
  onSaved: (next: SupportPlan) => void;
  onCancel: () => void;
}

interface DraftForm {
  draft_life_intention: string;
  draft_overall_policy: string;
  draft_long_term_goal: string;
  draft_short_term_goal: string;
}

export function DraftPlanEditor({ plan, onSaved, onCancel }: Props) {
  const toast = useToast();
  const [form, setForm] = useState<DraftForm>({
    draft_life_intention:  plan.draft_life_intention  ?? '',
    draft_overall_policy:  plan.draft_overall_policy  ?? '',
    draft_long_term_goal:  plan.draft_long_term_goal  ?? '',
    draft_short_term_goal: plan.draft_short_term_goal ?? '',
  });

  // plan が切り替わった時にフォームを再初期化
  useEffect(() => {
    setForm({
      draft_life_intention:  plan.draft_life_intention  ?? '',
      draft_overall_policy:  plan.draft_overall_policy  ?? '',
      draft_long_term_goal:  plan.draft_long_term_goal  ?? '',
      draft_short_term_goal: plan.draft_short_term_goal ?? '',
    });
  }, [plan.id]);  // eslint-disable-line react-hooks/exhaustive-deps

  // 担当者会議録 (参照表示用)
  const { data: meetings = [], isLoading: meetingsLoading } = useQuery({
    queryKey: ['support-plan', plan.id, 'meetings'],
    queryFn: async () => {
      const res = await api.get<{ data: PlanMeeting[] }>(`/api/staff/support-plans/${plan.id}/meetings`);
      return Array.isArray(res.data?.data) ? res.data.data : [];
    },
  });

  const saveMutation = useMutation({
    mutationFn: async (payload: DraftForm) => {
      const res = await api.put<{ data: SupportPlan }>(
        `/api/staff/support-plans/${plan.id}/save-draft`,
        payload,
      );
      return res.data.data;
    },
    onSuccess: (next) => {
      toast.success('原案を保存しました');
      onSaved(next);
    },
    onError: (err: unknown) => {
      const msg =
        (err as { response?: { data?: { message?: string } } })?.response?.data?.message
        || '原案の保存に失敗しました';
      toast.error(msg);
    },
  });

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    saveMutation.mutate(form);
  };

  return (
    <form onSubmit={handleSubmit} className="grid grid-cols-1 gap-4 lg:grid-cols-3">
      {/* 左カラム: 原案の編集領域 (主要 4 項目) */}
      <div className="space-y-3 lg:col-span-2">
        <div className="rounded-md border border-[var(--brand-160)] bg-[var(--brand-160)] p-2 text-xs text-[var(--brand-80)]">
          原案は本案とは別に保存されます。保護者コメントや会議録を踏まえて、別タブで本案を加筆修正します。
        </div>

        <DraftField
          label="本人・保護者の意向"
          value={form.draft_life_intention}
          onChange={(v) => setForm((f) => ({ ...f, draft_life_intention: v }))}
        />
        <DraftField
          label="総合的な支援方針"
          value={form.draft_overall_policy}
          onChange={(v) => setForm((f) => ({ ...f, draft_overall_policy: v }))}
        />
        <DraftField
          label="長期目標"
          value={form.draft_long_term_goal}
          onChange={(v) => setForm((f) => ({ ...f, draft_long_term_goal: v }))}
        />
        <DraftField
          label="短期目標"
          value={form.draft_short_term_goal}
          onChange={(v) => setForm((f) => ({ ...f, draft_short_term_goal: v }))}
        />

        <div className="flex items-center justify-between pt-2">
          <p className="text-xs text-[var(--neutral-foreground-4)]">
            最終保存:{' '}
            {plan.draft_saved_at
              ? new Date(plan.draft_saved_at).toLocaleString('ja-JP')
              : '未保存'}
          </p>
          <div className="flex gap-2">
            <Button type="button" variant="outline" onClick={onCancel}>閉じる</Button>
            <Button
              type="submit"
              isLoading={saveMutation.isPending}
              leftIcon={<MaterialIcon name="save" size={16} />}
            >
              原案を保存
            </Button>
          </div>
        </div>
      </div>

      {/* 右カラム: 保護者コメント + 会議録 (参照のみ) */}
      <div className="space-y-3">
        <ReferenceBlock
          icon="forum"
          title="保護者からのコメント"
          empty="まだコメントはありません"
        >
          {plan.guardian_review_comment ? (
            <div className="space-y-1">
              <p className="whitespace-pre-wrap text-sm text-[var(--neutral-foreground-1)]">
                {plan.guardian_review_comment}
              </p>
              {plan.guardian_review_comment_at && (
                <p className="text-[10px] text-[var(--neutral-foreground-4)]">
                  受信: {new Date(plan.guardian_review_comment_at).toLocaleString('ja-JP')}
                </p>
              )}
            </div>
          ) : null}
        </ReferenceBlock>

        <ReferenceBlock
          icon="groups"
          title="個別支援計画 会議録"
          empty="関連する会議録はまだ登録されていません"
        >
          {meetingsLoading ? (
            <p className="text-xs text-[var(--neutral-foreground-4)]">読み込み中...</p>
          ) : meetings.length > 0 ? (
            <div className="space-y-3">
              {meetings.map((m) => (
                <div key={m.id} className="rounded border border-[var(--neutral-stroke-3)] bg-[var(--neutral-background-1)] p-2">
                  <p className="text-xs font-semibold text-[var(--neutral-foreground-2)]">
                    {m.meeting_date ? formatDate(m.meeting_date) : '(日付未設定)'}
                    {m.title ? ` — ${m.title}` : ''}
                  </p>
                  {m.attendees && (
                    <p className="mt-1 text-[10px] text-[var(--neutral-foreground-3)]">出席者: {m.attendees}</p>
                  )}
                  {m.agenda && (
                    <p className="mt-1 whitespace-pre-wrap text-xs text-[var(--neutral-foreground-2)]">
                      <span className="font-semibold">議題:</span> {m.agenda}
                    </p>
                  )}
                  {m.decisions && (
                    <p className="mt-1 whitespace-pre-wrap text-xs text-[var(--neutral-foreground-2)]">
                      <span className="font-semibold">決定事項:</span> {m.decisions}
                    </p>
                  )}
                  {m.notes && (
                    <p className="mt-1 whitespace-pre-wrap text-xs text-[var(--neutral-foreground-2)]">
                      {m.notes}
                    </p>
                  )}
                </div>
              ))}
            </div>
          ) : null}
        </ReferenceBlock>
      </div>
    </form>
  );
}

function DraftField({
  label, value, onChange,
}: { label: string; value: string; onChange: (v: string) => void }) {
  return (
    <div>
      <label className="mb-1 block text-sm font-medium text-[var(--neutral-foreground-2)]">{label}</label>
      <textarea
        rows={3}
        value={value}
        onChange={(e) => onChange(e.target.value)}
        className="block w-full resize-y rounded-md border border-[var(--neutral-stroke-1)] bg-[var(--neutral-background-1)] px-3 py-2 text-sm text-[var(--neutral-foreground-1)] focus:border-[var(--brand-80)] focus:outline-none focus:ring-1 focus:ring-[var(--brand-80)]"
      />
    </div>
  );
}

function ReferenceBlock({
  icon, title, empty, children,
}: { icon: string; title: string; empty: string; children: React.ReactNode }) {
  // children が空文字列・null・undefined・false の場合は empty を表示
  const hasContent =
    children !== null && children !== undefined && children !== false && children !== '';
  return (
    <div className="rounded-md border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-3)] p-3">
      <div className="mb-2 flex items-center gap-2">
        <MaterialIcon name={icon} size={16} className="text-[var(--neutral-foreground-3)]" />
        <p className="text-xs font-semibold text-[var(--neutral-foreground-2)]">{title}</p>
      </div>
      {hasContent ? children : (
        <p className="text-xs text-[var(--neutral-foreground-4)]">{empty}</p>
      )}
    </div>
  );
}
