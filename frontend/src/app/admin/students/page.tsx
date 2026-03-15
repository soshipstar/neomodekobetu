'use client';

import { useState } from 'react';
import { usePagination } from '@/hooks/usePagination';
import { useDebounce } from '@/hooks/useDebounce';
import { Input } from '@/components/ui/Input';
import { Badge } from '@/components/ui/Badge';
import { Table, type Column } from '@/components/ui/Table';
import { SkeletonTable } from '@/components/ui/Skeleton';
import { Search } from 'lucide-react';
import type { Student } from '@/types/user';

const statusLabels: Record<string, string> = {
  active: '在籍', trial: '体験', short_term: '短期', withdrawn: '退所', waiting: '待機',
};

const statusVariants: Record<string, 'success' | 'warning' | 'danger' | 'default' | 'info'> = {
  active: 'success', trial: 'info', short_term: 'warning', withdrawn: 'danger', waiting: 'default',
};

export default function AdminStudentsPage() {
  const [search, setSearch] = useState('');
  const debouncedSearch = useDebounce(search, 300);

  const { data: students, meta, isLoading, goToPage } = usePagination<Student>({
    endpoint: '/api/admin/students',
    queryKey: ['admin', 'students'],
    params: { search: debouncedSearch || undefined },
  });

  const columns: Column<Student>[] = [
    { key: 'student_name', label: '生徒名', sortable: true, render: (s) => <span className="font-medium">{s.student_name}</span> },
    { key: 'classroom', label: '事業所', render: (s) => s.classroom?.classroom_name || '-' },
    { key: 'grade_level', label: '学年' },
    {
      key: 'status',
      label: 'ステータス',
      render: (s) => <Badge variant={statusVariants[s.status] || 'default'}>{statusLabels[s.status]}</Badge>,
    },
    { key: 'guardian', label: '保護者', render: (s) => s.guardian?.full_name || '-' },
  ];

  return (
    <div className="space-y-6">
      <h1 className="text-2xl font-bold text-gray-900">生徒管理 (管理者)</h1>

      <div className="relative">
        <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
        <Input placeholder="生徒名で検索..." value={search} onChange={(e) => setSearch(e.target.value)} className="pl-10" />
      </div>

      {isLoading ? (
        <SkeletonTable rows={8} cols={5} />
      ) : (
        <Table
          columns={columns}
          data={students}
          keyExtractor={(item) => item.id}
          currentPage={meta?.current_page}
          totalPages={meta?.last_page}
          onPageChange={goToPage}
          emptyMessage="生徒が見つかりません"
        />
      )}
    </div>
  );
}
