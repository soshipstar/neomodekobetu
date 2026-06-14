'use client';

import { useQuery } from '@tanstack/react-query';
import api from '@/lib/api';
import { Card, CardHeader, CardTitle, CardBody } from '@/components/ui/Card';
import { MaterialIcon } from '@/components/ui/MaterialIcon';

/**
 * 支援知蒸留 D5: 横断検索・根拠提示(法人内)。
 *
 * 「この児童と同じ条件(対象×成長段階)の児童 N名に、法人内でどんな支援が実施され、
 * 成果はどうか」を根拠付きで提示する。記録を書くスタッフに方向性の手がかりを与える。
 * 同条件の蓄積が無い(k匿名未満等)場合は自己非表示。
 */

type Knowledge = {
  cohort_label: string;
  growth_stage: string;
  sample_n: number;
  top_support_categories: { label: string; count: number }[];
  top_programs: { label: string; count: number }[];
  outcome: {
    objective_delta_avg: number | null;
    monitoring_pct_avg: number | null;
    agreement_avg: number | null;
  };
  exemplar_excerpts: { section: string; text: string }[];
  computed_at: string | null;
};

export function KnowledgePanel({ studentId }: { studentId: number }) {
  const { data } = useQuery({
    queryKey: ['staff', 'student', studentId, 'knowledge'],
    queryFn: async () =>
      (await api.get<{ data: Knowledge | null }>(`/api/staff/students/${studentId}/knowledge`)).data.data,
    enabled: !!studentId,
    retry: false,
  });

  if (!data) return null; // 同条件の蓄積なし → 非表示

  return (
    <Card>
      <CardHeader>
        <CardTitle>
          <div className="flex items-center gap-2">
            <MaterialIcon name="hub" size={20} />
            同じ条件の支援傾向（法人内・{data.cohort_label} / {data.growth_stage}・{data.sample_n}名）
          </div>
        </CardTitle>
      </CardHeader>
      <CardBody>
        <p className="mb-3 text-xs text-[var(--neutral-foreground-3)]">
          法人内で、この子と近い条件（対象・成長段階）の児童 {data.sample_n}名の記録から、よく実施されている支援と成果の傾向をまとめたものです。記録の方向性の参考にしてください（個人を特定する情報は含みません）。
        </p>

        <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
          {data.top_support_categories.length > 0 && (
            <div className="rounded-lg border border-[var(--neutral-stroke-2)] p-3">
              <div className="mb-1 text-xs font-medium text-[var(--neutral-foreground-2)]">よく扱う支援領域</div>
              <ul className="space-y-1 text-sm text-[var(--neutral-foreground-1)]">
                {data.top_support_categories.map((t, i) => (
                  <li key={i} className="flex items-center justify-between">
                    <span>{t.label}</span>
                    <span className="text-xs text-[var(--neutral-foreground-3)]">{t.count}件</span>
                  </li>
                ))}
              </ul>
            </div>
          )}
          {data.top_programs.length > 0 && (
            <div className="rounded-lg border border-[var(--neutral-stroke-2)] p-3">
              <div className="mb-1 text-xs font-medium text-[var(--neutral-foreground-2)]">よく実施するプログラム</div>
              <ul className="space-y-1 text-sm text-[var(--neutral-foreground-1)]">
                {data.top_programs.map((t, i) => (
                  <li key={i} className="flex items-center justify-between">
                    <span>{t.label}</span>
                    <span className="text-xs text-[var(--neutral-foreground-3)]">{t.count}件</span>
                  </li>
                ))}
              </ul>
            </div>
          )}
        </div>

        {(data.outcome.objective_delta_avg !== null ||
          data.outcome.monitoring_pct_avg !== null ||
          data.outcome.agreement_avg !== null) && (
          <div className="mt-3 grid grid-cols-3 gap-2 text-center">
            <Metric label="客観スコア変化(平均)" value={data.outcome.objective_delta_avg !== null ? (data.outcome.objective_delta_avg > 0 ? `+${data.outcome.objective_delta_avg}` : `${data.outcome.objective_delta_avg}`) : '—'} />
            <Metric label="モニタリング達成(平均)" value={data.outcome.monitoring_pct_avg !== null ? `${data.outcome.monitoring_pct_avg}%` : '—'} />
            <Metric label="主観×客観の一致(平均)" value={data.outcome.agreement_avg !== null ? `${data.outcome.agreement_avg}%` : '—'} />
          </div>
        )}

        {data.exemplar_excerpts.length > 0 && (
          <div className="mt-3 rounded-lg border border-[var(--neutral-stroke-2)] p-3">
            <div className="mb-1 text-xs font-medium text-[var(--neutral-foreground-2)]">記述例（マスク済の見本・確定記録）</div>
            <ul className="space-y-1 text-sm text-[var(--neutral-foreground-1)]">
              {data.exemplar_excerpts.map((e, i) => (
                <li key={i} className="border-l-2 border-[var(--neutral-stroke-2)] pl-2 text-[var(--neutral-foreground-2)]">
                  {e.text}
                </li>
              ))}
            </ul>
          </div>
        )}
      </CardBody>
    </Card>
  );
}

function Metric({ label, value }: { label: string; value: string }) {
  return (
    <div className="rounded-md bg-[var(--neutral-background-2)] p-2">
      <div className="text-sm font-medium text-[var(--neutral-foreground-1)]">{value}</div>
      <div className="text-[10px] leading-tight text-[var(--neutral-foreground-4)]">{label}</div>
    </div>
  );
}
