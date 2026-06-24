'use client';

import { useState, useEffect } from 'react';
import { useQuery } from '@tanstack/react-query';
import api from '@/lib/api';
import { Card, CardBody } from '@/components/ui/Card';
import { SkeletonList } from '@/components/ui/Skeleton';
import { MaterialIcon } from '@/components/ui/MaterialIcon';

// ---------------------------------------------------------------------------
// Types
// ---------------------------------------------------------------------------

interface ClassroomOption {
  id: number;
  classroom_name: string;
  absence_addition_enabled: boolean;
}

interface UsageRow {
  student_id: number;
  student_name: string;
  grade_level: string | null;
  usage_days: number;
  addition_records: number;
  addition_billable: number;
}

interface UsageData {
  classroom_id: number;
  classroom_name: string;
  year: number;
  month: number;
  absence_addition_enabled: boolean;
  rows: UsageRow[];
  totals: { usage_days: number; addition_records: number; addition_billable: number };
}

// ---------------------------------------------------------------------------
// Page
// ---------------------------------------------------------------------------

const CURRENT_YEAR = new Date().getFullYear();
const YEAR_OPTIONS = [CURRENT_YEAR + 1, CURRENT_YEAR, CURRENT_YEAR - 1, CURRENT_YEAR - 2];
const MONTH_OPTIONS = Array.from({ length: 12 }, (_, i) => i + 1);

const selectClass =
  'rounded-md border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-1)] px-3 py-2 text-sm text-[var(--neutral-foreground-1)] focus:border-[var(--brand-80)] focus:outline-none';

export default function MonthlyUsagePage() {
  const [classroomId, setClassroomId] = useState<number | null>(null);
  const [year, setYear] = useState(CURRENT_YEAR);
  const [month, setMonth] = useState(new Date().getMonth() + 1);

  // 施設一覧（スコープ済 = master は全施設 / 通常管理者はアクセス可能な施設）
  const { data: classrooms = [], isLoading: loadingRooms } = useQuery({
    queryKey: ['admin', 'classroom-settings'],
    queryFn: async () => {
      const res = await api.get<{ data: ClassroomOption[] }>('/api/admin/classroom-settings');
      return Array.isArray(res.data.data) ? res.data.data : [];
    },
    retry: false,
  });

  useEffect(() => {
    if (classrooms.length > 0 && classroomId === null) {
      setClassroomId(classrooms[0].id);
    }
  }, [classrooms, classroomId]);

  const { data: usage, isLoading, isFetching } = useQuery({
    queryKey: ['admin', 'monthly-usage', classroomId, year, month],
    queryFn: async () => {
      const res = await api.get<{ data: UsageData }>('/api/admin/monthly-usage', {
        params: { classroom_id: classroomId, year, month },
      });
      return res.data.data;
    },
    enabled: classroomId !== null,
  });

  const additionEnabled = usage?.absence_addition_enabled ?? false;
  const rows = usage?.rows ?? [];
  const totals = usage?.totals;
  // 加算列の有無で列数が変わる（OFF施設は加算欄を非表示）
  const colCount = additionEnabled ? 5 : 3;

  if (loadingRooms) {
    return (
      <div className="space-y-6">
        <h1 className="text-2xl font-bold text-[var(--neutral-foreground-1)]">月次利用日数</h1>
        <SkeletonList items={4} />
      </div>
    );
  }

  if (classrooms.length === 0) {
    return (
      <div className="space-y-6">
        <h1 className="text-2xl font-bold text-[var(--neutral-foreground-1)]">月次利用日数</h1>
        <p className="text-sm text-[var(--neutral-foreground-3)]">表示可能な事業所がありません。</p>
      </div>
    );
  }

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-bold text-[var(--neutral-foreground-1)]">月次利用日数</h1>
        <p className="text-sm text-[var(--neutral-foreground-3)]">
          連絡帳の記録をもとに、児童ごとの利用日数と欠席時対応加算の回数を月単位で確認できます。
        </p>
      </div>

      {/* フィルタ */}
      <Card>
        <CardBody>
          <div className="flex flex-wrap items-end gap-4">
            {classrooms.length > 1 && (
              <label className="flex flex-col gap-1">
                <span className="text-xs font-medium text-[var(--neutral-foreground-2)]">事業所</span>
                <select
                  className={selectClass}
                  value={classroomId ?? ''}
                  onChange={(e) => setClassroomId(Number(e.target.value))}
                >
                  {classrooms.map((c) => (
                    <option key={c.id} value={c.id}>
                      {c.classroom_name}
                    </option>
                  ))}
                </select>
              </label>
            )}
            <label className="flex flex-col gap-1">
              <span className="text-xs font-medium text-[var(--neutral-foreground-2)]">年</span>
              <select className={selectClass} value={year} onChange={(e) => setYear(Number(e.target.value))}>
                {YEAR_OPTIONS.map((y) => (
                  <option key={y} value={y}>
                    {y}年
                  </option>
                ))}
              </select>
            </label>
            <label className="flex flex-col gap-1">
              <span className="text-xs font-medium text-[var(--neutral-foreground-2)]">月</span>
              <select className={selectClass} value={month} onChange={(e) => setMonth(Number(e.target.value))}>
                {MONTH_OPTIONS.map((m) => (
                  <option key={m} value={m}>
                    {m}月
                  </option>
                ))}
              </select>
            </label>
            {isFetching && (
              <span className="flex items-center gap-1 pb-2 text-xs text-[var(--neutral-foreground-3)]">
                <div className="h-3 w-3 animate-spin rounded-full border-2 border-[var(--brand-80)] border-t-transparent" />
                更新中
              </span>
            )}
          </div>
          {!additionEnabled && (
            <p className="mt-3 flex items-center gap-1.5 text-xs text-[var(--neutral-foreground-3)]">
              <MaterialIcon name="info" size={14} />
              この事業所は欠席時対応加算を「算定しない」設定のため、加算の列は表示されません（設定は「教室基本設定」で変更できます）。
            </p>
          )}
        </CardBody>
      </Card>

      {/* 一覧表 */}
      <div className="overflow-hidden rounded-lg border border-[var(--neutral-stroke-2)]">
        <div className="overflow-x-auto">
          <table className="min-w-full divide-y divide-[var(--neutral-stroke-2)]">
            <thead className="bg-[var(--neutral-background-3)]">
              <tr>
                <th className="px-4 py-2.5 text-left text-xs font-semibold text-[var(--neutral-foreground-2)]">生徒名</th>
                <th className="px-4 py-2.5 text-left text-xs font-semibold text-[var(--neutral-foreground-2)]">学年</th>
                <th className="px-4 py-2.5 text-right text-xs font-semibold text-[var(--neutral-foreground-2)]">利用日数</th>
                {additionEnabled && (
                  <>
                    <th className="px-4 py-2.5 text-right text-xs font-semibold text-[var(--neutral-foreground-2)]">
                      欠席時対応 記録件数
                    </th>
                    <th className="px-4 py-2.5 text-right text-xs font-semibold text-[var(--neutral-foreground-2)]">
                      算定回数(上限4)
                    </th>
                  </>
                )}
              </tr>
            </thead>
            <tbody className="divide-y divide-[var(--neutral-stroke-3)] bg-[var(--neutral-background-1)]">
              {isLoading ? (
                <tr>
                  <td colSpan={colCount} className="px-4 py-8 text-center text-sm text-[var(--neutral-foreground-3)]">
                    <div className="flex items-center justify-center gap-2">
                      <div className="h-4 w-4 animate-spin rounded-full border-2 border-[var(--brand-80)] border-t-transparent" />
                      読み込み中...
                    </div>
                  </td>
                </tr>
              ) : rows.length === 0 ? (
                <tr>
                  <td colSpan={colCount} className="px-4 py-8 text-center text-sm text-[var(--neutral-foreground-3)]">
                    対象の児童がいません。
                  </td>
                </tr>
              ) : (
                rows.map((r) => (
                  <tr key={r.student_id} className="hover:bg-[var(--neutral-background-3)] transition-colors">
                    <td className="px-4 py-2.5 text-sm text-[var(--neutral-foreground-1)]">{r.student_name}</td>
                    <td className="px-4 py-2.5 text-sm text-[var(--neutral-foreground-3)]">{r.grade_level ?? '—'}</td>
                    <td className="px-4 py-2.5 text-right text-sm font-medium text-[var(--neutral-foreground-1)]">
                      {r.usage_days}
                    </td>
                    {additionEnabled && (
                      <>
                        <td className="px-4 py-2.5 text-right text-sm text-[var(--neutral-foreground-1)]">
                          {r.addition_records}
                        </td>
                        <td className="px-4 py-2.5 text-right text-sm font-medium text-[var(--neutral-foreground-1)]">
                          {r.addition_billable}
                        </td>
                      </>
                    )}
                  </tr>
                ))
              )}
            </tbody>
            {totals && rows.length > 0 && (
              <tfoot className="border-t-2 border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-3)]">
                <tr>
                  <td className="px-4 py-2.5 text-sm font-semibold text-[var(--neutral-foreground-1)]" colSpan={2}>
                    合計
                  </td>
                  <td className="px-4 py-2.5 text-right text-sm font-semibold text-[var(--neutral-foreground-1)]">
                    {totals.usage_days}
                  </td>
                  {additionEnabled && (
                    <>
                      <td className="px-4 py-2.5 text-right text-sm font-semibold text-[var(--neutral-foreground-1)]">
                        {totals.addition_records}
                      </td>
                      <td className="px-4 py-2.5 text-right text-sm font-semibold text-[var(--neutral-foreground-1)]">
                        {totals.addition_billable}
                      </td>
                    </>
                  )}
                </tr>
              </tfoot>
            )}
          </table>
        </div>
      </div>
    </div>
  );
}
