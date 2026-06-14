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
  exemplar_status: string | null;
  preview: string;
}

/**
 * 見本キュレーション(管理者): 学習に使う記録を「見本採用/学習除外」に振り分ける。
 * 低品質記録が自己改善ループ(S5)を汚さないようにする要。候補が無ければ非表示。
 */
export function ExemplarCuration() {
  const toast = useToast();
  const queryClient = useQueryClient();
  const [busyId, setBusyId] = useState<number | null>(null);

  const key = ['admin', 'exemplars'];
  const { data: rows = [], error } = useQuery({
    queryKey: key,
    queryFn: async () => (await api.get<{ data: ExemplarRow[] }>('/api/admin/exemplars')).data.data,
    retry: false,
  });

  const status = (error as { response?: { status?: number } } | null)?.response?.status;
  if (status === 403 || rows.length === 0) return null;

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
        <div className="space-y-2">
          {rows.map((r) => (
            <div key={r.id} className="rounded-lg border border-[var(--neutral-stroke-2)] p-3">
              <div className="mb-1 flex flex-wrap items-center gap-2 text-xs text-[var(--neutral-foreground-3)]">
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
