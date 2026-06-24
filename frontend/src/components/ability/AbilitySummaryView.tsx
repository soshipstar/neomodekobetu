'use client';

import { useState } from 'react';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import {
  Radar, RadarChart, PolarGrid, PolarAngleAxis, PolarRadiusAxis, ResponsiveContainer,
} from 'recharts';
import api, { formatApiError } from '@/lib/api';
import { Card, CardHeader, CardTitle, CardBody } from '@/components/ui/Card';
import { Button } from '@/components/ui/Button';
import { MaterialIcon } from '@/components/ui/MaterialIcon';
import { useToast } from '@/components/ui/Toast';

interface SummaryItem {
  item_id: string;
  item_name: string;
  score: number;
  axis_name: string | null;
  guardian_words: string | null;
  needs_review: boolean;
  subjective: number | null;
  subjective_norm: number | null;
  evaluated_on: string;
}
interface SummaryDomain {
  domain: string;
  tool_id: string;
  average: number | null;
  subjective_average: number | null;
  items: SummaryItem[];
}
interface Summary {
  has_data: boolean;
  has_subjective: boolean;
  mynameis_member_code: string | null;
  domains: SummaryDomain[];
  radar: { domain: string; average: number | null; subjective: number | null }[];
  counts: { scored: number; needs_review: number; subjective: number };
}

interface OutcomeData {
  objective_delta: { has: boolean; scored_items?: number; avg_change?: number | null; improved?: number; declined?: number };
  monitoring: { has: boolean; avg_level?: number; pct?: number; count?: number; monitoring_date?: string };
  agreement: { has: boolean; overall?: number; domains?: { domain: string; objective: number; subjective: number; agreement: number }[] };
}

interface Props {
  studentId: number;
}

/**
 * 個別支援計画の別添「評価状況の全体像」(レーダー+詳細表)。
 *
 * 能力評価トグルが OFF の教室では summary API が 409 を返すため何も表示しない(自己ゲート)。
 */
export function AbilitySummaryView({ studentId }: Props) {
  const toast = useToast();
  const queryClient = useQueryClient();
  const [busy, setBusy] = useState(false);
  const [linkInput, setLinkInput] = useState<string | null>(null);

  const queryKey = ['ability-summary', studentId];
  const { data, isLoading, error } = useQuery({
    queryKey,
    queryFn: async () => {
      const res = await api.get<{ data: Summary }>(`/api/staff/ability/students/${studentId}/summary`);
      return res.data.data;
    },
    retry: false,
  });

  // S6 成果(outcome): A スコアΔ / B モニタリング達成度 / C 主観×客観の一致
  const { data: outcome } = useQuery({
    queryKey: ['ability-outcome', studentId],
    queryFn: async () => (await api.get<{ data: OutcomeData }>(`/api/staff/ability/students/${studentId}/outcome`)).data.data,
    retry: false,
  });

  const status = (error as { response?: { status?: number } } | null)?.response?.status;
  if (status === 409 || status === 403) return null;

  const recompute = async () => {
    setBusy(true);
    try {
      await api.post(`/api/staff/ability/students/${studentId}/recompute-scores`);
      await queryClient.invalidateQueries({ queryKey });
      toast.success('能力評価スコアを更新しました');
    } catch {
      toast.error('スコア更新に失敗しました');
    } finally {
      setBusy(false);
    }
  };

  const saveLink = async () => {
    const raw = (linkInput ?? '').trim().toUpperCase();
    setBusy(true);
    try {
      const res = await api.post<{ data: { mynameis_classroom: string | null; classroom_matches: boolean | null } }>(
        `/api/staff/ability/students/${studentId}/link-mynameis`,
        { mynameis_member_code: raw === '' ? null : raw },
      );
      setLinkInput(null);
      await queryClient.invalidateQueries({ queryKey });

      const d = res.data?.data;
      if (raw === '') {
        toast.success('mynameis 連携を解除しました');
      } else if (d?.mynameis_classroom) {
        // mynameis の教室名と児童の教室名の一致を表示(取り違え防止)
        if (d.classroom_matches) {
          toast.success(`連携しました（mynameis教室: ${d.mynameis_classroom} ／ 教室一致）`);
        } else {
          toast.error(`連携しましたが教室名が一致しません（mynameis教室: ${d.mynameis_classroom}）。メンバーIDをご確認ください。`);
        }
      } else {
        toast.success('mynameis 連携を保存しました（教室名は照会できませんでした）');
      }
    } catch (err) {
      toast.error(formatApiError(err, '連携の保存に失敗しました'));
    } finally {
      setBusy(false);
    }
  };

  const downloadPdf = async () => {
    setBusy(true);
    try {
      const res = await api.get(`/api/staff/ability/students/${studentId}/summary/pdf`, { responseType: 'blob' });
      const url = window.URL.createObjectURL(new Blob([res.data], { type: 'application/pdf' }));
      const a = document.createElement('a');
      a.href = url;
      a.download = `ability_summary_${studentId}.pdf`;
      a.click();
      window.URL.revokeObjectURL(url);
    } catch {
      toast.error('別添PDFの出力に失敗しました');
    } finally {
      setBusy(false);
    }
  };

  return (
    <Card>
      <CardHeader>
        <CardTitle>
          <div className="flex items-center justify-between">
            <span className="flex items-center gap-2">
              <MaterialIcon name="insights" size={20} />
              評価状況の全体像（別添）
            </span>
            <span className="flex gap-2">
              <Button variant="secondary" size="sm" isLoading={busy} onClick={recompute}
                leftIcon={<MaterialIcon name="autorenew" size={16} />}>
                スコアを更新
              </Button>
              {data?.has_data && (
                <Button variant="secondary" size="sm" isLoading={busy} onClick={downloadPdf}
                  leftIcon={<MaterialIcon name="picture_as_pdf" size={16} />}>
                  別添PDF
                </Button>
              )}
            </span>
          </div>
        </CardTitle>
      </CardHeader>
      <CardBody>
        {/* mynameis(本人の主観自己評価)連携 */}
        <div className="mb-4 flex flex-wrap items-center gap-2 rounded-md bg-[var(--neutral-background-2)] p-3 text-sm">
          <MaterialIcon name="link" size={16} className="text-[var(--neutral-foreground-3)]" />
          <span className="text-[var(--neutral-foreground-2)]">mynameis 連携(本人の主観自己評価)メンバーID</span>
          <input
            type="text"
            value={linkInput ?? (data?.mynameis_member_code ?? '')}
            onChange={(e) => setLinkInput(e.target.value)}
            placeholder="例 ABC12345"
            maxLength={16}
            className="w-32 rounded-md border border-[var(--neutral-stroke-1)] bg-[var(--neutral-background-1)] px-2 py-1 text-sm uppercase focus:border-[var(--brand-80)] focus:outline-none"
          />
          <Button variant="secondary" size="sm" isLoading={busy} onClick={saveLink}>保存</Button>
        </div>

        {/* 成果(outcome): スコア変化 / モニタリング達成度 / 主観×客観の一致 */}
        {outcome && (outcome.objective_delta.has || outcome.monitoring.has || outcome.agreement.has) && (
          <div className="mb-4 grid grid-cols-1 gap-2 sm:grid-cols-3">
            {outcome.objective_delta.has && (
              <div className="rounded-lg border border-[var(--neutral-stroke-2)] p-3">
                <div className="text-xs text-[var(--neutral-foreground-3)]">能力スコアの変化</div>
                <div className="mt-1 text-sm text-[var(--neutral-foreground-1)]">
                  向上 {outcome.objective_delta.improved} ／ 低下 {outcome.objective_delta.declined}
                </div>
                {outcome.objective_delta.avg_change != null && (
                  <div className="text-xs text-[var(--neutral-foreground-4)]">平均Δ {outcome.objective_delta.avg_change}（{outcome.objective_delta.scored_items}項目）</div>
                )}
              </div>
            )}
            {outcome.monitoring.has && (
              <div className="rounded-lg border border-[var(--neutral-stroke-2)] p-3">
                <div className="text-xs text-[var(--neutral-foreground-3)]">モニタリング達成度</div>
                <div className="mt-1 text-sm text-[var(--neutral-foreground-1)]">{outcome.monitoring.pct}%</div>
                <div className="text-xs text-[var(--neutral-foreground-4)]">平均 {outcome.monitoring.avg_level}/5（{outcome.monitoring.count}項目）</div>
              </div>
            )}
            {outcome.agreement.has && (
              <div className="rounded-lg border border-[var(--neutral-stroke-2)] p-3">
                <div className="text-xs text-[var(--neutral-foreground-3)]">主観×客観の一致</div>
                <div className="mt-1 text-sm text-[var(--neutral-foreground-1)]">{outcome.agreement.overall}%</div>
                <div className="text-xs text-[var(--neutral-foreground-4)]">本人の見立てと支援者の評価の近さ</div>
              </div>
            )}
          </div>
        )}

        {isLoading ? (
          <p className="py-6 text-center text-sm text-[var(--neutral-foreground-4)]">読み込み中...</p>
        ) : !data?.has_data ? (
          <p className="py-6 text-center text-sm text-[var(--neutral-foreground-4)]">
            まだ評価スコアがありません。日々の観察記録が貯まったら「スコアを更新」を押してください。
          </p>
        ) : (
          <div className="space-y-6">
            <p className="text-xs text-[var(--neutral-foreground-3)]">
              点数0〜10は個人内評価（他児比較ではなく過去の自分からの成長）。評価済み {data.counts.scored} 項目／
              要確認 {data.counts.needs_review} 項目
              {data.has_subjective && <>／本人の主観 {data.counts.subjective} 項目（青=支援者の客観・緑=本人の主観を0〜10に換算）</>}。
            </p>

            {/* レーダー(領域平均: 客観 + 本人の主観) */}
            <div style={{ width: '100%', height: 320 }}>
              <ResponsiveContainer>
                <RadarChart data={data.radar} outerRadius="75%">
                  <PolarGrid />
                  <PolarAngleAxis dataKey="domain" tick={{ fontSize: 11 }} />
                  <PolarRadiusAxis domain={[0, 10]} tick={{ fontSize: 10 }} />
                  <Radar name="客観(支援者)" dataKey="average" stroke="#3b82f6" fill="#3b82f6" fillOpacity={0.35} />
                  {data.has_subjective && (
                    <Radar name="主観(本人)" dataKey="subjective" stroke="#22c55e" fill="#22c55e" fillOpacity={0.25} />
                  )}
                </RadarChart>
              </ResponsiveContainer>
            </div>

            {/* 詳細表(領域別) */}
            {data.domains.map((d) => (
              <div key={d.domain}>
                <div className="mb-1 flex items-center justify-between border-l-4 border-[var(--brand-80)] bg-[var(--neutral-background-2)] px-2 py-1">
                  <span className="text-sm font-semibold text-[var(--neutral-foreground-1)]">{d.domain}</span>
                  <span className="text-xs text-[var(--neutral-foreground-3)]">
                    {d.average !== null ? `平均 ${d.average}` : '客観評価はまだありません'}
                  </span>
                </div>
                <table className="w-full text-sm">
                  <thead>
                    <tr className="text-left text-xs text-[var(--neutral-foreground-3)]">
                      <th className="py-1 pr-2">項目</th>
                      <th className="py-1 pr-2">段階・水準</th>
                      <th className="py-1 pr-2 text-center">客観</th>
                      <th className="py-1 pr-2 text-center">主観</th>
                      <th className="py-1">保護者向けのことば</th>
                    </tr>
                  </thead>
                  <tbody>
                    {d.items.map((it) => (
                      <tr key={it.item_id} className="border-t border-[var(--neutral-stroke-2)] align-top">
                        <td className="py-1 pr-2">
                          {it.item_name}
                          {it.needs_review && <span className="ml-1 text-xs text-[var(--status-warning-fg)]">（要確認）</span>}
                        </td>
                        <td className="py-1 pr-2 text-[var(--neutral-foreground-3)]">{it.axis_name ?? ''}</td>
                        <td className="py-1 pr-2 text-center font-semibold text-[#2563eb]">{it.score}</td>
                        <td className="py-1 pr-2 text-center font-semibold text-[#16a34a]">{it.subjective ?? '—'}</td>
                        <td className="py-1 text-[var(--neutral-foreground-2)]">{it.guardian_words ?? ''}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            ))}
          </div>
        )}
      </CardBody>
    </Card>
  );
}
