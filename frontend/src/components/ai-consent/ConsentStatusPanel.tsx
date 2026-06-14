'use client';

import Link from 'next/link';
import { useQuery } from '@tanstack/react-query';
import api from '@/lib/api';
import { Card, CardHeader, CardTitle, CardBody } from '@/components/ui/Card';
import { MaterialIcon } from '@/components/ui/MaterialIcon';

interface ConsentStatus {
  company_id: number;
  company_name: string;
  ai_consent_aggregate: boolean;
  ai_consent_aggregate_at: string | null;
  students_total: number;
  students_consented: number;
  students_learning_active: number;
}

/**
 * AI学習基盤 rank2: 同意の充足状況パネル(蓄積0ボトルネックの可視化)。
 *
 * 「施設の集計同意 AND 児童の学習同意」が揃って初めてAIが学習する。どちらが欠けているかを
 * 件数で見せ、同意を入れる導線(施設=教室基本設定、児童=各個別支援計画)へ誘導する。
 */
export function ConsentStatusPanel() {
  const { data } = useQuery({
    queryKey: ['admin', 'ai-consent-status'],
    queryFn: async () => (await api.get<{ data: ConsentStatus }>('/api/admin/ai-consent/status')).data.data,
    retry: false,
  });

  if (!data) return null;

  const aggregate = data.ai_consent_aggregate;
  const active = data.students_learning_active;
  const total = data.students_total;
  const consented = data.students_consented;
  const learningOn = aggregate && active > 0;

  return (
    <Card>
      <CardHeader>
        <CardTitle>
          <div className="flex items-center gap-2">
            <MaterialIcon name="verified_user" size={20} />
            AI学習の同意状況
          </div>
        </CardTitle>
      </CardHeader>
      <CardBody>
        {!learningOn && (
          <div className="mb-3 flex items-start gap-2 rounded-lg border border-[var(--warning-stroke-1,#f0c36d)] bg-[var(--warning-background-2,#fdf6e3)] p-3 text-xs text-[var(--warning-foreground-1,#a16207)]">
            <MaterialIcon name="info" size={16} className="mt-0.5" />
            <span>
              現在、この施設の記録はAIの学習に使われていません。AIが「入力するほど修正が減る」状態になるには、
              <b>施設の集計同意</b>と<b>児童ごとの学習同意</b>の両方が必要です。
            </span>
          </div>
        )}

        <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
          {/* 施設の集計同意 */}
          <div className="rounded-lg border border-[var(--neutral-stroke-2)] p-3">
            <div className="text-xs text-[var(--neutral-foreground-3)]">施設の集計同意</div>
            <div className="mt-1 flex items-center gap-2">
              {aggregate ? (
                <span className="inline-flex items-center gap-1 rounded-full bg-[var(--success-background-2,#e6f4ea)] px-2 py-0.5 text-sm font-medium text-[var(--success-foreground-1,#137333)]">
                  <MaterialIcon name="check_circle" size={14} /> ON
                </span>
              ) : (
                <span className="inline-flex items-center gap-1 rounded-full bg-[var(--neutral-background-3)] px-2 py-0.5 text-sm font-medium text-[var(--neutral-foreground-3)]">
                  <MaterialIcon name="cancel" size={14} /> OFF
                </span>
              )}
            </div>
            {!aggregate && (
              <Link href="/admin/settings" className="mt-2 inline-block text-xs text-[var(--brand-foreground-1,#1a73e8)] underline">
                教室基本設定でONにする →
              </Link>
            )}
          </div>

          {/* 学習同意のある児童 */}
          <div className="rounded-lg border border-[var(--neutral-stroke-2)] p-3">
            <div className="text-xs text-[var(--neutral-foreground-3)]">学習同意のある児童</div>
            <div className="mt-1 text-sm font-medium text-[var(--neutral-foreground-1)]">
              {consented} <span className="text-xs font-normal text-[var(--neutral-foreground-3)]">/ {total} 名</span>
            </div>
            <div className="mt-2 text-xs text-[var(--neutral-foreground-4)]">各児童の個別支援計画で記録できます</div>
          </div>

          {/* 実際に学習対象の児童(AND成立) */}
          <div className="rounded-lg border border-[var(--neutral-stroke-2)] p-3">
            <div className="text-xs text-[var(--neutral-foreground-3)]">実際に学習対象の児童</div>
            <div className="mt-1 text-sm font-medium text-[var(--neutral-foreground-1)]">{active} 名</div>
            <div className="mt-2 text-xs text-[var(--neutral-foreground-4)]">
              {aggregate ? '施設同意 × 児童同意の成立数' : '施設の集計同意がOFFのため0'}
            </div>
          </div>
        </div>
      </CardBody>
    </Card>
  );
}
