'use client';

import { useState } from 'react';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import api, { formatApiError } from '@/lib/api';
import { Card, CardHeader, CardTitle, CardBody } from '@/components/ui/Card';
import { Button } from '@/components/ui/Button';
import { MaterialIcon } from '@/components/ui/MaterialIcon';
import { useToast } from '@/components/ui/Toast';

interface ExemplarRow {
  id: number;
  document_label: string;
  section_key: string;
  change_ratio: number | null;
  has_hypothesis: boolean;
  has_result: boolean;
  recommended: boolean;
  exemplar_status: string | null;
  preview: string;
}

interface CurationStats {
  finalized_total: number;
  adopted: number;
  excluded: number;
  uncurated: number;
  recommended_uncurated: number;
}

/**
 * 見本キュレーション(管理者): 学習に使う記録を「見本採用/学習除外」に振り分ける。
 * 低品質記録が自己改善ループ(S5)を汚さないようにする要。候補が無ければ非表示。
 * 運用可視化(rank7): 進捗(採用/除外/未判定)と「採用すべき良質な未判定候補」を提示。
 */
export function ExemplarCuration() {
  const toast = useToast();
  const queryClient = useQueryClient();
  const [busyId, setBusyId] = useState<number | null>(null);

  const key = ['admin', 'exemplars'];
  const { data, error } = useQuery({
    queryKey: key,
    queryFn: async () =>
      (await api.get<{ data: { stats: CurationStats; items: ExemplarRow[] } }>('/api/admin/exemplars')).data.data,
    retry: false,
  });

  const status = (error as { response?: { status?: number } } | null)?.response?.status;
  if (status === 403 || !data || data.items.length === 0) return null;
  const rows = data.items;
  const stats = data.stats;

  const set = async (r: ExemplarRow, s: 'adopted' | 'excluded' | 'cleared') => {
    setBusyId(r.id);
    try {
      await api.post(`/api/admin/exemplars/${r.id}`, { status: s });
      await queryClient.invalidateQueries({ queryKey: key });
    } catch (err) {
      toast.error(formatApiError(err, '更新に失敗しました'));
    } finally {
      setBusyId(null);
    }
  };

  return (
    <Card>
      <CardHeader>
        <CardTitle>
          <div className="flex items-center gap-2">
            <MaterialIcon name="verified" size={20} />
            見本キュレーション（学習に使う記録の選別）
          </div>
        </CardTitle>
      </CardHeader>
      <CardBody>
        <p className="mb-3 text-xs text-[var(--neutral-foreground-3)]">
          AIの精度向上には「良い記録」だけを学習させることが重要です。見本にしたい記録は「見本採用」、学習に使いたくない記録は「除外」に。確定済みの記録のみ自動で学習対象になります。
        </p>

        {/* 運用可視化: キュレーション進捗 */}
        <div className="mb-3 flex flex-wrap gap-2 text-xs">
          <span className="rounded-md bg-[var(--brand-background-2,#e8f0fe)] px-2 py-1 text-[var(--brand-foreground-1,#1a73e8)]">見本採用 {stats.adopted}</span>
          <span className="rounded-md bg-[var(--neutral-background-3)] px-2 py-1 text-[var(--neutral-foreground-2)]">除外 {stats.excluded}</span>
          <span className="rounded-md bg-[var(--neutral-background-3)] px-2 py-1 text-[var(--neutral-foreground-2)]">未判定 {stats.uncurated}</span>
          <span className="rounded-md bg-[var(--neutral-background-3)] px-2 py-1 text-[var(--neutral-foreground-4)]">確定記録 {stats.finalized_total}</span>
        </div>
        {stats.recommended_uncurated > 0 && (
          <div className="mb-3 flex items-start gap-2 rounded-lg border border-[var(--success-stroke-1,#a6d8b9)] bg-[var(--success-background-2,#e6f4ea)] p-3 text-xs text-[var(--success-foreground-1,#137333)]">
            <MaterialIcon name="recommend" size={16} className="mt-0.5" />
            <span>因果まで書けた良質な未判定の記録が <b>{stats.recommended_uncurated} 件</b>あります。見本採用すると、AIの下書きの質が上がります（下の「推奨」から確認できます）。</span>
          </div>
        )}

        <div className="space-y-2">
          {rows.map((r) => (
            <div key={r.id} className="rounded-lg border border-[var(--neutral-stroke-2)] p-3">
              <div className="mb-1 flex flex-wrap items-center gap-2 text-xs text-[var(--neutral-foreground-3)]">
                {r.recommended && (
                  <span className="inline-flex items-center gap-0.5 rounded bg-[var(--success-background-2,#e6f4ea)] px-2 py-0.5 font-medium text-[var(--success-foreground-1,#137333)]">
                    <MaterialIcon name="recommend" size={12} /> 推奨
                  </span>
                )}
                <span className="rounded bg-[var(--neutral-background-3)] px-2 py-0.5">{r.document_label}</span>
                {r.has_hypothesis && <span className="text-[var(--success-foreground-1,#137333)]">因果あり</span>}
                {r.change_ratio != null && <span>修正量 {Math.round(r.change_ratio * 100)}%</span>}
                {r.exemplar_status === 'adopted' && <span className="font-medium text-[var(--brand-foreground-1,#1a73e8)]">見本採用中</span>}
                {r.exemplar_status === 'excluded' && <span className="font-medium text-[var(--danger-foreground-1,#b91c1c)]">学習除外中</span>}
              </div>
              <p className="mb-2 line-clamp-2 text-sm text-[var(--neutral-foreground-1)]">{r.preview}…</p>
              <div className="flex gap-2">
                <Button size="sm" variant={r.exemplar_status === 'adopted' ? 'primary' : 'secondary'}
                  isLoading={busyId === r.id} onClick={() => set(r, 'adopted')}>見本採用</Button>
                <Button size="sm" variant={r.exemplar_status === 'excluded' ? 'danger' : 'ghost'}
                  isLoading={busyId === r.id} onClick={() => set(r, 'excluded')}>学習除外</Button>
                {r.exemplar_status && (
                  <Button size="sm" variant="ghost" isLoading={busyId === r.id} onClick={() => set(r, 'cleared')}>解除</Button>
                )}
              </div>
            </div>
          ))}
        </div>
      </CardBody>
    </Card>
  );
}
