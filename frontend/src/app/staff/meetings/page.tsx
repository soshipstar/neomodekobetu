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
import { format } from 'date-fns';
import { ja } from 'date-fns/locale';
import { Plus, Users, Calendar, CalendarCheck, CalendarClock, X, Check, ArrowRightLeft, FileText } from 'lucide-react';

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
  confirmed_by: string | null;
  confirmed_at: string | null;
  status: 'pending' | 'confirmed' | 'cancelled' | 'guardian_counter' | 'staff_counter';
  meeting_notes: string | null;
  meeting_guidance: string | null;
  guardian_counter_message: string | null;
  staff_counter_message: string | null;
  related_plan_id: number | null;
  related_monitoring_id: number | null;
  is_completed: boolean;
  student?: { id: number; student_name: string };
  guardian?: { id: number; full_name: string };
  staff?: { id: number; full_name: string };
  created_at: string;
}

interface Student { id: number; student_name: string; }
interface Guardian { id: number; full_name: string; }

const STATUS_MAP: Record<string, { label: string; variant: 'success' | 'danger' | 'info' | 'warning' | 'default' }> = {
  pending: { label: '回答待ち', variant: 'warning' },
  guardian_counter: { label: '保護者対案あり', variant: 'info' },
  staff_counter: { label: 'スタッフ再提案中', variant: 'warning' },
  confirmed: { label: '確定', variant: 'success' },
  cancelled: { label: 'キャンセル', variant: 'danger' },
};

const emptyForm = { purpose: '', purpose_detail: '', meeting_notes: '', meeting_guidance: '', candidate_dates: [''], student_id: '', guardian_id: '', confirmed_date: '', create_mode: 'candidate' as 'candidate' | 'direct' };

function fmtDate(d: string) {
  try { return format(new Date(d), 'yyyy年M月d日(E) HH:mm', { locale: ja }); } catch { return d; }
}

export default function MeetingsPage() {
  const queryClient = useQueryClient();
  const toast = useToast();
  const searchParams = useSearchParams();
  const [showCreate, setShowCreate] = useState(false);
  const [form, setForm] = useState(emptyForm);
  const [detailMeeting, setDetailMeeting] = useState<MeetingRequest | null>(null);
  const [counterDates, setCounterDates] = useState<string[]>(['']);
  const [counterMessage, setCounterMessage] = useState('');
  const [showCounterForm, setShowCounterForm] = useState(false);
  const [meetingNotesEdit, setMeetingNotesEdit] = useState('');
  const [hearingNotes, setHearingNotes] = useState('');
  const [kakehashiResult, setKakehashiResult] = useState<{ data: Record<string, string>; period: { period_name: string; submission_deadline: string } } | null>(null);

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
      const res = await api.get('/api/staff/meetings', { params: { per_page: 100 } });
      const payload = res.data?.data;
      const data = Array.isArray(payload) ? payload : (payload?.data ?? []);
      return Array.isArray(data) ? data : [];
    },
  });

  const { data: students = [] } = useQuery({
    queryKey: ['staff', 'students-list'],
    queryFn: async () => { const res = await api.get<{ data: Student[] }>('/api/staff/students'); return res.data.data; },
    enabled: showCreate,
  });

  const { data: guardians = [] } = useQuery({
    queryKey: ['staff', 'guardians-list'],
    queryFn: async () => { const res = await api.get<{ data: Guardian[] }>('/api/staff/students/guardians'); return Array.isArray(res.data.data) ? res.data.data : []; },
    enabled: showCreate,
  });

  const createMutation = useMutation({
    mutationFn: async () => {
      const payload: Record<string, unknown> = {
        student_id: Number(form.student_id),
        guardian_id: Number(form.guardian_id),
        purpose: form.purpose,
        purpose_detail: form.purpose_detail || null,
        meeting_notes: form.meeting_notes || null,
        meeting_guidance: form.meeting_guidance || null,
      };
      if (form.create_mode === 'direct' && form.confirmed_date) {
        payload.confirmed_date = form.confirmed_date;
        payload.candidate_dates = [];
      } else {
        payload.candidate_dates = form.candidate_dates.filter((d) => d);
      }
      await api.post('/api/staff/meetings', payload);
    },
    onSuccess: () => { queryClient.invalidateQueries({ queryKey: ['staff', 'meetings'] }); setShowCreate(false); setForm(emptyForm); toast.success('面談予約を送信しました'); },
    onError: () => toast.error('作成に失敗しました'),
  });

  const updateMutation = useMutation({
    mutationFn: async (payload: Record<string, unknown>) => {
      return api.put(`/api/staff/meetings/${detailMeeting!.id}`, payload);
    },
    onSuccess: (res) => {
      queryClient.invalidateQueries({ queryKey: ['staff', 'meetings'] });
      setDetailMeeting(res.data?.data ?? null);
      setShowCounterForm(false);
      setCounterDates(['']);
      setCounterMessage('');
      toast.success('更新しました');
    },
    onError: () => toast.error('更新に失敗しました'),
  });

  const kakehashiMutation = useMutation({
    mutationFn: async () => {
      const res = await api.post(`/api/staff/meetings/${detailMeeting!.id}/generate-kakehashi`, { hearing_notes: hearingNotes });
      return res.data;
    },
    onSuccess: (res) => {
      setKakehashiResult({ data: res.data, period: res.period });
      toast.success(res.message || '保護者かけはしに反映しました');
    },
    onError: (err: any) => {
      toast.error(err?.response?.data?.message || 'かけはし生成に失敗しました');
    },
  });

  const closeCreate = () => { setShowCreate(false); setForm(emptyForm); };
  const closeDetail = () => { setDetailMeeting(null); setShowCounterForm(false); setKakehashiResult(null); setHearingNotes(''); };

  const handleConfirmDate = (date: string) => {
    if (!confirm(`${fmtDate(date)} で確定しますか？`)) return;
    updateMutation.mutate({ action: 'confirm', confirmed_date: date });
  };

  const handleCounter = () => {
    const dates = counterDates.filter((d) => d);
    if (dates.length === 0) { toast.error('候補日を入力してください'); return; }
    updateMutation.mutate({ action: 'counter', candidate_dates: dates, staff_counter_message: counterMessage || null });
  };

  const handleCancel = () => {
    if (!confirm('この面談予約をキャンセルしますか？')) return;
    updateMutation.mutate({ action: 'cancel' });
  };

  // Sync meetingNotesEdit when detail meeting changes
  useEffect(() => {
    if (detailMeeting) setMeetingNotesEdit(detailMeeting.meeting_notes || '');
  }, [detailMeeting?.id, detailMeeting?.meeting_notes]);

  // Open detail from URL param
  useEffect(() => {
    const meetingId = searchParams.get('meeting_id');
    if (meetingId && meetings) {
      const m = meetings.find((x) => x.id === Number(meetingId));
      if (m) setDetailMeeting(m);
    }
  }, [searchParams, meetings]);

  const selectCls = 'block w-full rounded-lg border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-1)] px-3 py-2 text-sm';
  const textareaCls = 'block w-full rounded-lg border border-[var(--neutral-stroke-2)] px-3 py-2 text-sm focus:border-[var(--brand-80)] focus:outline-none focus:ring-2 focus:ring-[var(--brand-80)]/20';
  const inputCls = 'flex-1 rounded-lg border border-[var(--neutral-stroke-2)] px-3 py-2 text-sm';

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-bold text-[var(--neutral-foreground-1)]">面談管理</h1>
        <Button leftIcon={<Plus className="h-4 w-4" />} onClick={() => setShowCreate(true)}>新規作成</Button>
      </div>

      {/* Upcoming confirmed meetings */}
      {!isLoading && meetings && (() => {
        const today = new Date().toISOString().split('T')[0];
        const upcoming = meetings.filter((m) => m.status === 'confirmed' && m.confirmed_date && m.confirmed_date >= today)
          .sort((a, b) => (a.confirmed_date! > b.confirmed_date! ? 1 : -1));
        if (upcoming.length === 0) return null;
        return (
          <Card>
            <CardHeader><CardTitle><CalendarCheck className="inline h-5 w-5 mr-1 text-green-600" />今後の面談予定</CardTitle></CardHeader>
            <CardBody>
              <div className="space-y-2">
                {upcoming.map((m) => (
                  <div key={m.id} onClick={() => setDetailMeeting(m)} className="flex items-center justify-between rounded-lg border border-green-200 bg-green-50 p-3 cursor-pointer hover:bg-green-100 transition-colors">
                    <div>
                      <p className="font-semibold text-green-800">{fmtDate(m.confirmed_date!)}</p>
                      <p className="text-sm text-green-700">{m.purpose}</p>
                    </div>
                    <div className="text-right text-sm text-green-600">
                      <p>{m.student?.student_name}</p>
                      <p className="text-xs">{m.guardian?.full_name}</p>
                    </div>
                  </div>
                ))}
              </div>
            </CardBody>
          </Card>
        );
      })()}

      {isLoading ? <SkeletonList items={4} /> : meetings && meetings.length > 0 ? (
        <div className="space-y-3">
          {meetings.map((m) => {
            const st = STATUS_MAP[m.status] || { label: m.status, variant: 'default' as const };
            return (
              <Card key={m.id} className="cursor-pointer hover:shadow-md transition-shadow" onClick={() => setDetailMeeting(m)}>
                <CardBody>
                  <div className="flex items-center justify-between">
                    <div className="flex items-center gap-2">
                      <span className="font-semibold text-[var(--neutral-foreground-1)]">{m.purpose}</span>
                      <Badge variant={st.variant}>{st.label}</Badge>
                    </div>
                    <span className="text-xs text-[var(--neutral-foreground-4)]">{format(new Date(m.created_at), 'yyyy/MM/dd')}</span>
                  </div>
                  <div className="mt-2 flex items-center gap-4 text-sm text-[var(--neutral-foreground-3)]">
                    {m.confirmed_date && <span className="flex items-center gap-1"><CalendarCheck className="h-4 w-4 text-green-600" />{fmtDate(m.confirmed_date)}</span>}
                    {m.student && <span className="flex items-center gap-1"><Users className="h-4 w-4" />{m.student.student_name}</span>}
                    {m.guardian && <span>{m.guardian.full_name}</span>}
                  </div>
                  {!m.confirmed_date && m.candidate_dates?.length > 0 && (
                    <div className="mt-1 text-xs text-[var(--neutral-foreground-4)]">
                      候補日: {m.candidate_dates.map((d: string) => fmtDate(d)).join(' / ')}
                    </div>
                  )}
                </CardBody>
              </Card>
            );
          })}
        </div>
      ) : (
        <Card><CardBody><p className="py-8 text-center text-sm text-[var(--neutral-foreground-3)]">面談予定がありません</p></CardBody></Card>
      )}

      {/* ===== Create Modal ===== */}
      <Modal isOpen={showCreate} onClose={closeCreate} title="面談予約を作成" size="lg">
        <div className="space-y-4">
          <div>
            <label className="mb-1 block text-sm font-medium text-[var(--neutral-foreground-2)]">生徒 *</label>
            <select value={form.student_id} onChange={(e) => setForm({ ...form, student_id: e.target.value, guardian_id: '' })} className={selectCls}>
              <option value="">-- 生徒を選択 --</option>
              {students.map((s) => <option key={s.id} value={s.id}>{s.student_name}</option>)}
            </select>
          </div>
          {form.student_id && (
            <div>
              <label className="mb-1 block text-sm font-medium text-[var(--neutral-foreground-2)]">保護者 *</label>
              <select value={form.guardian_id} onChange={(e) => setForm({ ...form, guardian_id: e.target.value })} className={selectCls}>
                <option value="">-- 保護者を選択 --</option>
                {guardians.map((g) => <option key={g.id} value={g.id}>{g.full_name}</option>)}
              </select>
            </div>
          )}
          <Input label="目的 *" value={form.purpose} onChange={(e) => setForm({ ...form, purpose: e.target.value })} />
          <div>
            <label className="mb-1 block text-sm font-medium text-[var(--neutral-foreground-2)]">詳細</label>
            <textarea value={form.purpose_detail} onChange={(e) => setForm({ ...form, purpose_detail: e.target.value })} className={textareaCls} rows={3} />
          </div>
          <div>
            <label className="mb-1 block text-sm font-medium text-[var(--neutral-foreground-2)]">面談メモ（持ち物・注意事項など）</label>
            <textarea value={form.meeting_notes} onChange={(e) => setForm({ ...form, meeting_notes: e.target.value })} className={textareaCls} rows={2} placeholder="持ち物や注意事項があれば記入" />
          </div>
          <div>
            <label className="mb-1 block text-sm font-medium text-[var(--neutral-foreground-2)]">保護者へのご案内</label>
            <textarea value={form.meeting_guidance} onChange={(e) => setForm({ ...form, meeting_guidance: e.target.value })} className={textareaCls} rows={2} placeholder="保護者に伝えるご案内があれば記入" />
          </div>
          {/* 作成モード選択 */}
          <div>
            <label className="mb-2 block text-sm font-medium text-[var(--neutral-foreground-2)]">日程設定方法 *</label>
            <div className="flex gap-3">
              <label className="flex items-center gap-1.5 text-sm cursor-pointer">
                <input type="radio" checked={form.create_mode === 'candidate'} onChange={() => setForm({ ...form, create_mode: 'candidate' })} className="text-purple-600" />
                候補日を提示して保護者に選んでもらう
              </label>
              <label className="flex items-center gap-1.5 text-sm cursor-pointer">
                <input type="radio" checked={form.create_mode === 'direct'} onChange={() => setForm({ ...form, create_mode: 'direct' })} className="text-purple-600" />
                日程を確定して通知する
              </label>
            </div>
          </div>
          {form.create_mode === 'candidate' ? (
            <div>
              <label className="mb-1 block text-sm font-medium text-[var(--neutral-foreground-2)]">候補日 *</label>
              {form.candidate_dates.map((d, i) => (
                <div key={i} className="mb-2 flex items-center gap-2">
                  <input type="datetime-local" value={d} onChange={(e) => { const dates = [...form.candidate_dates]; dates[i] = e.target.value; setForm({ ...form, candidate_dates: dates }); }} className={inputCls} />
                  {form.candidate_dates.length > 1 && <Button variant="ghost" size="sm" onClick={() => setForm({ ...form, candidate_dates: form.candidate_dates.filter((_, idx) => idx !== i) })}>削除</Button>}
                </div>
              ))}
              {form.candidate_dates.length < 3 && <Button variant="ghost" size="sm" onClick={() => setForm({ ...form, candidate_dates: [...form.candidate_dates, ''] })}>候補日を追加</Button>}
            </div>
          ) : (
            <div>
              <label className="mb-1 block text-sm font-medium text-[var(--neutral-foreground-2)]">面談日時 *</label>
              <input type="datetime-local" value={form.confirmed_date} onChange={(e) => setForm({ ...form, confirmed_date: e.target.value })} className={inputCls + ' w-full'} />
              <p className="mt-1 text-xs text-[var(--neutral-foreground-4)]">確定した日時で保護者にチャット通知されます</p>
            </div>
          )}
          <div className="flex justify-end gap-2">
            <Button variant="ghost" onClick={closeCreate}>キャンセル</Button>
            <Button onClick={() => createMutation.mutate()} isLoading={createMutation.isPending}
              disabled={!form.purpose || !form.student_id || !form.guardian_id || (form.create_mode === 'candidate' ? !form.candidate_dates[0] : !form.confirmed_date)}>
              {form.create_mode === 'direct' ? '確定して通知' : '候補日を送信'}
            </Button>
          </div>
        </div>
      </Modal>

      {/* ===== Detail Modal ===== */}
      <Modal isOpen={!!detailMeeting} onClose={closeDetail} title="面談詳細" size="lg">
        {detailMeeting && (() => {
          const m = detailMeeting;
          const st = STATUS_MAP[m.status] || { label: m.status, variant: 'default' as const };
          const isActionable = m.status !== 'confirmed' && m.status !== 'cancelled';
          const needsStaffAction = m.status === 'guardian_counter';

          return (
            <div className="space-y-5">
              {/* Status & Basic Info */}
              <div className="rounded-lg bg-[var(--neutral-background-3)] p-4 space-y-2 text-sm">
                <div className="flex items-center justify-between">
                  <Badge variant={st.variant}>{st.label}</Badge>
                  <span className="text-xs text-[var(--neutral-foreground-4)]">{format(new Date(m.created_at), 'yyyy/MM/dd HH:mm')}</span>
                </div>
                <div><span className="font-medium">目的:</span> {m.purpose}</div>
                {m.purpose_detail && <div><span className="font-medium">詳細:</span> {m.purpose_detail}</div>}
                <div className="flex gap-4">
                  <span><Users className="inline h-4 w-4 mr-1" />{m.student?.student_name}</span>
                  <span>保護者: {m.guardian?.full_name}</span>
                  {m.staff && <span>担当: {m.staff.full_name}</span>}
                </div>
                {m.meeting_notes && <div className="mt-2 rounded border border-amber-200 bg-amber-50 p-2 text-xs text-amber-800"><FileText className="inline h-3 w-3 mr-1" />メモ: {m.meeting_notes}</div>}
              </div>

              {/* Confirmed Date */}
              {m.confirmed_date && (
                <div className="rounded-lg border-2 border-green-300 bg-green-50 p-4 text-center">
                  <CalendarCheck className="mx-auto mb-2 h-8 w-8 text-green-600" />
                  <p className="text-lg font-bold text-green-800">{fmtDate(m.confirmed_date)}</p>
                  {m.confirmed_by && <p className="text-xs text-green-600 mt-1">{m.confirmed_by === 'guardian' ? '保護者' : 'スタッフ'}が確定 ({m.confirmed_at ? format(new Date(m.confirmed_at), 'yyyy/MM/dd HH:mm') : ''})</p>}
                </div>
              )}

              {/* Candidate Dates (clickable to confirm for staff) */}
              {!m.confirmed_date && m.candidate_dates?.length > 0 && (
                <div>
                  <h4 className="mb-2 text-sm font-semibold text-[var(--neutral-foreground-2)]">
                    <CalendarClock className="inline h-4 w-4 mr-1" />
                    {needsStaffAction ? '保護者からの候補日（クリックで確定）' : '提案中の候補日'}
                  </h4>
                  <div className="space-y-2">
                    {m.candidate_dates.map((d, i) => (
                      <button
                        key={i}
                        onClick={() => needsStaffAction && handleConfirmDate(d)}
                        disabled={!needsStaffAction || updateMutation.isPending}
                        className={`w-full rounded-lg border p-3 text-left text-sm transition-colors ${
                          needsStaffAction
                            ? 'border-purple-300 bg-purple-50 hover:bg-purple-100 cursor-pointer'
                            : 'border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-1)]'
                        }`}
                      >
                        <span className="font-medium">{['①', '②', '③'][i]} {fmtDate(d)}</span>
                        {needsStaffAction && <span className="ml-2 text-xs text-purple-600">（クリックで確定）</span>}
                      </button>
                    ))}
                  </div>
                </div>
              )}

              {/* Guardian counter message */}
              {m.guardian_counter_message && (
                <div className="rounded-lg border border-blue-200 bg-blue-50 p-3 text-sm text-blue-800">
                  <span className="font-medium">保護者からのメッセージ:</span> {m.guardian_counter_message}
                </div>
              )}
              {m.staff_counter_message && (
                <div className="rounded-lg border border-purple-200 bg-purple-50 p-3 text-sm text-purple-800">
                  <span className="font-medium">スタッフからのメッセージ:</span> {m.staff_counter_message}
                </div>
              )}

              {/* Staff Counter-Proposal Form */}
              {isActionable && !showCounterForm && (
                <div className="flex flex-wrap gap-2">
                  <Button variant="outline" size="sm" leftIcon={<ArrowRightLeft className="h-4 w-4" />} onClick={() => setShowCounterForm(true)}>
                    別日程を提案
                  </Button>
                  <Button variant="ghost" size="sm" leftIcon={<X className="h-4 w-4" />} className="text-red-600" onClick={handleCancel}>
                    キャンセル
                  </Button>
                </div>
              )}

              {showCounterForm && (
                <div className="rounded-lg border border-[var(--neutral-stroke-2)] p-4 space-y-3">
                  <h4 className="text-sm font-semibold text-[var(--neutral-foreground-1)]">別日程を提案</h4>
                  {counterDates.map((d, i) => (
                    <div key={i} className="flex items-center gap-2">
                      <input type="datetime-local" value={d} onChange={(e) => { const arr = [...counterDates]; arr[i] = e.target.value; setCounterDates(arr); }} className={inputCls} />
                      {counterDates.length > 1 && <Button variant="ghost" size="sm" onClick={() => setCounterDates(counterDates.filter((_, idx) => idx !== i))}>削除</Button>}
                    </div>
                  ))}
                  {counterDates.length < 3 && <Button variant="ghost" size="sm" onClick={() => setCounterDates([...counterDates, ''])}>候補日を追加</Button>}
                  <textarea value={counterMessage} onChange={(e) => setCounterMessage(e.target.value)} className={textareaCls} rows={2} placeholder="メッセージ（任意）" />
                  <div className="flex justify-end gap-2">
                    <Button variant="ghost" size="sm" onClick={() => setShowCounterForm(false)}>やめる</Button>
                    <Button size="sm" onClick={handleCounter} isLoading={updateMutation.isPending} disabled={!counterDates[0]}>提案を送信</Button>
                  </div>
                </div>
              )}

              {/* Confirmed meeting actions: Notify & Complete */}
              {m.status === 'confirmed' && (
                <div className="space-y-4">
                  {/* Chat notify button */}
                  <div className="flex flex-wrap gap-2">
                    <Button variant="outline" size="sm" leftIcon={<Calendar className="h-4 w-4" />}
                      onClick={() => { if (confirm('保護者にチャットで面談日時を通知しますか？')) updateMutation.mutate({ action: 'notify' }); }}
                      isLoading={updateMutation.isPending}>
                      チャットで通知を送信
                    </Button>
                    {!m.is_completed && (
                      <Button variant="outline" size="sm" leftIcon={<Check className="h-4 w-4" />}
                        onClick={() => { if (confirm('この面談を完了にしますか？')) updateMutation.mutate({ action: 'complete', meeting_notes: meetingNotesEdit }); }}
                        isLoading={updateMutation.isPending}>
                        面談完了
                      </Button>
                    )}
                    {m.is_completed && (
                      <Badge variant="success">完了済み</Badge>
                    )}
                  </div>

                  {/* Meeting notes edit */}
                  <div>
                    <label className="mb-1 block text-sm font-medium text-[var(--neutral-foreground-2)]">面談記録</label>
                    <textarea
                      value={meetingNotesEdit}
                      onChange={(e) => setMeetingNotesEdit(e.target.value)}
                      className={textareaCls}
                      rows={4}
                      placeholder="面談の内容・結果を記録..."
                    />
                    <div className="mt-2 flex justify-end">
                      <Button size="sm" variant="outline"
                        onClick={() => updateMutation.mutate({ meeting_notes: meetingNotesEdit || null })}
                        isLoading={updateMutation.isPending}>
                        記録を保存
                      </Button>
                    </div>
                  </div>

                  {/* Hearing notes → Guardian Kakehashi */}
                  <div className="border-t border-[var(--neutral-stroke-2)] pt-4">
                    <h4 className="mb-2 text-sm font-semibold text-purple-700 flex items-center gap-1.5">
                      <FileText className="h-4 w-4" />
                      保護者かけはし用ヒアリング
                    </h4>
                    <p className="mb-2 text-xs text-[var(--neutral-foreground-4)]">
                      面談で聞き取った内容を入力し、AIで保護者かけはしに変換・反映します。
                      提出期限1か月以内の空のかけはしがあればそこに、なければ最新の期限切れかけはしに記入されます。
                    </p>
                    <textarea
                      value={hearingNotes}
                      onChange={(e) => setHearingNotes(e.target.value)}
                      className={textareaCls}
                      rows={5}
                      placeholder="例: 家では着替えが一人でできるようになった。学校では友達とのトラブルが減ってきた。本人は算数が得意になりたいと言っている。家庭では食事のマナーを身につけさせたい..."
                    />
                    <div className="mt-2 flex justify-end">
                      <Button size="sm" variant="primary"
                        leftIcon={<FileText className="h-4 w-4" />}
                        onClick={() => kakehashiMutation.mutate()}
                        isLoading={kakehashiMutation.isPending}
                        disabled={hearingNotes.trim().length < 10}>
                        {kakehashiMutation.isPending ? 'AI生成中...' : '保護者かけはしに反映'}
                      </Button>
                    </div>

                    {/* Kakehashi result */}
                    {kakehashiResult && (
                      <div className="mt-3 rounded-lg border border-green-200 bg-green-50 p-3 space-y-2">
                        <p className="text-sm font-semibold text-green-800">
                          保護者かけはしに反映しました（{kakehashiResult.period.period_name}）
                        </p>
                        <p className="text-xs text-green-600">
                          提出期限: {kakehashiResult.period.submission_deadline}
                        </p>
                        <div className="space-y-1 text-xs text-green-700">
                          {kakehashiResult.data.student_wish && <p><span className="font-medium">本人の願い:</span> {kakehashiResult.data.student_wish.substring(0, 60)}...</p>}
                          {kakehashiResult.data.home_challenges && <p><span className="font-medium">家庭での願い:</span> {kakehashiResult.data.home_challenges.substring(0, 60)}...</p>}
                          {kakehashiResult.data.short_term_goal && <p><span className="font-medium">短期目標:</span> {kakehashiResult.data.short_term_goal.substring(0, 60)}...</p>}
                          {kakehashiResult.data.long_term_goal && <p><span className="font-medium">長期目標:</span> {kakehashiResult.data.long_term_goal.substring(0, 60)}...</p>}
                        </div>
                      </div>
                    )}
                  </div>
                </div>
              )}
            </div>
          );
        })()}
      </Modal>
    </div>
  );
}
