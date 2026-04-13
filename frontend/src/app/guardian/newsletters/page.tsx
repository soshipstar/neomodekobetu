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

/**
 * テキスト中のマークダウン画像 ![alt](url) を <img> に変換して React ノードを返す
 */
function renderWithPhotos(text: string): React.ReactNode[] {
  const parts: React.ReactNode[] = [];
  const regex = /!\[([^\]]*)\]\(([^)]+)\)/g;
  let lastIndex = 0;
  let match: RegExpExecArray | null;

  while ((match = regex.exec(text)) !== null) {
    // テキスト部分
    if (match.index > lastIndex) {
      parts.push(
        <span key={`t-${lastIndex}`} className="whitespace-pre-wrap">
          {text.slice(lastIndex, match.index)}
        </span>,
      );
    }
    // 画像
    parts.push(
      <a key={`img-${match.index}`} href={match[2]} target="_blank" rel="noopener noreferrer" className="block my-3">
        {/* eslint-disable-next-line @next/next/no-img-element */}
        <img
          src={match[2]}
          alt={match[1] || '写真'}
          className="w-full max-w-md rounded-xl shadow-sm border border-[var(--neutral-stroke-2)]"
        />
        {match[1] && (
          <span className="block mt-1 text-xs text-[var(--neutral-foreground-3)] italic">{match[1]}</span>
        )}
      </a>,
    );
    lastIndex = match.index + match[0].length;
  }

  if (lastIndex < text.length) {
    parts.push(
      <span key={`t-${lastIndex}`} className="whitespace-pre-wrap">
        {text.slice(lastIndex)}
      </span>,
    );
  }

  return parts;
}

/**
 * カレンダーテキストを行ごとにパースしてスタイリングする
 */
function CalendarSection({ text }: { text: string }) {
  const lines = text.split('\n').filter((l) => l.trim());
  return (
    <div className="space-y-1">
      {lines.map((line, i) => {
        const trimmed = line.trim();
        // 日付行のパターン: "4/1(火) xxx" や "1日(月) xxx"
        const dateMatch = trimmed.match(/^(\d{1,2}[/月]\d{0,2}[日]?\s*[\(（][^\)）]+[\)）])\s*(.*)/);
        if (dateMatch) {
          return (
            <div key={i} className="flex items-start gap-3 rounded-lg px-3 py-2 hover:bg-purple-50 transition-colors">
              <span className="shrink-0 inline-flex items-center justify-center rounded-lg bg-purple-100 px-2.5 py-1 text-xs font-bold text-purple-700 min-w-[90px] text-center">
                {dateMatch[1]}
              </span>
              <span className="text-sm text-[var(--neutral-foreground-1)] pt-0.5">{dateMatch[2]}</span>
            </div>
          );
        }
        // 見出し的な行（★や■で始まる）
        if (/^[★■●◆▶]/.test(trimmed)) {
          return (
            <div key={i} className="mt-2 px-3 py-1.5 text-sm font-bold text-purple-700">
              {trimmed}
            </div>
          );
        }
        // 通常行
        return (
          <div key={i} className="px-3 py-1 text-sm text-[var(--neutral-foreground-1)]">
            {trimmed}
          </div>
        );
      })}
    </div>
  );
}

function Section({
  title,
  icon,
  color,
  children,
}: {
  title: string;
  icon: string;
  color: string;
  children: React.ReactNode;
}) {
  return (
    <div className="mb-6">
      <div className={`flex items-center gap-2 rounded-t-xl ${color} px-5 py-2.5 text-sm font-bold text-white`}>
        <MaterialIcon name={icon} size={18} />
        {title}
      </div>
      <div className="rounded-b-xl border border-t-0 border-[var(--neutral-stroke-2)] bg-white px-5 py-4">
        {children}
      </div>
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
        <div className="flex gap-2 print:hidden">
          <Button variant="secondary" size="sm" onClick={() => setSelectedId(null)} leftIcon={<MaterialIcon name="arrow_back" size={16} />}>
            一覧に戻る
          </Button>
          <Button variant="secondary" size="sm" onClick={() => window.print()} leftIcon={<MaterialIcon name="print" size={16} />}>
            印刷
          </Button>
        </div>

        <div className="mx-auto max-w-3xl">
          {/* Header Card */}
          <div className="relative overflow-hidden rounded-t-2xl bg-gradient-to-br from-purple-600 via-purple-500 to-indigo-500 px-8 py-8 text-center text-white">
            <div className="absolute inset-0 opacity-10" style={{ backgroundImage: 'radial-gradient(circle at 25% 25%, white 1px, transparent 1px)', backgroundSize: '24px 24px' }} />
            <p className="relative text-3xl font-bold tracking-wide">{nl.title}</p>
            <p className="relative mt-2 text-lg font-medium opacity-90">
              {nl.year}年{nl.month}月号
            </p>
            {(nl.report_start_date || nl.schedule_start_date) && (
              <div className="relative mt-3 flex flex-wrap justify-center gap-4 text-xs opacity-80">
                {nl.report_start_date && nl.report_end_date && (
                  <span>報告: {formatShortDate(nl.report_start_date)} ~ {formatShortDate(nl.report_end_date)}</span>
                )}
                {nl.schedule_start_date && nl.schedule_end_date && (
                  <span>予定: {formatShortDate(nl.schedule_start_date)} ~ {formatShortDate(nl.schedule_end_date)}</span>
                )}
              </div>
            )}
          </div>

          <div className="rounded-b-2xl bg-[var(--neutral-background-2)] px-4 py-6 sm:px-6 shadow-lg">
            {/* Greeting */}
            {nl.greeting && (
              <div className="mb-6 rounded-xl border-l-4 border-purple-400 bg-white px-5 py-4 shadow-sm">
                <div className="text-sm leading-relaxed text-[var(--neutral-foreground-1)]">
                  {renderWithPhotos(normalizeNewlines(nl.greeting))}
                </div>
              </div>
            )}

            {/* Event Calendar */}
            {nl.event_calendar && (
              <Section title="今月の予定" icon="calendar_month" color="bg-purple-600">
                <CalendarSection text={normalizeNewlines(nl.event_calendar)} />
              </Section>
            )}

            {/* Event Details */}
            {nl.event_details && (
              <Section title="イベント詳細" icon="event_note" color="bg-indigo-500">
                <div className="text-sm leading-relaxed text-[var(--neutral-foreground-1)]">
                  {renderWithPhotos(normalizeNewlines(nl.event_details))}
                </div>
              </Section>
            )}

            {/* Weekly Reports */}
            {nl.weekly_reports && (
              <Section title="活動紹介まとめ" icon="auto_stories" color="bg-teal-600">
                <div className="text-sm leading-relaxed text-[var(--neutral-foreground-1)]">
                  {renderWithPhotos(normalizeNewlines(nl.weekly_reports))}
                </div>
              </Section>
            )}

            {/* Weekly Intro */}
            {nl.weekly_intro && (
              <Section title="曜日別活動紹介" icon="view_week" color="bg-sky-600">
                <div className="text-sm leading-relaxed text-[var(--neutral-foreground-1)]">
                  {renderWithPhotos(normalizeNewlines(nl.weekly_intro))}
                </div>
              </Section>
            )}

            {/* Event Results */}
            {nl.event_results && (
              <Section title="イベント結果報告" icon="celebration" color="bg-amber-600">
                <div className="text-sm leading-relaxed text-[var(--neutral-foreground-1)]">
                  {renderWithPhotos(normalizeNewlines(nl.event_results))}
                </div>
              </Section>
            )}

            {/* Grade Sections */}
            {(nl.elementary_report || nl.junior_report) && (
              <div className="mb-6 grid grid-cols-1 gap-4 md:grid-cols-2">
                {nl.elementary_report && (
                  <div className="rounded-xl border border-[var(--neutral-stroke-2)] bg-white shadow-sm overflow-hidden">
                    <div className="bg-green-600 px-4 py-2 text-sm font-bold text-white flex items-center gap-2">
                      <MaterialIcon name="school" size={16} />
                      小学生の活動
                    </div>
                    <div className="p-4 text-sm leading-relaxed text-[var(--neutral-foreground-1)]">
                      {renderWithPhotos(normalizeNewlines(nl.elementary_report))}
                    </div>
                  </div>
                )}
                {nl.junior_report && (
                  <div className="rounded-xl border border-[var(--neutral-stroke-2)] bg-white shadow-sm overflow-hidden">
                    <div className="bg-blue-600 px-4 py-2 text-sm font-bold text-white flex items-center gap-2">
                      <MaterialIcon name="menu_book" size={16} />
                      中高生の活動
                    </div>
                    <div className="p-4 text-sm leading-relaxed text-[var(--neutral-foreground-1)]">
                      {renderWithPhotos(normalizeNewlines(nl.junior_report))}
                    </div>
                  </div>
                )}
              </div>
            )}

            {/* Requests */}
            {nl.requests && (
              <Section title="施設からのお願い" icon="volunteer_activism" color="bg-rose-500">
                <div className="text-sm leading-relaxed text-[var(--neutral-foreground-1)]">
                  {renderWithPhotos(normalizeNewlines(nl.requests))}
                </div>
              </Section>
            )}

            {/* Others */}
            {nl.others && (
              <Section title="その他のお知らせ" icon="info" color="bg-gray-600">
                <div className="text-sm leading-relaxed text-[var(--neutral-foreground-1)]">
                  {renderWithPhotos(normalizeNewlines(nl.others))}
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
              className="group block w-full overflow-hidden rounded-2xl bg-white text-left shadow-md transition-all hover:-translate-y-1 hover:shadow-xl"
            >
              {/* Card header with gradient */}
              <div className="bg-gradient-to-r from-purple-600 to-indigo-500 px-5 py-4 text-white">
                <p className="text-lg font-bold">{nl.title}</p>
                <p className="mt-0.5 text-sm opacity-90">{nl.year}年{nl.month}月号</p>
              </div>
              <div className="px-5 py-4">
                {nl.report_start_date && nl.report_end_date && (
                  <div className="flex items-center gap-2 text-xs text-[var(--neutral-foreground-3)]">
                    <MaterialIcon name="history" size={14} />
                    報告: {formatShortDate(nl.report_start_date)} ~ {formatShortDate(nl.report_end_date)}
                  </div>
                )}
                {nl.schedule_start_date && nl.schedule_end_date && (
                  <div className="flex items-center gap-2 text-xs text-[var(--neutral-foreground-3)] mt-1">
                    <MaterialIcon name="event" size={14} />
                    予定: {formatShortDate(nl.schedule_start_date)} ~ {formatShortDate(nl.schedule_end_date)}
                  </div>
                )}
                <div className="mt-3 flex items-center justify-between border-t border-[var(--neutral-stroke-3)] pt-3">
                  <span className="text-xs text-[var(--neutral-foreground-4)]">
                    発行日: {formatDate(nl.published_at)}
                  </span>
                  <span className="text-xs font-medium text-purple-600 group-hover:underline flex items-center gap-1">
                    詳しく見る <MaterialIcon name="arrow_forward" size={12} />
                  </span>
                </div>
              </div>
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
