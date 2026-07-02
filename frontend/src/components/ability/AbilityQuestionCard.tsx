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
  answered: boolean;
  answered_degree: number | null;
  /** 回答済みの内訳(誰が・何時に・どの活動で)。同日の別活動/別スタッフの回答でも
      「本日回答済」になる仕様のため、出所を明示して誤解を防ぐ。 */
  answered_at?: string | null;
  answered_by?: string | null;
  answered_in?: string | null;
}

interface SupportCode {
  code: string;
  content: string;
  score_band: string | null;
}

interface NextQuestionData {
  questions: Question[];
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
 * 1日3問まで、成長段階に合った具体設問を並べて表示し、各設問に「該当度」を1つ選んで記録する。
 * 能力評価トグルが OFF の教室では API が 409 を返すため何も表示しない(自己ゲート)。
 */
export function AbilityQuestionCard({ studentId, dailyRecordId }: Props) {
  const queryClient = useQueryClient();

  const queryKey = ['ability-next-question', studentId];
  const { data, isLoading, error } = useQuery({
    queryKey,
    queryFn: async () => {
      const res = await api.get<{ data: NextQuestionData | null }>(
        `/api/staff/ability/students/${studentId}/next-question`,
      );
      return res.data.data;
    },
    retry: false,
  });

  // 409 = この教室では能力評価が無効 / 403 = 越境。いずれも設問UIは出さない(フェイルクローズ)。
  const status = (error as { response?: { status?: number } } | null)?.response?.status;
  if (status === 409 || status === 403) return null;

  const questions = data?.questions ?? [];

  return (
    <div className="rounded-lg border border-[var(--brand-stroke-2,var(--neutral-stroke-2))] bg-[var(--neutral-background-2)] p-4">
      <div className="mb-2 flex items-center gap-2">
        <MaterialIcon name="psychology" size={18} className="text-[var(--brand-foreground-1,var(--brand-80))]" />
        <span className="text-sm font-semibold text-[var(--neutral-foreground-1)]">
          能力評価の設問(本日 {questions.filter((q) => q.answered).length}/{questions.length} 回答)
        </span>
      </div>

      {isLoading ? (
        <p className="py-4 text-center text-xs text-[var(--neutral-foreground-4)]">設問を読み込み中...</p>
      ) : questions.length === 0 ? (
        <p className="py-4 text-center text-xs text-[var(--neutral-foreground-4)]">出題できる項目がありません。</p>
      ) : (
        <div className="space-y-3">
          {questions.map((q) => (
            <AbilityQuestionItem
              key={q.item_id}
              question={q}
              supportCodes={data?.support_codes ?? []}
              results={data?.results ?? []}
              studentId={studentId}
              dailyRecordId={dailyRecordId}
              onSaved={() => {
                // 回答→スコア自動計算済み。到達マップ・全体像を更新(設問は他項目の入力を保つため再取得しない)
                queryClient.invalidateQueries({ queryKey: ['ability-summary', studentId] });
                queryClient.invalidateQueries({ queryKey: ['ability-progress-map', studentId] });
              }}
            />
          ))}
        </div>
      )}
    </div>
  );
}

interface ItemProps {
  question: Question;
  supportCodes: SupportCode[];
  results: string[];
  studentId: number;
  dailyRecordId?: number | null;
  onSaved?: () => void;
}

/** 1つの設問に「該当度＋任意コメント」で回答して観察記録を保存する。 */
function AbilityQuestionItem({ question, supportCodes, results, studentId, dailyRecordId, onSaved }: ItemProps) {
  const toast = useToast();
  const [degree, setDegree] = useState<number | null>(null);
  const [supportCode, setSupportCode] = useState<string>('');
  const [result, setResult] = useState<string>('');
  const [isNewScene, setIsNewScene] = useState(false);
  const [behavior, setBehavior] = useState('');
  const [submitting, setSubmitting] = useState(false);
  const [saved, setSaved] = useState(false);

  const submit = async () => {
    setSubmitting(true);
    try {
      await api.post('/api/staff/ability/observations', {
        student_id: studentId,
        item_id: question.item_id,
        degree,
        support_code: supportCode || null,
        result: result || null,
        is_new_scene: isNewScene,
        behavior: behavior.trim() || null,
        daily_record_id: dailyRecordId ?? null,
      });
      toast.success('能力評価の観察記録を保存しました');
      setSaved(true);
      onSaved?.();
    } catch {
      toast.error('観察記録の保存に失敗しました');
    } finally {
      setSubmitting(false);
    }
  };

  // 回答済み(本日回答済み=question.answered / たった今記録=saved)は結果を表示し、再入力させない
  if (question.answered || saved) {
    const shownDegree = question.answered_degree ?? (saved ? degree : null);
    const label = shownDegree !== null
      ? (DEGREE_OPTIONS.find((d) => d.score === shownDegree)?.label ?? `該当度 ${shownDegree}`)
      : null;
    // 記録の出所(誰が・何時に・どの活動で)。設問は生徒×日単位のため、
    // 同じ日の別の活動記録や別スタッフの回答でも「本日記録済み」になる。
    const sourceParts: string[] = [];
    if (question.answered_at) sourceParts.push(`本日 ${question.answered_at}`);
    if (question.answered_by) sourceParts.push(`${question.answered_by} さんが記録`);
    if (question.answered_in) sourceParts.push(`活動「${question.answered_in}」にて`);
    const sourceText = question.answered ? sourceParts.join(' / ') : '';
    return (
      <div className="rounded-md border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-1)] p-2 text-sm">
        <div className="flex items-center gap-2 text-[var(--neutral-foreground-2)]">
          <MaterialIcon name="check_circle" size={16} className="text-emerald-600" />
          <span className="font-medium">{question.item_name}（本日記録済み）</span>
        </div>
        {question.question && (
          <p className="mt-1 text-xs text-[var(--neutral-foreground-3)]">{question.question}</p>
        )}
        {label && <p className="mt-1 text-[var(--neutral-foreground-1)]">回答: {label}</p>}
        {sourceText && (
          <p className="mt-1 text-xs text-[var(--neutral-foreground-3)]">記録: {sourceText}</p>
        )}
      </div>
    );
  }

  return (
    <div className="space-y-2 rounded-md border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-1)] p-3">
      <div>
        <div className="flex flex-wrap items-center gap-2 text-xs text-[var(--neutral-foreground-3)]">
          <span className="rounded bg-[var(--neutral-background-4)] px-1.5 py-0.5">{question.domain}</span>
          {question.axis_name && (
            <span className="rounded bg-[var(--neutral-background-4)] px-1.5 py-0.5">成長段階: {question.axis_name}</span>
          )}
        </div>
        <p className="mt-1 text-xs text-[var(--neutral-foreground-3)]">{question.item_name}</p>
        {question.question && (
          <p className="mt-1 rounded-md bg-[var(--brand-160)] p-2 text-sm font-medium text-[var(--neutral-foreground-1)]">
            {question.question}
          </p>
        )}
        {question.hint && (
          <p className="mt-1 flex items-start gap-1 text-xs text-[var(--neutral-foreground-3)]">
            <MaterialIcon name="lightbulb" size={13} className="mt-0.5 shrink-0" />
            <span>観察のヒント: {question.hint}</span>
          </p>
        )}
        {question.benchmark && question.benchmark !== question.question && (
          <p className="mt-1 text-xs text-[var(--neutral-foreground-4)]">到達目安: {question.benchmark}</p>
        )}
      </div>

      {/* 該当度(主入力) */}
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
      <input
        type="text"
        value={behavior}
        onChange={(e) => setBehavior(e.target.value)}
        placeholder="補足コメント(任意)"
        className="block w-full rounded-md border border-[var(--neutral-stroke-1)] bg-[var(--neutral-background-1)] px-3 py-1.5 text-sm text-[var(--neutral-foreground-1)] placeholder-[var(--neutral-foreground-4)] focus:border-[var(--brand-80)] focus:outline-none"
      />

      {/* 詳細入力(任意・従来方式) */}
      <details className="text-xs text-[var(--neutral-foreground-3)]">
        <summary className="cursor-pointer">詳細入力(支援コード・結果)— 任意</summary>
        <div className="mt-2 flex flex-wrap items-end gap-3">
          <select
            value={supportCode}
            onChange={(e) => setSupportCode(e.target.value)}
            className="rounded-md border border-[var(--neutral-stroke-1)] bg-[var(--neutral-background-1)] px-2 py-1.5 text-sm text-[var(--neutral-foreground-1)] focus:border-[var(--brand-80)] focus:outline-none"
          >
            <option value="">支援を選択</option>
            {supportCodes.map((sc) => (
              <option key={sc.code} value={sc.code}>{sc.content}</option>
            ))}
          </select>
          <select
            value={result}
            onChange={(e) => setResult(e.target.value)}
            className="rounded-md border border-[var(--neutral-stroke-1)] bg-[var(--neutral-background-1)] px-2 py-1.5 text-sm text-[var(--neutral-foreground-1)] focus:border-[var(--brand-80)] focus:outline-none"
          >
            <option value="">結果</option>
            {results.map((r) => (
              <option key={r} value={r}>{RESULT_LABELS[r] ?? r}</option>
            ))}
          </select>
          <label className="flex cursor-pointer items-center gap-2 text-[var(--neutral-foreground-2)]">
            <input
              type="checkbox"
              className="h-4 w-4 accent-[var(--brand-background-1,var(--brand-80))]"
              checked={isNewScene}
              onChange={(e) => setIsNewScene(e.target.checked)}
            />
            初めての場面
          </label>
        </div>
      </details>

      <div className="flex justify-end">
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
  );
}
