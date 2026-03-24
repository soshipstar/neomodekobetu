'use client';

import { useState, useEffect, useCallback, useMemo } from 'react';
import Link from 'next/link';
import api from '@/lib/api';
import { Card, CardBody } from '@/components/ui/Card';
import { Badge } from '@/components/ui/Badge';
import { Button } from '@/components/ui/Button';
import { SkeletonList } from '@/components/ui/Skeleton';
import { format } from 'date-fns';
import { ja } from 'date-fns/locale';
import { MaterialIcon } from '@/components/ui/MaterialIcon';

// ---------------------------------------------------------------------------
// Types
// ---------------------------------------------------------------------------

interface UnconfirmedNote {
  id: number;
  integrated_content: string;
  sent_at: string;
  guardian_confirmed: boolean;
  student?: {
    id: number;
    student_name: string;
    guardian?: {
      id: number;
      full_name: string;
    };
  };
  daily_record?: {
    id: number;
    record_date: string;
    activity_name: string;
  };
}

type SortField = 'sent_at' | 'student_name';
type SortDir = 'asc' | 'desc';

/** Normalize escaped newlines from API */
function nl(text: string | null | undefined): string {
  if (!text) return '';
  return text.replace(/\\r\\n|\\n|\\r/g, '\n').replace(/\r\n|\r/g, '\n');
}

// ---------------------------------------------------------------------------
// Component
// ---------------------------------------------------------------------------

export default function UnconfirmedNotesPage() {
  const [notes, setNotes] = useState<UnconfirmedNote[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [sortField, setSortField] = useState<SortField>('sent_at');
  const [sortDir, setSortDir] = useState<SortDir>('asc'); // oldest first by default

  // =========================================================================
  // Data fetching
  // =========================================================================

  const fetchNotes = useCallback(async () => {
    setIsLoading(true);
    try {
      const res = await api.get('/api/staff/unconfirmed-notes', {
        params: { filter: 'unconfirmed' },
      });
      const items = res.data?.data;
      setNotes(Array.isArray(items) ? items : []);
    } catch {
      setNotes([]);
    } finally {
      setIsLoading(false);
    }
  }, []);

  useEffect(() => {
    fetchNotes();
  }, [fetchNotes]);

  // =========================================================================
  // Sorting
  // =========================================================================

  const toggleSort = (field: SortField) => {
    if (sortField === field) {
      setSortDir((d) => (d === 'asc' ? 'desc' : 'asc'));
    } else {
      setSortField(field);
      setSortDir(field === 'sent_at' ? 'asc' : 'asc');
    }
  };

  const sortedNotes = useMemo(() => {
    const sorted = [...notes];
    sorted.sort((a, b) => {
      let cmp = 0;
      if (sortField === 'sent_at') {
        cmp = new Date(a.sent_at).getTime() - new Date(b.sent_at).getTime();
      } else if (sortField === 'student_name') {
        const nameA = a.student?.student_name || '';
        const nameB = b.student?.student_name || '';
        cmp = nameA.localeCompare(nameB, 'ja');
      }
      return sortDir === 'asc' ? cmp : -cmp;
    });
    return sorted;
  }, [notes, sortField, sortDir]);

  // =========================================================================
  // Stats
  // =========================================================================

  const stats = useMemo(() => {
    let overThreeDays = 0;
    let oneToTwoDays = 0;
    let today = 0;
    for (const note of notes) {
      const diffDays = Math.floor(
        (Date.now() - new Date(note.sent_at).getTime()) / 86400000
      );
      if (diffDays >= 3) overThreeDays++;
      else if (diffDays >= 1) oneToTwoDays++;
      else today++;
    }
    return { total: notes.length, overThreeDays, oneToTwoDays, today };
  }, [notes]);

  // =========================================================================
  // Helpers
  // =========================================================================

  const SortIcon = ({ field }: { field: SortField }) => {
    if (sortField !== field)
      return <MaterialIcon name="swap_vert" size={16} className="h-3 w-3 opacity-40" />;
    return sortDir === 'asc' ? (
      <MaterialIcon name="arrow_upward" size={16} className="h-3 w-3" />
    ) : (
      <MaterialIcon name="arrow_downward" size={16} className="h-3 w-3" />
    );
  };

  const truncate = (text: string, maxLen: number) => {
    const cleaned = nl(text);
    return cleaned.length > maxLen ? cleaned.slice(0, maxLen) + '...' : cleaned;
  };

  // =========================================================================
  // Render
  // =========================================================================

  return (
    <div className="space-y-6">
      {/* Page header */}
      <div className="flex items-center justify-between flex-wrap gap-3">
        <div>
          <h1 className="text-2xl font-bold text-[var(--neutral-foreground-1)]">
            未確認連絡帳
          </h1>
          <p className="text-sm text-[var(--neutral-foreground-3)]">
            保護者に送信済みで未確認の連絡帳一覧
          </p>
        </div>
        <Button
          variant="outline"
          size="sm"
          leftIcon={<MaterialIcon name="refresh" size={16} className="h-4 w-4" />}
          onClick={fetchNotes}
          isLoading={isLoading}
        >
          更新
        </Button>
      </div>

      {/* Stats cards */}
      <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
        <Card>
          <CardBody>
            <div className="text-center">
              <p className="text-2xl font-bold text-[var(--brand-80)]">
                {stats.total}
              </p>
              <p className="text-xs text-[var(--neutral-foreground-3)]">
                未確認合計
              </p>
            </div>
          </CardBody>
        </Card>
        <Card>
          <CardBody>
            <div className="text-center">
              <p className="text-2xl font-bold text-[var(--status-danger-fg)]">
                {stats.overThreeDays}
              </p>
              <p className="text-xs text-[var(--neutral-foreground-3)]">
                3日以上経過
              </p>
            </div>
          </CardBody>
        </Card>
        <Card>
          <CardBody>
            <div className="text-center">
              <p className="text-2xl font-bold text-[var(--status-warning-fg)]">
                {stats.oneToTwoDays}
              </p>
              <p className="text-xs text-[var(--neutral-foreground-3)]">
                1-2日経過
              </p>
            </div>
          </CardBody>
        </Card>
        <Card>
          <CardBody>
            <div className="text-center">
              <p className="text-2xl font-bold text-[var(--status-success-fg)]">
                {stats.today}
              </p>
              <p className="text-xs text-[var(--neutral-foreground-3)]">
                本日送信
              </p>
            </div>
          </CardBody>
        </Card>
      </div>

      {/* Sort controls */}
      {notes.length > 0 && (
        <div className="flex items-center gap-2 text-xs text-[var(--neutral-foreground-3)]">
          <span>並び替え:</span>
          <button
            onClick={() => toggleSort('sent_at')}
            className={`flex items-center gap-1 rounded-md px-2 py-1 transition-colors ${
              sortField === 'sent_at'
                ? 'bg-[var(--brand-160)] text-[var(--brand-80)]'
                : 'hover:bg-[var(--neutral-background-3)]'
            }`}
          >
            送信日 <SortIcon field="sent_at" />
          </button>
          <button
            onClick={() => toggleSort('student_name')}
            className={`flex items-center gap-1 rounded-md px-2 py-1 transition-colors ${
              sortField === 'student_name'
                ? 'bg-[var(--brand-160)] text-[var(--brand-80)]'
                : 'hover:bg-[var(--neutral-background-3)]'
            }`}
          >
            生徒名 <SortIcon field="student_name" />
          </button>
        </div>
      )}

      {/* Notes list */}
      {isLoading ? (
        <SkeletonList items={5} />
      ) : sortedNotes.length > 0 ? (
        <div className="space-y-3">
          {sortedNotes.map((note) => {
            const sentDate = new Date(note.sent_at);
            const diffDays = Math.floor(
              (Date.now() - sentDate.getTime()) / 86400000
            );
            const urgency: 'danger' | 'warning' | 'info' =
              diffDays >= 3 ? 'danger' : diffDays >= 1 ? 'warning' : 'info';

            return (
              <Card
                key={note.id}
                className="transition-shadow hover:shadow-[var(--shadow-8)]"
              >
                <CardBody>
                  <div className="flex items-start justify-between gap-2 mb-2">
                    <div className="flex items-center gap-2 flex-wrap">
                      <span className="text-sm font-semibold text-[var(--neutral-foreground-1)]">
                        {note.student?.student_name || '不明'}
                      </span>
                      <Badge variant={urgency}>
                        {diffDays === 0 ? '今日送信' : `${diffDays}日経過`}
                      </Badge>
                    </div>
                    {note.daily_record && (
                      <Link
                        href={`/staff/renrakucho?date=${note.daily_record.record_date}`}
                        className="flex items-center gap-1 text-xs text-[var(--brand-80)] hover:underline shrink-0"
                      >
                        詳細
                        <MaterialIcon name="open_in_new" size={12} />
                      </Link>
                    )}
                  </div>

                  <div className="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-[var(--neutral-foreground-3)] mb-2">
                    <span>
                      送信日:{' '}
                      {format(sentDate, 'M月d日(E) HH:mm', { locale: ja })}
                    </span>
                    {note.daily_record && (
                      <span>
                        活動: {note.daily_record.activity_name} |{' '}
                        {format(
                          new Date(note.daily_record.record_date),
                          'M月d日'
                        )}
                      </span>
                    )}
                    {note.student?.guardian && (
                      <span>
                        保護者: {note.student.guardian.full_name}
                      </span>
                    )}
                  </div>

                  <div className="rounded-md bg-[var(--neutral-background-3)] p-3 text-xs text-[var(--neutral-foreground-2)] whitespace-pre-wrap max-h-24 overflow-y-auto">
                    {truncate(note.integrated_content, 200)}
                  </div>
                </CardBody>
              </Card>
            );
          })}
        </div>
      ) : (
        <Card>
          <CardBody>
            <div className="py-10 text-center">
              <MaterialIcon name="check_circle" size={40} className="mx-auto mb-3 text-[var(--status-success-fg)]" />
              <p className="text-sm font-medium text-[var(--status-success-fg)]">
                未確認の連絡帳はありません
              </p>
              <p className="mt-1 text-xs text-[var(--neutral-foreground-3)]">
                全ての送信済み連絡帳が保護者に確認されています
              </p>
            </div>
          </CardBody>
        </Card>
      )}
    </div>
  );
}
