'use client';

import { useState } from 'react';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import api from '@/lib/api';
import { Button } from '@/components/ui/Button';
import { MaterialIcon } from '@/components/ui/MaterialIcon';
import { useToast } from '@/components/ui/Toast';

interface Question {
  item_id: string;
  domain: string;
  item_name: string;
  definition: string | null;
  perspective: string | null;
  axis_id: string;
  axis_name: string | null;
  benchmark: string | null;
  question: string | null;
  hint: string | null;
}

interface SupportCode {
  code: string;
  content: string;
  score_band: string | null;
}

interface NextQuestionData {
  question: Question;
  support_codes: SupportCode[];
  results: string[];
}

const RESULT_LABELS: Record<string, string> = {
  completed: '完了',
  partial: '途中',
  refused: '拒否',
};

// 該当度(7段階)。「設問にどれくらい該当しているか」を1つ選ぶ。値は0〜10スコアに対応。
const DEGREE_OPTIONS: { score: number; label: string }[] = [
  { score: 0, label: 'まだ難しい' },
  { score: 2, label: '手伝えばできる' },
  { score: 4, label: '促せば・ヒントで' },
  { score: 5, label: 'きっかけ(一声)で' },
  { score: 6, label: 'だいたい自分で' },
  { score: 8, label: '安定して自分で（到達）' },
  { score: 9, label: 'いろんな場面で（般化）' },
];

interface Props {
  studentId: number;
  dailyRecordId?: number | null;
}

/**
 * 能力評価の日々の設問カード。
 *
 * 成長段階に合った設問(到達目安)を1問表示し、支援コード+結果+新規場面+行動で回答すると
 * 観察記録を保存する。能力評価トグルが OFF の教室では API が 409 を返すため何も表示しない
 * (画面側で機能フラグを別途取得しなくてよい自己ゲート方式)。
 */
export function AbilityQuestionCard({ studentId, dailyRecordId }: Props) {
  const toast = useToast();
  const queryClient = useQueryClient();
  const [excludeItemId, setExcludeItemId] = useState<string | undefined>(undefined);
  const [submitting, setSubmitting] = useState(false);

  const [degree, setDegree] = useState<number | null>(null);
  const [supportCode, setSupportCode] = useState<string>('');
  const [result, setResult] = useState<string>('');
  const [isNewScene, setIsNewScene] = useState(false);
  const [behavior, setBehavior] = useState('');

  const queryKey = ['ability-next-question', studentId, excludeItemId];

  const { data, isLoading, error } = useQuery({
    queryKey,
    queryFn: async () => {
      const res = await api.get<{ data: NextQuestionData | null }>(
        `/api/staff/ability/students/${studentId}/next-question`,
        { params: excludeItemId ? { exclude_item_id: excludeItemId } : {} },
      );
      return res.data.data;
    },
    retry: false,
  });

  const resetForm = () => {
    setDegree(null);
    setSupportCode('');
    setResult('');
    setIsNewScene(false);
    setBehavior('');
  };

  // 409 = この教室では能力評価が無効 / 403 = 越境。いずれも設問UIは出さない(フェイルクローズ)。
  const status = (error as { response?: { status?: number } } | null)?.response?.status;
  if (status === 409 || status === 403) return null;

  const reroll = () => {
    if (data?.question) {
      setExcludeItemId(data.question.item_id);
      resetForm();
    }
  };

  const submit = async () => {
    if (!data?.question) return;
    setSubmitting(true);
    try {
      await api.post('/api/staff/ability/observations', {
        student_id: studentId,
        item_id: data.question.item_id,
        degree,
        support_code: supportCode || null,
        result: result || null,
        is_new_scene: isNewScene,
        behavior: behavior.trim() || null,
        daily_record_id: dailyRecordId ?? null,
      });
      toast.success('能力評価の観察記録を保存しました');
      resetForm();
      setExcludeItemId(undefined);
      // 次の設問へ(サーバ側が記録の薄い項目をローテーション)
      await queryClient.invalidateQueries({ queryKey: ['ability-next-question', studentId] });
    } catch {
      toast.error('観察記録の保存に失敗しました');
    } finally {
      setSubmitting(false);
    }
  };

  return (
    <div className="rounded-lg border border-[var(--brand-stroke-2,var(--neutral-stroke-2))] bg-[var(--neutral-background-2)] p-4">
      <div className="mb-2 flex items-center gap-2">
        <MaterialIcon name="psychology" size={18} className="text-[var(--brand-foreground-1,var(--brand-80))]" />
        <span className="text-sm font-semibold text-[var(--neutral-foreground-1)]">能力評価の設問(1問)</span>
      </div>

      {isLoading ? (
        <p className="py-4 text-center text-xs text-[var(--neutral-foreground-4)]">設問を読み込み中...</p>
      ) : !data?.question ? (
        <p className="py-4 text-center text-xs text-[var(--neutral-foreground-4)]">出題できる項目がありません。</p>
      ) : (
        <div className="space-y-3">
          <div>
            <div className="flex flex-wrap items-center gap-2 text-xs text-[var(--neutral-foreground-3)]">
              <span className="rounded bg-[var(--neutral-background-4)] px-1.5 py-0.5">{data.question.domain}</span>
              {data.question.axis_name && (
                <span className="rounded bg-[var(--neutral-background-4)] px-1.5 py-0.5">
                  成長段階: {data.question.axis_name}
                </span>
              )}
            </div>
            <p className="mt-1 text-xs text-[var(--neutral-foreground-3)]">{data.question.item_name}</p>
            {/* 段階別の具体設問(無ければ到達目安) */}
            {data.question.question && (
              <p className="mt-1 rounded-md bg-[var(--brand-160)] p-2 text-sm font-medium text-[var(--neutral-foreground-1)]">
                {data.question.question}
              </p>
            )}
            {data.question.hint && (
              <p className="mt-1 flex items-start gap-1 text-xs text-[var(--neutral-foreground-3)]">
                <MaterialIcon name="lightbulb" size={13} className="mt-0.5 shrink-0" />
                <span>観察のヒント: {data.question.hint}</span>
              </p>
            )}
            {data.question.benchmark && data.question.benchmark !== data.question.question && (
              <p className="mt-1 text-xs text-[var(--neutral-foreground-4)]">到達目安: {data.question.benchmark}</p>
            )}
          </div>

          {/* 該当度(主入力): 設問にどれくらい該当するか を1つ選ぶ */}
          <div>
            <label className="mb-1 block text-xs font-medium text-[var(--neutral-foreground-2)]">
              この設問にどれくらい該当しますか?
            </label>
            <div className="flex flex-wrap gap-1.5">
              {DEGREE_OPTIONS.map((d) => (
                <button
                  key={d.score}
                  type="button"
                  onClick={() => setDegree(d.score)}
                  className={`rounded-md border px-2 py-1 text-xs ${
                    degree === d.score
                      ? 'border-[var(--brand-80)] bg-[var(--brand-background-1)] text-white'
                      : 'border-[var(--neutral-stroke-1)] bg-[var(--neutral-background-1)] text-[var(--neutral-foreground-2)]'
                  }`}
                >
                  {d.label}
                </button>
              ))}
            </div>
          </div>

          {/* 補足コメント(任意) */}
          <div>
            <label className="mb-1 block text-xs font-medium text-[var(--neutral-foreground-2)]">補足コメント(任意)</label>
            <textarea
              rows={2}
              value={behavior}
              onChange={(e) => setBehavior(e.target.value)}
              placeholder="気づいたことを自由に(任意)。例: 初めての場所でも自分から取り組んだ"
              className="block w-full resize-none rounded-md border border-[var(--neutral-stroke-1)] bg-[var(--neutral-background-1)] px-3 py-1.5 text-sm text-[var(--neutral-foreground-1)] placeholder-[var(--neutral-foreground-4)] focus:border-[var(--brand-80)] focus:outline-none"
            />
          </div>

          {/* 詳細入力(任意・従来方式): 支援コード・結果 */}
          <details className="text-xs text-[var(--neutral-foreground-3)]">
            <summary className="cursor-pointer">詳細入力(支援コード・結果)— 任意</summary>
            <div className="mt-2 space-y-2">
              <div>
                <label className="mb-1 block font-medium text-[var(--neutral-foreground-2)]">提供した支援</label>
                <select
                  value={supportCode}
                  onChange={(e) => setSupportCode(e.target.value)}
                  className="block w-full rounded-md border border-[var(--neutral-stroke-1)] bg-[var(--neutral-background-1)] px-2 py-1.5 text-sm text-[var(--neutral-foreground-1)] focus:border-[var(--brand-80)] focus:outline-none"
                >
                  <option value="">選択してください</option>
                  {data.support_codes.map((sc) => (
                    <option key={sc.code} value={sc.code}>{sc.content}</option>
                  ))}
                </select>
              </div>
              <div className="flex flex-wrap items-center gap-4">
                <div>
                  <label className="mb-1 block font-medium text-[var(--neutral-foreground-2)]">結果</label>
                  <select
                    value={result}
                    onChange={(e) => setResult(e.target.value)}
                    className="rounded-md border border-[var(--neutral-stroke-1)] bg-[var(--neutral-background-1)] px-2 py-1.5 text-sm text-[var(--neutral-foreground-1)] focus:border-[var(--brand-80)] focus:outline-none"
                  >
                    <option value="">選択</option>
                    {data.results.map((r) => (
                      <option key={r} value={r}>{RESULT_LABELS[r] ?? r}</option>
                    ))}
                  </select>
                </div>
                <label className="flex cursor-pointer items-center gap-2 pt-4 text-[var(--neutral-foreground-2)]">
                  <input
                    type="checkbox"
                    className="h-4 w-4 accent-[var(--brand-background-1,var(--brand-80))]"
                    checked={isNewScene}
                    onChange={(e) => setIsNewScene(e.target.checked)}
                  />
                  初めての場面
                </label>
              </div>
            </div>
          </details>

          <div className="flex justify-between gap-2">
            <Button
              variant="secondary"
              size="sm"
              onClick={reroll}
              leftIcon={<MaterialIcon name="refresh" size={16} />}
            >
              別の設問にする
            </Button>
            <Button
              size="sm"
              isLoading={submitting}
              disabled={degree === null && !supportCode}
              leftIcon={<MaterialIcon name="check" size={16} />}
              onClick={submit}
            >
              記録する
            </Button>
          </div>
        </div>
      )}
    </div>
  );
}
