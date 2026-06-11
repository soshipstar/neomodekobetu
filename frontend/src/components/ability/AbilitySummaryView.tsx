'use client';

import { useState } from 'react';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import {
  Radar, RadarChart, PolarGrid, PolarAngleAxis, PolarRadiusAxis, ResponsiveContainer,
} from 'recharts';
import api from '@/lib/api';
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
  evaluated_on: string;
}
interface SummaryDomain {
  domain: string;
  tool_id: string;
  average: number;
  items: SummaryItem[];
}
interface Summary {
  has_data: boolean;
  domains: SummaryDomain[];
  radar: { domain: string; average: number }[];
  counts: { scored: number; needs_review: number };
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

  const queryKey = ['ability-summary', studentId];
  const { data, isLoading, error } = useQuery({
    queryKey,
    queryFn: async () => {
      const res = await api.get<{ data: Summary }>(`/api/staff/ability/students/${studentId}/summary`);
      return res.data.data;
    },
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
              要確認 {data.counts.needs_review} 項目。
            </p>

            {/* レーダー(領域平均) */}
            <div style={{ width: '100%', height: 320 }}>
              <ResponsiveContainer>
                <RadarChart data={data.radar} outerRadius="75%">
                  <PolarGrid />
                  <PolarAngleAxis dataKey="domain" tick={{ fontSize: 11 }} />
                  <PolarRadiusAxis domain={[0, 10]} tick={{ fontSize: 10 }} />
                  <Radar name="平均" dataKey="average" stroke="#3b82f6" fill="#3b82f6" fillOpacity={0.4} />
                </RadarChart>
              </ResponsiveContainer>
            </div>

            {/* 詳細表(領域別) */}
            {data.domains.map((d) => (
              <div key={d.domain}>
                <div className="mb-1 flex items-center justify-between border-l-4 border-[var(--brand-80)] bg-[var(--neutral-background-2)] px-2 py-1">
                  <span className="text-sm font-semibold text-[var(--neutral-foreground-1)]">{d.domain}</span>
                  <span className="text-xs text-[var(--neutral-foreground-3)]">平均 {d.average}</span>
                </div>
                <table className="w-full text-sm">
                  <thead>
                    <tr className="text-left text-xs text-[var(--neutral-foreground-3)]">
                      <th className="py-1 pr-2">項目</th>
                      <th className="py-1 pr-2">段階・水準</th>
                      <th className="py-1 pr-2 text-center">点数</th>
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
                        <td className="py-1 pr-2 text-center font-semibold">{it.score}</td>
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
