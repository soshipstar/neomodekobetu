'use client';

import { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import api, { formatApiError } from '@/lib/api';
import { Card, CardHeader, CardTitle, CardBody } from '@/components/ui/Card';
import { SkeletonList } from '@/components/ui/Skeleton';
import { MaterialIcon } from '@/components/ui/MaterialIcon';
import { AiReasonCandidates } from '@/components/ai-consent/AiReasonCandidates';

interface ReasonRef { category_id: number | null; label: string; count: number }
interface MetricRow {
  dim_value: string | number | null;
  label: string;
  distinct_students: number;
  gen_count: number;
  revision_count: number;
  edited_document_count: number;
  edit_rate: number | null;
  ai_acceptance: number | null;
  change_ratio_avg: number | null;
  change_ratio_p50: number | null;
  change_ratio_p90: number | null;
  top_reasons: ReasonRef[];
}
interface ReportData {
  period: string;
  facet: string;
  periods: string[];
  rows: MetricRow[];
}

const FACETS: { key: string; label: string }[] = [
  { key: 'company', label: '施設全体' },
  { key: 'classroom', label: '教室別' },
  { key: 'cohort', label: '対象(学齢)別' },
  { key: 'growth_stage', label: '成長段階別' },
  { key: 'document_type', label: '文書種別' },
  { key: 'support_category', label: '支援区分(5領域)' },
  { key: 'program_category', label: '実施プログラム別' },
  { key: 'author', label: '記入者別' },
];

const pct = (v: number | null | undefined): string => (v == null ? '—' : `${Math.round(v * 100)}%`);

export default function AiReportPage() {
  const [facet, setFacet] = useState('company');
  const [period, setPeriod] = useState<string | null>(null);

  const { data, isLoading, error } = useQuery({
    queryKey: ['admin', 'ai-edit-metrics', facet, period],
    queryFn: async () => {
      const qs = new URLSearchParams({ facet });
      if (period) qs.set('period', period);
      const res = await api.get<{ data: ReportData }>(`/api/admin/ai-edit-metrics?${qs.toString()}`);
      return res.data.data;
    },
    retry: false,
  });

  const status = (error as { response?: { status?: number } } | null)?.response?.status;
  if (status === 403) {
    return (
      <div className="space-y-6">
        <h1 className="text-2xl font-bold text-[var(--neutral-foreground-1)]">AI学習レポート</h1>
        <p className="text-sm text-[var(--neutral-foreground-3)]">このレポートは施設管理者のみ閲覧できます。</p>
      </div>
    );
  }

  const rows = data?.rows ?? [];
  const periods = data?.periods ?? [];

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-bold text-[var(--neutral-foreground-1)]">AI学習レポート</h1>
        <p className="mt-1 text-sm text-[var(--neutral-foreground-3)]">
          AIが生成した下書きに対して、職員がどれだけ・どんな理由で修正したかの傾向です。同意のある記録のみ・個人を特定しない集計(5件未満は非表示)。
        </p>
      </div>

      {/* 期間 + 集計軸 */}
      <div className="flex flex-wrap items-center gap-3">
        <label className="flex items-center gap-2 text-sm">
          <span className="text-[var(--neutral-foreground-2)]">対象月</span>
          <select
            className="rounded-md border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-1)] px-3 py-1.5 text-sm"
            value={period ?? data?.period ?? ''}
            onChange={(e) => setPeriod(e.target.value)}
          >
            {(periods.length ? periods : [data?.period].filter(Boolean) as string[]).map((p) => (
              <option key={p} value={p}>{p}</option>
            ))}
          </select>
        </label>
      </div>

      <div className="flex flex-wrap gap-2">
        {FACETS.map((f) => (
          <button
            key={f.key}
            onClick={() => setFacet(f.key)}
            className={`rounded-full px-3 py-1.5 text-sm font-medium transition-colors ${
              facet === f.key
                ? 'bg-[var(--brand-background-1)] text-white'
                : 'bg-[var(--neutral-background-3)] text-[var(--neutral-foreground-2)] hover:bg-[var(--neutral-background-4)]'
            }`}
          >
            {f.label}
          </button>
        ))}
      </div>

      {/* 記入者別は逆インセンティブ注意 */}
      {facet === 'author' && (
        <div className="flex items-start gap-2 rounded-lg border border-[var(--warning-stroke-1,#f0c36d)] bg-[var(--warning-background-2,#fdf6e3)] p-3 text-xs text-[var(--warning-foreground-1,#a16207)]">
          <MaterialIcon name="info" size={16} />
          <span>修正の「回数」で職員を評価しないでください。各職員がどんな観点で修正しているかの傾向把握・研修の手がかりとして活用してください。</span>
        </div>
      )}

      {isLoading ? (
        <SkeletonList items={5} />
      ) : rows.length === 0 ? (
        <Card>
          <CardBody>
            <p className="py-8 text-center text-sm text-[var(--neutral-foreground-3)]">
              この期間の集計データはありません。<br />
              (集計には同意済みの修正が5件以上必要です。蓄積後、翌日の自動集計で表示されます)
            </p>
          </CardBody>
        </Card>
      ) : (
        <Card>
          <CardHeader>
            <CardTitle>
              <div className="flex items-center gap-2">
                <MaterialIcon name="insights" size={20} />
                {FACETS.find((f) => f.key === facet)?.label}（{data?.period}）
              </div>
            </CardTitle>
          </CardHeader>
          <CardBody>
            <div className="overflow-x-auto">
              <table className="w-full text-sm">
                <thead>
                  <tr className="border-b border-[var(--neutral-stroke-2)] text-left text-xs text-[var(--neutral-foreground-3)]">
                    <th className="py-2 pr-3">区分</th>
                    <th className="py-2 pr-3">児童数</th>
                    <th className="py-2 pr-3 w-48">AI採用度</th>
                    <th className="py-2 pr-3">平均修正量</th>
                    <th className="py-2 pr-3">修正率</th>
                    <th className="py-2 pr-3">主要な修正理由</th>
                    <th className="py-2 pr-3">修正/生成</th>
                  </tr>
                </thead>
                <tbody>
                  {rows.map((r, i) => (
                    <tr key={i} className="border-b border-[var(--neutral-stroke-3,#eee)]">
                      <td className="py-2 pr-3 font-medium text-[var(--neutral-foreground-1)]">{r.label}</td>
                      <td className="py-2 pr-3 text-[var(--neutral-foreground-2)]">{r.distinct_students}</td>
                      <td className="py-2 pr-3">
                        <div className="flex items-center gap-2">
                          <div className="h-2 w-24 overflow-hidden rounded-full bg-[var(--neutral-background-3)]">
                            <div
                              className="h-full rounded-full bg-[var(--brand-background-1)]"
                              style={{ width: `${Math.round((r.ai_acceptance ?? 0) * 100)}%` }}
                            />
                          </div>
                          <span className="text-xs text-[var(--neutral-foreground-2)]">{pct(r.ai_acceptance)}</span>
                        </div>
                      </td>
                      <td className="py-2 pr-3 text-[var(--neutral-foreground-2)]">{pct(r.change_ratio_avg)}</td>
                      <td className="py-2 pr-3 text-[var(--neutral-foreground-2)]">{pct(r.edit_rate)}</td>
                      <td className="py-2 pr-3">
                        <div className="flex flex-wrap gap-1">
                          {r.top_reasons.length === 0 ? (
                            <span className="text-xs text-[var(--neutral-foreground-4)]">—</span>
                          ) : (
                            r.top_reasons.map((t, j) => (
                              <span key={j} className="rounded-full bg-[var(--neutral-background-3)] px-2 py-0.5 text-xs text-[var(--neutral-foreground-2)]">
                                {t.label}（{t.count}）
                              </span>
                            ))
                          )}
                        </div>
                      </td>
                      <td className="py-2 pr-3 text-xs text-[var(--neutral-foreground-3)]">
                        {r.revision_count}/{r.gen_count}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
            <p className="mt-3 text-xs text-[var(--neutral-foreground-4)]">
              AI採用度=1−平均修正量(高いほどAI下書きがそのまま使われている)。平均修正量=セクションごとの変更割合の平均。修正率=AI生成のうち修正が入った文書の割合。
            </p>
          </CardBody>
        </Card>
      )}

      {/* 修正理由の新カテゴリ候補(動的タクソノミー) */}
      <AiReasonCandidates />

      {formatError(error, status)}
    </div>
  );
}

function formatError(error: unknown, status?: number) {
  if (!error || status === 403) return null;
  return (
    <p className="text-sm text-[var(--danger-foreground-1,#b91c1c)]">{formatApiError(error, 'レポートの取得に失敗しました')}</p>
  );
}
