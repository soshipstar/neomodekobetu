'use client';

import { useState } from 'react';
import { useMutation, useQueryClient } from '@tanstack/react-query';
import api from '@/lib/api';
import { usePagination } from '@/hooks/usePagination';
import { useDebounce } from '@/hooks/useDebounce';
import { Card } from '@/components/ui/Card';
import { Button } from '@/components/ui/Button';
import { Input } from '@/components/ui/Input';
import { Badge } from '@/components/ui/Badge';
import { Modal } from '@/components/ui/Modal';
import { Table, type Column } from '@/components/ui/Table';
import { useToast } from '@/components/ui/Toast';
import { Search, Pencil, UserPlus, Clock } from 'lucide-react';
import { format } from 'date-fns';
import { ja } from 'date-fns/locale';

interface WaitingStudent {
  id: number;
  student_name: string;
  guardian_name: string;
  guardian_phone: string | null;
  desired_start_date: string | null;
  desired_weekly_count: number | null;
  waiting_notes: string | null;
  status: 'waiting' | 'contacted' | 'trial_scheduled' | 'enrolled' | 'cancelled';
  created_at: string;
  updated_at: string;
}

const statusConfig: Record<string, { text: string; variant: 'default' | 'info' | 'warning' | 'success' | 'danger' }> = {
  waiting: { text: '待機中', variant: 'default' },
  contacted: { text: '連絡済み', variant: 'info' },
  trial_scheduled: { text: '体験予定', variant: 'warning' },
  enrolled: { text: '入所済み', variant: 'success' },
  cancelled: { text: 'キャンセル', variant: 'danger' },
};

export default function AdminWaitingListPage() {
  const queryClient = useQueryClient();
  const toast = useToast();
  const [search, setSearch] = useState('');
  const [statusFilter, setStatusFilter] = useState('');
  const [editModal, setEditModal] = useState(false);
  const [editingStudent, setEditingStudent] = useState<WaitingStudent | null>(null);
  const [editForm, setEditForm] = useState({ status: '', waiting_notes: '' });
  const debouncedSearch = useDebounce(search, 300);

  const { data: students, meta, isLoading, goToPage } = usePagination<WaitingStudent>({
    endpoint: '/api/admin/waiting-list',
    queryKey: ['admin', 'waiting-list'],
    params: { search: debouncedSearch || undefined, status: statusFilter || undefined },
  });

  const updateMutation = useMutation({
    mutationFn: ({ id, data }: { id: number; data: typeof editForm }) => api.put(`/api/admin/waiting-list/${id}`, data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['admin', 'waiting-list'] });
      toast.success('更新しました');
      setEditModal(false);
    },
    onError: () => toast.error('更新に失敗しました'),
  });

  const openEdit = (student: WaitingStudent) => {
    setEditingStudent(student);
    setEditForm({ status: student.status, waiting_notes: student.waiting_notes || '' });
    setEditModal(true);
  };

  const columns: Column<WaitingStudent>[] = [
    { key: 'student_name', label: '生徒名', render: (s) => <span className="font-medium text-gray-900">{s.student_name}</span> },
    { key: 'guardian_name', label: '保護者名' },
    { key: 'guardian_phone', label: '電話番号', render: (s) => s.guardian_phone || '-' },
    {
      key: 'desired_start_date',
      label: '希望開始日',
      render: (s) => s.desired_start_date ? format(new Date(s.desired_start_date), 'yyyy/MM/dd') : '-',
    },
    {
      key: 'desired_weekly_count',
      label: '希望日数',
      render: (s) => s.desired_weekly_count ? `週${s.desired_weekly_count}日` : '-',
    },
    {
      key: 'status',
      label: 'ステータス',
      render: (s) => {
        const config = statusConfig[s.status] || statusConfig.waiting;
        return <Badge variant={config.variant}>{config.text}</Badge>;
      },
    },
    {
      key: 'created_at',
      label: '登録日',
      render: (s) => format(new Date(s.created_at), 'yyyy/MM/dd'),
    },
    {
      key: 'actions',
      label: '操作',
      render: (s) => (
        <Button variant="ghost" size="sm" onClick={() => openEdit(s)}>
          <Pencil className="h-4 w-4" />
        </Button>
      ),
    },
  ];

  const waitingCount = students.filter((s) => s.status === 'waiting').length;

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-bold text-gray-900">待機者管理</h1>
        {waitingCount > 0 && (
          <Badge variant="warning" className="text-sm px-3 py-1">
            <Clock className="mr-1 inline h-3 w-3" />
            待機中: {waitingCount}名
          </Badge>
        )}
      </div>

      <div className="flex flex-col gap-3 sm:flex-row">
        <div className="relative flex-1">
          <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
          <Input placeholder="名前で検索..." value={search} onChange={(e) => setSearch(e.target.value)} className="pl-10" />
        </div>
        <select value={statusFilter} onChange={(e) => setStatusFilter(e.target.value)} className="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm">
          <option value="">全ステータス</option>
          {Object.entries(statusConfig).map(([val, cfg]) => <option key={val} value={val}>{cfg.text}</option>)}
        </select>
      </div>

      <Table columns={columns} data={students} keyExtractor={(s) => s.id} isLoading={isLoading} currentPage={meta?.current_page} totalPages={meta?.last_page} onPageChange={goToPage} emptyMessage="待機者はいません" />

      <Modal isOpen={editModal} onClose={() => setEditModal(false)} title={`${editingStudent?.student_name || ''} - ステータス更新`}>
        <form onSubmit={(e) => { e.preventDefault(); if (editingStudent) updateMutation.mutate({ id: editingStudent.id, data: editForm }); }} className="space-y-4">
          <div>
            <label className="mb-1 block text-sm font-medium text-gray-700">ステータス</label>
            <select value={editForm.status} onChange={(e) => setEditForm({ ...editForm, status: e.target.value })} className="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
              {Object.entries(statusConfig).map(([val, cfg]) => <option key={val} value={val}>{cfg.text}</option>)}
            </select>
          </div>
          <div>
            <label className="mb-1 block text-sm font-medium text-gray-700">メモ</label>
            <textarea value={editForm.waiting_notes} onChange={(e) => setEditForm({ ...editForm, waiting_notes: e.target.value })} className="block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm" rows={4} />
          </div>
          <div className="flex justify-end gap-2 pt-2">
            <Button variant="secondary" type="button" onClick={() => setEditModal(false)}>キャンセル</Button>
            <Button type="submit" isLoading={updateMutation.isPending}>更新</Button>
          </div>
        </form>
      </Modal>
    </div>
  );
}
