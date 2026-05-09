'use client';

import type { StrengthsSummary } from '@/types/monitoring';

/**
 * 連絡帳の強み(才能)チェックを期間集計したサマリーを読み取り専用で表示する。
 * モニタリング表示と個別支援計画の参照表示で共用する想定。
 *
 * 旧アプリ syuro26 の formatAfterSchoolMetrics に相当する内容を、
 * テキストではなく表形式で見せる。
 */
export function StrengthsSummaryView({
  summary,
  title = '強み（才能）チェック サマリー',
}: {
  summary: StrengthsSummary | null | undefined;
  title?: string;
}) {
  if (!summary || !summary.trends || summary.trends.length === 0) {
    return (
      <div className="rounded-md border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-3)] p-3 text-xs text-[var(--neutral-foreground-3)]">
        対象期間（{summary?.from || '-'} 〜 {summary?.to || '-'}）に強みチェックの記録がありません。
      </div>
    );
  }

  const growing = summary.trends.filter((t) => t.trend === 'up').sort((a, b) => b.change - a.change);
  const declining = summary.trends.filter((t) => t.trend === 'down').sort((a, b) => a.change - b.change);
  const monthKeys = Array.from(
    new Set(summary.trends.flatMap((t) => Object.keys(t.monthly_averages))),
  ).sort();

  return (
    <div className="rounded-md border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-3)] p-3 text-xs">
      <div className="mb-2 flex items-center justify-between">
        <p className="font-semibold text-[var(--neutral-foreground-2)]">{title}</p>
        <p className="text-[var(--neutral-foreground-3)]">
          {summary.from} 〜 {summary.to}（{summary.record_count}件）
        </p>
      </div>

      <div className="overflow-x-auto">
        <table className="w-full table-fixed border-collapse text-left">
          <thead>
            <tr className="border-b border-[var(--neutral-stroke-2)] text-[var(--neutral-foreground-3)]">
              <th className="w-40 py-1 pr-2 font-medium">項目</th>
              <th className="w-20 py-1 pr-2 font-medium">領域</th>
              <th className="w-16 py-1 pr-2 font-medium text-right">平均</th>
              <th className="w-20 py-1 pr-2 font-medium text-right">推移</th>
              {monthKeys.map((m) => (
                <th key={m} className="w-16 py-1 pr-2 font-medium text-right">
                  {m}
                </th>
              ))}
            </tr>
          </thead>
          <tbody>
            {summary.trends.map((t) => {
              const arrow = t.trend === 'up' ? '↑' : t.trend === 'down' ? '↓' : '→';
              const sign = t.change >= 0 ? '+' : '';
              const trendColor =
                t.trend === 'up'
                  ? 'text-[var(--status-success-fg)]'
                  : t.trend === 'down'
                    ? 'text-[var(--status-danger-fg)]'
                    : 'text-[var(--neutral-foreground-3)]';
              return (
                <tr key={t.label} className="border-b border-[var(--neutral-stroke-3)]">
                  <td className="py-1 pr-2 text-[var(--neutral-foreground-1)]">{t.label}</td>
                  <td className="py-1 pr-2 text-[var(--neutral-foreground-3)]">{t.domain ?? '-'}</td>
                  <td className="py-1 pr-2 text-right font-semibold text-[var(--neutral-foreground-1)]">
                    {t.overall_average}
                  </td>
                  <td className={`py-1 pr-2 text-right font-semibold ${trendColor}`}>
                    {arrow} {sign}
                    {t.change}
                  </td>
                  {monthKeys.map((m) => (
                    <td key={m} className="py-1 pr-2 text-right tabular-nums text-[var(--neutral-foreground-2)]">
                      {t.monthly_averages[m] != null ? t.monthly_averages[m] : '-'}
                    </td>
                  ))}
                </tr>
              );
            })}
          </tbody>
        </table>
      </div>

      {(growing.length > 0 || declining.length > 0) && (
        <div className="mt-2 space-y-0.5 text-[var(--neutral-foreground-2)]">
          {growing.length > 0 && (
            <p>
              <span className="font-semibold">★成長:</span>{' '}
              {growing.map((t) => `${t.label}(+${t.change})`).join('、')}
            </p>
          )}
          {declining.length > 0 && (
            <p>
              <span className="font-semibold">※低下:</span>{' '}
              {declining.map((t) => `${t.label}(${t.change})`).join('、')}
            </p>
          )}
        </div>
      )}
    </div>
  );
}
