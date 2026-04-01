'use client';

import { useEffect, useState, useCallback } from 'react';
import { useParams } from 'next/navigation';
import { useChat } from '@/hooks/useChat';
import { useChatStore } from '@/stores/chatStore';
import { useAuthStore } from '@/stores/authStore';
import { ChatMessageList } from '@/components/chat/ChatMessageList';
import { ChatInput } from '@/components/chat/ChatInput';
import { SkeletonList } from '@/components/ui/Skeleton';
import { Button } from '@/components/ui/Button';
import { useToast } from '@/components/ui/Toast';
import Link from 'next/link';
import { MaterialIcon } from '@/components/ui/MaterialIcon';
import api from '@/lib/api';

type MessageFormType = 'normal' | 'absence_notification' | 'event_registration' | 'meeting_request';

const MESSAGE_TYPES: { key: MessageFormType; label: string; icon: string }[] = [
  { key: 'normal', label: '通常', icon: 'chat' },
  { key: 'absence_notification', label: '欠席連絡', icon: 'event_busy' },
  { key: 'event_registration', label: 'イベント参加', icon: 'celebration' },
  { key: 'meeting_request', label: '面談申込', icon: 'calendar_month' },
];

const MEETING_PURPOSES = [
  '個別支援計画',
  'モニタリング',
  '進路相談',
  '学習相談',
  '生活・行動について',
  'その他',
];

interface EventOption {
  id: number;
  event_name: string;
  event_date: string;
  description: string | null;
}

export default function GuardianChatRoomPage() {
  const params = useParams();
  const roomId = Number(params.roomId);
  const { user } = useAuthStore();
  const toast = useToast();
  const [loadingOlder, setLoadingOlder] = useState(false);

  const {
    activeRoom,
    messages,
    isLoadingMessages,
    isSending,
    fetchRooms,
    setActiveRoom,
    fetchMessages,
    fetchOlderMessages,
    hasMoreMessages,
    sendMessage,
    markAsRead,
    rooms,
  } = useChat(roomId);

  const [messageType, setMessageType] = useState<MessageFormType>('normal');

  // Set guardian API prefix before fetching
  useEffect(() => {
    useChatStore.getState().setApiPrefix('/api/guardian');
  }, []);

  useEffect(() => {
    if (rooms.length === 0) fetchRooms();
  }, [rooms.length, fetchRooms]);

  useEffect(() => {
    if (roomId && rooms.length > 0) {
      const room = rooms.find((r) => r.id === roomId);
      if (room) {
        setActiveRoom(room);
        fetchMessages(roomId);
        markAsRead(roomId);
      }
    }
  }, [roomId, rooms, setActiveRoom, fetchMessages, markAsRead]);

  useEffect(() => {
    return () => { setActiveRoom(null); };
  }, [setActiveRoom]);

  const handleLoadOlder = useCallback(async () => {
    setLoadingOlder(true);
    await fetchOlderMessages(roomId);
    setLoadingOlder(false);
  }, [roomId, fetchOlderMessages]);

  const handleFormSuccess = useCallback(() => {
    fetchMessages(roomId);
    setMessageType('normal');
  }, [fetchMessages, roomId]);

  const studentId = activeRoom?.student_id || activeRoom?.student?.id;

  return (
    <div className="flex h-[calc(100vh-6rem)] flex-col sm:h-[calc(100vh-7rem)] lg:h-[calc(100vh-5rem)]">
      <div className="flex items-center gap-2 border-b border-[var(--neutral-stroke-2)] bg-white px-3 py-2 sm:gap-3 sm:px-4 sm:py-3">
        <Link href="/guardian/chat" className="rounded-lg p-1 text-[var(--neutral-foreground-4)] hover:text-[var(--neutral-foreground-3)] lg:hidden">
          <MaterialIcon name="arrow_back" size={20} />
        </Link>
        <div className="flex-1 min-w-0">
          <h2 className="text-sm font-semibold text-[var(--neutral-foreground-1)] truncate">
            {activeRoom?.student?.student_name || 'チャット'}
          </h2>
        </div>
      </div>

      <div className="flex-1 overflow-y-auto bg-[var(--neutral-background-3)]">
        {isLoadingMessages ? (
          <div className="p-4"><SkeletonList items={5} /></div>
        ) : (
          <>
            {hasMoreMessages && (
              <div className="flex justify-center py-3">
                <button
                  onClick={handleLoadOlder}
                  disabled={loadingOlder}
                  className="flex items-center gap-1 rounded-full bg-white px-4 py-1.5 text-xs text-[var(--neutral-foreground-3)] shadow-sm hover:bg-[var(--neutral-background-3)] transition-colors disabled:opacity-50"
                >
                  <MaterialIcon name="expand_less" size={14} />
                  {loadingOlder ? '読み込み中...' : '過去のメッセージを読み込む'}
                </button>
              </div>
            )}
            <ChatMessageList messages={messages} currentUserId={user?.id || 0} />
          </>
        )}
      </div>

      {/* Message Type Selector */}
      <div className="border-t border-[var(--neutral-stroke-2)] bg-white px-2 pt-2 sm:px-3">
        <div className="flex gap-1 overflow-x-auto">
          {MESSAGE_TYPES.map((t) => (
            <button
              key={t.key}
              onClick={() => setMessageType(t.key)}
              className={`flex shrink-0 items-center gap-1 rounded-lg px-2.5 py-1.5 text-xs font-medium transition-colors ${
                messageType === t.key
                  ? 'bg-[var(--brand-80)] text-white'
                  : 'bg-[var(--neutral-background-3)] text-[var(--neutral-foreground-3)] hover:bg-[var(--neutral-background-4)]'
              }`}
            >
              <MaterialIcon name={t.icon} size={14} />
              {t.label}
            </button>
          ))}
        </div>
      </div>

      {/* Form Area */}
      {messageType === 'normal' ? (
        <ChatInput onSend={sendMessage} isSending={isSending} disabled={!activeRoom} />
      ) : messageType === 'absence_notification' ? (
        <AbsenceForm
          roomId={roomId}
          studentId={studentId}
          onSuccess={handleFormSuccess}
          toast={toast}
        />
      ) : messageType === 'event_registration' ? (
        <EventRegistrationForm
          roomId={roomId}
          studentId={studentId}
          onSuccess={handleFormSuccess}
          toast={toast}
        />
      ) : messageType === 'meeting_request' ? (
        <MeetingRequestForm
          roomId={roomId}
          studentId={studentId}
          onSuccess={handleFormSuccess}
          toast={toast}
        />
      ) : null}
    </div>
  );
}

// ---------------------------------------------------------------------------
// Shared form wrapper styles
// ---------------------------------------------------------------------------

const formClass = 'border-t border-[var(--neutral-stroke-2)] bg-white px-3 py-3 sm:px-4';
const labelClass = 'mb-1 block text-xs font-medium text-[var(--neutral-foreground-2)]';
const inputClass = 'block w-full rounded-xl border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-3)] px-3 py-2 text-sm text-[var(--neutral-foreground-1)] focus:border-[var(--brand-80)] focus:bg-white focus:outline-none focus:ring-2 focus:ring-[var(--brand-80)]/20';
const textareaClass = `${inputClass} resize-none`;

// ---------------------------------------------------------------------------
// Absence Notification Form
// ---------------------------------------------------------------------------

interface FormProps {
  roomId: number;
  studentId: number | undefined;
  onSuccess: () => void;
  toast: ReturnType<typeof useToast>;
}

function AbsenceForm({ roomId, studentId, onSuccess, toast }: FormProps) {
  const [absenceDate, setAbsenceDate] = useState('');
  const [reason, setReason] = useState('');
  const [makeupOption, setMakeupOption] = useState<'decide_later' | 'choose_date'>('decide_later');
  const [makeupDate, setMakeupDate] = useState('');
  const [sending, setSending] = useState(false);

  const handleSubmit = async () => {
    if (!studentId || !absenceDate) return;
    setSending(true);
    try {
      await api.post(`/api/guardian/chat/rooms/${roomId}/absence`, {
        student_id: studentId,
        absence_date: absenceDate,
        reason: reason || undefined,
        makeup_option: makeupOption,
        makeup_date: makeupOption === 'choose_date' ? makeupDate : undefined,
      });
      toast.success('欠席連絡を送信しました');
      setAbsenceDate('');
      setReason('');
      setMakeupOption('decide_later');
      setMakeupDate('');
      onSuccess();
    } catch (err: unknown) {
      const msg = (err as { response?: { data?: { message?: string } } })?.response?.data?.message || '欠席連絡の送信に失敗しました';
      toast.error(msg);
    } finally {
      setSending(false);
    }
  };

  return (
    <div className={formClass}>
      <div className="space-y-3">
        <div className="flex items-center gap-2 text-xs font-semibold text-[var(--neutral-foreground-2)]">
          <MaterialIcon name="event_busy" size={16} className="text-[var(--status-danger-fg)]" />
          欠席連絡
        </div>

        <div>
          <label className={labelClass}>欠席日 *</label>
          <input
            type="date"
            value={absenceDate}
            onChange={(e) => setAbsenceDate(e.target.value)}
            className={inputClass}
          />
        </div>

        <div>
          <label className={labelClass}>理由</label>
          <textarea
            value={reason}
            onChange={(e) => setReason(e.target.value)}
            rows={2}
            className={textareaClass}
            placeholder="体調不良、家庭の都合など..."
          />
        </div>

        <div>
          <label className={labelClass}>振替について *</label>
          <div className="flex flex-col gap-2 sm:flex-row sm:gap-4">
            <label className="flex items-center gap-1.5 text-sm text-[var(--neutral-foreground-2)] cursor-pointer">
              <input
                type="radio"
                name="makeup_option"
                checked={makeupOption === 'decide_later'}
                onChange={() => setMakeupOption('decide_later')}
                className="accent-[var(--brand-80)]"
              />
              後日決める
            </label>
            <label className="flex items-center gap-1.5 text-sm text-[var(--neutral-foreground-2)] cursor-pointer">
              <input
                type="radio"
                name="makeup_option"
                checked={makeupOption === 'choose_date'}
                onChange={() => setMakeupOption('choose_date')}
                className="accent-[var(--brand-80)]"
              />
              今すぐ日にちを決める
            </label>
          </div>
        </div>

        {makeupOption === 'choose_date' && (
          <div>
            <label className={labelClass}>振替希望日</label>
            <input
              type="date"
              value={makeupDate}
              onChange={(e) => setMakeupDate(e.target.value)}
              className={inputClass}
            />
          </div>
        )}

        <div className="flex justify-end">
          <Button
            onClick={handleSubmit}
            isLoading={sending}
            disabled={!absenceDate || !studentId}
            size="md"
            className="rounded-xl"
            leftIcon={<MaterialIcon name="send" size={14} />}
          >
            送信
          </Button>
        </div>
      </div>
    </div>
  );
}

// ---------------------------------------------------------------------------
// Event Registration Form
// ---------------------------------------------------------------------------

function EventRegistrationForm({ roomId, studentId, onSuccess, toast }: FormProps) {
  const [eventId, setEventId] = useState<number | ''>('');
  const [notes, setNotes] = useState('');
  const [sending, setSending] = useState(false);
  const [events, setEvents] = useState<EventOption[]>([]);
  const [loadingEvents, setLoadingEvents] = useState(true);

  useEffect(() => {
    let cancelled = false;
    (async () => {
      try {
        const res = await api.get('/api/guardian/events', { params: { upcoming: true } });
        if (!cancelled) {
          setEvents(res.data?.data || []);
        }
      } catch {
        // ignore
      } finally {
        if (!cancelled) setLoadingEvents(false);
      }
    })();
    return () => { cancelled = true; };
  }, []);

  const handleSubmit = async () => {
    if (!studentId || !eventId) return;
    setSending(true);
    try {
      await api.post(`/api/guardian/chat/rooms/${roomId}/event-registration`, {
        student_id: studentId,
        event_id: eventId,
        notes: notes || undefined,
      });
      toast.success('イベント参加申込を送信しました');
      setEventId('');
      setNotes('');
      onSuccess();
    } catch (err: unknown) {
      const msg = (err as { response?: { data?: { message?: string } } })?.response?.data?.message || 'イベント参加申込の送信に失敗しました';
      toast.error(msg);
    } finally {
      setSending(false);
    }
  };

  return (
    <div className={formClass}>
      <div className="space-y-3">
        <div className="flex items-center gap-2 text-xs font-semibold text-[var(--neutral-foreground-2)]">
          <MaterialIcon name="celebration" size={16} className="text-[var(--brand-80)]" />
          イベント参加申込
        </div>

        <div>
          <label className={labelClass}>イベント *</label>
          {loadingEvents ? (
            <div className="flex items-center gap-2 py-2 text-xs text-[var(--neutral-foreground-4)]">
              <MaterialIcon name="progress_activity" size={14} className="animate-spin" />
              読み込み中...
            </div>
          ) : events.length === 0 ? (
            <p className="py-2 text-xs text-[var(--neutral-foreground-4)]">現在参加可能なイベントはありません</p>
          ) : (
            <select
              value={eventId}
              onChange={(e) => setEventId(e.target.value ? Number(e.target.value) : '')}
              className={inputClass}
            >
              <option value="">イベントを選択...</option>
              {events.map((ev) => (
                <option key={ev.id} value={ev.id}>
                  {ev.event_name} ({ev.event_date})
                </option>
              ))}
            </select>
          )}
        </div>

        <div>
          <label className={labelClass}>備考</label>
          <textarea
            value={notes}
            onChange={(e) => setNotes(e.target.value)}
            rows={2}
            className={textareaClass}
            placeholder="備考があれば..."
          />
        </div>

        <div className="flex justify-end">
          <Button
            onClick={handleSubmit}
            isLoading={sending}
            disabled={!eventId || !studentId}
            size="md"
            className="rounded-xl"
            leftIcon={<MaterialIcon name="send" size={14} />}
          >
            送信
          </Button>
        </div>
      </div>
    </div>
  );
}

// ---------------------------------------------------------------------------
// Meeting Request Form
// ---------------------------------------------------------------------------

function MeetingRequestForm({ roomId, studentId, onSuccess, toast }: FormProps) {
  const [purpose, setPurpose] = useState('');
  const [detail, setDetail] = useState('');
  const [date1, setDate1] = useState('');
  const [date2, setDate2] = useState('');
  const [date3, setDate3] = useState('');
  const [sending, setSending] = useState(false);

  const handleSubmit = async () => {
    if (!studentId || !purpose || !date1) return;
    setSending(true);
    try {
      await api.post(`/api/guardian/chat/rooms/${roomId}/meeting-request`, {
        student_id: studentId,
        purpose,
        detail: detail || undefined,
        date1,
        date2: date2 || undefined,
        date3: date3 || undefined,
      });
      toast.success('面談申込を送信しました');
      setPurpose('');
      setDetail('');
      setDate1('');
      setDate2('');
      setDate3('');
      onSuccess();
    } catch (err: unknown) {
      const msg = (err as { response?: { data?: { message?: string } } })?.response?.data?.message || '面談申込の送信に失敗しました';
      toast.error(msg);
    } finally {
      setSending(false);
    }
  };

  return (
    <div className={formClass}>
      <div className="space-y-3">
        <div className="flex items-center gap-2 text-xs font-semibold text-[var(--neutral-foreground-2)]">
          <MaterialIcon name="calendar_month" size={16} className="text-[var(--brand-80)]" />
          面談の申し込み
        </div>

        <div>
          <label className={labelClass}>面談目的 *</label>
          <select
            value={purpose}
            onChange={(e) => setPurpose(e.target.value)}
            className={inputClass}
          >
            <option value="">目的を選択...</option>
            {MEETING_PURPOSES.map((p) => (
              <option key={p} value={p}>{p}</option>
            ))}
          </select>
        </div>

        <div>
          <label className={labelClass}>詳細・補足</label>
          <textarea
            value={detail}
            onChange={(e) => setDetail(e.target.value)}
            rows={2}
            className={textareaClass}
            placeholder="面談で相談したい内容など..."
          />
        </div>

        <div>
          <label className={labelClass}>希望日時1 *</label>
          <input
            type="datetime-local"
            value={date1}
            onChange={(e) => setDate1(e.target.value)}
            className={inputClass}
          />
        </div>
        <div>
          <label className={labelClass}>希望日時2</label>
          <input
            type="datetime-local"
            value={date2}
            onChange={(e) => setDate2(e.target.value)}
            className={inputClass}
          />
        </div>
        <div>
          <label className={labelClass}>希望日時3</label>
          <input
            type="datetime-local"
            value={date3}
            onChange={(e) => setDate3(e.target.value)}
            className={inputClass}
          />
        </div>

        <div className="flex justify-end">
          <Button
            onClick={handleSubmit}
            isLoading={sending}
            disabled={!purpose || !date1 || !studentId}
            size="md"
            className="rounded-xl"
            leftIcon={<MaterialIcon name="send" size={14} />}
          >
            送信
          </Button>
        </div>
      </div>
    </div>
  );
}
