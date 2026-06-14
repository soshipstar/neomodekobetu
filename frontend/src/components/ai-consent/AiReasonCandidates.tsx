'use client';

import { useState } from 'react';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import api, { formatApiError } from '@/lib/api';
import { Card, CardHeader, CardTitle, CardBody } from '@/components/ui/Card';
import { Button } from '@/components/ui/Button';
import { MaterialIcon } from '@/components/ui/MaterialIcon';
import { useToast } from '@/components/ui/Toast';

interface Candidate { id: number; normalized_text: string; frequency: number; distinct_users: number }

/**
 * 修正理由の新カテゴリ候補の確認・昇格(§11、管理者)。動的タクソノミー。
 * 候補が無ければ何も表示しない。
 */
export function AiReasonCandidates() {
  const toast = useToast();
  const queryClient = useQueryClient();
  const [labels, setLabels] = useState<Record<number, string>>({});
  const [busyId, setBusyId] = useState<number | null>(null);

  const key = ['admin', 'edit-reason-candidates'];
  const { data: candidates = [], error } = useQuery({
    queryKey: key,
    queryFn: async () => (await api.get<{ data: Candidate[] }>('/api/admin/edit-reason-candidates')).data.data,
    retry: false,
  });

  const status = (error as { response?: { status?: number } } | null)?.response?.status;
  if (status === 403 || candidates.length === 0) return null;

  const promote = async (c: Candidate) => {
    const label = (labels[c.id] ?? c.normalized_text).trim();
    if (label === '') return;
    setBusyId(c.id);
    try {
      await api.post(`/api/admin/edit-reason-candidates/${c.id}/promote`, { code: `custom_${c.id}`, label_ja: label });
      await queryClient.invalidateQueries({ queryKey: key });
      toast.success('修正理由カテゴリに昇格しました');
    } catch (err) {
      toast.error(formatApiError(err, '昇格に失敗しました'));
    } finally {
      setBusyId(null);
    }
  };

  const reject = async (c: Candidate) => {
    setBusyId(c.id);
    try {
      await api.post(`/api/admin/edit-reason-candidates/${c.id}/reject`);
      await queryClient.invalidateQueries({ queryKey: key });
      toast.success('却下しました');
    } catch (err) {
      toast.error(formatApiError(err, '操作に失敗しました'));
    } finally {
      setBusyId(null);
    }
  };

  return (
    <Card>
      <CardHeader>
        <CardTitle>
          <div className="flex items-center gap-2">
            <MaterialIcon name="new_label" size={20} />
            修正理由の新カテゴリ候補（{candidates.length}件）
          </div>
        </CardTitle>
      </CardHeader>
      <CardBody>
        <p className="mb-3 text-xs text-[var(--neutral-foreground-3)]">
          職員の自由記述から集まった新しい観点です。承認するとチップ（選択肢）に追加され、現場で使えるようになります。
        </p>
        <div className="space-y-2">
          {candidates.map((c) => (
            <div key={c.id} className="flex flex-wrap items-center gap-2 rounded-lg border border-[var(--neutral-stroke-2)] p-2">
              <input
                type="text"
                value={labels[c.id] ?? c.normalized_text}
                onChange={(e) => setLabels({ ...labels, [c.id]: e.target.value })}
                maxLength={100}
                className="flex-1 min-w-[12rem] rounded-md border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-1)] px-3 py-1.5 text-sm"
              />
              <span className="text-xs text-[var(--neutral-foreground-3)]">{c.frequency}回 / {c.distinct_users}名</span>
              <Button size="sm" isLoading={busyId === c.id} leftIcon={<MaterialIcon name="check" size={16} />} onClick={() => promote(c)}>承認</Button>
              <Button size="sm" variant="ghost" isLoading={busyId === c.id} onClick={() => reject(c)}>却下</Button>
            </div>
          ))}
        </div>
      </CardBody>
    </Card>
  );
}
