'use client';

import { useState } from 'react';
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

interface UnconsentedStudent {
  id: number;
  name: string;
  classroom_name: string | null;
}

/**
 * AI学習基盤 rank2: 同意の充足状況パネル(蓄積0ボトルネックの可視化)。
 *
 * 「施設の集計同意 AND 児童の学習同意」が揃って初めてAIが学習する。どちらが欠けているかを
 * 件数で見せ、同意を入れる導線(施設=教室基本設定、児童=各個別支援計画)へ誘導する。
 */
export function ConsentStatusPanel() {
  const [showList, setShowList] = useState(false);

  const { data } = useQuery({
    queryKey: ['admin', 'ai-consent-status'],
    queryFn: async () => (await api.get<{ data: ConsentStatus }>('/api/admin/ai-consent/status')).data.data,
    retry: false,
  });

  // 未同意児童の一覧は展開時にのみ取得(氏名を含むため必要時だけ)。
  const { data: unconsented } = useQuery({
    queryKey: ['admin', 'ai-consent-unconsented'],
    queryFn: async () =>
      (await api.get<{ data: { students: UnconsentedStudent[] } }>('/api/admin/ai-consent/unconsented-students')).data.data.students,
    enabled: showList,
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

        {/* 未同意児童の一覧・個別導線(rank2続) */}
        {total - consented > 0 && (
          <div className="mt-3 border-t border-[var(--neutral-stroke-3,#eee)] pt-3">
            <button
              type="button"
              onClick={() => setShowList((v) => !v)}
              className="inline-flex items-center gap-1 text-sm text-[var(--brand-foreground-1,#1a73e8)]"
              aria-expanded={showList}
            >
              <MaterialIcon name={showList ? 'expand_less' : 'expand_more'} size={18} />
              未同意の児童 {total - consented} 名を{showList ? '隠す' : '表示'}
            </button>
            {showList && (
              <ul className="mt-2 divide-y divide-[var(--neutral-stroke-3,#eee)] rounded-lg border border-[var(--neutral-stroke-2)]">
                {(unconsented ?? []).map((s) => (
                  <li key={s.id} className="flex items-center justify-between px-3 py-2 text-sm">
                    <span className="text-[var(--neutral-foreground-1)]">
                      {s.name}
                      {s.classroom_name && (
                        <span className="ml-2 text-xs text-[var(--neutral-foreground-4)]">{s.classroom_name}</span>
                      )}
                    </span>
                    <Link
                      href={`/staff/students/${s.id}/support-plan`}
                      className="inline-flex items-center gap-1 text-xs text-[var(--brand-foreground-1,#1a73e8)] underline"
                    >
                      同意を記録 <MaterialIcon name="arrow_forward" size={14} />
                    </Link>
                  </li>
                ))}
                {unconsented && unconsented.length === 0 && (
                  <li className="px-3 py-2 text-xs text-[var(--neutral-foreground-4)]">未同意の児童はいません。</li>
                )}
              </ul>
            )}
          </div>
        )}
      </CardBody>
    </Card>
  );
}
