'use client';

import { useState } from 'react';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import api, { formatApiError } from '@/lib/api';
import { Card, CardHeader, CardTitle, CardBody } from '@/components/ui/Card';
import { Button } from '@/components/ui/Button';
import { MaterialIcon } from '@/components/ui/MaterialIcon';
import { useToast } from '@/components/ui/Toast';
import { formatDate } from '@/lib/utils';

interface ConsentData {
  student_id: number;
  ai_consent_learning: boolean;
  ai_consent_learning_at: string | null;
  company_aggregate: boolean;
  can_use_for_learning: boolean;
}

type Method = 'paper' | 'verbal' | 'online' | 'other';

const methodLabels: Record<Method, string> = {
  paper: '書面（同意書）',
  verbal: '口頭',
  online: 'オンライン',
  other: 'その他',
};

/**
 * 児童のAI学習利用同意（model_learning）をスタッフが代理記録するパネル。
 *
 * 学習可否は「施設の集計同意 AND 児童の学習同意」で決まるため、施設未同意時は
 * 児童が同意していても学習に使われない旨を明示する。越境(403)時は何も表示しない。
 */
export function StudentLearningConsent({ studentId }: { studentId: number }) {
  const toast = useToast();
  const queryClient = useQueryClient();
  const [busy, setBusy] = useState(false);
  const [method, setMethod] = useState<Method>('paper');
  const [note, setNote] = useState('');

  const queryKey = ['ai-consent', studentId];
  const { data, isLoading, error } = useQuery({
    queryKey,
    queryFn: async () => {
      const res = await api.get<{ data: ConsentData }>(`/api/staff/students/${studentId}/ai-consent`);
      return res.data.data;
    },
    retry: false,
  });

  const status = (error as { response?: { status?: number } } | null)?.response?.status;
  if (status === 403) return null; // アクセス権限が無い教室では非表示

  const save = async (granted: boolean) => {
    setBusy(true);
    try {
      await api.put(`/api/staff/students/${studentId}/ai-consent`, {
        granted,
        acquisition_method: granted ? method : undefined,
        note: granted && note.trim() !== '' ? note.trim() : undefined,
      });
      await queryClient.invalidateQueries({ queryKey });
      if (granted) {
        setNote('');
        toast.success('学習同意を記録しました');
      } else {
        toast.success('学習同意を撤回しました');
      }
    } catch (err) {
      toast.error(formatApiError(err, '保存に失敗しました'));
    } finally {
      setBusy(false);
    }
  };

  if (isLoading || !data) return null;

  return (
    <Card>
      <CardHeader>
        <CardTitle>
          <div className="flex items-center gap-2">
            <MaterialIcon name="model_training" size={20} />
            AI学習への利用同意
          </div>
        </CardTitle>
      </CardHeader>
      <CardBody>
        {/* 現在の状態 */}
        <div className="flex flex-wrap items-center gap-2 text-sm">
          {data.ai_consent_learning ? (
            <span className="inline-flex items-center gap-1 rounded-full bg-[var(--success-background-2,#e6f4ea)] px-3 py-1 font-medium text-[var(--success-foreground-1,#137333)]">
              <MaterialIcon name="check_circle" size={16} />
              同意済
              {data.ai_consent_learning_at && `（${formatDate(data.ai_consent_learning_at)}）`}
            </span>
          ) : (
            <span className="inline-flex items-center gap-1 rounded-full bg-[var(--neutral-background-3)] px-3 py-1 font-medium text-[var(--neutral-foreground-3)]">
              <MaterialIcon name="cancel" size={16} />
              未同意
            </span>
          )}

          {data.ai_consent_learning &&
            (data.can_use_for_learning ? (
              <span className="text-xs text-[var(--neutral-foreground-3)]">この児童の記録はAIの改善・学習に利用されます。</span>
            ) : (
              <span className="inline-flex items-center gap-1 text-xs text-[var(--warning-foreground-1,#a16207)]">
                <MaterialIcon name="warning" size={14} />
                施設が集計同意をしていないため、現在は学習に使われません（管理者の施設設定が必要）。
              </span>
            ))}
        </div>

        <p className="mt-3 text-xs text-[var(--neutral-foreground-3)]">
          保護者・本人から得た同意を、職員がここに記録します。同意がある児童の「AI生成文に対する職員の修正」を学習し、文章と支援案の精度向上に役立てます。同意はいつでも撤回できます。
        </p>

        {/* 操作 */}
        {data.ai_consent_learning ? (
          <div className="mt-4">
            <Button
              variant="secondary"
              size="sm"
              isLoading={busy}
              leftIcon={<MaterialIcon name="block" size={16} />}
              onClick={() => save(false)}
            >
              同意を撤回する
            </Button>
          </div>
        ) : (
          <div className="mt-4 space-y-3 rounded-lg border border-[var(--neutral-stroke-2)] p-4">
            <div className="flex flex-col gap-1">
              <label htmlFor="ai-consent-method" className="text-sm font-medium text-[var(--neutral-foreground-2)]">取得方法</label>
              <select
                id="ai-consent-method"
                className="rounded-md border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-1)] px-3 py-2 text-sm"
                value={method}
                onChange={(e) => setMethod(e.target.value as Method)}
              >
                {(Object.keys(methodLabels) as Method[]).map((m) => (
                  <option key={m} value={m}>
                    {methodLabels[m]}
                  </option>
                ))}
              </select>
            </div>
            <div className="flex flex-col gap-1">
              <label htmlFor="ai-consent-note" className="text-sm font-medium text-[var(--neutral-foreground-2)]">備考（任意）</label>
              <input
                id="ai-consent-note"
                type="text"
                className="rounded-md border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-1)] px-3 py-2 text-sm"
                value={note}
                onChange={(e) => setNote(e.target.value)}
                placeholder="例: 2026-06-14 面談で同意書を受領"
                maxLength={1000}
              />
            </div>
            <Button
              size="sm"
              isLoading={busy}
              leftIcon={<MaterialIcon name="check" size={16} />}
              onClick={() => save(true)}
            >
              同意を記録する
            </Button>
          </div>
        )}
      </CardBody>
    </Card>
  );
}
