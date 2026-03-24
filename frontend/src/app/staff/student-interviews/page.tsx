'use client';

import { useState, useCallback } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import api from '@/lib/api';
import { Card, CardBody } from '@/components/ui/Card';
import { Button } from '@/components/ui/Button';
import { Badge } from '@/components/ui/Badge';
import { Modal } from '@/components/ui/Modal';
import { Skeleton, SkeletonList } from '@/components/ui/Skeleton';
import { useToast } from '@/components/ui/Toast';
import { format } from 'date-fns';
import { MaterialIcon } from '@/components/ui/MaterialIcon';

// ---------------------------------------------------------------------------
// Types
// ---------------------------------------------------------------------------

interface Interview {
  id: number;
  student_id: number;
  interview_date: string;
  interviewer_id: number;
  interview_content: string | null;
  child_wish: string | null;
  check_school: boolean;
  check_school_notes: string | null;
  check_home: boolean;
  check_home_notes: string | null;
  check_troubles: boolean;
  check_troubles_notes: string | null;
  other_notes: string | null;
  created_at: string;
  updated_at: string;
  interviewer?: { id: number; full_name: string };
}

interface StudentWithInterviews {
  id: number;
  student_name: string;
  interview_count: number;
  latest_interview: Interview | null;
  interviews: Interview[];
}

interface InterviewForm {
  interview_date: string;
  interview_content: string;
  child_wish: string;
  check_school: boolean;
  check_school_notes: string;
  check_home: boolean;
  check_home_notes: string;
  check_troubles: boolean;
  check_troubles_notes: string;
  other_notes: string;
}

const emptyForm: InterviewForm = {
  interview_date: new Date().toISOString().split('T')[0],
  interview_content: '',
  child_wish: '',
  check_school: false,
  check_school_notes: '',
  check_home: false,
  check_home_notes: '',
  check_troubles: false,
  check_troubles_notes: '',
  other_notes: '',
};

function nl(text: string | null | undefined): string {
  if (!text) return '';
  return text.replace(/\\r\\n|\\n|\\r/g, '\n').replace(/\r\n|\r/g, '\n');
}

// ---------------------------------------------------------------------------
// Main Component
// ---------------------------------------------------------------------------

export default function StudentInterviewsPage() {
  const queryClient = useQueryClient();
  const toast = useToast();

  const [selectedStudentId, setSelectedStudentId] = useState<number | null>(null);
  const [selectedInterview, setSelectedInterview] = useState<Interview | null>(null);
  const [isEditing, setIsEditing] = useState(false);
  const [createModal, setCreateModal] = useState(false);
  const [form, setForm] = useState<InterviewForm>(emptyForm);

  // Fetch all students with interviews
  const { data: students = [], isLoading } = useQuery({
    queryKey: ['staff', 'student-interviews'],
    queryFn: async () => {
      const res = await api.get<{ data: StudentWithInterviews[] }>('/api/staff/student-interviews');
      return res.data.data;
    },
  });

  const selectedStudent = students.find((s) => s.id === selectedStudentId);
  const interviews = selectedStudent?.interviews ?? [];

  // Create mutation
  const createMutation = useMutation({
    mutationFn: (data: InterviewForm) =>
      api.post(`/api/staff/students/${selectedStudentId}/interview`, data),
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
    onSuccess: (res) => {
      queryClient.invalidateQueries({ queryKey: ['staff', 'student-interviews'] });
      toast.success('面談記録を更新しました');
      setIsEditing(false);
      setSelectedInterview(res.data?.data ?? null);
    },
    onError: () => toast.error('更新に失敗しました'),
  });

  // Delete mutation
  const deleteMutation = useMutation({
    mutationFn: (id: number) => api.delete(`/api/staff/student-interviews/${id}`),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['staff', 'student-interviews'] });
      toast.success('面談記録を削除しました');
      setSelectedInterview(null);
    },
    onError: () => toast.error('削除に失敗しました'),
  });

  // PDF download
  const handlePdf = useCallback(async (id: number) => {
    try {
      const res = await api.get(`/api/staff/student-interviews/${id}/pdf`, { responseType: 'blob' });
      const url = window.URL.createObjectURL(new Blob([res.data]));
      const link = document.createElement('a');
      link.href = url;
      link.download = `interview_${id}.pdf`;
      link.click();
      window.URL.revokeObjectURL(url);
    } catch {
      toast.error('PDF生成に失敗しました');
    }
  }, [toast]);

  const openEdit = (interview: Interview) => {
    setIsEditing(true);
    setForm({
      interview_date: interview.interview_date,
      interview_content: nl(interview.interview_content) || '',
      child_wish: nl(interview.child_wish) || '',
      check_school: interview.check_school,
      check_school_notes: nl(interview.check_school_notes) || '',
      check_home: interview.check_home,
      check_home_notes: nl(interview.check_home_notes) || '',
      check_troubles: interview.check_troubles,
      check_troubles_notes: nl(interview.check_troubles_notes) || '',
      other_notes: nl(interview.other_notes) || '',
    });
  };

  const updateField = (key: string, value: string | boolean) => {
    setForm((prev) => ({ ...prev, [key]: value }));
  };

  // ---- View: Student Grid (no student selected) ----
  if (!selectedStudentId) {
    return (
      <div className="space-y-6">
        <h1 className="text-2xl font-bold text-[var(--neutral-foreground-1)]">生徒面談記録</h1>

        {isLoading ? (
          <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            {[...Array(6)].map((_, i) => <Skeleton key={i} className="h-24 rounded-lg" />)}
          </div>
        ) : (
          <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            {students.map((student) => (
              <button
                key={student.id}
                onClick={() => setSelectedStudentId(student.id)}
                className="flex items-center gap-4 rounded-lg border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-1)] p-4 text-left transition-shadow hover:shadow-md"
              >
                <div className="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-[var(--brand-140)] text-sm font-bold text-[var(--brand-80)]">
                  {student.student_name.charAt(0)}
                </div>
                <div className="flex-1 min-w-0">
                  <p className="font-medium text-[var(--neutral-foreground-1)] truncate">{student.student_name}</p>
                  <div className="mt-1 flex items-center gap-2 text-xs text-[var(--neutral-foreground-3)]">
                    {student.interview_count > 0 ? (
                      <>
                        <Badge variant="success">記録あり</Badge>
                        <span>{student.interview_count}件</span>
                        {student.latest_interview && (
                          <span>最新: {format(new Date(student.latest_interview.interview_date), 'yyyy/MM/dd')}</span>
                        )}
                      </>
                    ) : (
                      <Badge variant="default">記録なし</Badge>
                    )}
                  </div>
                </div>
              </button>
            ))}
          </div>
        )}
      </div>
    );
  }

  // ---- View: Student Detail (2-column: main + sidebar) ----
  return (
    <div className="space-y-4">
      {/* Header */}
      <div className="flex items-center justify-between">
        <div className="flex items-center gap-3">
          <Button variant="ghost" size="sm" onClick={() => { setSelectedStudentId(null); setSelectedInterview(null); setIsEditing(false); }}>
            <MaterialIcon name="chevron_left" size={16} />
            戻る
          </Button>
          <h1 className="text-xl font-bold text-[var(--neutral-foreground-1)]">
            {selectedStudent?.student_name} の面談記録
          </h1>
        </div>
        <Button
          size="sm"
          leftIcon={<MaterialIcon name="add" size={16} />}
          onClick={() => { setForm(emptyForm); setCreateModal(true); }}
        >
          新規作成
        </Button>
      </div>

      <div className="flex gap-6">
        {/* Sidebar: Interview history */}
        <div className="w-[280px] shrink-0 space-y-2">
          <p className="text-xs font-semibold text-[var(--neutral-foreground-3)] uppercase tracking-wider px-1">
            面談履歴 ({interviews.length}件)
          </p>
          {interviews.length === 0 ? (
            <p className="text-sm text-[var(--neutral-foreground-4)] px-1 py-4">面談記録はありません</p>
          ) : (
            interviews.map((iv) => (
              <button
                key={iv.id}
                onClick={() => { setSelectedInterview(iv); setIsEditing(false); }}
                className={`w-full rounded-lg border p-3 text-left text-sm transition-colors ${
                  selectedInterview?.id === iv.id
                    ? 'border-[var(--brand-80)] bg-[var(--brand-160)]'
                    : 'border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-1)] hover:bg-[var(--neutral-background-3)]'
                }`}
              >
                <div className="flex items-center justify-between">
                  <span className="font-medium text-[var(--neutral-foreground-1)]">
                    {format(new Date(iv.interview_date), 'yyyy/MM/dd')}
                  </span>
                  <div className="flex gap-1">
                    {iv.check_school && <MaterialIcon name="school" size={12} className="text-[var(--status-info-fg)]" />}
                    {iv.check_home && <Home className="h-3 w-3 text-[var(--status-warning-fg)]" />}
                    {iv.check_troubles && <MaterialIcon name="warning" size={12} className="text-[var(--status-danger-fg)]" />}
                  </div>
                </div>
                <p className="text-xs text-[var(--neutral-foreground-3)] mt-0.5">
                  {iv.interviewer?.full_name || '不明'}
                </p>
                <p className="text-xs text-[var(--neutral-foreground-4)] mt-1 truncate">
                  {nl(iv.interview_content)?.substring(0, 30) || '（内容なし）'}
                </p>
              </button>
            ))
          )}
        </div>

        {/* Main content */}
        <div className="flex-1 min-w-0">
          {!selectedInterview && !isEditing ? (
            <Card>
              <CardBody>
                <div className="py-16 text-center text-[var(--neutral-foreground-4)]">
                  <MaterialIcon name="forum" size={48} className="mx-auto mb-3" />
                  <p className="text-sm">左の履歴から面談記録を選択するか、新規作成してください</p>
                </div>
              </CardBody>
            </Card>
          ) : isEditing && selectedInterview ? (
            /* Edit mode */
            <Card>
              <CardBody>
                <InterviewFormComponent
                  form={form}
                  updateField={updateField}
                  onSubmit={() => updateMutation.mutate({ id: selectedInterview.id, ...form })}
                  onCancel={() => setIsEditing(false)}
                  isLoading={updateMutation.isPending}
                  submitLabel="更新"
                />
              </CardBody>
            </Card>
          ) : selectedInterview ? (
            /* View mode */
            <Card>
              <CardBody>
                {/* Actions */}
                <div className="flex items-center justify-between mb-4">
                  <div className="flex items-center gap-2 text-sm text-[var(--neutral-foreground-3)]">
                    <MaterialIcon name="calendar_month" size={16} />
                    {format(new Date(selectedInterview.interview_date), 'yyyy年MM月dd日')}
                    <span className="mx-1">|</span>
                    <MaterialIcon name="person" size={16} />
                    {selectedInterview.interviewer?.full_name || '不明'}
                  </div>
                  <div className="flex gap-1">
                    <Button variant="outline" size="sm" leftIcon={<MaterialIcon name="download" size={16} />}
                      onClick={() => handlePdf(selectedInterview.id)}>PDF</Button>
                    <a href={`/staff/student-interviews/${selectedInterview.id}/print`} target="_blank" rel="noopener noreferrer">
                      <Button variant="outline" size="sm" leftIcon={<MaterialIcon name="print" size={16} />}>印刷プレビュー</Button>
                    </a>
                    <Button variant="outline" size="sm" leftIcon={<MaterialIcon name="edit" size={16} />}
                      onClick={() => openEdit(selectedInterview)}>編集</Button>
                    <Button variant="ghost" size="sm"
                      onClick={() => { if (confirm('この面談記録を削除しますか？')) deleteMutation.mutate(selectedInterview.id); }}>
                      <MaterialIcon name="delete" size={16} className="text-[var(--status-danger-fg)]" />
                    </Button>
                  </div>
                </div>

                {/* Content sections */}
                <div className="space-y-4">
                  {/* 面談内容 */}
                  <SectionBlock icon={<MaterialIcon name="forum" size={16} />} title="面談内容" color="var(--brand-80)">
                    <p className="text-sm whitespace-pre-wrap">{nl(selectedInterview.interview_content) || '（未入力）'}</p>
                  </SectionBlock>

                  {/* 児童の願い */}
                  {selectedInterview.child_wish && (
                    <SectionBlock icon={<Heart className="h-4 w-4" />} title="児童の願い" color="var(--status-danger-fg)">
                      <p className="text-sm whitespace-pre-wrap">{nl(selectedInterview.child_wish)}</p>
                    </SectionBlock>
                  )}

                  {/* チェック項目 */}
                  <div className="grid gap-3 md:grid-cols-3">
                    {selectedInterview.check_school && (
                      <CheckBlock icon={<MaterialIcon name="school" size={16} />} title="学校での様子"
                        color="var(--status-info-fg)" notes={selectedInterview.check_school_notes} />
                    )}
                    {selectedInterview.check_home && (
                      <CheckBlock icon={<Home className="h-4 w-4" />} title="家庭での様子"
                        color="var(--status-warning-fg)" notes={selectedInterview.check_home_notes} />
                    )}
                    {selectedInterview.check_troubles && (
                      <CheckBlock icon={<MaterialIcon name="warning" size={16} />} title="困りごと・悩み"
                        color="var(--status-danger-fg)" notes={selectedInterview.check_troubles_notes} />
                    )}
                  </div>

                  {/* その他備考 */}
                  {selectedInterview.other_notes && (
                    <SectionBlock icon={<MaterialIcon name="description" size={16} />} title="その他備考" color="var(--neutral-foreground-3)">
                      <p className="text-sm whitespace-pre-wrap">{nl(selectedInterview.other_notes)}</p>
                    </SectionBlock>
                  )}
                </div>
              </CardBody>
            </Card>
          ) : null}
        </div>
      </div>

      {/* Create modal */}
      <Modal isOpen={createModal} onClose={() => setCreateModal(false)} title="新規面談記録" size="lg">
        <InterviewFormComponent
          form={form}
          updateField={updateField}
          onSubmit={() => createMutation.mutate(form)}
          onCancel={() => setCreateModal(false)}
          isLoading={createMutation.isPending}
          submitLabel="作成"
        />
      </Modal>
    </div>
  );
}

// ---------------------------------------------------------------------------
// Section display components
// ---------------------------------------------------------------------------

function SectionBlock({ icon, title, color, children }: {
  icon: React.ReactNode; title: string; color: string; children: React.ReactNode;
}) {
  return (
    <div>
      <div className="flex items-center gap-2 mb-1.5">
        <span style={{ color }}>{icon}</span>
        <h3 className="text-sm font-bold text-[var(--neutral-foreground-1)]">{title}</h3>
      </div>
      <div className="rounded-lg border border-[var(--neutral-stroke-2)] p-3 bg-[var(--neutral-background-1)]">
        {children}
      </div>
    </div>
  );
}

function CheckBlock({ icon, title, color, notes }: {
  icon: React.ReactNode; title: string; color: string; notes: string | null;
}) {
  return (
    <div className="rounded-lg border border-[var(--neutral-stroke-2)] p-3">
      <div className="flex items-center gap-2 mb-1">
        <span style={{ color }}>{icon}</span>
        <span className="text-xs font-semibold text-[var(--neutral-foreground-2)]">{title}</span>
      </div>
      <p className="text-sm text-[var(--neutral-foreground-1)] whitespace-pre-wrap">
        {nl(notes) || '（詳細なし）'}
      </p>
    </div>
  );
}

// ---------------------------------------------------------------------------
// Interview Form (shared between create and edit)
// ---------------------------------------------------------------------------

function InterviewFormComponent({ form, updateField, onSubmit, onCancel, isLoading, submitLabel }: {
  form: InterviewForm;
  updateField: (key: string, value: string | boolean) => void;
  onSubmit: () => void;
  onCancel: () => void;
  isLoading: boolean;
  submitLabel: string;
}) {
  const inputCls = 'block w-full rounded-lg border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-1)] px-3 py-2 text-sm text-[var(--neutral-foreground-1)]';

  return (
    <div className="space-y-4">
      {/* 基本情報 */}
      <div>
        <label className="mb-1 block text-sm font-medium text-[var(--neutral-foreground-2)]">面談日 *</label>
        <input type="date" value={form.interview_date} onChange={(e) => updateField('interview_date', e.target.value)}
          className={inputCls} required />
      </div>

      {/* 面談内容 */}
      <div>
        <label className="mb-1 block text-sm font-medium text-[var(--neutral-foreground-2)]">面談内容 *</label>
        <textarea value={form.interview_content} onChange={(e) => updateField('interview_content', e.target.value)}
          className={inputCls} rows={5} required />
      </div>

      {/* 児童の願い */}
      <div>
        <label className="mb-1 block text-sm font-medium text-[var(--neutral-foreground-2)]">児童の願い</label>
        <textarea value={form.child_wish} onChange={(e) => updateField('child_wish', e.target.value)}
          className={inputCls} rows={3} />
      </div>

      {/* チェック項目 */}
      <h4 className="text-sm font-semibold text-[var(--neutral-foreground-1)] border-b border-[var(--neutral-stroke-2)] pb-2">
        チェック項目
      </h4>

      <CheckFieldGroup
        label="学校での様子" checked={form.check_school} notes={form.check_school_notes}
        onCheck={(v) => updateField('check_school', v)}
        onNotes={(v) => updateField('check_school_notes', v)}
        icon={<MaterialIcon name="school" size={16} className="text-[var(--status-info-fg)]" />}
      />
      <CheckFieldGroup
        label="家庭での様子" checked={form.check_home} notes={form.check_home_notes}
        onCheck={(v) => updateField('check_home', v)}
        onNotes={(v) => updateField('check_home_notes', v)}
        icon={<Home className="h-4 w-4 text-[var(--status-warning-fg)]" />}
      />
      <CheckFieldGroup
        label="困りごと・悩み" checked={form.check_troubles} notes={form.check_troubles_notes}
        onCheck={(v) => updateField('check_troubles', v)}
        onNotes={(v) => updateField('check_troubles_notes', v)}
        icon={<MaterialIcon name="warning" size={16} className="text-[var(--status-danger-fg)]" />}
      />

      {/* その他備考 */}
      <div>
        <label className="mb-1 block text-sm font-medium text-[var(--neutral-foreground-2)]">その他備考</label>
        <textarea value={form.other_notes} onChange={(e) => updateField('other_notes', e.target.value)}
          className={inputCls} rows={3} />
      </div>

      {/* Actions */}
      <div className="flex justify-end gap-2 pt-2">
        <Button variant="secondary" onClick={onCancel}>キャンセル</Button>
        <Button onClick={onSubmit} isLoading={isLoading}>{submitLabel}</Button>
      </div>
    </div>
  );
}

function CheckFieldGroup({ label, checked, notes, onCheck, onNotes, icon }: {
  label: string; checked: boolean; notes: string;
  onCheck: (v: boolean) => void; onNotes: (v: string) => void;
  icon: React.ReactNode;
}) {
  return (
    <div className="rounded-lg border border-[var(--neutral-stroke-2)] p-3">
      <label className="flex items-center gap-2 cursor-pointer">
        <input type="checkbox" checked={checked} onChange={(e) => onCheck(e.target.checked)}
          className="rounded border-[var(--neutral-stroke-2)]" />
        {icon}
        <span className="text-sm font-medium text-[var(--neutral-foreground-2)]">{label}</span>
      </label>
      {checked && (
        <textarea
          value={notes} onChange={(e) => onNotes(e.target.value)}
          className="mt-2 block w-full rounded-lg border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-1)] px-3 py-2 text-sm text-[var(--neutral-foreground-1)]"
          rows={3} placeholder={`${label}について記入...`}
        />
      )}
    </div>
  );
}
