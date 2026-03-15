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
import { SkeletonList, SkeletonTable } from '@/components/ui/Skeleton';
import { useToast } from '@/components/ui/Toast';
import {
  Plus,
  Pencil,
  Trash2,
  Eye,
  User,
  Calendar,
  MessageSquare,
  School,
  Download,
} from 'lucide-react';
import { format } from 'date-fns';

// ---------------------------------------------------------------------------
// Types
// ---------------------------------------------------------------------------

interface Student {
  id: number;
  student_name: string;
}

interface StudentInterview {
  id: number;
  student_id: number;
  student_name: string;
  interview_date: string;
  interviewer_id: number;
  interviewer_name: string;
  interview_content: string;
  check_school: boolean;
  check_school_note: string | null;
  created_at: string;
  updated_at: string;
}

interface InterviewForm {
  student_id: number | '';
  interview_date: string;
  interview_content: string;
  check_school: boolean;
  check_school_note: string;
}

const emptyForm: InterviewForm = {
  student_id: '',
  interview_date: new Date().toISOString().split('T')[0],
  interview_content: '',
  check_school: false,
  check_school_note: '',
};

export default function StudentInterviewsPage() {
  const queryClient = useQueryClient();
  const toast = useToast();

  const [selectedStudentId, setSelectedStudentId] = useState<number | null>(null);
  const [createModal, setCreateModal] = useState(false);
  const [detailModal, setDetailModal] = useState(false);
  const [editMode, setEditMode] = useState(false);
  const [selectedInterview, setSelectedInterview] = useState<StudentInterview | null>(null);
  const [form, setForm] = useState<InterviewForm>(emptyForm);

  // Fetch students
  const { data: students = [] } = useQuery({
    queryKey: ['staff', 'students'],
    queryFn: async () => {
      const res = await api.get<{ data: Student[] }>('/api/staff/students');
      return res.data.data;
    },
  });

  // Fetch interviews
  const { data: interviews = [], isLoading } = useQuery({
    queryKey: ['staff', 'student-interviews', selectedStudentId],
    queryFn: async () => {
      const url = selectedStudentId
        ? `/api/staff/student-interviews?student_id=${selectedStudentId}`
        : '/api/staff/student-interviews';
      const res = await api.get<{ data: StudentInterview[] }>(url);
      return res.data.data;
    },
  });

  // Create mutation
  const createMutation = useMutation({
    mutationFn: (data: InterviewForm) => api.post('/api/staff/student-interviews', data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['staff', 'student-interviews'] });
      toast.success('面談記録を作成しました');
      setCreateModal(false);
      setForm(emptyForm);
    },
    onError: () => toast.error('作成に失敗しました'),
  });

  // Update mutation
  const updateMutation = useMutation({
    mutationFn: ({ id, ...data }: InterviewForm & { id: number }) =>
      api.put(`/api/staff/student-interviews/${id}`, data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['staff', 'student-interviews'] });
      toast.success('面談記録を更新しました');
      setDetailModal(false);
      setEditMode(false);
      setSelectedInterview(null);
    },
    onError: () => toast.error('更新に失敗しました'),
  });

  // Delete mutation
  const deleteMutation = useMutation({
    mutationFn: (id: number) => api.delete(`/api/staff/student-interviews/${id}`),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['staff', 'student-interviews'] });
      toast.success('面談記録を削除しました');
      setDetailModal(false);
      setSelectedInterview(null);
    },
    onError: () => toast.error('削除に失敗しました'),
  });

  // PDF download
  const handlePdfDownload = async (id: number) => {
    try {
      const res = await api.get(`/api/staff/student-interviews/${id}/pdf`, { responseType: 'blob' });
      const url = window.URL.createObjectURL(new Blob([res.data]));
      const link = document.createElement('a');
      link.href = url;
      link.download = `student_interview_${id}.pdf`;
      link.click();
      window.URL.revokeObjectURL(url);
    } catch {
      toast.error('PDF生成に失敗しました');
    }
  };

  const openDetail = (interview: StudentInterview) => {
    setSelectedInterview(interview);
    setEditMode(false);
    setForm({
      student_id: interview.student_id,
      interview_date: interview.interview_date,
      interview_content: interview.interview_content,
      check_school: interview.check_school,
      check_school_note: interview.check_school_note || '',
    });
    setDetailModal(true);
  };

  const handleCreate = (e: React.FormEvent) => {
    e.preventDefault();
    createMutation.mutate(form);
  };

  const handleUpdate = (e: React.FormEvent) => {
    e.preventDefault();
    if (selectedInterview) {
      updateMutation.mutate({ id: selectedInterview.id, ...form });
    }
  };

  // Group interviews by student for card view
  const studentMap = new Map<number, { student_name: string; interviews: StudentInterview[] }>();
  interviews.forEach((iv) => {
    if (!studentMap.has(iv.student_id)) {
      studentMap.set(iv.student_id, { student_name: iv.student_name, interviews: [] });
    }
    studentMap.get(iv.student_id)!.interviews.push(iv);
  });

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-bold text-[var(--neutral-foreground-1)]">生徒面談記録</h1>
        <Button leftIcon={<Plus className="h-4 w-4" />} onClick={() => { setForm(emptyForm); setCreateModal(true); }}>
          新規作成
        </Button>
      </div>

      {/* Student filter */}
      <Card>
        <CardBody>
          <label className="mb-2 block text-sm font-medium text-[var(--neutral-foreground-2)]">生徒で絞り込み</label>
          <select
            className="block w-full rounded-lg border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-1)] px-3 py-2 text-sm text-[var(--neutral-foreground-1)]"
            value={selectedStudentId ?? ''}
            onChange={(e) => setSelectedStudentId(e.target.value ? Number(e.target.value) : null)}
          >
            <option value="">すべての生徒</option>
            {students.map((s) => (
              <option key={s.id} value={s.id}>{s.student_name}</option>
            ))}
          </select>
        </CardBody>
      </Card>

      {/* Interview cards grouped by student */}
      {isLoading ? (
        <SkeletonList items={4} />
      ) : interviews.length === 0 ? (
        <Card>
          <CardBody>
            <p className="py-8 text-center text-sm text-[var(--neutral-foreground-3)]">面談記録がありません</p>
          </CardBody>
        </Card>
      ) : (
        <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
          {Array.from(studentMap.entries()).map(([studentId, { student_name, interviews: ivs }]) => (
            <Card key={studentId}>
              <CardHeader>
                <div className="flex items-center gap-2">
                  <User className="h-5 w-5 text-[var(--brand-80)]" />
                  <CardTitle className="text-base">{student_name}</CardTitle>
                  <Badge variant="default">{ivs.length}件</Badge>
                </div>
              </CardHeader>
              <CardBody>
                <div className="space-y-2">
                  {ivs.slice(0, 5).map((iv) => (
                    <div
                      key={iv.id}
                      className="flex w-full items-center justify-between rounded-lg border border-[var(--neutral-stroke-2)] p-3 hover:bg-[var(--neutral-background-2)] transition-colors"
                    >
                      <button
                        className="flex-1 text-left"
                        onClick={() => openDetail(iv)}
                      >
                        <div className="flex items-center gap-2">
                          <Calendar className="h-3 w-3 text-[var(--neutral-foreground-3)]" />
                          <span className="text-sm font-medium text-[var(--neutral-foreground-1)]">
                            {format(new Date(iv.interview_date), 'yyyy/MM/dd')}
                          </span>
                          {iv.check_school && (
                            <Badge variant="info">
                              <School className="mr-1 h-3 w-3" />
                              学校
                            </Badge>
                          )}
                        </div>
                        <p className="mt-1 text-xs text-[var(--neutral-foreground-3)] line-clamp-1">
                          {iv.interview_content || '内容なし'}
                        </p>
                      </button>
                      <div className="flex items-center gap-1 ml-2">
                        <button
                          className="rounded p-1 hover:bg-[var(--neutral-background-3)] transition-colors"
                          onClick={(e) => { e.stopPropagation(); handlePdfDownload(iv.id); }}
                          title="PDF出力"
                        >
                          <Download className="h-4 w-4 text-[var(--neutral-foreground-3)]" />
                        </button>
                        <button
                          className="rounded p-1 hover:bg-[var(--neutral-background-3)] transition-colors"
                          onClick={() => openDetail(iv)}
                          title="詳細"
                        >
                          <Eye className="h-4 w-4 text-[var(--neutral-foreground-3)]" />
                        </button>
                      </div>
                    </div>
                  ))}
                  {ivs.length > 5 && (
                    <p className="text-center text-xs text-[var(--neutral-foreground-3)]">
                      他 {ivs.length - 5} 件
                    </p>
                  )}
                </div>
              </CardBody>
            </Card>
          ))}
        </div>
      )}

      {/* Create Modal */}
      <Modal isOpen={createModal} onClose={() => setCreateModal(false)} title="面談記録を作成" size="lg">
        <form onSubmit={handleCreate} className="space-y-4">
          <div>
            <label className="mb-1 block text-sm font-medium text-[var(--neutral-foreground-2)]">生徒</label>
            <select
              className="block w-full rounded-lg border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-1)] px-3 py-2 text-sm text-[var(--neutral-foreground-1)]"
              value={form.student_id}
              onChange={(e) => setForm({ ...form, student_id: e.target.value ? Number(e.target.value) : '' })}
              required
            >
              <option value="">生徒を選択</option>
              {students.map((s) => (
                <option key={s.id} value={s.id}>{s.student_name}</option>
              ))}
            </select>
          </div>
          <Input
            label="面談日"
            type="date"
            value={form.interview_date}
            onChange={(e) => setForm({ ...form, interview_date: e.target.value })}
            required
          />
          <div>
            <label className="mb-1 block text-sm font-medium text-[var(--neutral-foreground-2)]">面談内容</label>
            <textarea
              value={form.interview_content}
              onChange={(e) => setForm({ ...form, interview_content: e.target.value })}
              className="block w-full rounded-lg border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-1)] px-3 py-2 text-sm text-[var(--neutral-foreground-1)]"
              rows={6}
              placeholder="面談の内容を自由に記述してください"
            />
          </div>
          <div className="rounded-lg bg-[var(--neutral-background-2)] p-4">
            <label className="flex items-center gap-2">
              <input
                type="checkbox"
                checked={form.check_school}
                onChange={(e) => setForm({ ...form, check_school: e.target.checked })}
                className="h-4 w-4 rounded border-[var(--neutral-stroke-2)]"
              />
              <span className="text-sm font-medium text-[var(--neutral-foreground-1)]">学校での様子について話があった</span>
            </label>
            {form.check_school && (
              <textarea
                value={form.check_school_note}
                onChange={(e) => setForm({ ...form, check_school_note: e.target.value })}
                className="mt-2 block w-full rounded-lg border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-1)] px-3 py-2 text-sm text-[var(--neutral-foreground-1)]"
                rows={3}
                placeholder="学校での様子についてのメモ"
              />
            )}
          </div>
          <div className="flex justify-end gap-2 pt-2">
            <Button variant="secondary" type="button" onClick={() => setCreateModal(false)}>キャンセル</Button>
            <Button type="submit" isLoading={createMutation.isPending}>作成</Button>
          </div>
        </form>
      </Modal>

      {/* Detail / Edit Modal */}
      <Modal
        isOpen={detailModal}
        onClose={() => { setDetailModal(false); setSelectedInterview(null); setEditMode(false); }}
        title={selectedInterview ? `${selectedInterview.student_name}の面談記録` : '面談記録'}
        size="lg"
      >
        {selectedInterview && !editMode ? (
          <div className="space-y-4">
            <div className="flex items-center gap-4">
              <div>
                <p className="text-xs text-[var(--neutral-foreground-3)]">面談日</p>
                <p className="text-sm font-medium text-[var(--neutral-foreground-1)]">
                  {format(new Date(selectedInterview.interview_date), 'yyyy年MM月dd日')}
                </p>
              </div>
              <div>
                <p className="text-xs text-[var(--neutral-foreground-3)]">面談者</p>
                <p className="text-sm font-medium text-[var(--neutral-foreground-1)]">{selectedInterview.interviewer_name}</p>
              </div>
            </div>

            <div>
              <p className="text-xs font-medium text-[var(--neutral-foreground-3)]">面談内容</p>
              <div className="mt-1 rounded-lg bg-[var(--neutral-background-2)] p-3">
                <p className="text-sm text-[var(--neutral-foreground-1)] whitespace-pre-wrap">
                  {selectedInterview.interview_content || '未記入'}
                </p>
              </div>
            </div>

            <div className="rounded-lg bg-[var(--neutral-background-2)] p-3">
              <div className="flex items-center gap-2">
                <Badge variant={selectedInterview.check_school ? 'success' : 'default'}>
                  {selectedInterview.check_school ? '学校の話あり' : '学校の話なし'}
                </Badge>
              </div>
              {selectedInterview.check_school && selectedInterview.check_school_note && (
                <p className="mt-2 text-sm text-[var(--neutral-foreground-1)] whitespace-pre-wrap">
                  {selectedInterview.check_school_note}
                </p>
              )}
            </div>

            <div className="flex justify-end gap-2 pt-2">
              <Button
                variant="ghost"
                size="sm"
                onClick={() => {
                  if (confirm('削除しますか？')) deleteMutation.mutate(selectedInterview.id);
                }}
              >
                <Trash2 className="mr-1 h-4 w-4 text-[var(--status-danger-fg)]" />
                削除
              </Button>
              <Button variant="outline" size="sm" leftIcon={<Download className="h-4 w-4" />} onClick={() => handlePdfDownload(selectedInterview.id)}>
                PDF
              </Button>
              <Button variant="outline" size="sm" leftIcon={<Pencil className="h-4 w-4" />} onClick={() => setEditMode(true)}>
                編集
              </Button>
            </div>
          </div>
        ) : selectedInterview && editMode ? (
          <form onSubmit={handleUpdate} className="space-y-4">
            <Input
              label="面談日"
              type="date"
              value={form.interview_date}
              onChange={(e) => setForm({ ...form, interview_date: e.target.value })}
              required
            />
            <div>
              <label className="mb-1 block text-sm font-medium text-[var(--neutral-foreground-2)]">面談内容</label>
              <textarea
                value={form.interview_content}
                onChange={(e) => setForm({ ...form, interview_content: e.target.value })}
                className="block w-full rounded-lg border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-1)] px-3 py-2 text-sm text-[var(--neutral-foreground-1)]"
                rows={6}
              />
            </div>
            <div className="rounded-lg bg-[var(--neutral-background-2)] p-4">
              <label className="flex items-center gap-2">
                <input
                  type="checkbox"
                  checked={form.check_school}
                  onChange={(e) => setForm({ ...form, check_school: e.target.checked })}
                  className="h-4 w-4 rounded border-[var(--neutral-stroke-2)]"
                />
                <span className="text-sm font-medium text-[var(--neutral-foreground-1)]">学校での様子について話があった</span>
              </label>
              {form.check_school && (
                <textarea
                  value={form.check_school_note}
                  onChange={(e) => setForm({ ...form, check_school_note: e.target.value })}
                  className="mt-2 block w-full rounded-lg border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-1)] px-3 py-2 text-sm text-[var(--neutral-foreground-1)]"
                  rows={3}
                />
              )}
            </div>
            <div className="flex justify-end gap-2 pt-2">
              <Button variant="secondary" type="button" onClick={() => setEditMode(false)}>キャンセル</Button>
              <Button type="submit" isLoading={updateMutation.isPending}>更新</Button>
            </div>
          </form>
        ) : null}
      </Modal>
    </div>
  );
}
