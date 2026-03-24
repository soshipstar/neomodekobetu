'use client';

import { useState } from 'react';
import { useParams } from 'next/navigation';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import api from '@/lib/api';
import { nl } from '@/lib/utils';
import { Card, CardHeader, CardTitle } from '@/components/ui/Card';
import { Button } from '@/components/ui/Button';
import { Input } from '@/components/ui/Input';
import { Badge } from '@/components/ui/Badge';
import { Modal } from '@/components/ui/Modal';
import { SkeletonList } from '@/components/ui/Skeleton';
import { useToast } from '@/components/ui/Toast';
import { format } from 'date-fns';
import { ja } from 'date-fns/locale';
import Link from 'next/link';
import { MaterialIcon } from '@/components/ui/MaterialIcon';

interface Interview {
  id: number;
  student_id: number;
  classroom_id: number;
  interviewer_id: number;
  interview_date: string;
  interview_content: string | null;
  child_wish: string | null;
  check_school: boolean;
  check_school_notes: string | null;
  check_home: boolean;
  check_home_notes: string | null;
  check_troubles: boolean;
  check_troubles_notes: string | null;
  other_notes: string | null;
  interviewer?: { id: number; full_name: string };
  created_at: string;
}

interface StudentInfo {
  id: number;
  student_name: string;
}

// No status labels or check categories needed - backend uses flat fields

export default function StudentInterviewPage() {
  const params = useParams();
  const studentId = params.id as string;
  const queryClient = useQueryClient();
  const toast = useToast();
  const [modalOpen, setModalOpen] = useState(false);
  const [expandedId, setExpandedId] = useState<number | null>(null);
  const [form, setForm] = useState({
    interview_date: format(new Date(), 'yyyy-MM-dd'),
    interview_content: '',
    child_wish: '',
    check_school: false,
    check_school_notes: '',
    check_home: false,
    check_home_notes: '',
    check_troubles: false,
    check_troubles_notes: '',
    other_notes: '',
  });

  const { data: student } = useQuery({
    queryKey: ['staff', 'student', studentId],
    queryFn: async () => {
      const res = await api.get<{ data: StudentInfo }>(`/api/staff/students/${studentId}`);
      return res.data.data;
    },
  });

  const { data: interviews = [], isLoading } = useQuery({
    queryKey: ['staff', 'student', studentId, 'interviews'],
    queryFn: async () => {
      const res = await api.get<{ data: Interview[] }>(`/api/staff/students/${studentId}/interviews`);
      return res.data.data;
    },
  });

  const createMutation = useMutation({
    mutationFn: (data: typeof form) => api.post(`/api/staff/students/${studentId}/interviews`, data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['staff', 'student', studentId, 'interviews'] });
      toast.success('面談記録を作成しました');
      setModalOpen(false);
    },
    onError: () => toast.error('作成に失敗しました'),
  });

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <div>
          <Link href={`/staff/students/${studentId}`} className="text-sm text-[var(--brand-80)] hover:underline">
            &larr; {student?.student_name || '生徒詳細'}に戻る
          </Link>
          <h1 className="mt-1 text-2xl font-bold text-[var(--neutral-foreground-1)]">面談記録</h1>
        </div>
        <Button onClick={() => setModalOpen(true)} leftIcon={<MaterialIcon name="add" size={16} />}>
          新規面談記録
        </Button>
      </div>

      {/* Interview History */}
      {isLoading ? (
        <SkeletonList items={3} />
      ) : interviews.length === 0 ? (
        <Card>
          <div className="py-12 text-center">
            <MaterialIcon name="description" size={48} className="mx-auto text-[var(--neutral-foreground-4)]" />
            <p className="mt-2 text-sm text-[var(--neutral-foreground-3)]">面談記録がありません</p>
          </div>
        </Card>
      ) : (
        <div className="space-y-4">
          {interviews.map((interview) => {
            const isExpanded = expandedId === interview.id;
            return (
              <Card key={interview.id}>
                <button
                  onClick={() => setExpandedId(isExpanded ? null : interview.id)}
                  className="flex w-full items-center justify-between text-left"
                >
                  <div className="flex items-center gap-3">
                    <div className="flex h-10 w-10 items-center justify-center rounded-full bg-[var(--neutral-background-3)]">
                      <MaterialIcon name="calendar_month" size={20} className="text-[var(--brand-80)]" />
                    </div>
                    <div>
                      <p className="font-medium text-[var(--neutral-foreground-1)]">
                        {format(new Date(interview.interview_date), 'yyyy年M月d日(E)', { locale: ja })}
                      </p>
                      <p className="text-sm text-[var(--neutral-foreground-3)]">担当: {interview.interviewer?.full_name || '-'}</p>
                    </div>
                  </div>
                  {isExpanded ? <MaterialIcon name="expand_less" size={20} className="text-[var(--neutral-foreground-4)]" /> : <MaterialIcon name="expand_more" size={20} className="text-[var(--neutral-foreground-4)]" />}
                </button>

                {isExpanded && (
                  <div className="mt-4 space-y-4 border-t border-[var(--neutral-stroke-2)] pt-4">
                    {interview.child_wish && (
                      <div className="rounded-lg bg-[var(--brand-160)] p-3">
                        <p className="text-xs font-medium text-[var(--brand-80)]">お子さまの願い</p>
                        <p className="mt-1 text-sm text-[var(--neutral-foreground-2)]">{interview.child_wish}</p>
                      </div>
                    )}

                    {interview.interview_content && (
                      <div className="rounded-lg bg-[var(--neutral-background-2)] p-3">
                        <p className="text-xs font-medium text-[var(--neutral-foreground-2)]">面談内容</p>
                        <p className="mt-1 text-sm text-[var(--neutral-foreground-2)] whitespace-pre-wrap">{nl(interview.interview_content)}</p>
                      </div>
                    )}

                    {/* Check items */}
                    <div className="space-y-2">
                      {interview.check_school && (
                        <div className="rounded border border-[var(--neutral-stroke-3)] px-3 py-2">
                          <div className="flex items-center gap-2">
                            <Badge variant="success">学校</Badge>
                            {interview.check_school_notes && <span className="text-sm text-[var(--neutral-foreground-2)]">{interview.check_school_notes}</span>}
                          </div>
                        </div>
                      )}
                      {interview.check_home && (
                        <div className="rounded border border-[var(--neutral-stroke-3)] px-3 py-2">
                          <div className="flex items-center gap-2">
                            <Badge variant="success">家庭</Badge>
                            {interview.check_home_notes && <span className="text-sm text-[var(--neutral-foreground-2)]">{interview.check_home_notes}</span>}
                          </div>
                        </div>
                      )}
                      {interview.check_troubles && (
                        <div className="rounded border border-[var(--neutral-stroke-3)] px-3 py-2">
                          <div className="flex items-center gap-2">
                            <Badge variant="warning">困りごと</Badge>
                            {interview.check_troubles_notes && <span className="text-sm text-[var(--neutral-foreground-2)]">{interview.check_troubles_notes}</span>}
                          </div>
                        </div>
                      )}
                    </div>

                    {interview.other_notes && (
                      <div className="rounded-lg bg-[var(--neutral-background-2)] p-3">
                        <p className="text-xs font-medium text-[var(--neutral-foreground-2)]">その他</p>
                        <p className="mt-1 text-sm text-[var(--neutral-foreground-2)] whitespace-pre-wrap">{nl(interview.other_notes)}</p>
                      </div>
                    )}
                  </div>
                )}
              </Card>
            );
          })}
        </div>
      )}

      {/* New Interview Modal */}
      <Modal isOpen={modalOpen} onClose={() => setModalOpen(false)} title="新規面談記録" size="full">
        <form onSubmit={(e) => { e.preventDefault(); createMutation.mutate(form); }} className="space-y-6">
          <Input label="面談日" type="date" value={form.interview_date} onChange={(e) => setForm({ ...form, interview_date: e.target.value })} required />

          <div>
            <label className="mb-1 block text-sm font-medium text-[var(--neutral-foreground-2)]">面談内容</label>
            <textarea
              value={form.interview_content}
              onChange={(e) => setForm({ ...form, interview_content: e.target.value })}
              className="block w-full rounded-lg border border-[var(--neutral-stroke-2)] px-3 py-2 text-sm"
              rows={3}
              placeholder="面談の内容を入力..."
            />
          </div>

          <div>
            <label className="mb-1 block text-sm font-medium text-[var(--neutral-foreground-2)]">お子さまの願い</label>
            <textarea
              value={form.child_wish}
              onChange={(e) => setForm({ ...form, child_wish: e.target.value })}
              className="block w-full rounded-lg border border-[var(--neutral-stroke-2)] px-3 py-2 text-sm"
              rows={3}
              placeholder="お子さまが希望していること、やりたいことなど..."
            />
          </div>

          {/* Check Items */}
          <div className="space-y-4">
            <h3 className="text-sm font-semibold text-[var(--neutral-foreground-1)]">チェック項目</h3>
            <div className="flex items-center gap-3">
              <label className="flex items-center gap-2 text-sm">
                <input type="checkbox" checked={form.check_school} onChange={(e) => setForm({ ...form, check_school: e.target.checked })} />
                学校
              </label>
              {form.check_school && (
                <input type="text" value={form.check_school_notes} onChange={(e) => setForm({ ...form, check_school_notes: e.target.value })}
                  placeholder="学校に関するメモ" className="flex-1 rounded border border-[var(--neutral-stroke-2)] px-2 py-1 text-sm" />
              )}
            </div>
            <div className="flex items-center gap-3">
              <label className="flex items-center gap-2 text-sm">
                <input type="checkbox" checked={form.check_home} onChange={(e) => setForm({ ...form, check_home: e.target.checked })} />
                家庭
              </label>
              {form.check_home && (
                <input type="text" value={form.check_home_notes} onChange={(e) => setForm({ ...form, check_home_notes: e.target.value })}
                  placeholder="家庭に関するメモ" className="flex-1 rounded border border-[var(--neutral-stroke-2)] px-2 py-1 text-sm" />
              )}
            </div>
            <div className="flex items-center gap-3">
              <label className="flex items-center gap-2 text-sm">
                <input type="checkbox" checked={form.check_troubles} onChange={(e) => setForm({ ...form, check_troubles: e.target.checked })} />
                困りごと
              </label>
              {form.check_troubles && (
                <input type="text" value={form.check_troubles_notes} onChange={(e) => setForm({ ...form, check_troubles_notes: e.target.value })}
                  placeholder="困りごとに関するメモ" className="flex-1 rounded border border-[var(--neutral-stroke-2)] px-2 py-1 text-sm" />
              )}
            </div>
          </div>

          <div>
            <label className="mb-1 block text-sm font-medium text-[var(--neutral-foreground-2)]">その他</label>
            <textarea
              value={form.other_notes}
              onChange={(e) => setForm({ ...form, other_notes: e.target.value })}
              className="block w-full rounded-lg border border-[var(--neutral-stroke-2)] px-3 py-2 text-sm"
              rows={3}
            />
          </div>

          <div className="flex justify-end gap-2 pt-2">
            <Button variant="secondary" type="button" onClick={() => setModalOpen(false)}>キャンセル</Button>
            <Button type="submit" isLoading={createMutation.isPending}>保存</Button>
          </div>
        </form>
      </Modal>
    </div>
  );
}
