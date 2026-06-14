'use client';

import { useState } from 'react';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import api, { formatApiError } from '@/lib/api';
import { Card, CardHeader, CardTitle, CardBody } from '@/components/ui/Card';
import { Button } from '@/components/ui/Button';
import { MaterialIcon } from '@/components/ui/MaterialIcon';
import { useToast } from '@/components/ui/Toast';

interface TraitsData {
  student_id: number;
  available: { code: string; label: string }[];
  selected: string[];
}

/**
 * AI学習基盤 S4e: 児童の「特性」(統制タグ)をスタッフが記録するパネル。
 *
 * 診断名・医療情報ではなく「支援上の特性」を固定の統制語彙から選ぶ。多次元分析の軸として
 * 集計のみに使う(同意済み・k匿名)。要配慮のため越境(403)時は非表示。
 */
export function StudentTraitsPanel({ studentId }: { studentId: number }) {
  const toast = useToast();
  const queryClient = useQueryClient();
  const [busy, setBusy] = useState(false);
  // 編集中のみ draft を持ち、未編集はサーバ値をそのまま表示する(effectでの同期を避ける)。
  const [draft, setDraft] = useState<string[] | null>(null);

  const queryKey = ['student-traits', studentId];
  const { data, isLoading, error } = useQuery({
    queryKey,
    queryFn: async () => (await api.get<{ data: TraitsData }>(`/api/staff/students/${studentId}/traits`)).data.data,
    retry: false,
  });

  const status = (error as { response?: { status?: number } } | null)?.response?.status;
  if (status === 403) return null; // アクセス権限が無い教室では非表示
  if (isLoading || !data) return null;

  const saved = data.selected;
  const selected = draft ?? saved;

  const toggle = (code: string) => {
    setDraft((prev) => {
      const base = prev ?? saved;
      return base.includes(code) ? base.filter((c) => c !== code) : [...base, code];
    });
  };

  const dirty =
    draft !== null && (selected.length !== saved.length || selected.some((c) => !saved.includes(c)));

  const save = async () => {
    setBusy(true);
    try {
      await api.put(`/api/staff/students/${studentId}/traits`, { traits: selected });
      await queryClient.invalidateQueries({ queryKey });
      setDraft(null); // サーバ値へ戻す(再取得結果を表示)
      toast.success('特性を保存しました');
    } catch (err) {
      toast.error(formatApiError(err, '保存に失敗しました'));
    } finally {
      setBusy(false);
    }
  };

  return (
    <Card>
      <CardHeader>
        <CardTitle>
          <div className="flex items-center gap-2">
            <MaterialIcon name="diversity_3" size={20} />
            支援上の特性（多次元分析）
          </div>
        </CardTitle>
      </CardHeader>
      <CardBody>
        <p className="mb-3 text-xs text-[var(--neutral-foreground-3)]">
          診断名ではなく「支援上の特性」を選びます。記録の傾向を特性ごとに分析するために使い、集計のみ（個人を特定しない形・同意済みのみ）で利用します。AIの文章生成には既定で使いません。
        </p>
        <div className="flex flex-wrap gap-2">
          {data.available.map((t) => {
            const on = selected.includes(t.code);
            return (
              <button
                key={t.code}
                type="button"
                onClick={() => toggle(t.code)}
                aria-pressed={on}
                className={
                  'rounded-full border px-3 py-1 text-sm transition-colors ' +
                  (on
                    ? 'border-[var(--brand-stroke-1,#1a73e8)] bg-[var(--brand-background-2,#e8f0fe)] font-medium text-[var(--brand-foreground-1,#1a73e8)]'
                    : 'border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-1)] text-[var(--neutral-foreground-2)]')
                }
              >
                {on && <MaterialIcon name="check" size={14} className="mr-1 align-text-bottom" />}
                {t.label}
              </button>
            );
          })}
        </div>
        <div className="mt-4">
          <Button
            size="sm"
            isLoading={busy}
            disabled={!dirty}
            leftIcon={<MaterialIcon name="save" size={16} />}
            onClick={save}
          >
            特性を保存する
          </Button>
        </div>
      </CardBody>
    </Card>
  );
}
