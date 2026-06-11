'use client';

import { useState } from 'react';
import { useMutation, useQueryClient } from '@tanstack/react-query';
import api from '@/lib/api';
import { nl } from '@/lib/utils';
import { Button } from '@/components/ui/Button';
import { Badge } from '@/components/ui/Badge';
import { useToast } from '@/components/ui/Toast';
import { format } from 'date-fns';
import { ja } from 'date-fns/locale';
import { MaterialIcon } from '@/components/ui/MaterialIcon';

export interface MeetingRequest {
  id: number;
  purpose: string;
  purpose_detail: string | null;
  status: 'pending' | 'guardian_counter' | 'staff_counter' | 'confirmed' | 'cancelled';
  candidate_dates: string[] | null;
  confirmed_date: string | null;
  confirmed_by: string | null;
  confirmed_at: string | null;
  guardian_counter_message: string | null;
  staff_counter_message: string | null;
  student: { id: number; student_name: string } | null;
  staff: { id: number; full_name: string } | null;
}

export const meetingStatusLabels: Record<string, { label: string; variant: 'warning' | 'info' | 'success' | 'danger' }> = {
  pending: { label: '回答待ち', variant: 'warning' },
  guardian_counter: { label: '別日程を提案中', variant: 'info' },
  staff_counter: { label: 'スタッフ再提案中', variant: 'info' },
  confirmed: { label: '確定', variant: 'success' },
  cancelled: { label: 'キャンセル', variant: 'danger' },
};

export function formatCandidateDate(dateStr: string): string {
  try {
    return format(new Date(dateStr), 'yyyy年M月d日(E) HH:mm', { locale: ja });
  } catch {
    return dateStr;
  }
}

interface Props {
  meeting: MeetingRequest;
  /** 選択確定・別日程提案など状態が変わったとき(親で閉じる/再取得する) */
  onUpdated?: () => void;
}

/**
 * 保護者の面談予約 詳細・応答ビュー。面談一覧ページとチャット内モーダルで共用する。
 */
export function GuardianMeetingDetail({ meeting, onUpdated }: Props) {
  const queryClient = useQueryClient();
  const toast = useToast();
  const [selectedDate, setSelectedDate] = useState('');
  const [counterDate1, setCounterDate1] = useState('');
  const [counterDate2, setCounterDate2] = useState('');
  const [counterDate3, setCounterDate3] = useState('');
  const [counterMessage, setCounterMessage] = useState('');

  const afterSuccess = () => {
    queryClient.invalidateQueries({ queryKey: ['guardian', 'meetings'] });
    queryClient.invalidateQueries({ queryKey: ['guardian', 'meeting', meeting.id] });
    onUpdated?.();
  };

  const selectMutation = useMutation({
    mutationFn: async () => {
      await api.put(`/api/guardian/meetings/${meeting.id}`, { action: 'select', selected_date: selectedDate });
    },
    onSuccess: () => { toast.success('面談日時が確定しました'); afterSuccess(); },
    onError: () => toast.error('送信に失敗しました'),
  });

  const counterMutation = useMutation({
    mutationFn: async () => {
      await api.put(`/api/guardian/meetings/${meeting.id}`, {
        action: 'counter',
        counter_date1: counterDate1,
        counter_date2: counterDate2 || null,
        counter_date3: counterDate3 || null,
        counter_message: counterMessage || null,
      });
    },
    onSuccess: () => { toast.success('別日程を提案しました。スタッフからの回答をお待ちください。'); afterSuccess(); },
    onError: () => toast.error('送信に失敗しました'),
  });

  const status = meetingStatusLabels[meeting.status] ?? { label: '不明', variant: 'warning' as const };
  const candidates = meeting.candidate_dates ?? [];

  return (
    <div>
      {/* Meeting info */}
      <div className="space-y-2 rounded-lg bg-[var(--neutral-background-3)] p-4 mb-6">
        <div className="flex gap-2">
          <span className="min-w-[100px] text-sm font-medium text-[var(--neutral-foreground-3)]">対象児童</span>
          <span className="text-sm text-[var(--neutral-foreground-1)]">{meeting.student?.student_name}さん</span>
        </div>
        <div className="flex gap-2">
          <span className="min-w-[100px] text-sm font-medium text-[var(--neutral-foreground-3)]">面談目的</span>
          <span className="text-sm text-[var(--neutral-foreground-1)]">{meeting.purpose}</span>
        </div>
        {meeting.purpose_detail && (
          <div className="flex gap-2">
            <span className="min-w-[100px] text-sm font-medium text-[var(--neutral-foreground-3)]">詳細</span>
            <span className="text-sm text-[var(--neutral-foreground-1)] whitespace-pre-wrap">{nl(meeting.purpose_detail)}</span>
          </div>
        )}
        <div className="flex gap-2">
          <span className="min-w-[100px] text-sm font-medium text-[var(--neutral-foreground-3)]">担当者</span>
          <span className="text-sm text-[var(--neutral-foreground-1)]">{meeting.staff?.full_name ?? ''}</span>
        </div>
        <div className="flex gap-2">
          <span className="min-w-[100px] text-sm font-medium text-[var(--neutral-foreground-3)]">ステータス</span>
          <Badge variant={status.variant}>{status.label}</Badge>
        </div>
      </div>

      {/* Confirmed */}
      {meeting.status === 'confirmed' && meeting.confirmed_date && (
        <div className="rounded-lg border-2 border-green-400 bg-green-50 p-6 text-center mb-6">
          <MaterialIcon name="check" size={48} className="mx-auto text-green-500" />
          <p className="mt-2 font-semibold text-[var(--neutral-foreground-1)]">面談日時が確定しました</p>
          <p className="mt-2 text-xl font-bold text-green-600">{formatCandidateDate(meeting.confirmed_date)}</p>
        </div>
      )}

      {/* Pending or Staff Counter — choose candidate or propose alternatives */}
      {(meeting.status === 'pending' || meeting.status === 'staff_counter') && (
        <>
          <div className="mb-6">
            <h3 className="mb-3 text-base font-semibold text-[var(--neutral-foreground-1)]">候補日時から選択</h3>
            {meeting.status === 'staff_counter' && (
              <p className="mb-3 text-sm text-[var(--neutral-foreground-3)]">スタッフから新たな候補日時が提案されました。</p>
            )}
            <div className="space-y-3">
              {candidates.map((date, i) => (
                <label
                  key={i}
                  className={`flex cursor-pointer items-center gap-3 rounded-lg border-2 p-4 transition-colors ${
                    selectedDate === date ? 'border-green-500 bg-green-50' : 'border-[var(--neutral-stroke-2)] hover:border-[var(--neutral-stroke-1)]'
                  }`}
                >
                  <input type="radio" name="selected_date" value={date} checked={selectedDate === date}
                    onChange={(e) => setSelectedDate(e.target.value)} className="sr-only" />
                  <span className={`flex h-10 w-10 shrink-0 items-center justify-center rounded-full font-semibold ${
                    selectedDate === date ? 'bg-green-500 text-white' : 'bg-[var(--neutral-background-5)] text-[var(--neutral-foreground-3)]'
                  }`}>{i + 1}</span>
                  <span className="text-sm font-medium text-[var(--neutral-foreground-1)]">{formatCandidateDate(date)}</span>
                </label>
              ))}
            </div>
          </div>

          <div className="mb-6 rounded-lg border border-[var(--brand-130)] bg-[var(--brand-160)] p-4">
            <h3 className="mb-2 flex items-center gap-1 text-sm font-semibold text-[var(--brand-70)]">
              <MaterialIcon name="calendar_month" size={16} />
              上記日程でご都合が合わない場合
            </h3>
            <p className="mb-3 text-xs text-[var(--neutral-foreground-3)]">別の希望日時を3つまでご提案ください。</p>
            <div className="space-y-3">
              {[
                { label: '希望日時1', value: counterDate1, set: setCounterDate1 },
                { label: '希望日時2', value: counterDate2, set: setCounterDate2 },
                { label: '希望日時3', value: counterDate3, set: setCounterDate3 },
              ].map((row) => (
                <div key={row.label} className="flex items-center gap-3">
                  <span className="min-w-[80px] text-sm font-medium text-[var(--neutral-foreground-3)]">{row.label}</span>
                  <input type="datetime-local" value={row.value} onChange={(e) => row.set(e.target.value)}
                    className="flex-1 rounded-lg border border-[var(--neutral-stroke-1)] px-3 py-2 text-sm" />
                </div>
              ))}
              <textarea value={counterMessage} onChange={(e) => setCounterMessage(e.target.value)}
                className="w-full rounded-lg border border-[var(--neutral-stroke-1)] px-3 py-2 text-sm" rows={3} placeholder="メッセージ（任意）" />
            </div>
          </div>

          <div className="flex flex-wrap gap-3">
            <Button variant="primary" leftIcon={<MaterialIcon name="check" size={16} />} isLoading={selectMutation.isPending}
              onClick={() => {
                if (!selectedDate) { toast.error('日程を選択してください'); return; }
                selectMutation.mutate();
              }}>
              選択した日程で確定
            </Button>
            <Button variant="outline" leftIcon={<MaterialIcon name="send" size={16} />} isLoading={counterMutation.isPending}
              onClick={() => {
                if (!counterDate1) { toast.error('別日程を提案する場合は、少なくとも1つの希望日時を入力してください'); return; }
                counterMutation.mutate();
              }}>
              別日程を提案
            </Button>
          </div>
        </>
      )}

      {/* Guardian counter — waiting for staff response */}
      {meeting.status === 'guardian_counter' && (
        <div className="mb-2">
          <h3 className="mb-3 text-base font-semibold text-[var(--neutral-foreground-1)]">ご提案いただいた日程</h3>
          <p className="mb-3 text-sm text-[var(--neutral-foreground-3)]">スタッフからの回答をお待ちください。</p>
          <div className="space-y-3">
            {candidates.map((date, i) => (
              <div key={i} className="flex items-center gap-3 rounded-lg border-2 border-[var(--brand-120)] p-4">
                <span className="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-[var(--brand-80)] font-semibold text-white">{i + 1}</span>
                <span className="text-sm font-medium text-[var(--neutral-foreground-1)]">{formatCandidateDate(date)}</span>
              </div>
            ))}
          </div>
          {meeting.guardian_counter_message && (
            <div className="mt-3 rounded-lg bg-[var(--neutral-background-3)] p-3">
              <p className="text-xs font-medium text-[var(--neutral-foreground-3)] mb-1">メッセージ:</p>
              <p className="text-sm text-[var(--neutral-foreground-2)] whitespace-pre-wrap">{nl(meeting.guardian_counter_message)}</p>
            </div>
          )}
        </div>
      )}
    </div>
  );
}
