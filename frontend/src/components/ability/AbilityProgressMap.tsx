'use client';

import { useMemo, useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import api from '@/lib/api';
import { Card, CardHeader, CardTitle, CardBody } from '@/components/ui/Card';
import { MaterialIcon } from '@/components/ui/MaterialIcon';

type CellStatus = 'not_started' | 'in_progress' | 'achieved' | 'generalized';

interface Cell {
  axis_id: string;
  score: number | null;
  status: CellStatus;
  achieved_on: string | null;
}
interface MapItem {
  item_id: string;
  item_name: string;
  current_axis: string;
  reached_axis: string | null;
  cells: Cell[];
}
interface MapDomain {
  domain: string;
  items: MapItem[];
}
interface MapAxis {
  axis_id: string;
  name: string;
}
interface MapTool {
  tool_id: string;
  axes: MapAxis[];
  domains: MapDomain[];
}
interface Growth {
  achieved_delta: number;
  score_gain_total: number;
  by_domain: { domain: string; achieved_delta: number; score_gain: number }[];
  top_items: { item_id: string; item_name: string; from: string; to: string }[];
}
interface ProgressMap {
  student: { id: number; name: string };
  since: string;
  as_of: string;
  threshold: number;
  tools: MapTool[];
  growth: Growth;
}

interface Props {
  studentId: number;
}

const TOOL_LABEL: Record<string, string> = {
  DEV: '発達(5領域)',
  ADV: '高卒標準・発展',
  WRK: '就業',
  UNV: '大学・研究',
};

const CELL_STYLE: Record<CellStatus, string> = {
  not_started: 'bg-[var(--neutral-background-3)] text-[var(--neutral-foreground-4)]',
  in_progress: 'bg-amber-200 text-amber-900',
  achieved: 'bg-emerald-500 text-white',
  generalized: 'bg-emerald-700 text-white',
};

function cellLabel(cell: Cell | undefined): string {
  if (!cell || cell.status === 'not_started') return '·';
  if (cell.status === 'generalized') return `★${cell.score ?? ''}`;
  return String(cell.score ?? '');
}

/**
 * 個別支援計画の「到達マップ」: 項目×学年帯の到達状況と半年の成長を可視化する。
 *
 * レーダー(現在地)に対し、マップは「どの段階まで到達したか(広がり)」と「期間の前進」を示す。
 * 能力評価トグル OFF の教室では progress-map API が 409 を返すため何も表示しない(自己ゲート)。
 */
export function AbilityProgressMap({ studentId }: Props) {
  const [monthsBack, setMonthsBack] = useState(6);

  const since = useMemo(() => {
    const d = new Date();
    d.setMonth(d.getMonth() - monthsBack);
    return d.toISOString().slice(0, 10);
  }, [monthsBack]);

  const { data, isLoading, error } = useQuery({
    queryKey: ['ability-progress-map', studentId, since],
    queryFn: async () => {
      const res = await api.get<{ data: ProgressMap }>(
        `/api/staff/ability/students/${studentId}/progress-map`,
        { params: { since } },
      );
      return res.data.data;
    },
    retry: false,
  });

  const status = (error as { response?: { status?: number } } | null)?.response?.status;
  if (status === 409 || status === 403) return null;
  if (isLoading || !data) return null;

  const hasAny = data.tools.some((t) => t.domains.some((d) => d.items.length > 0));
  if (!hasAny) return null;

  const g = data.growth;

  return (
    <Card>
      <CardHeader>
        <CardTitle>
          <span className="flex items-center gap-2">
            <MaterialIcon name="grid_view" size={20} />
            到達マップ(成長の見える化)
          </span>
        </CardTitle>
      </CardHeader>
      <CardBody className="space-y-4">
        {/* 期間 + 成長サマリ */}
        <div className="flex flex-wrap items-center justify-between gap-3">
          <div className="flex items-center gap-1 text-xs">
            <span className="text-[var(--neutral-foreground-3)]">期間:</span>
            {[3, 6, 12].map((m) => (
              <button
                key={m}
                type="button"
                onClick={() => setMonthsBack(m)}
                className={`rounded px-2 py-1 ${
                  monthsBack === m
                    ? 'bg-[var(--brand-background-1)] text-white'
                    : 'bg-[var(--neutral-background-3)] text-[var(--neutral-foreground-2)]'
                }`}
              >
                {m}か月
              </button>
            ))}
          </div>
          <div className="flex flex-wrap gap-4 text-sm">
            <span className="flex items-center gap-1">
              <MaterialIcon name="trending_up" size={16} className="text-emerald-600" />
              新たに到達 <strong className="text-emerald-700">+{g.achieved_delta}</strong> マス
            </span>
            <span className="text-[var(--neutral-foreground-2)]">
              スコア増分 合計 <strong>+{g.score_gain_total}</strong>
            </span>
          </div>
        </div>

        {/* 伸びた項目 */}
        {g.top_items.length > 0 && (
          <div className="rounded-lg border border-[var(--neutral-stroke-2)] p-3">
            <p className="mb-1 text-xs font-semibold text-[var(--neutral-foreground-2)]">この期間で前進した力</p>
            <ul className="flex flex-wrap gap-x-4 gap-y-1 text-xs text-[var(--neutral-foreground-2)]">
              {g.top_items.map((t) => (
                <li key={t.item_id} className="flex items-center gap-1">
                  <span className="font-medium">{t.item_name}</span>
                  <span className="text-[var(--neutral-foreground-3)]">{t.from} → {t.to}</span>
                </li>
              ))}
            </ul>
          </div>
        )}

        {/* 凡例 */}
        <div className="flex flex-wrap gap-3 text-xs text-[var(--neutral-foreground-3)]">
          <Legend cls={CELL_STYLE.not_started} label="未着手" />
          <Legend cls={CELL_STYLE.in_progress} label="途上 (1-7)" />
          <Legend cls={CELL_STYLE.achieved} label="到達 (8)" />
          <Legend cls={CELL_STYLE.generalized} label="般化 (9-10)" />
        </div>

        {/* ツールごとのグリッド */}
        {data.tools.map((tool) => (
          <div key={tool.tool_id} className="overflow-x-auto">
            <p className="mb-1 text-xs font-semibold text-[var(--neutral-foreground-2)]">
              {TOOL_LABEL[tool.tool_id] ?? tool.tool_id}
            </p>
            <table className="w-full border-collapse text-xs">
              <thead>
                <tr>
                  <th className="sticky left-0 bg-[var(--neutral-background-1)] p-1 text-left font-medium text-[var(--neutral-foreground-3)]">
                    能力項目
                  </th>
                  {tool.axes.map((a) => (
                    <th
                      key={a.axis_id}
                      className="p-1 text-center font-medium text-[var(--neutral-foreground-3)]"
                      title={a.name}
                    >
                      {a.axis_id}
                    </th>
                  ))}
                </tr>
              </thead>
              <tbody>
                {tool.domains.map((domain) => (
                  <DomainRows key={domain.domain} domain={domain} axes={tool.axes} />
                ))}
              </tbody>
            </table>
          </div>
        ))}

        <p className="text-[10px] text-[var(--neutral-foreground-4)]">
          列は発達段階({data.tools.find((t) => t.tool_id === 'DEV')?.axes.map((a) => a.name).join(' / ')})。
          数字は0〜10のスコア。到達(8)で次の段階へ進みます。
        </p>
      </CardBody>
    </Card>
  );
}

function Legend({ cls, label }: { cls: string; label: string }) {
  return (
    <span className="flex items-center gap-1">
      <span className={`inline-block h-3 w-3 rounded ${cls}`} />
      {label}
    </span>
  );
}

function DomainRows({ domain, axes }: { domain: MapDomain; axes: MapAxis[] }) {
  return (
    <>
      <tr>
        <td
          colSpan={axes.length + 1}
          className="bg-[var(--neutral-background-2)] px-1 py-0.5 text-[11px] font-semibold text-[var(--neutral-foreground-2)]"
        >
          {domain.domain}
        </td>
      </tr>
      {domain.items.map((item) => {
        const byAxis = new Map(item.cells.map((c) => [c.axis_id, c]));
        return (
          <tr key={item.item_id} className="border-t border-[var(--neutral-stroke-3)]">
            <td className="sticky left-0 bg-[var(--neutral-background-1)] py-1 pr-2 text-[var(--neutral-foreground-1)]">
              {item.item_name}
            </td>
            {axes.map((a) => {
              const cell = byAxis.get(a.axis_id);
              const st: CellStatus = cell?.status ?? 'not_started';
              return (
                <td key={a.axis_id} className="p-0.5 text-center">
                  <span
                    className={`inline-flex h-6 w-7 items-center justify-center rounded ${CELL_STYLE[st]}`}
                    title={cell?.achieved_on ? `到達: ${cell.achieved_on}` : a.name}
                  >
                    {cellLabel(cell)}
                  </span>
                </td>
              );
            })}
          </tr>
        );
      })}
    </>
  );
}
