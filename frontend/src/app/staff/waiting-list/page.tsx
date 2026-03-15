'use client';

import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import api from '@/lib/api';
import { Card, CardHeader, CardTitle, CardBody } from '@/components/ui/Card';
import { Button } from '@/components/ui/Button';
import { Input } from '@/components/ui/Input';
import { Badge } from '@/components/ui/Badge';
import { Modal } from '@/components/ui/Modal';
import { Table, type Column } from '@/components/ui/Table';
import { SkeletonTable } from '@/components/ui/Skeleton';
import { useToast } from '@/components/ui/Toast';
import {
  Plus,
  Pencil,
  Trash2,
  Users,
  Calendar,
  Phone,
  AlertCircle,
} from 'lucide-react';
import { format } from 'date-fns';

// ---------------------------------------------------------------------------
// Types
// ---------------------------------------------------------------------------

const DAY_NAMES = ['日', '月', '火', '水', '木', '金', '土'] as const;

interface WaitingStudent {
  id: number;
  child_name: string;
  child_kana: string | null;
  birth_date: string | null;
  gender: 'male' | 'female' | 'other' | null;
  guardian_name: string;
  guardian_phone: string | null;
  guardian_email: string | null;
  school_name: string | null;
  grade: string | null;
  disability_type: string | null;
  notes: string | null;
  desired_days: boolean[];
  status: 'waiting' | 'trial' | 'enrolled' | 'cancelled';
  priority: number;
  registered_at: string;
  created_at: string;
}

interface CapacityInfo {
  day_of_week: number;
  max_capacity: number;
  current_count: number;
  is_open: boolean;
}

interface WaitingForm {
  child_name: string;
  child_kana: string;
  birth_date: string;
  gender: string;
  guardian_name: string;
  guardian_phone: string;
  guardian_email: string;
  school_name: string;
  grade: string;
  disability_type: string;
  notes: string;
  desired_sunday: boolean;
  desired_monday: boolean;
  desired_tuesday: boolean;
  desired_wednesday: boolean;
  desired_thursday: boolean;
  desired_friday: boolean;
  desired_saturday: boolean;
  priority: number;
}

const emptyForm: WaitingForm = {
  child_name: '',
  child_kana: '',
  birth_date: '',
  gender: '',
  guardian_name: '',
  guardian_phone: '',
  guardian_email: '',
  school_name: '',
  grade: '',
  disability_type: '',
  notes: '',
  desired_sunday: false,
  desired_monday: false,
  desired_tuesday: false,
  desired_wednesday: false,
  desired_thursday: false,
  desired_friday: false,
  desired_saturday: false,
  priority: 0,
};

const STATUS_MAP: Record<string, { label: string; variant: 'default' | 'warning' | 'success' | 'danger' }> = {
  waiting: { label: '待機中', variant: 'warning' },
  trial: { label: '体験予定', variant: 'default' },
  enrolled: { label: '入所済み', variant: 'success' },
  cancelled: { label: 'キャンセル', variant: 'danger' },
};

const DESIRED_DAY_KEYS = [
  'desired_sunday', 'desired_monday', 'desired_tuesday',
  'desired_wednesday', 'desired_thursday', 'desired_friday', 'desired_saturday',
] as const;

export default function WaitingListPage() {
  const queryClient = useQueryClient();
  const toast = useToast();

  const [createModal, setCreateModal] = useState(false);
  const [editModal, setEditModal] = useState(false);
  const [editingStudent, setEditingStudent] = useState<WaitingStudent | null>(null);
  const [form, setForm] = useState<WaitingForm>(emptyForm);

  // Fetch waiting list
  const { data: waitingList = [], isLoading } = useQuery({
    queryKey: ['staff', 'waiting-list'],
    queryFn: async () => {
      const res = await api.get<{ data: WaitingStudent[] }>('/api/staff/waiting-list');
      return res.data.data;
    },
  });

  // Fetch capacity info
  const { data: capacityInfo = [] } = useQuery({
    queryKey: ['staff', 'waiting-list', 'capacity'],
    queryFn: async () => {
      const res = await api.get<{ data: CapacityInfo[] }>('/api/staff/waiting-list/capacity');
      return res.data.data;
    },
  });

  // Create mutation
  const createMutation = useMutation({
    mutationFn: (data: WaitingForm) => api.post('/api/staff/waiting-list', data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['staff', 'waiting-list'] });
      toast.success('待機児童を登録しました');
      setCreateModal(false);
      setForm(emptyForm);
    },
    onError: () => toast.error('登録に失敗しました'),
  });

  // Update mutation
  const updateMutation = useMutation({
    mutationFn: ({ id, ...data }: WaitingForm & { id: number }) =>
      api.put(`/api/staff/waiting-list/${id}`, data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['staff', 'waiting-list'] });
      toast.success('情報を更新しました');
      setEditModal(false);
      setEditingStudent(null);
    },
    onError: () => toast.error('更新に失敗しました'),
  });

  // Update status mutation
  const statusMutation = useMutation({
    mutationFn: ({ id, status }: { id: number; status: string }) =>
      api.put(`/api/staff/waiting-list/${id}`, { status }),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['staff', 'waiting-list'] });
      toast.success('ステータスを更新しました');
    },
    onError: () => toast.error('更新に失敗しました'),
  });

  // Delete mutation
  const deleteMutation = useMutation({
    mutationFn: (id: number) => api.delete(`/api/staff/waiting-list/${id}`),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['staff', 'waiting-list'] });
      toast.success('待機児童を削除しました');
    },
    onError: () => toast.error('削除に失敗しました'),
  });

  const openEditModal = (student: WaitingStudent) => {
    setEditingStudent(student);
    setForm({
      child_name: student.child_name,
      child_kana: student.child_kana || '',
      birth_date: student.birth_date || '',
      gender: student.gender || '',
      guardian_name: student.guardian_name,
      guardian_phone: student.guardian_phone || '',
      guardian_email: student.guardian_email || '',
      school_name: student.school_name || '',
      grade: student.grade || '',
      disability_type: student.disability_type || '',
      notes: student.notes || '',
      desired_sunday: student.desired_days?.[0] ?? false,
      desired_monday: student.desired_days?.[1] ?? false,
      desired_tuesday: student.desired_days?.[2] ?? false,
      desired_wednesday: student.desired_days?.[3] ?? false,
      desired_thursday: student.desired_days?.[4] ?? false,
      desired_friday: student.desired_days?.[5] ?? false,
      desired_saturday: student.desired_days?.[6] ?? false,
      priority: student.priority,
    });
    setEditModal(true);
  };

  const columns: Column<WaitingStudent>[] = [
    {
      key: 'priority',
      label: '優先',
      render: (s) => <span className="text-sm font-medium text-[var(--neutral-foreground-1)]">{s.priority}</span>,
    },
    {
      key: 'child_name',
      label: '児童名',
      render: (s) => (
        <div>
          <p className="font-medium text-[var(--neutral-foreground-1)]">{s.child_name}</p>
          {s.child_kana && <p className="text-xs text-[var(--neutral-foreground-3)]">{s.child_kana}</p>}
        </div>
      ),
    },
    {
      key: 'guardian_name',
      label: '保護者',
      render: (s) => (
        <div>
          <p className="text-sm text-[var(--neutral-foreground-1)]">{s.guardian_name}</p>
          {s.guardian_phone && (
            <p className="flex items-center gap-1 text-xs text-[var(--neutral-foreground-3)]">
              <Phone className="h-3 w-3" />
              {s.guardian_phone}
            </p>
          )}
        </div>
      ),
    },
    {
      key: 'desired_days',
      label: '希望曜日',
      render: (s) => (
        <div className="flex gap-1">
          {DAY_NAMES.map((day, idx) => (
            <span
              key={day}
              className={`inline-flex h-6 w-6 items-center justify-center rounded-full text-xs font-medium ${
                s.desired_days?.[idx]
                  ? 'bg-[var(--brand-80)] text-white'
                  : 'bg-[var(--neutral-background-3)] text-[var(--neutral-foreground-3)]'
              }`}
            >
              {day}
            </span>
          ))}
        </div>
      ),
    },
    {
      key: 'status',
      label: 'ステータス',
      render: (s) => {
        const config = STATUS_MAP[s.status] || STATUS_MAP.waiting;
        return <Badge variant={config.variant}>{config.label}</Badge>;
      },
    },
    {
      key: 'registered_at',
      label: '登録日',
      render: (s) => <span className="text-sm text-[var(--neutral-foreground-2)]">{format(new Date(s.registered_at || s.created_at), 'yyyy/MM/dd')}</span>,
    },
    {
      key: 'actions',
      label: '操作',
      render: (s) => (
        <div className="flex gap-1">
          <Button variant="outline" size="sm" onClick={() => openEditModal(s)}>
            <Pencil className="h-4 w-4" />
          </Button>
          <Button
            variant="ghost"
            size="sm"
            onClick={() => { if (confirm('削除しますか？')) deleteMutation.mutate(s.id); }}
          >
            <Trash2 className="h-4 w-4 text-[var(--status-danger-fg)]" />
          </Button>
        </div>
      ),
    },
  ];

  // Capacity overview
  const capacityByDay = new Map(capacityInfo.map((c) => [c.day_of_week, c]));

  const renderForm = (onSubmit: (e: React.FormEvent) => void, isLoading: boolean) => (
    <form onSubmit={onSubmit} className="space-y-4">
      <div className="grid gap-4 md:grid-cols-2">
        <Input label="児童名" value={form.child_name} onChange={(e) => setForm({ ...form, child_name: e.target.value })} required />
        <Input label="ふりがな" value={form.child_kana} onChange={(e) => setForm({ ...form, child_kana: e.target.value })} />
        <Input label="生年月日" type="date" value={form.birth_date} onChange={(e) => setForm({ ...form, birth_date: e.target.value })} />
        <div>
          <label className="mb-1 block text-sm font-medium text-[var(--neutral-foreground-2)]">性別</label>
          <select
            className="block w-full rounded-lg border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-1)] px-3 py-2 text-sm text-[var(--neutral-foreground-1)]"
            value={form.gender}
            onChange={(e) => setForm({ ...form, gender: e.target.value })}
          >
            <option value="">選択してください</option>
            <option value="male">男</option>
            <option value="female">女</option>
            <option value="other">その他</option>
          </select>
        </div>
      </div>

      <div className="grid gap-4 md:grid-cols-2">
        <Input label="保護者名" value={form.guardian_name} onChange={(e) => setForm({ ...form, guardian_name: e.target.value })} required />
        <Input label="電話番号" value={form.guardian_phone} onChange={(e) => setForm({ ...form, guardian_phone: e.target.value })} />
        <Input label="メールアドレス" type="email" value={form.guardian_email} onChange={(e) => setForm({ ...form, guardian_email: e.target.value })} />
        <Input label="学校名" value={form.school_name} onChange={(e) => setForm({ ...form, school_name: e.target.value })} />
        <Input label="学年" value={form.grade} onChange={(e) => setForm({ ...form, grade: e.target.value })} />
        <Input label="障害種別" value={form.disability_type} onChange={(e) => setForm({ ...form, disability_type: e.target.value })} />
      </div>

      <div>
        <label className="mb-2 block text-sm font-medium text-[var(--neutral-foreground-2)]">希望曜日</label>
        <div className="flex gap-3">
          {DAY_NAMES.map((day, idx) => (
            <label key={day} className="flex items-center gap-1">
              <input
                type="checkbox"
                checked={!!form[DESIRED_DAY_KEYS[idx]]}
                onChange={(e) => setForm({ ...form, [DESIRED_DAY_KEYS[idx]]: e.target.checked })}
                className="h-4 w-4 rounded border-[var(--neutral-stroke-2)]"
              />
              <span className="text-sm text-[var(--neutral-foreground-1)]">{day}</span>
            </label>
          ))}
        </div>
      </div>

      <Input
        label="優先順位"
        type="number"
        value={String(form.priority)}
        onChange={(e) => setForm({ ...form, priority: Number(e.target.value) })}
      />

      <div>
        <label className="mb-1 block text-sm font-medium text-[var(--neutral-foreground-2)]">備考</label>
        <textarea
          value={form.notes}
          onChange={(e) => setForm({ ...form, notes: e.target.value })}
          className="block w-full rounded-lg border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-1)] px-3 py-2 text-sm text-[var(--neutral-foreground-1)]"
          rows={3}
        />
      </div>

      <div className="flex justify-end gap-2 pt-2">
        <Button variant="secondary" type="button" onClick={() => { setCreateModal(false); setEditModal(false); }}>
          キャンセル
        </Button>
        <Button type="submit" isLoading={isLoading}>
          {editingStudent ? '更新' : '登録'}
        </Button>
      </div>
    </form>
  );

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-bold text-[var(--neutral-foreground-1)]">待機児童管理</h1>
        <Button leftIcon={<Plus className="h-4 w-4" />} onClick={() => { setForm(emptyForm); setCreateModal(true); }}>
          新規登録
        </Button>
      </div>

      {/* Capacity overview */}
      {capacityInfo.length > 0 && (
        <Card>
          <CardHeader>
            <div className="flex items-center gap-2">
              <Users className="h-5 w-5 text-[var(--brand-80)]" />
              <CardTitle>曜日別定員状況</CardTitle>
            </div>
          </CardHeader>
          <CardBody>
            <div className="grid grid-cols-7 gap-2">
              {DAY_NAMES.map((day, idx) => {
                const cap = capacityByDay.get(idx);
                if (!cap || !cap.is_open) {
                  return (
                    <div key={day} className="rounded-lg bg-[var(--neutral-background-3)] p-3 text-center">
                      <p className="text-xs font-medium text-[var(--neutral-foreground-3)]">{day}</p>
                      <p className="text-xs text-[var(--neutral-foreground-3)]">休業</p>
                    </div>
                  );
                }
                const remaining = cap.max_capacity - cap.current_count;
                const isFull = remaining <= 0;
                return (
                  <div
                    key={day}
                    className={`rounded-lg p-3 text-center ${isFull ? 'bg-[rgba(var(--status-danger-rgb),0.1)]' : 'bg-[var(--neutral-background-2)]'}`}
                  >
                    <p className="text-xs font-medium text-[var(--neutral-foreground-1)]">{day}</p>
                    <p className={`text-lg font-bold ${isFull ? 'text-[var(--status-danger-fg)]' : 'text-[var(--neutral-foreground-1)]'}`}>
                      {cap.current_count}/{cap.max_capacity}
                    </p>
                    <p className={`text-xs ${isFull ? 'text-[var(--status-danger-fg)]' : 'text-[var(--status-success-fg)]'}`}>
                      {isFull ? '満員' : `残${remaining}`}
                    </p>
                  </div>
                );
              })}
            </div>
          </CardBody>
        </Card>
      )}

      {/* Waiting list table */}
      {isLoading ? (
        <SkeletonTable rows={5} cols={7} />
      ) : (
        <Table
          columns={columns}
          data={waitingList}
          keyExtractor={(s) => s.id}
          emptyMessage="待機児童はいません"
        />
      )}

      {/* Create Modal */}
      <Modal isOpen={createModal} onClose={() => setCreateModal(false)} title="待機児童を登録" size="lg">
        {renderForm((e) => { e.preventDefault(); createMutation.mutate(form); }, createMutation.isPending)}
      </Modal>

      {/* Edit Modal */}
      <Modal isOpen={editModal} onClose={() => { setEditModal(false); setEditingStudent(null); }} title="待機児童を編集" size="lg">
        {renderForm((e) => {
          e.preventDefault();
          if (editingStudent) updateMutation.mutate({ id: editingStudent.id, ...form });
        }, updateMutation.isPending)}
      </Modal>
    </div>
  );
}
