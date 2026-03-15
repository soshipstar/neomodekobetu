'use client';

import { useState, type ReactNode } from 'react';
import { cn } from '@/lib/utils';
import { ChevronUp, ChevronDown, ChevronLeft, ChevronRight } from 'lucide-react';
import { Button } from './Button';

export interface Column<T> {
  key: string;
  label: string;
  sortable?: boolean;
  className?: string;
  render?: (item: T, index: number) => ReactNode;
}

interface TableProps<T> {
  columns: Column<T>[];
  data: T[];
  keyExtractor: (item: T) => string | number;
  onSort?: (key: string, direction: 'asc' | 'desc') => void;
  currentPage?: number;
  totalPages?: number;
  onPageChange?: (page: number) => void;
  isLoading?: boolean;
  emptyMessage?: string;
  className?: string;
}

// eslint-disable-next-line @typescript-eslint/no-explicit-any
export function Table<T extends Record<string, any>>({
  columns,
  data,
  keyExtractor,
  onSort,
  currentPage,
  totalPages,
  onPageChange,
  isLoading = false,
  emptyMessage = 'データがありません',
  className,
}: TableProps<T>) {
  const [sortKey, setSortKey] = useState<string>('');
  const [sortDir, setSortDir] = useState<'asc' | 'desc'>('asc');

  const handleSort = (key: string) => {
    const newDir = sortKey === key && sortDir === 'asc' ? 'desc' : 'asc';
    setSortKey(key);
    setSortDir(newDir);
    onSort?.(key, newDir);
  };

  return (
    <div className={cn('overflow-hidden rounded-lg border border-[var(--neutral-stroke-2)]', className)}>
      <div className="overflow-x-auto">
        <table className="min-w-full divide-y divide-[var(--neutral-stroke-2)]">
          <thead className="bg-[var(--neutral-background-3)]">
            <tr>
              {columns.map((col) => (
                <th
                  key={col.key}
                  className={cn(
                    'px-4 py-2.5 text-left text-xs font-semibold text-[var(--neutral-foreground-2)]',
                    col.sortable && 'cursor-pointer select-none hover:text-[var(--neutral-foreground-1)]',
                    col.className
                  )}
                  onClick={col.sortable ? () => handleSort(col.key) : undefined}
                >
                  <div className="flex items-center gap-1">
                    {col.label}
                    {col.sortable && sortKey === col.key && (
                      sortDir === 'asc' ? (
                        <ChevronUp className="h-3 w-3" />
                      ) : (
                        <ChevronDown className="h-3 w-3" />
                      )
                    )}
                  </div>
                </th>
              ))}
            </tr>
          </thead>
          <tbody className="divide-y divide-[var(--neutral-stroke-3)] bg-[var(--neutral-background-1)]">
            {isLoading ? (
              <tr>
                <td colSpan={columns.length} className="px-4 py-8 text-center text-sm text-[var(--neutral-foreground-3)]">
                  <div className="flex items-center justify-center gap-2">
                    <div className="h-4 w-4 animate-spin rounded-full border-2 border-[var(--brand-80)] border-t-transparent" />
                    読み込み中...
                  </div>
                </td>
              </tr>
            ) : data.length === 0 ? (
              <tr>
                <td colSpan={columns.length} className="px-4 py-8 text-center text-sm text-[var(--neutral-foreground-3)]">
                  {emptyMessage}
                </td>
              </tr>
            ) : (
              data.map((item, index) => (
                <tr key={keyExtractor(item)} className="hover:bg-[var(--neutral-background-3)] transition-colors">
                  {columns.map((col) => (
                    <td key={col.key} className={cn('px-4 py-2.5 text-sm text-[var(--neutral-foreground-1)]', col.className)}>
                      {col.render
                        ? col.render(item, index)
                        : (item[col.key] as ReactNode)}
                    </td>
                  ))}
                </tr>
              ))
            )}
          </tbody>
        </table>
      </div>

      {/* Pagination */}
      {totalPages && totalPages > 1 && onPageChange && (
        <div className="flex items-center justify-between border-t border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-3)] px-4 py-2.5">
          <p className="text-xs text-[var(--neutral-foreground-3)]">
            {currentPage} / {totalPages} ページ
          </p>
          <div className="flex items-center gap-2">
            <Button
              variant="outline"
              size="sm"
              onClick={() => onPageChange(currentPage! - 1)}
              disabled={currentPage === 1}
              leftIcon={<ChevronLeft className="h-4 w-4" />}
            >
              前へ
            </Button>
            <Button
              variant="outline"
              size="sm"
              onClick={() => onPageChange(currentPage! + 1)}
              disabled={currentPage === totalPages}
              rightIcon={<ChevronRight className="h-4 w-4" />}
            >
              次へ
            </Button>
          </div>
        </div>
      )}
    </div>
  );
}

export default Table;
