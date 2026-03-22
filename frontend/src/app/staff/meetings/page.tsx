'use client';

import { useState, useEffect } from 'react';
import { useSearchParams } from 'next/navigation';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import api from '@/lib/api';
import { Card, CardHeader, CardTitle, CardBody } from '@/components/ui/Card';
import { Button } from '@/components/ui/Button';
import { Badge } from '@/components/ui/Badge';
import { Modal } from '@/components/ui/Modal';
import { Input } from '@/components/ui/Input';
import { SkeletonList } from '@/components/ui/Skeleton';
import { useToast } from '@/components/ui/Toast';
import { formatDate } from '@/lib/utils';
import { Plus, Users, Calendar } from 'lucide-react';

interface MeetingRequest {
  id: number;
  classroom_id: number;
  student_id: number;
  guardian_id: number | null;
  staff_id: number | null;
  purpose: string;
  purpose_detail: string | null;
  candidate_dates: string[];
  confirmed_date: string | null;
  status: 'pending' | 'confirmed' | 'cancelled' | 'guardian_counter';
  meeting_notes: string | null;
  meeting_guidance: string | null;
  student?: { id: number; student_name: string };
  guardian?: { id: number; full_name: string };
  created_at: string;
}

interface Student {
  id: number;
  student_name: string;
}

interface Guardian {
  id: number;
  full_name: string;
}

const emptyForm = { purpose: '', purpose_detail: '', candidate_dates: [''], student_id: '', guardian_id: '' };

export default function MeetingsPage() {
  const queryClient = useQueryClient();
  const toast = useToast();
  const searchParams = useSearchParams();
  const [showCreate, setShowCreate] = useState(false);
  const [form, setForm] = useState(emptyForm);

  // チャットから面談予約ボタン経由で遷移した場合、自動でモーダルを開く
  useEffect(() => {
    if (searchParams.get('action') === 'create') {
      const studentId = searchParams.get('student_id') || '';
      const guardianId = searchParams.get('guardian_id') || '';
      setForm({ ...emptyForm, student_id: studentId, guardian_id: guardianId });
      setShowCreate(true);
    }
  }, [searchParams]);

  const { data: meetings, isLoading } = useQuery({
    queryKey: ['staff', 'meetings'],
    queryFn: async () => {
      const response = await api.get<{ data: MeetingRequest[] }>('/api/staff/meetings');
      const data = response.data.data;
      return Array.isArray(data) ? data : [];
    },
  });

  const { data: students = [] } = useQuery({
    queryKey: ['staff', 'students-list'],
    queryFn: async () => {
      const res = await api.get<{ data: Student[] }>('/api/staff/students');
      return res.data.data;
    },
    enabled: showCreate,
  });

  const { data: guardians = [] } = useQuery({
    queryKey: ['staff', 'guardians-list'],
    queryFn: async () => {
      const res = await api.get<{ data: Guardian[] }>('/api/staff/students/guardians');
      return Array.isArray(res.data.data) ? res.data.data : [];
    },
    enabled: showCreate,
  });

  const createMutation = useMutation({
    mutationFn: async () => {
      await api.post('/api/staff/meetings', {
        ...form,
        student_id: Number(form.student_id),
        guardian_id: Number(form.guardian_id),
        candidate_dates: form.candidate_dates.filter((d) => d),
      });
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['staff', 'meetings'] });
      setShowCreate(false);
      setForm(emptyForm);
      toast.success('面談予定を作成しました');
    },
    onError: () => toast.error('作成に失敗しました'),
  });

  const closeModal = () => { setShowCreate(false); setForm(emptyForm); };

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-bold text-[var(--neutral-foreground-1)]">面談管理</h1>
        <Button leftIcon={<Plus className="h-4 w-4" />} onClick={() => setShowCreate(true)}>
          新規作成
        </Button>
      </div>

      {isLoading ? (
        <SkeletonList items={4} />
      ) : meetings && meetings.length > 0 ? (
        <div className="space-y-3">
          {meetings.map((meeting) => (
            <Card key={meeting.id}>
              <CardHeader>
                <div className="flex items-center gap-2">
                  <CardTitle>{meeting.purpose}</CardTitle>
                  <Badge variant={
                    meeting.status === 'confirmed' ? 'success' :
                    meeting.status === 'cancelled' ? 'danger' :
                    meeting.status === 'guardian_counter' ? 'info' : 'warning'
                  }>
                    {meeting.status === 'confirmed' ? '確定' : meeting.status === 'cancelled' ? 'キャンセル' : meeting.status === 'guardian_counter' ? '保護者対案' : '未確定'}
                  </Badge>
                </div>
              </CardHeader>
              <CardBody>
                <div className="flex items-center gap-4 text-sm text-[var(--neutral-foreground-3)]">
                  {meeting.confirmed_date && (
                    <div className="flex items-center gap-1">
                      <Calendar className="h-4 w-4" />
                      <span>{formatDate(meeting.confirmed_date)}</span>
                    </div>
                  )}
                  {meeting.student && (
                    <div className="flex items-center gap-1">
                      <Users className="h-4 w-4" />
                      <span>{meeting.student.student_name}</span>
                    </div>
                  )}
                </div>
                {meeting.purpose_detail && (
                  <p className="mt-2 text-sm text-[var(--neutral-foreground-2)]">{meeting.purpose_detail}</p>
                )}
                {meeting.candidate_dates && meeting.candidate_dates.length > 0 && !meeting.confirmed_date && (
                  <div className="mt-2 text-xs text-[var(--neutral-foreground-3)]">
                    候補日: {meeting.candidate_dates.map((d) => formatDate(d)).join(', ')}
                  </div>
                )}
              </CardBody>
            </Card>
          ))}
        </div>
      ) : (
        <Card><CardBody><p className="py-8 text-center text-sm text-[var(--neutral-foreground-3)]">面談予定がありません</p></CardBody></Card>
      )}

      <Modal isOpen={showCreate} onClose={closeModal} title="面談予定を作成" size="lg">
        <div className="space-y-4">
          <div>
            <label className="mb-1 block text-sm font-medium text-[var(--neutral-foreground-2)]">生徒 *</label>
            <select
              value={form.student_id}
              onChange={(e) => setForm({ ...form, student_id: e.target.value, guardian_id: '' })}
              className="block w-full rounded-lg border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-1)] px-3 py-2 text-sm"
            >
              <option value="">-- 生徒を選択 --</option>
              {students.map((s) => (
                <option key={s.id} value={s.id}>{s.student_name}</option>
              ))}
            </select>
          </div>
          {form.student_id && (
            <div>
              <label className="mb-1 block text-sm font-medium text-[var(--neutral-foreground-2)]">保護者 *</label>
              <select
                value={form.guardian_id}
                onChange={(e) => setForm({ ...form, guardian_id: e.target.value })}
                className="block w-full rounded-lg border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-1)] px-3 py-2 text-sm"
              >
                <option value="">-- 保護者を選択 --</option>
                {guardians.map((g) => (
                  <option key={g.id} value={g.id}>{g.full_name}</option>
                ))}
              </select>
            </div>
          )}
          <Input label="目的 *" value={form.purpose} onChange={(e) => setForm({ ...form, purpose: e.target.value })} />
          <div>
            <label className="mb-1 block text-sm font-medium text-[var(--neutral-foreground-2)]">詳細</label>
            <textarea value={form.purpose_detail} onChange={(e) => setForm({ ...form, purpose_detail: e.target.value })}
              className="block w-full rounded-lg border border-[var(--neutral-stroke-2)] px-3 py-2 text-sm focus:border-[var(--brand-80)] focus:outline-none focus:ring-2 focus:ring-[var(--brand-80)]/20" rows={4} />
          </div>
          <div>
            <label className="mb-1 block text-sm font-medium text-[var(--neutral-foreground-2)]">候補日 *</label>
            {form.candidate_dates.map((d, i) => (
              <div key={i} className="mb-2 flex items-center gap-2">
                <input type="datetime-local" value={d}
                  onChange={(e) => { const dates = [...form.candidate_dates]; dates[i] = e.target.value; setForm({ ...form, candidate_dates: dates }); }}
                  className="flex-1 rounded-lg border border-[var(--neutral-stroke-2)] px-3 py-2 text-sm" />
                {form.candidate_dates.length > 1 && (
                  <Button variant="ghost" size="sm" onClick={() => setForm({ ...form, candidate_dates: form.candidate_dates.filter((_, idx) => idx !== i) })}>削除</Button>
                )}
              </div>
            ))}
            {form.candidate_dates.length < 3 && (
              <Button variant="ghost" size="sm" onClick={() => setForm({ ...form, candidate_dates: [...form.candidate_dates, ''] })}>候補日を追加</Button>
            )}
          </div>
          <div className="flex justify-end gap-2">
            <Button variant="ghost" onClick={closeModal}>キャンセル</Button>
            <Button
              onClick={() => createMutation.mutate()}
              isLoading={createMutation.isPending}
              disabled={!form.purpose || !form.student_id || !form.guardian_id || !form.candidate_dates[0]}
            >
              作成
            </Button>
          </div>
        </div>
      </Modal>
    </div>
  );
}
