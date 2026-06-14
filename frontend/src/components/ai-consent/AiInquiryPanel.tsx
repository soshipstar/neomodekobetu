'use client';

import { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import api, { formatApiError } from '@/lib/api';
import { Card, CardHeader, CardTitle, CardBody } from '@/components/ui/Card';
import { Button } from '@/components/ui/Button';
import { MaterialIcon } from '@/components/ui/MaterialIcon';
import { useToast } from '@/components/ui/Toast';

/**
 * 支援知蒸留 D2: AI記録支援(問い返し・仮説提示)。
 * 記録メモを入力すると、AIが「書く」のではなく「観察を深める問い」と「因果仮説の候補」を返す。
 * 既存の下書き生成とは別の、考えるための補助。
 */
export function AiInquiryPanel({ studentId }: { studentId: number }) {
  const toast = useToast();
  const [text, setText] = useState('');
  const [busy, setBusy] = useState(false);
  const [questions, setQuestions] = useState<string[]>([]);
  const [hypotheses, setHypotheses] = useState<string[]>([]);

  // D3: 自分の記録レベル(育成・成長志向の表示)
  const { data: level } = useQuery({
    queryKey: ['ai-assist-level'],
    queryFn: async () => (await api.get<{ data: { level: number; label: string; next_hint: string } }>('/api/staff/ai-assist/level')).data.data,
    retry: false,
  });

  const ask = async () => {
    if (text.trim() === '') return;
    setBusy(true);
    try {
      const res = await api.post<{ data: { questions: string[]; hypotheses: string[] } }>(
        '/api/staff/ai-assist/inquiry',
        { text: text.trim(), student_id: studentId },
      );
      setQuestions(res.data.data.questions ?? []);
      setHypotheses(res.data.data.hypotheses ?? []);
    } catch (err) {
      toast.error(formatApiError(err, 'AIアシストに失敗しました'));
    } finally {
      setBusy(false);
    }
  };

  return (
    <Card>
      <CardHeader>
        <CardTitle>
          <div className="flex items-center gap-2">
            <MaterialIcon name="psychology" size={20} />
            AI記録支援（問い返し・仮説候補）
          </div>
        </CardTitle>
      </CardHeader>
      <CardBody>
        <p className="mb-2 text-xs text-[var(--neutral-foreground-3)]">
          記録のメモを入れると、AIが文章を書く代わりに「観察を深める問い」と「考えられる原因(仮説)の候補」を返します。記録の質を高めるための補助です。
        </p>
        {level && (
          <div className="mb-2 flex items-start gap-2 rounded-md bg-[var(--neutral-background-2)] p-2 text-xs">
            <MaterialIcon name="trending_up" size={14} className="mt-0.5 text-[var(--brand-foreground-1,#1a73e8)]" />
            <span className="text-[var(--neutral-foreground-2)]">
              あなたの記録レベル: <span className="font-medium">{level.label}</span>。{level.next_hint}
              <span className="block text-[var(--neutral-foreground-4)]">（成長に合わせてAIの問いの量が変わります。回数で評価するものではありません）</span>
            </span>
          </div>
        )}
        <textarea
          value={text}
          onChange={(e) => setText(e.target.value)}
          rows={3}
          maxLength={2000}
          placeholder="例: 今日は落ち着いて活動に参加できた"
          className="w-full rounded-md border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-1)] px-3 py-2 text-sm"
        />
        <div className="mt-2">
          <Button size="sm" isLoading={busy} leftIcon={<MaterialIcon name="lightbulb" size={16} />} onClick={ask}>
            AIに問い・仮説をもらう
          </Button>
        </div>

        {(questions.length > 0 || hypotheses.length > 0) && (
          <div className="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2">
            <div className="rounded-lg border border-[var(--neutral-stroke-2)] p-3">
              <div className="mb-1 text-xs font-medium text-[var(--neutral-foreground-2)]">観察を深める問い</div>
              <ul className="list-disc space-y-1 pl-4 text-sm text-[var(--neutral-foreground-1)]">
                {questions.map((q, i) => <li key={i}>{q}</li>)}
              </ul>
            </div>
            <div className="rounded-lg border border-[var(--neutral-stroke-2)] p-3">
              <div className="mb-1 text-xs font-medium text-[var(--neutral-foreground-2)]">考えられる原因(仮説)の候補</div>
              <ul className="list-disc space-y-1 pl-4 text-sm text-[var(--neutral-foreground-1)]">
                {hypotheses.map((h, i) => <li key={i}>{h}</li>)}
              </ul>
            </div>
          </div>
        )}
      </CardBody>
    </Card>
  );
}
