'use client';

import { useMemo, useState } from 'react';
import { Badge } from '@/components/ui/Badge';
import { Input } from '@/components/ui/Input';
import { MaterialIcon } from '@/components/ui/MaterialIcon';

/**
 * 共通: ソート/検索可能な生徒一覧 (assessment-staff/-guardian, kobetsu-plan, kobetsu-monitoring 等で再利用)
 *
 * 各ページごとに「最新作成日」「アラート」の意味は異なるため、各ページが
 * StudentRow に { latest_date, has_alert, alert_label } を埋めた配列を渡す。
 *
 * デフォルトのソート順: 最新作成日 (desc) → 氏名昇順
 */

export interface StudentRow {
  id: number;
  student_name: string;
  /** ふりがな。50音順ソートの基準 (未設定時は漢字氏名でフォールバック) */
  student_name_kana?: string | null;
  /** 'preschool', 'elementary_1' などの enum 値 (Backend が返す) */
  grade_level: string | null;
  /** ページ別の「最新作成日」(ISO 文字列 / null)。例: 最新の period.start_date、最新の plan.created_date 等 */
  latest_date?: string | null;
  /** アラートの有無 (例: 期限切れ・未提出) */
  has_alert?: boolean;
  /** アラート表示ラベル (例: 「期限切れ」「未提出」) */
  alert_label?: string | null;
  /** 任意のサブテキスト (例: 「2025/03/01 ～ 2025/06/30」) */
  subtitle?: string | null;
}

export type SortKey = 'latest_desc' | 'name_asc' | 'grade_asc' | 'alert_first';

const SORT_OPTIONS: { value: SortKey; label: string }[] = [
  { value: 'latest_desc', label: '最新作成日順' },
  { value: 'name_asc',    label: '氏名順 (あいうえお)' },
  { value: 'grade_asc',   label: '学年順' },
  { value: 'alert_first', label: 'アラート優先' },
];

const GRADE_ORDER: Record<string, number> = {
  preschool: 0,
  elementary: 1, elementary_1: 1, elementary_2: 2, elementary_3: 3,
  elementary_4: 4, elementary_5: 5, elementary_6: 6,
  junior_high: 7, junior_high_1: 7, junior_high_2: 8, junior_high_3: 9,
  high_school: 10, high_school_1: 10, high_school_2: 11, high_school_3: 12,
};

const GRADE_LABEL: Record<string, string> = {
  preschool: '未就学',
  elementary: '小学生',
  elementary_1: '小1', elementary_2: '小2', elementary_3: '小3',
  elementary_4: '小4', elementary_5: '小5', elementary_6: '小6',
  junior_high: '中学生',
  junior_high_1: '中1', junior_high_2: '中2', junior_high_3: '中3',
  high_school: '高校生',
  high_school_1: '高1', high_school_2: '高2', high_school_3: '高3',
};

interface Props {
  students: StudentRow[];
  selectedId: number | null;
  onSelect: (studentId: number) => void;
  loading?: boolean;
  emptyMessage?: string;
  /**
   * 初期の並び順。指定しなければ 'latest_desc' (最新作成日順)。
   * 50音順 (name_asc) を既定にしたい画面はこれを 'name_asc' で渡す。
   * バグ報告: 「個別支援計画のところでは50音順になりませんでした」
   */
  defaultSort?: SortKey;
}

export function StudentSortableList({
  students,
  selectedId,
  onSelect,
  loading = false,
  emptyMessage = '生徒が見つかりません',
  defaultSort = 'latest_desc',
}: Props) {
  const [sortKey, setSortKey] = useState<SortKey>(defaultSort);
  const [search, setSearch] = useState('');
  const [onlyAlert, setOnlyAlert] = useState(false);

  const filteredSorted = useMemo(() => {
    let list = [...students];
    if (search.trim()) {
      const q = search.toLowerCase();
      list = list.filter((s) => s.student_name.toLowerCase().includes(q));
    }
    if (onlyAlert) {
      list = list.filter((s) => s.has_alert);
    }
    list.sort((a, b) => {
      switch (sortKey) {
        case 'name_asc': {
          // ふりがな優先で50音順。未設定は漢字氏名でフォールバック。
          const ka = (a.student_name_kana || a.student_name) ?? '';
          const kb = (b.student_name_kana || b.student_name) ?? '';
          return ka.localeCompare(kb, 'ja');
        }
        case 'grade_asc': {
          const ga = GRADE_ORDER[a.grade_level ?? ''] ?? 99;
          const gb = GRADE_ORDER[b.grade_level ?? ''] ?? 99;
          if (ga !== gb) return ga - gb;
          return a.student_name.localeCompare(b.student_name, 'ja');
        }
        case 'alert_first': {
          // has_alert=true を先に。 同じグループ内は最新日付 desc → 氏名
          const ad = (b.has_alert ? 1 : 0) - (a.has_alert ? 1 : 0);
          if (ad !== 0) return ad;
          const da = a.latest_date ? new Date(a.latest_date).getTime() : 0;
          const db = b.latest_date ? new Date(b.latest_date).getTime() : 0;
          if (da !== db) return db - da;
          return a.student_name.localeCompare(b.student_name, 'ja');
        }
        case 'latest_desc':
        default: {
          const da = a.latest_date ? new Date(a.latest_date).getTime() : 0;
          const db = b.latest_date ? new Date(b.latest_date).getTime() : 0;
          if (da !== db) return db - da;
          return a.student_name.localeCompare(b.student_name, 'ja');
        }
      }
    });
    return list;
  }, [students, search, onlyAlert, sortKey]);

  return (
    <div className="space-y-2">
      <div className="flex flex-wrap items-center gap-2">
        <div className="relative min-w-[160px] flex-1">
          <MaterialIcon name="search" size={14} className="absolute left-2 top-1/2 -translate-y-1/2 text-[var(--neutral-foreground-4)]" />
          <Input
            placeholder="氏名で検索..."
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            className="h-8 pl-7 text-xs"
          />
        </div>
        <label className="flex shrink-0 items-center gap-1 text-xs text-[var(--neutral-foreground-2)]">
          <span>並び:</span>
          <select
            value={sortKey}
            onChange={(e) => setSortKey(e.target.value as SortKey)}
            className="rounded border border-[var(--neutral-stroke-2)] bg-white px-1.5 py-1 text-xs focus:border-[var(--brand-80)] focus:outline-none"
          >
            {SORT_OPTIONS.map((o) => (
              <option key={o.value} value={o.value}>{o.label}</option>
            ))}
          </select>
        </label>
        <label className="flex shrink-0 cursor-pointer items-center gap-1 text-xs text-[var(--neutral-foreground-2)]">
          <input
            type="checkbox"
            checked={onlyAlert}
            onChange={(e) => setOnlyAlert(e.target.checked)}
            className="rounded border-[var(--neutral-stroke-2)]"
          />
          アラートのみ
        </label>
      </div>

      <div className="max-h-[480px] overflow-y-auto rounded-lg border border-[var(--neutral-stroke-2)]">
        {loading ? (
          <p className="p-4 text-center text-xs text-[var(--neutral-foreground-4)]">読み込み中...</p>
        ) : filteredSorted.length === 0 ? (
          <p className="p-4 text-center text-xs text-[var(--neutral-foreground-4)]">{emptyMessage}</p>
        ) : (
          <ul className="divide-y divide-[var(--neutral-stroke-3)]">
            {filteredSorted.map((s) => {
              const isSelected = s.id === selectedId;
              return (
                <li key={s.id}>
                  <button
                    type="button"
                    onClick={() => onSelect(s.id)}
                    className={`flex w-full items-center justify-between gap-2 px-3 py-2 text-left transition-colors ${
                      isSelected
                        ? 'bg-[var(--brand-160)]'
                        : 'hover:bg-[var(--neutral-background-3)]'
                    }`}
                  >
                    <div className="min-w-0 flex-1">
                      <div className="flex items-center gap-2">
                        <span className="truncate text-sm font-medium text-[var(--neutral-foreground-1)]">
                          {s.student_name}
                        </span>
                        {s.grade_level && (
                          <span className="rounded bg-[var(--neutral-background-3)] px-1.5 py-0.5 text-[10px] text-[var(--neutral-foreground-3)]">
                            {GRADE_LABEL[s.grade_level] ?? s.grade_level}
                          </span>
                        )}
                      </div>
                      {s.subtitle && (
                        <p className="mt-0.5 truncate text-[10px] text-[var(--neutral-foreground-4)]">
                          {s.subtitle}
                        </p>
                      )}
                    </div>
                    {s.has_alert && (
                      <Badge variant="danger" className="shrink-0 text-[10px]">
                        {s.alert_label || 'アラート'}
                      </Badge>
                    )}
                  </button>
                </li>
              );
            })}
          </ul>
        )}
      </div>
      <p className="text-right text-[10px] text-[var(--neutral-foreground-4)]">
        {filteredSorted.length} / {students.length} 件
      </p>
    </div>
  );
}

export default StudentSortableList;
