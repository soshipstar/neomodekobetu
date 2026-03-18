'use client';

import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import api from '@/lib/api';
import { Card, CardHeader, CardTitle, CardBody } from '@/components/ui/Card';
import { Button } from '@/components/ui/Button';
import { Badge } from '@/components/ui/Badge';
import { SkeletonList } from '@/components/ui/Skeleton';
import { useToast } from '@/components/ui/Toast';
import { format } from 'date-fns';
import { ja } from 'date-fns/locale';
import { Calendar, Check, Send, ArrowLeft } from 'lucide-react';

interface MeetingRequest {
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
  student: {
    id: number;
    student_name: string;
  };
  staff: {
    id: number;
    full_name: string;
  } | null;
}

const statusLabels: Record<string, { label: string; variant: 'warning' | 'info' | 'success' | 'danger' }> = {
  pending: { label: '回答待ち', variant: 'warning' },
  guardian_counter: { label: '別日程を提案中', variant: 'info' },
  staff_counter: { label: 'スタッフ再提案中', variant: 'info' },
  confirmed: { label: '確定', variant: 'success' },
  cancelled: { label: 'キャンセル', variant: 'danger' },
};

function formatCandidateDate(dateStr: string): string {
  try {
    const d = new Date(dateStr);
    return format(d, 'yyyy年M月d日(E) HH:mm', { locale: ja });
  } catch {
    return dateStr;
  }
}

export default function GuardianMeetingsPage() {
  const queryClient = useQueryClient();
  const toast = useToast();
  const [selectedMeeting, setSelectedMeeting] = useState<number | null>(null);
  const [selectedDate, setSelectedDate] = useState<string>('');
  const [counterDate1, setCounterDate1] = useState('');
  const [counterDate2, setCounterDate2] = useState('');
  const [counterDate3, setCounterDate3] = useState('');
  const [counterMessage, setCounterMessage] = useState('');

  const { data: meetingsData, isLoading } = useQuery({
    queryKey: ['guardian', 'meetings'],
    queryFn: async () => {
      const response = await api.get<{ data: { data: MeetingRequest[] } }>('/api/guardian/meetings');
      // Handle both paginated and non-paginated responses
      const d = response.data.data;
      return Array.isArray(d) ? d : (d as { data: MeetingRequest[] }).data ?? [];
    },
  });

  const meetings = meetingsData ?? [];

  const selectMutation = useMutation({
    mutationFn: async ({ meetingId, date }: { meetingId: number; date: string }) => {
      await api.put(`/api/guardian/meetings/${meetingId}`, {
        action: 'select',
        selected_date: date,
      });
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['guardian', 'meetings'] });
      toast.success('面談日時が確定しました');
      setSelectedMeeting(null);
      setSelectedDate('');
    },
    onError: () => toast.error('送信に失敗しました'),
  });

  const counterMutation = useMutation({
    mutationFn: async ({ meetingId }: { meetingId: number }) => {
      await api.put(`/api/guardian/meetings/${meetingId}`, {
        action: 'counter',
        counter_date1: counterDate1,
        counter_date2: counterDate2 || null,
        counter_date3: counterDate3 || null,
        counter_message: counterMessage || null,
      });
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['guardian', 'meetings'] });
      toast.success('別日程を提案しました。スタッフからの回答をお待ちください。');
      setSelectedMeeting(null);
      setCounterDate1('');
      setCounterDate2('');
      setCounterDate3('');
      setCounterMessage('');
    },
    onError: () => toast.error('送信に失敗しました'),
  });

  // Detail view for a specific meeting
  const detail = selectedMeeting !== null ? meetings.find((m) => m.id === selectedMeeting) : null;

  if (detail) {
    const status = statusLabels[detail.status] ?? { label: '不明', variant: 'warning' as const };
    // Determine which candidate dates to show
    const candidates = detail.candidate_dates ?? [];

    return (
      <div className="space-y-6">
        <Button variant="ghost" size="sm" onClick={() => setSelectedMeeting(null)} leftIcon={<ArrowLeft className="h-4 w-4" />}>
          一覧に戻る
        </Button>

        <Card>
          <CardHeader>
            <CardTitle className="flex items-center gap-2">
              <Calendar className="h-5 w-5" />
              面談予約
            </CardTitle>
          </CardHeader>
          <CardBody>
            {/* Meeting info */}
            <div className="space-y-2 rounded-lg bg-gray-50 p-4 mb-6">
              <div className="flex gap-2">
                <span className="min-w-[100px] text-sm font-medium text-gray-500">対象児童</span>
                <span className="text-sm text-gray-900">{detail.student?.student_name}さん</span>
              </div>
              <div className="flex gap-2">
                <span className="min-w-[100px] text-sm font-medium text-gray-500">面談目的</span>
                <span className="text-sm text-gray-900">{detail.purpose}</span>
              </div>
              {detail.purpose_detail && (
                <div className="flex gap-2">
                  <span className="min-w-[100px] text-sm font-medium text-gray-500">詳細</span>
                  <span className="text-sm text-gray-900 whitespace-pre-wrap">{detail.purpose_detail}</span>
                </div>
              )}
              <div className="flex gap-2">
                <span className="min-w-[100px] text-sm font-medium text-gray-500">担当者</span>
                <span className="text-sm text-gray-900">{detail.staff?.full_name ?? ''}</span>
              </div>
              <div className="flex gap-2">
                <span className="min-w-[100px] text-sm font-medium text-gray-500">ステータス</span>
                <Badge variant={status.variant}>{status.label}</Badge>
              </div>
            </div>

            {/* Confirmed */}
            {detail.status === 'confirmed' && detail.confirmed_date && (
              <div className="rounded-lg border-2 border-green-400 bg-green-50 p-6 text-center mb-6">
                <Check className="mx-auto h-12 w-12 text-green-500" />
                <p className="mt-2 font-semibold text-gray-900">面談日時が確定しました</p>
                <p className="mt-2 text-xl font-bold text-green-600">
                  {formatCandidateDate(detail.confirmed_date)}
                </p>
              </div>
            )}

            {/* Pending or Staff Counter - show candidate dates and counter form */}
            {(detail.status === 'pending' || detail.status === 'staff_counter') && (
              <>
                <div className="mb-6">
                  <h3 className="mb-3 text-base font-semibold text-gray-900">候補日時から選択</h3>
                  {detail.status === 'staff_counter' && (
                    <p className="mb-3 text-sm text-gray-500">
                      スタッフから新たな候補日時が提案されました。
                    </p>
                  )}
                  <div className="space-y-3">
                    {candidates.map((date: string, i: number) => (
                      <label
                        key={i}
                        className={`flex cursor-pointer items-center gap-3 rounded-lg border-2 p-4 transition-colors ${
                          selectedDate === date ? 'border-green-500 bg-green-50' : 'border-gray-200 hover:border-gray-300'
                        }`}
                      >
                        <input
                          type="radio"
                          name="selected_date"
                          value={date}
                          checked={selectedDate === date}
                          onChange={(e) => setSelectedDate(e.target.value)}
                          className="sr-only"
                        />
                        <span className={`flex h-10 w-10 shrink-0 items-center justify-center rounded-full font-semibold ${
                          selectedDate === date ? 'bg-green-500 text-white' : 'bg-gray-200 text-gray-600'
                        }`}>
                          {i + 1}
                        </span>
                        <span className="text-sm font-medium text-gray-900">
                          {formatCandidateDate(date)}
                        </span>
                      </label>
                    ))}
                  </div>
                </div>

                {/* Counter proposal section */}
                <div className="mb-6 rounded-lg border border-blue-200 bg-blue-50 p-4">
                  <h3 className="mb-2 flex items-center gap-1 text-sm font-semibold text-blue-700">
                    <Calendar className="h-4 w-4" />
                    上記日程でご都合が合わない場合
                  </h3>
                  <p className="mb-3 text-xs text-gray-500">
                    別の希望日時を3つまでご提案ください。
                  </p>
                  <div className="space-y-3">
                    <div className="flex items-center gap-3">
                      <span className="min-w-[80px] text-sm font-medium text-gray-500">希望日時1</span>
                      <input
                        type="datetime-local"
                        value={counterDate1}
                        onChange={(e) => setCounterDate1(e.target.value)}
                        className="flex-1 rounded-lg border border-gray-300 px-3 py-2 text-sm"
                      />
                    </div>
                    <div className="flex items-center gap-3">
                      <span className="min-w-[80px] text-sm font-medium text-gray-500">希望日時2</span>
                      <input
                        type="datetime-local"
                        value={counterDate2}
                        onChange={(e) => setCounterDate2(e.target.value)}
                        className="flex-1 rounded-lg border border-gray-300 px-3 py-2 text-sm"
                      />
                    </div>
                    <div className="flex items-center gap-3">
                      <span className="min-w-[80px] text-sm font-medium text-gray-500">希望日時3</span>
                      <input
                        type="datetime-local"
                        value={counterDate3}
                        onChange={(e) => setCounterDate3(e.target.value)}
                        className="flex-1 rounded-lg border border-gray-300 px-3 py-2 text-sm"
                      />
                    </div>
                    <textarea
                      value={counterMessage}
                      onChange={(e) => setCounterMessage(e.target.value)}
                      className="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm"
                      rows={3}
                      placeholder="メッセージ（任意）"
                    />
                  </div>
                </div>

                {/* Action buttons */}
                <div className="flex flex-wrap gap-3">
                  <Button
                    variant="primary"
                    leftIcon={<Check className="h-4 w-4" />}
                    isLoading={selectMutation.isPending}
                    onClick={() => {
                      if (!selectedDate) {
                        toast.error('日程を選択してください');
                        return;
                      }
                      selectMutation.mutate({ meetingId: detail.id, date: selectedDate });
                    }}
                  >
                    選択した日程で確定
                  </Button>
                  <Button
                    variant="outline"
                    leftIcon={<Send className="h-4 w-4" />}
                    isLoading={counterMutation.isPending}
                    onClick={() => {
                      if (!counterDate1) {
                        toast.error('別日程を提案する場合は、少なくとも1つの希望日時を入力してください');
                        return;
                      }
                      counterMutation.mutate({ meetingId: detail.id });
                    }}
                  >
                    別日程を提案
                  </Button>
                </div>
              </>
            )}

            {/* Guardian counter - waiting for staff response */}
            {detail.status === 'guardian_counter' && (
              <div className="mb-6">
                <h3 className="mb-3 text-base font-semibold text-gray-900">ご提案いただいた日程</h3>
                <p className="mb-3 text-sm text-gray-500">
                  スタッフからの回答をお待ちください。
                </p>
                <div className="space-y-3">
                  {candidates.map((date: string, i: number) => (
                    <div
                      key={i}
                      className="flex items-center gap-3 rounded-lg border-2 border-blue-300 p-4"
                    >
                      <span className="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-blue-500 font-semibold text-white">
                        {i + 1}
                      </span>
                      <span className="text-sm font-medium text-gray-900">
                        {formatCandidateDate(date)}
                      </span>
                    </div>
                  ))}
                </div>
                {detail.guardian_counter_message && (
                  <div className="mt-3 rounded-lg bg-gray-50 p-3">
                    <p className="text-xs font-medium text-gray-500 mb-1">メッセージ:</p>
                    <p className="text-sm text-gray-700 whitespace-pre-wrap">{detail.guardian_counter_message}</p>
                  </div>
                )}
              </div>
            )}
          </CardBody>
        </Card>
      </div>
    );
  }

  // List view
  return (
    <div className="space-y-6">
      <h1 className="text-2xl font-bold text-gray-900">面談予約</h1>

      {isLoading ? (
        <SkeletonList items={3} />
      ) : meetings.length > 0 ? (
        <div className="space-y-4">
          {meetings.map((meeting) => {
            const status = statusLabels[meeting.status] ?? { label: '不明', variant: 'warning' as const };
            return (
              <Card key={meeting.id} className="cursor-pointer hover:shadow-md transition-shadow" onClick={() => setSelectedMeeting(meeting.id)}>
                <CardHeader>
                  <CardTitle className="text-base">{meeting.purpose}</CardTitle>
                  <Badge variant={status.variant}>{status.label}</Badge>
                </CardHeader>
                <CardBody>
                  <div className="flex flex-col gap-1 text-sm text-gray-600">
                    <p>対象: {meeting.student?.student_name}さん</p>
                    {meeting.staff && <p>担当: {meeting.staff.full_name}</p>}
                    {meeting.status === 'confirmed' && meeting.confirmed_date && (
                      <p className="font-medium text-green-600">
                        確定: {formatCandidateDate(meeting.confirmed_date)}
                      </p>
                    )}
                  </div>
                </CardBody>
              </Card>
            );
          })}
        </div>
      ) : (
        <Card>
          <CardBody>
            <p className="py-8 text-center text-sm text-gray-500">面談の予定はありません</p>
          </CardBody>
        </Card>
      )}
    </div>
  );
}
