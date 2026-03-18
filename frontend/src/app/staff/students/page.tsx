'use client';

import { useState, useCallback } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import Link from 'next/link';
import api from '@/lib/api';
import { Card, CardHeader, CardTitle, CardBody } from '@/components/ui/Card';
import { Button } from '@/components/ui/Button';
import { Input } from '@/components/ui/Input';
import { Badge } from '@/components/ui/Badge';
import { Modal } from '@/components/ui/Modal';
import { Skeleton } from '@/components/ui/Skeleton';
import { useToast } from '@/components/ui/Toast';
import { useDebounce } from '@/hooks/useDebounce';
import {
  Plus,
  Pencil,
  Search,
  UserX,
  Trash2,
  Printer,
  Calendar,
  Info,
} from 'lucide-react';
import { format } from 'date-fns';

// ---------------------------------------------------------------------------
// Types
// ---------------------------------------------------------------------------

interface Student {
  id: number;
  student_name: string;
  birth_date: string | null;
  grade_level: string | null;
  grade_adjustment: number | null;
  status: string;
  support_start_date: string | null;
  support_plan_start_type: string | null;
  guardian_id: number | null;
  guardian?: { id: number; full_name: string; email?: string } | null;
  username: string | null;
  is_active: boolean;
  withdrawal_date: string | null;
  created_at: string | null;
  scheduled_monday: boolean;
  scheduled_tuesday: boolean;
  scheduled_wednesday: boolean;
  scheduled_thursday: boolean;
  scheduled_friday: boolean;
  scheduled_saturday: boolean;
  scheduled_sunday: boolean;
  desired_start_date: string | null;
  desired_weekly_count: number | null;
  desired_monday: boolean;
  desired_tuesday: boolean;
  desired_wednesday: boolean;
  desired_thursday: boolean;
  desired_friday: boolean;
  desired_saturday: boolean;
  desired_sunday: boolean;
  waiting_notes: string | null;
}

interface Guardian {
  id: number;
  full_name: string;
  email?: string;
}

interface StudentForm {
  student_name: string;
  birth_date: string;
  grade_adjustment: number;
  support_start_date: string;
  support_plan_start_type: string;
  guardian_id: string;
  status: string;
  withdrawal_date: string;
  username: string;
  password: string;
  scheduled_monday: boolean;
  scheduled_tuesday: boolean;
  scheduled_wednesday: boolean;
  scheduled_thursday: boolean;
  scheduled_friday: boolean;
  scheduled_saturday: boolean;
  scheduled_sunday: boolean;
  desired_start_date: string;
  desired_weekly_count: string;
  desired_monday: boolean;
  desired_tuesday: boolean;
  desired_wednesday: boolean;
  desired_thursday: boolean;
  desired_friday: boolean;
  desired_saturday: boolean;
  desired_sunday: boolean;
  waiting_notes: string;
}

const emptyForm: StudentForm = {
  student_name: '',
  birth_date: '',
  grade_adjustment: 0,
  support_start_date: '',
  support_plan_start_type: 'current',
  guardian_id: '',
  status: 'active',
  withdrawal_date: '',
  username: '',
  password: '',
  scheduled_monday: false,
  scheduled_tuesday: false,
  scheduled_wednesday: false,
  scheduled_thursday: false,
  scheduled_friday: false,
  scheduled_saturday: false,
  scheduled_sunday: false,
  desired_start_date: '',
  desired_weekly_count: '',
  desired_monday: false,
  desired_tuesday: false,
  desired_wednesday: false,
  desired_thursday: false,
  desired_friday: false,
  desired_saturday: false,
  desired_sunday: false,
  waiting_notes: '',
};

const GRADE_LABELS: Record<string, string> = {
  preschool: '未就学児',
  elementary_1: '小学1年生', elementary_2: '小学2年生', elementary_3: '小学3年生',
  elementary_4: '小学4年生', elementary_5: '小学5年生', elementary_6: '小学6年生',
  junior_high_1: '中学1年生', junior_high_2: '中学2年生', junior_high_3: '中学3年生',
  high_school_1: '高校1年生', high_school_2: '高校2年生', high_school_3: '高校3年生',
};

const STATUS_LABELS: Record<string, string> = {
  active: '在籍', trial: '体験', short_term: '短期利用', waiting: '待機', withdrawn: '退所',
};

const STATUS_VARIANTS: Record<string, 'success' | 'warning' | 'danger' | 'default'> = {
  active: 'success', trial: 'success', short_term: 'success', waiting: 'warning', withdrawn: 'danger',
};

const DAYS = [
  { key: 'scheduled_monday', label: '月' },
  { key: 'scheduled_tuesday', label: '火' },
  { key: 'scheduled_wednesday', label: '水' },
  { key: 'scheduled_thursday', label: '木' },
  { key: 'scheduled_friday', label: '金' },
  { key: 'scheduled_saturday', label: '土' },
  { key: 'scheduled_sunday', label: '日' },
] as const;

const DESIRED_DAYS = [
  { key: 'desired_monday', label: '月' },
  { key: 'desired_tuesday', label: '火' },
  { key: 'desired_wednesday', label: '水' },
  { key: 'desired_thursday', label: '木' },
  { key: 'desired_friday', label: '金' },
  { key: 'desired_saturday', label: '土' },
  { key: 'desired_sunday', label: '日' },
] as const;

// ---------------------------------------------------------------------------
// Main Component
// ---------------------------------------------------------------------------

export default function StudentsPage() {
  const queryClient = useQueryClient();
  const toast = useToast();
  const [search, setSearch] = useState('');
  const [statusFilter, setStatusFilter] = useState('active');
  const debouncedSearch = useDebounce(search, 300);

  const [createModal, setCreateModal] = useState(false);
  const [editModal, setEditModal] = useState(false);
  const [editingStudent, setEditingStudent] = useState<Student | null>(null);
  const [form, setForm] = useState<StudentForm>(emptyForm);

  // Fetch students
  const { data: students = [], isLoading } = useQuery({
    queryKey: ['staff', 'students', debouncedSearch, statusFilter],
    queryFn: async () => {
      const params: Record<string, string> = {};
      if (debouncedSearch) params.search = debouncedSearch;
      if (statusFilter) params.status = statusFilter;
      else params.status = 'all';
      const res = await api.get('/api/staff/students', { params });
      const payload = res.data?.data;
      return Array.isArray(payload) ? payload as Student[] : [];
    },
  });

  // Fetch guardians
  const { data: guardians = [] } = useQuery({
    queryKey: ['staff', 'students', 'guardians'],
    queryFn: async () => {
      const res = await api.get('/api/staff/students/guardians');
      return (res.data?.data || []) as Guardian[];
    },
  });

  // Create
  const createMutation = useMutation({
    mutationFn: (data: StudentForm) => {
      const payload: Record<string, unknown> = { ...data };
      if (data.guardian_id) payload.guardian_id = Number(data.guardian_id);
      else delete payload.guardian_id;
      if (data.desired_weekly_count) payload.desired_weekly_count = Number(data.desired_weekly_count);
      else delete payload.desired_weekly_count;
      if (!data.username) delete payload.username;
      if (!data.password) delete payload.password;
      if (!data.withdrawal_date) delete payload.withdrawal_date;
      if (!data.desired_start_date) delete payload.desired_start_date;
      if (!data.waiting_notes) delete payload.waiting_notes;
      return api.post('/api/staff/students', payload);
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['staff', 'students'] });
      toast.success('生徒を登録しました');
      setCreateModal(false);
      setForm(emptyForm);
    },
    onError: (err: any) => toast.error(err.response?.data?.message || '登録に失敗しました'),
  });

  // Update
  const updateMutation = useMutation({
    mutationFn: ({ id, ...data }: StudentForm & { id: number }) => {
      const payload: Record<string, unknown> = { ...data };
      if (data.guardian_id) payload.guardian_id = Number(data.guardian_id);
      else payload.guardian_id = null;
      if (data.desired_weekly_count) payload.desired_weekly_count = Number(data.desired_weekly_count);
      else payload.desired_weekly_count = null;
      if (!data.password) delete payload.password;
      if (!data.withdrawal_date) payload.withdrawal_date = null;
      if (!data.desired_start_date) payload.desired_start_date = null;
      if (!data.waiting_notes) payload.waiting_notes = null;
      return api.put(`/api/staff/students/${id}`, payload);
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['staff', 'students'] });
      toast.success('生徒情報を更新しました');
      setEditModal(false);
      setEditingStudent(null);
    },
    onError: (err: any) => toast.error(err.response?.data?.message || '更新に失敗しました'),
  });

  // Delete (退所処理)
  const deleteMutation = useMutation({
    mutationFn: (id: number) => api.delete(`/api/staff/students/${id}`),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['staff', 'students'] });
      toast.success('退所処理しました');
      setEditModal(false);
      setEditingStudent(null);
    },
    onError: () => toast.error('退所処理に失敗しました'),
  });

  const openEdit = (student: Student) => {
    setEditingStudent(student);
    setForm({
      student_name: student.student_name,
      birth_date: student.birth_date || '',
      grade_adjustment: student.grade_adjustment ?? 0,
      support_start_date: student.support_start_date || '',
      support_plan_start_type: student.support_plan_start_type || 'current',
      guardian_id: student.guardian_id ? String(student.guardian_id) : '',
      status: student.status,
      withdrawal_date: student.withdrawal_date || '',
      username: student.username || '',
      password: '',
      scheduled_monday: student.scheduled_monday,
      scheduled_tuesday: student.scheduled_tuesday,
      scheduled_wednesday: student.scheduled_wednesday,
      scheduled_thursday: student.scheduled_thursday,
      scheduled_friday: student.scheduled_friday,
      scheduled_saturday: student.scheduled_saturday,
      scheduled_sunday: student.scheduled_sunday,
      desired_start_date: student.desired_start_date || '',
      desired_weekly_count: student.desired_weekly_count ? String(student.desired_weekly_count) : '',
      desired_monday: student.desired_monday,
      desired_tuesday: student.desired_tuesday,
      desired_wednesday: student.desired_wednesday,
      desired_thursday: student.desired_thursday,
      desired_friday: student.desired_friday,
      desired_saturday: student.desired_saturday,
      desired_sunday: student.desired_sunday,
      waiting_notes: student.waiting_notes || '',
    });
    setEditModal(true);
  };

  const updateField = (key: string, value: string | number | boolean) => {
    setForm((prev) => ({ ...prev, [key]: value }));
  };

  const handleDelete = () => {
    if (!editingStudent) return;
    if (confirm(`本当に「${editingStudent.student_name}」を退所処理しますか？\n\nこの操作は取り消せません。`)) {
      deleteMutation.mutate(editingStudent.id);
    }
  };

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold text-[var(--neutral-foreground-1)]">生徒管理</h1>
          <p className="text-sm text-[var(--neutral-foreground-3)]">生徒の登録・編集</p>
        </div>
        <Button leftIcon={<Plus className="h-4 w-4" />} onClick={() => { setForm(emptyForm); setCreateModal(true); }}>
          新規生徒登録
        </Button>
      </div>

      {/* Filters */}
      <div className="flex flex-col gap-3 sm:flex-row">
        <div className="relative flex-1">
          <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-[var(--neutral-foreground-4)]" />
          <Input placeholder="生徒名で検索..." value={search} onChange={(e) => setSearch(e.target.value)} className="pl-10" />
        </div>
        <select value={statusFilter} onChange={(e) => setStatusFilter(e.target.value)}
          className="rounded-lg border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-1)] px-3 py-2 text-sm">
          <option value="">全ステータス</option>
          <option value="active">在籍</option>
          <option value="trial">体験</option>
          <option value="short_term">短期利用</option>
          <option value="waiting">待機</option>
          <option value="withdrawn">退所</option>
        </select>
      </div>

      {/* Student list */}
      {isLoading ? (
        <div className="space-y-2">{[...Array(6)].map((_, i) => <Skeleton key={i} className="h-16 rounded-lg" />)}</div>
      ) : students.length === 0 ? (
        <Card><CardBody><p className="py-8 text-center text-sm text-[var(--neutral-foreground-4)]">生徒が見つかりません</p></CardBody></Card>
      ) : (
        <div className="overflow-x-auto rounded-lg border border-[var(--neutral-stroke-2)]">
          <table className="w-full text-sm">
            <thead>
              <tr className="border-b border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-3)]">
                <th className="px-3 py-2 text-left text-xs font-semibold text-[var(--neutral-foreground-3)]">ID</th>
                <th className="px-3 py-2 text-left text-xs font-semibold text-[var(--neutral-foreground-3)]">生徒名</th>
                <th className="px-3 py-2 text-left text-xs font-semibold text-[var(--neutral-foreground-3)]">生年月日</th>
                <th className="px-3 py-2 text-left text-xs font-semibold text-[var(--neutral-foreground-3)]">年齢</th>
                <th className="px-3 py-2 text-left text-xs font-semibold text-[var(--neutral-foreground-3)]">学年</th>
                <th className="px-3 py-2 text-left text-xs font-semibold text-[var(--neutral-foreground-3)]">保護者</th>
                <th className="px-3 py-2 text-left text-xs font-semibold text-[var(--neutral-foreground-3)]">状態</th>
                <th className="px-3 py-2 text-left text-xs font-semibold text-[var(--neutral-foreground-3)]">登録日</th>
                <th className="px-3 py-2 text-left text-xs font-semibold text-[var(--neutral-foreground-3)]">操作</th>
              </tr>
            </thead>
            <tbody>
              {students.map((student) => {
                const age = student.birth_date
                  ? Math.floor((Date.now() - new Date(student.birth_date).getTime()) / (365.25 * 24 * 60 * 60 * 1000))
                  : null;
                return (
                  <tr key={student.id} className="border-b border-[var(--neutral-stroke-3)] hover:bg-[var(--neutral-background-3)] transition-colors">
                    <td className="px-3 py-2 text-[var(--neutral-foreground-4)]">{student.id}</td>
                    <td className="px-3 py-2">
                      <Link href={`/staff/students/${student.id}`} className="font-medium text-[var(--brand-80)] hover:underline">
                        {student.student_name}
                      </Link>
                    </td>
                    <td className="px-3 py-2 text-[var(--neutral-foreground-2)]">
                      {student.birth_date ? format(new Date(student.birth_date), 'yyyy/MM/dd') : '-'}
                    </td>
                    <td className="px-3 py-2 text-[var(--neutral-foreground-2)]">{age !== null ? `${age}歳` : '-'}</td>
                    <td className="px-3 py-2 text-[var(--neutral-foreground-2)]">
                      {GRADE_LABELS[student.grade_level || ''] || student.grade_level || '未設定'}
                    </td>
                    <td className="px-3 py-2 text-[var(--neutral-foreground-2)]">{student.guardian?.full_name || '-'}</td>
                    <td className="px-3 py-2">
                      <Badge variant={STATUS_VARIANTS[student.status] || 'default'}>
                        {STATUS_LABELS[student.status] || student.status}
                      </Badge>
                    </td>
                    <td className="px-3 py-2 text-[var(--neutral-foreground-2)]">
                      {student.created_at ? format(new Date(student.created_at), 'yyyy/MM/dd') : '-'}
                    </td>
                    <td className="px-3 py-2">
                      <Button variant="outline" size="sm" onClick={() => openEdit(student)}>
                        編集
                      </Button>
                    </td>
                  </tr>
                );
              })}
            </tbody>
          </table>
        </div>
      )}

      {/* Create Modal */}
      <Modal isOpen={createModal} onClose={() => setCreateModal(false)} title="新規生徒登録" size="lg">
        <StudentFormComponent
          form={form} updateField={updateField} guardians={guardians}
          onSubmit={() => createMutation.mutate(form)}
          onCancel={() => setCreateModal(false)}
          isLoading={createMutation.isPending}
          submitLabel="登録する" isNew
        />
      </Modal>

      {/* Edit Modal */}
      <Modal isOpen={editModal} onClose={() => { setEditModal(false); setEditingStudent(null); }}
        title={`${editingStudent?.student_name} の編集`} size="lg">
        <StudentFormComponent
          form={form} updateField={updateField} guardians={guardians}
          onSubmit={() => editingStudent && updateMutation.mutate({ id: editingStudent.id, ...form })}
          onCancel={() => { setEditModal(false); setEditingStudent(null); }}
          isLoading={updateMutation.isPending}
          submitLabel="更新する" isNew={false}
          onDelete={handleDelete}
          studentId={editingStudent?.id}
        />
      </Modal>
    </div>
  );
}

// ---------------------------------------------------------------------------
// Student Form
// ---------------------------------------------------------------------------

function StudentFormComponent({ form, updateField, guardians, onSubmit, onCancel, isLoading, submitLabel, isNew, onDelete, studentId }: {
  form: StudentForm;
  updateField: (key: string, value: string | number | boolean) => void;
  guardians: Guardian[];
  onSubmit: () => void;
  onCancel: () => void;
  isLoading: boolean;
  submitLabel: string;
  isNew: boolean;
  onDelete?: () => void;
  studentId?: number;
}) {
  const inputCls = 'block w-full rounded-lg border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-1)] px-3 py-2 text-sm text-[var(--neutral-foreground-1)]';

  const handlePrintLogin = () => {
    if (!form.username) {
      alert('生徒用ログイン情報が設定されていません。\n\nまず、ユーザー名とパスワードを設定して保存してください。');
      return;
    }
    if (studentId) {
      const backendUrl = process.env.NEXT_PUBLIC_BACKEND_URL || '';
      window.open(`${backendUrl}/staff/student-login-print/${studentId}`, '_blank', 'width=800,height=600');
    }
  };

  return (
    <div className="space-y-4">
      {/* 生徒名 */}
      <div>
        <label className="mb-1 block text-sm font-medium text-[var(--neutral-foreground-2)]">生徒名 <span className="text-[var(--status-danger-fg)]">*</span></label>
        <input value={form.student_name} onChange={(e) => updateField('student_name', e.target.value)}
          className={inputCls} placeholder="例: 山田 太郎" required />
      </div>

      {/* 生年月日 */}
      <div>
        <label className="mb-1 block text-sm font-medium text-[var(--neutral-foreground-2)]">生年月日 <span className="text-[var(--status-danger-fg)]">*</span></label>
        <input type="date" value={form.birth_date} onChange={(e) => updateField('birth_date', e.target.value)}
          className={inputCls} required />
        <p className="mt-1 text-xs text-[var(--neutral-foreground-4)]">※学年は生年月日から自動で計算されます</p>
      </div>

      {/* 学年調整 */}
      <div>
        <label className="mb-1 block text-sm font-medium text-[var(--neutral-foreground-2)]">学年調整</label>
        <select value={form.grade_adjustment} onChange={(e) => updateField('grade_adjustment', Number(e.target.value))} className={inputCls}>
          <option value={0}>調整なし (0)</option>
          <option value={1}>1学年上 (+1)</option>
          <option value={2}>2学年上 (+2)</option>
          <option value={-1}>1学年下 (-1)</option>
          <option value={-2}>2学年下 (-2)</option>
        </select>
      </div>

      {/* 支援開始日 */}
      <div>
        <label className="mb-1 block text-sm font-medium text-[var(--neutral-foreground-2)]">支援開始日 {form.status !== 'waiting' && <span className="text-[var(--status-danger-fg)]">*</span>}</label>
        <input type="date" value={form.support_start_date} onChange={(e) => updateField('support_start_date', e.target.value)}
          className={inputCls} required={form.status !== 'waiting'} />
        <p className="mt-1 text-xs text-[var(--neutral-foreground-4)]">※かけはしの提出期限が自動で設定されます</p>
      </div>

      {/* 個別支援計画の開始タイミング */}
      <div>
        <label className="mb-1 block text-sm font-medium text-[var(--neutral-foreground-2)]">個別支援計画の開始タイミング</label>
        <select value={form.support_plan_start_type} onChange={(e) => updateField('support_plan_start_type', e.target.value)} className={inputCls}>
          <option value="current">現在の期間から作成する</option>
          <option value="next">次回の期間から作成する</option>
        </select>
        <div className="mt-1 rounded-lg bg-[var(--neutral-background-3)] p-2.5 text-xs text-[var(--neutral-foreground-3)]">
          <p className="flex items-start gap-1"><Info className="h-3.5 w-3.5 mt-0.5 shrink-0" /><span>【新規入所の児童】「現在の期間から作成する」を選択してください。</span></p>
          <p className="flex items-start gap-1 mt-1"><Info className="h-3.5 w-3.5 mt-0.5 shrink-0" /><span>【既に入所中の児童】既存の計画を移行する場合 →「現在の期間から」、次回から開始する場合 →「次回の期間から」</span></p>
        </div>
      </div>

      {/* 保護者 */}
      <div>
        <label className="mb-1 block text-sm font-medium text-[var(--neutral-foreground-2)]">保護者（任意）</label>
        <select value={form.guardian_id} onChange={(e) => updateField('guardian_id', e.target.value)} className={inputCls}>
          <option value="">保護者を選択（後で設定可能）</option>
          {guardians.map((g) => <option key={g.id} value={g.id}>{g.full_name}</option>)}
        </select>
      </div>

      {/* 状態 */}
      <div>
        <label className="mb-1 block text-sm font-medium text-[var(--neutral-foreground-2)]">状態</label>
        <select value={form.status} onChange={(e) => updateField('status', e.target.value)} className={inputCls}>
          <option value="active">在籍</option>
          <option value="trial">体験</option>
          <option value="short_term">短期利用</option>
          <option value="waiting">待機</option>
          <option value="withdrawn">退所</option>
        </select>
      </div>

      {/* 退所日（退所ステータスの場合のみ表示） */}
      {form.status === 'withdrawn' && (
        <div>
          <label className="mb-1 block text-sm font-medium text-[var(--neutral-foreground-2)]">退所日</label>
          <input type="date" value={form.withdrawal_date} onChange={(e) => updateField('withdrawal_date', e.target.value)}
            className={inputCls} />
          <p className="mt-1 text-xs text-[var(--neutral-foreground-4)]">※退所日以降のかけはし・計画書・モニタリング表は作成されません</p>
        </div>
      )}

      {/* 待機児童情報（待機ステータスの場合のみ表示） */}
      {form.status === 'waiting' && (
        <div className="rounded-lg border-2 border-[var(--status-warning-fg)] bg-[var(--neutral-background-3)] p-4">
          <h4 className="text-sm font-semibold text-[var(--status-warning-fg)] mb-3">待機児童情報</h4>
          <div className="grid gap-3 sm:grid-cols-2">
            <div>
              <label className="mb-1 block text-xs text-[var(--neutral-foreground-3)]">入所希望日</label>
              <input type="date" value={form.desired_start_date} onChange={(e) => updateField('desired_start_date', e.target.value)}
                className={inputCls} />
            </div>
            <div>
              <label className="mb-1 block text-xs text-[var(--neutral-foreground-3)]">希望利用回数（週）</label>
              <select value={form.desired_weekly_count} onChange={(e) => updateField('desired_weekly_count', e.target.value)} className={inputCls}>
                <option value="">選択してください</option>
                <option value="1">週1回</option>
                <option value="2">週2回</option>
                <option value="3">週3回</option>
                <option value="4">週4回</option>
                <option value="5">週5回</option>
              </select>
            </div>
          </div>
          <div className="mt-3">
            <label className="mb-1 block text-xs text-[var(--neutral-foreground-3)]">希望曜日</label>
            <div className="flex flex-wrap gap-3">
              {DESIRED_DAYS.map(({ key, label }) => (
                <label key={key} className="flex items-center gap-1.5 cursor-pointer text-sm">
                  <input type="checkbox" checked={form[key as keyof StudentForm] as boolean}
                    onChange={(e) => updateField(key, e.target.checked)}
                    className="rounded border-[var(--neutral-stroke-2)]" />
                  {label}曜日
                </label>
              ))}
            </div>
          </div>
          <div className="mt-3">
            <label className="mb-1 block text-xs text-[var(--neutral-foreground-3)]">待機メモ</label>
            <textarea value={form.waiting_notes} onChange={(e) => updateField('waiting_notes', e.target.value)}
              className={inputCls} rows={2} placeholder="空き次第連絡希望、など" />
          </div>
        </div>
      )}

      {/* 参加予定曜日（待機ステータス以外の場合のみ表示） */}
      {form.status !== 'waiting' && (
        <div>
          <label className="mb-1 block text-sm font-medium text-[var(--neutral-foreground-2)]">参加予定曜日</label>
          <div className="flex flex-wrap gap-3">
            {DAYS.map(({ key, label }) => (
              <label key={key} className="flex items-center gap-1.5 cursor-pointer text-sm">
                <input type="checkbox" checked={form[key as keyof StudentForm] as boolean}
                  onChange={(e) => updateField(key, e.target.checked)}
                  className="rounded border-[var(--neutral-stroke-2)]" />
                {label}曜日
              </label>
            ))}
          </div>
        </div>
      )}

      {/* ログイン情報 */}
      {!isNew && (
        <div className="border-t border-[var(--neutral-stroke-2)] pt-4">
          <h4 className="text-sm font-semibold text-[var(--neutral-foreground-1)] mb-3">生徒用ログイン設定</h4>
          <div className="rounded-lg bg-[var(--neutral-background-3)] p-3 space-y-3">
            <div>
              <label className="mb-1 block text-xs text-[var(--neutral-foreground-3)]">ユーザー名（半角英数字）</label>
              <input value={form.username} onChange={(e) => updateField('username', e.target.value)}
                className={inputCls} placeholder="例: tanaka_taro" pattern="[a-zA-Z0-9_]+" />
              <p className="mt-1 text-xs text-[var(--neutral-foreground-4)]">※空欄の場合、ログイン不可</p>
            </div>
            <div>
              <label className="mb-1 block text-xs text-[var(--neutral-foreground-3)]">パスワード</label>
              <input type="password" value={form.password} onChange={(e) => updateField('password', e.target.value)}
                className={inputCls} placeholder="変更する場合のみ入力" />
              <p className="mt-1 text-xs text-[var(--neutral-foreground-4)]">※変更しない場合は空欄</p>
            </div>
          </div>
        </div>
      )}

      {/* Actions */}
      <div className="flex items-center gap-2 pt-2">
        <Button variant="secondary" onClick={onCancel}>キャンセル</Button>
        {!isNew && (
          <Button variant="outline" size="sm" onClick={handlePrintLogin} leftIcon={<Printer className="h-3.5 w-3.5" />}>
            生徒用資料を印刷
          </Button>
        )}
        <div className="flex-1" />
        {!isNew && onDelete && (
          <Button variant="danger" size="sm" onClick={onDelete} leftIcon={<Trash2 className="h-3.5 w-3.5" />}>
            退所処理
          </Button>
        )}
        <Button onClick={onSubmit} isLoading={isLoading}>{submitLabel}</Button>
      </div>
    </div>
  );
}
