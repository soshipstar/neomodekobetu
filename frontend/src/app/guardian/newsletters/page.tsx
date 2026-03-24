'use client';

import { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import api from '@/lib/api';
import { Card, CardBody } from '@/components/ui/Card';
import { Button } from '@/components/ui/Button';
import { SkeletonList } from '@/components/ui/Skeleton';
import { formatDate, nl as normalizeNewlines } from '@/lib/utils';
import { MaterialIcon } from '@/components/ui/MaterialIcon';

interface Newsletter {
  id: number;
  title: string;
  year: number;
  month: number;
  greeting: string | null;
  event_calendar: string | null;
  event_details: string | null;
  weekly_reports: string | null;
  weekly_intro: string | null;
  event_results: string | null;
  elementary_report: string | null;
  junior_report: string | null;
  requests: string | null;
  others: string | null;
  report_start_date: string | null;
  report_end_date: string | null;
  schedule_start_date: string | null;
  schedule_end_date: string | null;
  status: string;
  published_at: string;
}

function formatShortDate(dateStr: string | null): string {
  if (!dateStr) return '';
  const d = new Date(dateStr);
  return `${d.getFullYear()}/${d.getMonth() + 1}/${d.getDate()}`;
}

function Section({ title, icon, children }: { title: string; icon?: string; children: React.ReactNode }) {
  return (
    <div className="mb-5">
      <div className="flex items-center gap-2 rounded bg-purple-600 px-4 py-2 text-sm font-bold text-white mb-3">
        {icon && <span>{icon}</span>}
        {title}
      </div>
      {children}
    </div>
  );
}

export default function GuardianNewslettersPage() {
  const [selectedId, setSelectedId] = useState<number | null>(null);

  const { data: newslettersData, isLoading } = useQuery({
    queryKey: ['guardian', 'newsletters'],
    queryFn: async () => {
      const response = await api.get<{ data: Newsletter[] | { data: Newsletter[] } }>('/api/guardian/newsletters');
      const d = response.data.data;
      return Array.isArray(d) ? d : (d as { data: Newsletter[] }).data ?? [];
    },
  });

  const newsletters = newslettersData ?? [];

  const { data: selectedNewsletter } = useQuery({
    queryKey: ['guardian', 'newsletters', selectedId],
    queryFn: async () => {
      const response = await api.get<{ data: Newsletter }>(`/api/guardian/newsletters/${selectedId}`);
      return response.data.data;
    },
    enabled: !!selectedId,
  });

  if (isLoading) {
    return (
      <div className="space-y-6">
        <h1 className="text-2xl font-bold text-[var(--neutral-foreground-1)]">施設通信</h1>
        <SkeletonList items={4} />
      </div>
    );
  }

  // Detail view
  if (selectedId && selectedNewsletter) {
    const nl = selectedNewsletter;
    return (
      <div className="space-y-4">
        <h1 className="text-2xl font-bold text-[var(--neutral-foreground-1)]">施設通信</h1>

        <div className="flex gap-2 print:hidden">
          <Button variant="secondary" size="sm" onClick={() => setSelectedId(null)} leftIcon={<MaterialIcon name="arrow_back" size={16} />}>
            一覧に戻る
          </Button>
          <Button variant="secondary" size="sm" onClick={() => window.print()} leftIcon={<MaterialIcon name="print" size={16} />}>
            印刷
          </Button>
        </div>

        <div className="mx-auto max-w-3xl rounded-xl bg-white p-6 shadow-md">
          {/* Header */}
          <div className="mb-5 border-b-2 border-purple-600 pb-4 text-center">
            <p className="text-2xl font-bold text-[var(--neutral-foreground-1)]">{nl.title}</p>
            <p className="mt-1 text-sm font-semibold text-[var(--brand-70)]">
              {nl.year}年{nl.month}月号
            </p>
            <p className="mt-2 text-xs text-[var(--neutral-foreground-3)]">
              {nl.report_start_date && nl.report_end_date && (
                <>報告期間: {formatShortDate(nl.report_start_date)} ~ {formatShortDate(nl.report_end_date)}</>
              )}
              {nl.report_start_date && nl.schedule_start_date && ' | '}
              {nl.schedule_start_date && nl.schedule_end_date && (
                <>予定期間: {formatShortDate(nl.schedule_start_date)} ~ {formatShortDate(nl.schedule_end_date)}</>
              )}
            </p>
          </div>

          {/* Greeting */}
          {nl.greeting && (
            <div className="mb-5 rounded border-l-4 border-blue-500 bg-[var(--brand-160)] px-5 py-3">
              <p className="whitespace-pre-wrap text-sm leading-relaxed text-[var(--neutral-foreground-1)]">{normalizeNewlines(nl.greeting)}</p>
            </div>
          )}

          {/* Event Calendar */}
          {nl.event_calendar && (
            <Section title="今月の予定" icon="📅">
              <div className="rounded border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-3)] p-4">
                <pre className="whitespace-pre-wrap font-mono text-sm leading-relaxed text-[var(--neutral-foreground-1)]">{normalizeNewlines(nl.event_calendar)}</pre>
              </div>
            </Section>
          )}

          {/* Event Details */}
          {nl.event_details && (
            <Section title="イベント詳細" icon="📝">
              <p className="whitespace-pre-wrap px-2 text-sm leading-relaxed text-[var(--neutral-foreground-1)]">{normalizeNewlines(nl.event_details)}</p>
            </Section>
          )}

          {/* Weekly Reports */}
          {nl.weekly_reports && (
            <Section title="活動紹介まとめ" icon="📖">
              <p className="whitespace-pre-wrap px-2 text-sm leading-relaxed text-[var(--neutral-foreground-1)]">{normalizeNewlines(nl.weekly_reports)}</p>
            </Section>
          )}

          {/* Weekly Intro */}
          {nl.weekly_intro && (
            <Section title="曜日別活動紹介" icon="📆">
              <p className="whitespace-pre-wrap px-2 text-sm leading-relaxed text-[var(--neutral-foreground-1)]">{normalizeNewlines(nl.weekly_intro)}</p>
            </Section>
          )}

          {/* Event Results */}
          {nl.event_results && (
            <Section title="イベント結果報告" icon="🎉">
              <p className="whitespace-pre-wrap px-2 text-sm leading-relaxed text-[var(--neutral-foreground-1)]">{normalizeNewlines(nl.event_results)}</p>
            </Section>
          )}

          {/* Grade Sections */}
          {(nl.elementary_report || nl.junior_report) && (
            <div className="mb-5 grid grid-cols-1 gap-4 md:grid-cols-2">
              {nl.elementary_report && (
                <div className="rounded border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-3)] p-3">
                  <p className="mb-2 border-b-2 border-purple-600 pb-2 text-sm font-bold text-[var(--brand-70)]">
                    🎒 小学生の活動
                  </p>
                  <p className="whitespace-pre-wrap text-sm leading-relaxed text-[var(--neutral-foreground-1)]">{normalizeNewlines(nl.elementary_report)}</p>
                </div>
              )}
              {nl.junior_report && (
                <div className="rounded border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-3)] p-3">
                  <p className="mb-2 border-b-2 border-purple-600 pb-2 text-sm font-bold text-[var(--brand-70)]">
                    📚 中高生の活動
                  </p>
                  <p className="whitespace-pre-wrap text-sm leading-relaxed text-[var(--neutral-foreground-1)]">{normalizeNewlines(nl.junior_report)}</p>
                </div>
              )}
            </div>
          )}

          {/* Requests */}
          {nl.requests && (
            <Section title="施設からのお願い" icon="🙏">
              <div className="rounded border border-yellow-300 bg-yellow-50 px-4 py-3">
                <p className="whitespace-pre-wrap text-sm leading-relaxed text-[var(--neutral-foreground-1)]">{normalizeNewlines(nl.requests)}</p>
              </div>
            </Section>
          )}

          {/* Others */}
          {nl.others && (
            <Section title="その他のお知らせ" icon="📌">
              <div className="rounded border border-yellow-300 bg-yellow-50 px-4 py-3">
                <p className="whitespace-pre-wrap text-sm leading-relaxed text-[var(--neutral-foreground-1)]">{normalizeNewlines(nl.others)}</p>
              </div>
            </Section>
          )}

          {/* Footer */}
          <div className="mt-8 border-t border-[var(--neutral-stroke-2)] pt-4 text-center">
            <p className="text-xs text-[var(--neutral-foreground-3)]">
              発行日: {formatDate(nl.published_at)}
            </p>
          </div>
        </div>
      </div>
    );
  }

  // List view
  return (
    <div className="space-y-6">
      <h1 className="text-2xl font-bold text-[var(--neutral-foreground-1)]">施設通信</h1>

      {newsletters.length > 0 ? (
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
          {newsletters.map((nl) => (
            <button
              key={nl.id}
              onClick={() => setSelectedId(nl.id)}
              className="block w-full rounded-xl bg-white p-5 text-left shadow-md transition-all hover:-translate-y-1 hover:shadow-lg"
            >
              <p className="mb-2 font-semibold text-[var(--brand-70)]">{nl.title}</p>
              {nl.report_start_date && nl.report_end_date && (
                <p className="text-xs text-[var(--neutral-foreground-3)]">
                  報告: {formatShortDate(nl.report_start_date)} ~ {formatShortDate(nl.report_end_date)}
                </p>
              )}
              {nl.schedule_start_date && nl.schedule_end_date && (
                <p className="text-xs text-[var(--neutral-foreground-3)]">
                  予定: {formatShortDate(nl.schedule_start_date)} ~ {formatShortDate(nl.schedule_end_date)}
                </p>
              )}
              <p className="mt-2 border-t border-[var(--neutral-stroke-3)] pt-2 text-xs text-[var(--neutral-foreground-4)]">
                発行日: {formatDate(nl.published_at)}
              </p>
            </button>
          ))}
        </div>
      ) : (
        <Card>
          <CardBody>
            <div className="py-12 text-center">
              <MaterialIcon name="campaign" size={48} className="mx-auto text-[var(--neutral-foreground-disabled)]" />
              <p className="mt-2 text-sm text-[var(--neutral-foreground-3)]">まだ通信が発行されていません</p>
            </div>
          </CardBody>
        </Card>
      )}
    </div>
  );
}
