'use client';

import { useState } from 'react';
import Link from 'next/link';
import { usePagination } from '@/hooks/usePagination';
import { useDebounce } from '@/hooks/useDebounce';
import { Input } from '@/components/ui/Input';
import { Badge } from '@/components/ui/Badge';
import { Table, type Column } from '@/components/ui/Table';
import { Search } from 'lucide-react';
import type { Student } from '@/types/user';

const statusLabels: Record<string, string> = {
  active: '在籍',
  trial: '体験',
  short_term: '短期',
  withdrawn: '退所',
  waiting: '待機',
};

const statusVariants: Record<string, 'success' | 'warning' | 'danger' | 'default' | 'info'> = {
  active: 'success',
  trial: 'info',
  short_term: 'warning',
  withdrawn: 'danger',
  waiting: 'default',
};

const gradeLabels: Record<string, string> = {
  preschool: '未就学',
  elementary: '小学生',
  middle: '中学生',
  high: '高校生',
  other: 'その他',
};

export default function StudentsPage() {
  const [search, setSearch] = useState('');
  const [statusFilter, setStatusFilter] = useState<string>('');
  const debouncedSearch = useDebounce(search, 300);

  const {
    data: students,
    meta,
    isLoading,
    goToPage,
  } = usePagination<Student>({
    endpoint: '/api/staff/students',
    queryKey: ['staff', 'students'],
    params: {
      search: debouncedSearch || undefined,
      status: statusFilter || undefined,
    },
  });

  const columns: Column<Student>[] = [
    {
      key: 'student_name',
      label: '生徒名',
      sortable: true,
      render: (student) => (
        <Link
          href={`/staff/students/${student.id}`}
          className="font-medium text-[var(--brand-80)] hover:text-[var(--brand-80)]"
        >
          {student.student_name}
        </Link>
      ),
    },
    {
      key: 'grade_level',
      label: '学年',
      render: (student) => gradeLabels[student.grade_level] || student.grade_level,
    },
    {
      key: 'status',
      label: 'ステータス',
      render: (student) => (
        <Badge variant={statusVariants[student.status] || 'default'}>
          {statusLabels[student.status] || student.status}
        </Badge>
      ),
    },
    {
      key: 'guardian',
      label: '保護者',
      render: (student) => student.guardian?.full_name || '-',
    },
  ];

  return (
    <div className="space-y-4">
      <h1 className="text-2xl font-bold text-[var(--neutral-foreground-1)]">生徒管理</h1>

      {/* Filters */}
      <div className="flex flex-col gap-3 sm:flex-row">
        <div className="relative flex-1">
          <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-[var(--neutral-foreground-4)]" />
          <Input
            placeholder="生徒名で検索..."
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            className="pl-10"
          />
        </div>
        <select
          value={statusFilter}
          onChange={(e) => setStatusFilter(e.target.value)}
          className="rounded-lg border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-1)] px-3 py-2 text-sm"
        >
          <option value="">全ステータス</option>
          <option value="active">在籍</option>
          <option value="trial">体験</option>
          <option value="short_term">短期</option>
          <option value="waiting">待機</option>
          <option value="withdrawn">退所</option>
        </select>
      </div>

      {/* Table */}
      <Table
        columns={columns}
        data={students}
        keyExtractor={(item) => item.id}
        isLoading={isLoading}
        currentPage={meta?.current_page}
        totalPages={meta?.last_page}
        onPageChange={goToPage}
        emptyMessage="生徒が見つかりません"
      />
    </div>
  );
}
