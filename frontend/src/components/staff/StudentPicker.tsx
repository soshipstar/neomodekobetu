'use client';

import { useMemo, useState } from 'react';
import { format } from 'date-fns';
import { ja } from 'date-fns/locale';
import { Card, CardBody } from '@/components/ui/Card';
import { Input } from '@/components/ui/Input';
import { Badge } from '@/components/ui/Badge';
import { Skeleton } from '@/components/ui/Skeleton';
import { MaterialIcon } from '@/components/ui/MaterialIcon';
import { useWorkspace } from '@/hooks/useWorkspace';

/**
 * /staff/{assessment-guardian, assessment-staff, kobetsu-plan, kobetsu-monitoring} で
 * 共通して使う「生徒/利用者を選ぶ」UI。
 *
 * 旧 UI は <select> 1 個で、生徒数が多くなると探しにくく並べ替えもできなかった。
 * これを以下の機能を持つフィルタリスト UI に置き換える:
 *   1. 名前で検索 (前方/部分一致)
 *   2. ソート: 名前 / 学年 / 更新が新しい順 / 更新が古い順
 *   3. 在籍状態フィルタ (在籍/体験/短期/全て)
 *   4. 条件にマッチする件数を表示
 *   5. 選択中の生徒はハイライト
 *
 * サービス種別 (放デイ / 就労 A/B / 就労移行) で呼称が変わるため (利用者 vs 生徒)、
 * useWorkspace から terms.client を引いてラベルを動的に切り替える。
 */

export interface StudentPickerStudent {
  id: number;
  student_name: string;
  grade_level?: string | null;
  status?: string | null;
  updated_at?: string | null;
  // 任意: アセスメント/モニタリング/個別支援計画の各画面が固有で表示したい情報
  // 例: 「最終アセスメント日」「次回モニタリング期限」など
  extra_label?: string | null;
}

interface Props {
  students: StudentPickerStudent[];
  selectedStudentId: number | null;
  onSelect: (id: number) => void;
  isLoading?: boolean;
  /** ヘッダ表示。既定は「{client}を選択」 (workspace の呼称) */
  title?: string;
  /** 検索プレースホルダ。既定は「名前で検索…」 */
  searchPlaceholder?: string;
  /** リストの最大高さ。px 指定。既定 480 */
  maxHeight?: number;
}

type SortKey = 'name_asc' | 'grade_asc' | 'updated_desc' | 'updated_asc';

const STATUS_LABEL: Record<string, string> = {
  active: '在籍',
  trial: '体験',
  short_term: '短期',
  waiting: '待機',
  withdrawn: '退所',
};

const STATUS_VARIANT: Record<string, 'success' | 'info' | 'warning' | 'default' | 'danger'> = {
  active: 'success',
  trial: 'info',
  short_term: 'info',
  waiting: 'warning',
  withdrawn: 'default',
};

// 旧アプリ・新アプリで使われる学年文字列を並び順スコアにマップする。
// 旧 (粗): preschool, elementary, middle, high, other
// 新 (細): preschool, elementary_1..6, junior_high_1..3, high_school_1..3,
//          (就労系) age_18_24, age_25_34, age_35_49, age_50_plus
//          (就労移行) practice, job_search
// それ以外は末尾扱い。
const GRADE_ORDER: Record<string, number> = {
  preschool: 0,
  elementary: 10,
  elementary_1: 11, elementary_2: 12, elementary_3: 13,
  elementary_4: 14, elementary_5: 15, elementary_6: 16,
  middle: 20,
  junior_high_1: 21, junior_high_2: 22, junior_high_3: 23,
  high: 30,
  high_school_1: 31, high_school_2: 32, high_school_3: 33,
  age_18_24: 40, age_25_34: 41, age_35_49: 42, age_50_plus: 43,
  practice: 50, job_search: 51,
  other: 99,
};

const GRADE_LABEL: Record<string, string> = {
  preschool: '未就学',
  elementary: '小学生',
  elementary_1: '小1', elementary_2: '小2', elementary_3: '小3',
  elementary_4: '小4', elementary_5: '小5', elementary_6: '小6',
  middle: '中学生',
  junior_high_1: '中1', junior_high_2: '中2', junior_high_3: '中3',
  high: '高校生',
  high_school_1: '高1', high_school_2: '高2', high_school_3: '高3',
  age_18_24: '18-24歳', age_25_34: '25-34歳', age_35_49: '35-49歳', age_50_plus: '50歳以上',
  practice: '実習中', job_search: '就活中',
  other: 'その他',
};

function gradeBadgeVariant(grade?: string | null): 'success' | 'info' | 'warning' | 'default' {
  if (!grade) return 'default';
  if (grade.startsWith('preschool')) return 'info';
  if (grade.startsWith('elementary')) return 'success';
  if (grade.startsWith('junior_high') || grade === 'middle') return 'info';
  if (grade.startsWith('high_school') || grade === 'high') return 'warning';
  return 'default';
}

export function StudentPicker({
  students,
  selectedStudentId,
  onSelect,
  isLoading,
  title,
  searchPlaceholder,
  maxHeight = 480,
}: Props) {
  const { terms } = useWorkspace();
  const clientTerm = terms?.client ?? '生徒';

  const [search, setSearch] = useState('');
  const [sortKey, setSortKey] = useState<SortKey>('name_asc');
  const [statusFilter, setStatusFilter] = useState<string>('active'); // 'active' | 'all' | etc

  const filtered = useMemo(() => {
    const q = search.trim().toLowerCase();
    const out = students.filter((s) => {
      if (statusFilter !== 'all' && s.status && s.status !== statusFilter) return false;
      if (!q) return true;
      return s.student_name.toLowerCase().includes(q);
    });

    const sorted = [...out];
    switch (sortKey) {
      case 'name_asc':
        sorted.sort((a, b) => a.student_name.localeCompare(b.student_name, 'ja'));
        break;
      case 'grade_asc':
        sorted.sort((a, b) => {
          const ga = GRADE_ORDER[a.grade_level ?? 'other'] ?? 99;
          const gb = GRADE_ORDER[b.grade_level ?? 'other'] ?? 99;
          if (ga !== gb) return ga - gb;
          return a.student_name.localeCompare(b.student_name, 'ja');
        });
        break;
      case 'updated_desc':
        sorted.sort((a, b) => (b.updated_at ?? '').localeCompare(a.updated_at ?? ''));
        break;
      case 'updated_asc':
        sorted.sort((a, b) => (a.updated_at ?? '').localeCompare(b.updated_at ?? ''));
        break;
    }
    return sorted;
  }, [students, search, sortKey, statusFilter]);

  return (
    <Card>
      <CardBody>
        <label className="mb-2 block text-sm font-medium text-[var(--neutral-foreground-2)]">
          {title ?? `${clientTerm}を選択`} <span className="text-[var(--status-danger-fg)]">*</span>
        </label>

        {/* Controls: search + sort + status filter */}
        <div className="mb-3 grid gap-2 sm:grid-cols-[1fr_180px_140px]">
          <div className="relative">
            <MaterialIcon
              name="search"
              size={16}
              className="absolute left-3 top-1/2 -translate-y-1/2 text-[var(--neutral-foreground-4)]"
            />
            <Input
              placeholder={searchPlaceholder ?? '名前で検索…'}
              value={search}
              onChange={(e) => setSearch(e.target.value)}
              className="pl-9"
            />
          </div>
          <select
            value={sortKey}
            onChange={(e) => setSortKey(e.target.value as SortKey)}
            className="rounded-lg border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-1)] px-3 py-2 text-sm text-[var(--neutral-foreground-1)]"
            aria-label="並び順"
          >
            <option value="name_asc">名前順 (50音)</option>
            <option value="grade_asc">学年順</option>
            <option value="updated_desc">更新が新しい順</option>
            <option value="updated_asc">更新が古い順</option>
          </select>
          <select
            value={statusFilter}
            onChange={(e) => setStatusFilter(e.target.value)}
            className="rounded-lg border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-1)] px-3 py-2 text-sm text-[var(--neutral-foreground-1)]"
            aria-label="在籍状態"
          >
            <option value="active">在籍中のみ</option>
            <option value="trial">体験のみ</option>
            <option value="short_term">短期のみ</option>
            <option value="all">全て</option>
          </select>
        </div>

        {/* Count */}
        <p className="mb-2 text-xs text-[var(--neutral-foreground-3)]">
          {isLoading
            ? '読み込み中…'
            : `条件に合う${clientTerm}: ${filtered.length}名 / 全${students.length}名`}
        </p>

        {/* List */}
        {isLoading ? (
          <div className="space-y-2">
            {Array.from({ length: 5 }).map((_, i) => (
              <Skeleton key={i} className="h-12 w-full rounded-lg" />
            ))}
          </div>
        ) : filtered.length === 0 ? (
          <div className="rounded-lg border border-dashed border-[var(--neutral-stroke-2)] py-8 text-center text-sm text-[var(--neutral-foreground-4)]">
            <MaterialIcon name="search_off" size={32} className="mx-auto mb-2" />
            条件に合う{clientTerm}が見つかりません
          </div>
        ) : (
          <div
            className="space-y-1 overflow-y-auto rounded-lg border border-[var(--neutral-stroke-2)] p-1"
            style={{ maxHeight: `${maxHeight}px` }}
          >
            {filtered.map((s) => {
              const isSelected = s.id === selectedStudentId;
              return (
                <button
                  key={s.id}
                  type="button"
                  onClick={() => onSelect(s.id)}
                  className={`flex w-full items-center gap-3 rounded-md px-3 py-2 text-left transition-colors ${
                    isSelected
                      ? 'bg-[var(--brand-160)] text-[var(--brand-60)] ring-1 ring-[var(--brand-80)]'
                      : 'hover:bg-[var(--neutral-background-3)] text-[var(--neutral-foreground-1)]'
                  }`}
                >
                  <MaterialIcon
                    name={isSelected ? 'check_circle' : 'radio_button_unchecked'}
                    size={18}
                    className={isSelected ? 'text-[var(--brand-80)]' : 'text-[var(--neutral-foreground-4)]'}
                  />
                  <span className="flex-1 truncate text-sm font-medium">{s.student_name}</span>
                  {s.grade_level && GRADE_LABEL[s.grade_level] && (
                    <Badge variant={gradeBadgeVariant(s.grade_level)} className="text-[10px]">
                      {GRADE_LABEL[s.grade_level]}
                    </Badge>
                  )}
                  {s.status && s.status !== 'active' && STATUS_LABEL[s.status] && (
                    <Badge variant={STATUS_VARIANT[s.status] ?? 'default'} className="text-[10px]">
                      {STATUS_LABEL[s.status]}
                    </Badge>
                  )}
                  {s.extra_label && (
                    <span className="hidden sm:inline text-[10px] text-[var(--neutral-foreground-3)]">
                      {s.extra_label}
                    </span>
                  )}
                  {s.updated_at && (
                    <span className="hidden md:inline text-[10px] text-[var(--neutral-foreground-4)]">
                      更新: {format(new Date(s.updated_at), 'M/d', { locale: ja })}
                    </span>
                  )}
                </button>
              );
            })}
          </div>
        )}
      </CardBody>
    </Card>
  );
}
