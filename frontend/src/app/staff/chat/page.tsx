'use client';

import { useEffect, useState, useMemo, useCallback, useRef } from 'react';
import { useChat } from '@/hooks/useChat';
import { useAuthStore } from '@/stores/authStore';
import { useDebounce } from '@/hooks/useDebounce';
import { useIsDesktop } from '@/hooks/useMediaQuery';
import { ChatMessageList } from '@/components/chat/ChatMessageList';
import { ChatInput } from '@/components/chat/ChatInput';
import { Input } from '@/components/ui/Input';
import { Badge } from '@/components/ui/Badge';
import { Skeleton } from '@/components/ui/Skeleton';
import { cn, formatRelativeTime, truncate } from '@/lib/utils';
import { Button } from '@/components/ui/Button';
import { Modal } from '@/components/ui/Modal';
import { useToast } from '@/components/ui/Toast';
import api from '@/lib/api';
import type { ChatRoom } from '@/types/chat';
import { MaterialIcon } from '@/components/ui/MaterialIcon';

// Grade group ordering for sidebar accordions
const GRADE_GROUPS = [
  { key: 'preschool', label: '未就学児' },
  { key: 'elementary', label: '小学生' },
  { key: 'junior_high', label: '中学生' },
  { key: 'high_school', label: '高校生' },
];

function gradeGroupKey(gradeLevel?: string): string {
  if (!gradeLevel) return 'elementary';
  if (gradeLevel.startsWith('preschool')) return 'preschool';
  if (gradeLevel.startsWith('elementary')) return 'elementary';
  if (gradeLevel.startsWith('junior_high')) return 'junior_high';
  if (gradeLevel.startsWith('high_school')) return 'high_school';
  return 'elementary';
}

export default function StaffChatPage() {
  const { user } = useAuthStore();
  const isDesktop = useIsDesktop();
  const {
    rooms,
    activeRoom,
    messages,
    unreadCounts,
    isLoadingRooms,
    isLoadingMessages,
    isSending,
    fetchRooms,
    setActiveRoom,
    fetchMessages,
    sendMessage,
    markAsRead,
  } = useChat();

  const [search, setSearch] = useState('');
  const debouncedSearch = useDebounce(search, 300);
  const [expandedGroups, setExpandedGroups] = useState<Set<string>>(new Set());
  // Mobile: track whether we're showing chat view
  const [showChat, setShowChat] = useState(false);
  const toast = useToast();

  // Meeting modal state
  const [meetingModal, setMeetingModal] = useState(false);
  const [meetingPurpose, setMeetingPurpose] = useState('');
  const [meetingDetail, setMeetingDetail] = useState('');
  const [meetingDates, setMeetingDates] = useState(['', '', '']);
  const [meetingSending, setMeetingSending] = useState(false);

  // Submission deadline modal state
  const [submissionModal, setSubmissionModal] = useState(false);
  const [submissionTitle, setSubmissionTitle] = useState('');
  const [submissionDesc, setSubmissionDesc] = useState('');
  const [submissionDueDate, setSubmissionDueDate] = useState('');
  const [submissionSending, setSubmissionSending] = useState(false);

  // Broadcast modal state
  const [broadcastModal, setBroadcastModal] = useState(false);
  const [broadcastMessage, setBroadcastMessage] = useState('');
  const [broadcastSending, setBroadcastSending] = useState(false);
  const [broadcastFile, setBroadcastFile] = useState<File | null>(null);
  const [broadcastRoomIds, setBroadcastRoomIds] = useState<Set<number>>(new Set());
  const [quickModal, setQuickModal] = useState<'departure' | 'arrival' | null>(null);
  const [quickRoomIds, setQuickRoomIds] = useState<Set<number>>(new Set());
  const [quickSending, setQuickSending] = useState(false);

  useEffect(() => {
    fetchRooms();
  }, [fetchRooms]);

  // Select room handler
  const handleSelectRoom = useCallback(
    (room: ChatRoom) => {
      setActiveRoom(room);
      fetchMessages(room.id);
      markAsRead(room.id);
      setShowChat(true); // switch to chat view on mobile
    },
    [setActiveRoom, fetchMessages, markAsRead]
  );

  const handleBack = useCallback(() => {
    setShowChat(false);
  }, []);

  const handleSend = useCallback(
    async (message: string, attachment?: File) => {
      await sendMessage(message, attachment);
    },
    [sendMessage]
  );

  // Toggle pin
  const handleTogglePin = useCallback(async () => {
    if (!activeRoom) return;
    try {
      await api.post(`/api/staff/chat/rooms/${activeRoom.id}/pin`);
      fetchRooms();
    } catch { /* ignore */ }
  }, [activeRoom, fetchRooms]);

  // Meeting request
  const handleMeetingSubmit = useCallback(async () => {
    if (!activeRoom || !meetingPurpose || meetingDates.every((d) => !d)) return;
    setMeetingSending(true);
    try {
      await api.post('/api/staff/meetings', {
        student_id: activeRoom.student_id,
        guardian_id: activeRoom.guardian_id,
        purpose: meetingPurpose,
        purpose_detail: meetingDetail || undefined,
        candidate_dates: meetingDates.filter((d) => d),
      });
      toast.success('面談予約を送信しました');
      setMeetingModal(false);
      setMeetingPurpose('');
      setMeetingDetail('');
      setMeetingDates(['', '', '']);
      fetchMessages(activeRoom.id);
    } catch (err: unknown) {
      const axiosErr = err as { response?: { data?: { message?: string; errors?: Record<string, string[]> } } };
      const msg = axiosErr?.response?.data?.message || '面談予約の送信に失敗しました';
      toast.error(msg);
      console.error('Meeting request failed:', axiosErr?.response?.data);
    } finally {
      setMeetingSending(false);
    }
  }, [activeRoom, meetingPurpose, meetingDetail, meetingDates, fetchMessages, toast]);

  // Submission deadline
  const handleSubmissionSubmit = useCallback(async () => {
    if (!activeRoom || !submissionTitle || !submissionDueDate) return;
    setSubmissionSending(true);
    try {
      await api.post('/api/staff/submissions', {
        student_id: activeRoom.student_id,
        title: submissionTitle,
        description: submissionDesc || undefined,
        due_date: submissionDueDate,
      });
      toast.success('提出期限を設定しました');
      setSubmissionModal(false);
      setSubmissionTitle('');
      setSubmissionDesc('');
      setSubmissionDueDate('');
    } catch {
      toast.error('提出期限の設定に失敗しました');
    } finally {
      setSubmissionSending(false);
    }
  }, [activeRoom, submissionTitle, submissionDesc, submissionDueDate, toast]);

  // Broadcast
  const handleBroadcast = useCallback(async () => {
    if (!broadcastMessage.trim() && !broadcastFile) return;
    if (broadcastRoomIds.size === 0) {
      toast.error('送信先を選択してください');
      return;
    }
    setBroadcastSending(true);
    try {
      const formData = new FormData();
      formData.append('message', broadcastMessage);
      Array.from(broadcastRoomIds).forEach((id) => formData.append('room_ids[]', String(id)));
      if (broadcastFile) {
        formData.append('attachment', broadcastFile);
      }
      const res = await api.post('/api/staff/chat/broadcast', formData);
      toast.success(res.data?.message || '一斉送信しました');
      setBroadcastModal(false);
      setBroadcastMessage('');
      setBroadcastFile(null);
      setBroadcastRoomIds(new Set());
      fetchRooms();
    } catch {
      toast.error('一斉送信に失敗しました');
    } finally {
      setBroadcastSending(false);
    }
  }, [broadcastMessage, broadcastFile, broadcastRoomIds, fetchRooms, toast]);

  // クイック通知モーダルを開く（ルーム選択）
  const openQuickModal = useCallback((action: 'departure' | 'arrival') => {
    setQuickRoomIds(new Set());
    setQuickModal(action);
  }, []);

  const toggleQuickRoom = useCallback((roomId: number) => {
    setQuickRoomIds((prev) => {
      const next = new Set(prev);
      if (next.has(roomId)) next.delete(roomId);
      else next.add(roomId);
      return next;
    });
  }, []);

  const selectAllQuickRooms = useCallback((ids: number[]) => {
    setQuickRoomIds(new Set(ids));
  }, []);

  const clearQuickRooms = useCallback(() => {
    setQuickRoomIds(new Set());
  }, []);

  // クイック通知送信 (選択した保護者に)
  const handleQuickBroadcastSubmit = useCallback(async () => {
    if (!quickModal) return;
    if (quickRoomIds.size === 0) {
      toast.error('送信先を選択してください');
      return;
    }
    const label = quickModal === 'departure' ? 'これから帰ります' : '到着しました';
    setQuickSending(true);
    try {
      const res = await api.post('/api/staff/chat/quick-broadcast', {
        action: quickModal,
        room_ids: Array.from(quickRoomIds),
      });
      toast.success(res.data?.message || `${label} を送信しました`);
      setQuickModal(null);
      setQuickRoomIds(new Set());
      fetchRooms();
    } catch {
      toast.error(`${label}の送信に失敗しました`);
    } finally {
      setQuickSending(false);
    }
  }, [quickModal, quickRoomIds, fetchRooms, toast]);

  // Toggle grade group accordion
  const toggleGroup = (key: string) => {
    setExpandedGroups((prev) => {
      const next = new Set(prev);
      if (next.has(key)) next.delete(key);
      else next.add(key);
      return next;
    });
  };

  // Organize rooms: pinned first, then by grade group
  const { pinnedRooms, gradeGroupedRooms } = useMemo(() => {
    const filtered = rooms.filter((room) => {
      if (!debouncedSearch) return true;
      const q = debouncedSearch.toLowerCase();
      const studentName = room.student?.student_name?.toLowerCase() || '';
      const guardianName = room.guardian?.full_name?.toLowerCase() || '';
      return studentName.includes(q) || guardianName.includes(q);
    });

    const pinned = filtered.filter((r) => r.is_pinned);
    const grouped: Record<string, ChatRoom[]> = {};
    GRADE_GROUPS.forEach((g) => { grouped[g.key] = []; });

    filtered.forEach((room) => {
      const gk = gradeGroupKey(room.student?.grade_level);
      if (grouped[gk]) grouped[gk].push(room);
    });

    return { pinnedRooms: pinned, gradeGroupedRooms: grouped };
  }, [rooms, debouncedSearch]);

  // Mobile: show room list or chat
  const showRoomList = isDesktop || !showChat;
  const showChatArea = isDesktop || showChat;

  return (
    <div className="flex h-[calc(100vh-3.5rem)] overflow-hidden sm:h-[calc(100vh-4rem)] sm:rounded-lg sm:border sm:border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-1)]">
      {/* ===== Left Panel: Room List ===== */}
      {showRoomList && (
        <div className={cn(
          'flex flex-col border-r border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-1)]',
          isDesktop ? 'w-[300px] shrink-0' : 'w-full'
        )}>
          {/* Header */}
          <div className="border-b border-[var(--neutral-stroke-2)] px-3 py-2.5 space-y-2">
            <div className="flex items-center justify-between">
              <span className="text-xs font-semibold text-[var(--neutral-foreground-2)]">チャット</span>
              <button
                onClick={() => setBroadcastModal(true)}
                className="flex items-center gap-1 rounded-md px-2 py-1 text-[10px] font-medium text-[var(--neutral-foreground-3)] hover:bg-[var(--neutral-background-3)] transition-colors"
                title="一斉送信"
              >
                <MaterialIcon name="campaign" size={14} />
                一斉送信
              </button>
            </div>
            {/* クイック通知 (選択した保護者に送信) */}
            <div className="flex gap-1.5">
              <button
                onClick={() => openQuickModal('departure')}
                className="flex-1 flex items-center justify-center gap-1 rounded-md border border-[var(--brand-80)]/40 bg-[var(--brand-10)] px-2 py-1.5 text-[10px] font-medium text-[var(--brand-100)] hover:bg-[var(--brand-20)] transition-colors"
                title="選択した保護者に「これから帰ります」を送信"
              >
                <MaterialIcon name="directions_bus" size={14} />
                これから帰ります
              </button>
              <button
                onClick={() => openQuickModal('arrival')}
                className="flex-1 flex items-center justify-center gap-1 rounded-md border border-[var(--brand-80)]/40 bg-[var(--brand-10)] px-2 py-1.5 text-[10px] font-medium text-[var(--brand-100)] hover:bg-[var(--brand-20)] transition-colors"
                title="選択した保護者に「到着しました」を送信"
              >
                <MaterialIcon name="check_circle" size={14} />
                到着しました
              </button>
            </div>
            <div className="relative">
              <MaterialIcon name="search" size={14} className="absolute left-2.5 top-1/2 -translate-y-1/2 text-[var(--neutral-foreground-4)]" />
              <Input
                placeholder="生徒名・保護者名で検索..."
                value={search}
                onChange={(e) => setSearch(e.target.value)}
                className="h-8 pl-8 text-xs"
              />
            </div>
          </div>

          {/* Room list */}
          <div className="flex-1 overflow-y-auto">
            {isLoadingRooms ? (
              <div className="space-y-2 p-3">
                {[...Array(8)].map((_, i) => (
                  <Skeleton key={i} className="h-12 w-full rounded-md" />
                ))}
              </div>
            ) : (
              <>
                {/* Pinned rooms */}
                {pinnedRooms.length > 0 && (
                  <div className="border-b border-[var(--neutral-stroke-3)]">
                    <div className="px-3 py-1.5 text-[10px] font-semibold uppercase tracking-wider text-[var(--neutral-foreground-4)]">
                      ピン留め
                    </div>
                    {pinnedRooms.map((room) => (
                      <RoomItem
                        key={room.id}
                        room={room}
                        isActive={activeRoom?.id === room.id}
                        unread={unreadCounts[room.id] || 0}
                        onClick={() => handleSelectRoom(room)}
                      />
                    ))}
                  </div>
                )}

                {/* Grade groups */}
                {GRADE_GROUPS.map((group) => {
                  const groupRooms = gradeGroupedRooms[group.key] || [];
                  if (groupRooms.length === 0) return null;
                  const isExpanded = expandedGroups.has(group.key);
                  const unreadInGroup = groupRooms.reduce(
                    (sum, r) => sum + (unreadCounts[r.id] || 0), 0
                  );

                  return (
                    <div key={group.key} className="border-b border-[var(--neutral-stroke-3)]">
                      <button
                        onClick={() => toggleGroup(group.key)}
                        className="flex w-full items-center justify-between px-3 py-2 text-left hover:bg-[var(--neutral-background-3)] transition-colors"
                      >
                        <div className="flex items-center gap-1.5">
                          {isExpanded ? (
                            <MaterialIcon name="expand_more" size={14} className="text-[var(--neutral-foreground-3)]" />
                          ) : (
                            <MaterialIcon name="chevron_right" size={14} className="text-[var(--neutral-foreground-3)]" />
                          )}
                          <span className="text-xs font-semibold text-[var(--neutral-foreground-2)]">
                            {group.label}
                          </span>
                          <span className="text-[10px] text-[var(--neutral-foreground-4)]">
                            ({groupRooms.length})
                          </span>
                        </div>
                        {unreadInGroup > 0 && (
                          <Badge variant="danger">{unreadInGroup}</Badge>
                        )}
                      </button>
                      {isExpanded && groupRooms.map((room) => (
                        <RoomItem
                          key={room.id}
                          room={room}
                          isActive={activeRoom?.id === room.id}
                          unread={unreadCounts[room.id] || 0}
                          onClick={() => handleSelectRoom(room)}
                        />
                      ))}
                    </div>
                  );
                })}

                {rooms.length === 0 && !isLoadingRooms && (
                  <div className="px-4 py-12 text-center text-sm text-[var(--neutral-foreground-4)]">
                    チャットルームがありません
                  </div>
                )}
              </>
            )}
          </div>
        </div>
      )}

      {/* ===== Right Panel: Chat Area ===== */}
      {showChatArea && (
        <div className="flex flex-1 flex-col">
          {activeRoom ? (
            <>
              {/* Chat header */}
              <div className="flex items-center justify-between border-b border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-1)] px-4 py-2.5">
                <div className="flex items-center gap-2">
                  {/* Back button on mobile */}
                  {!isDesktop && (
                    <button
                      onClick={handleBack}
                      className="rounded-md p-1 text-[var(--neutral-foreground-3)] hover:bg-[var(--neutral-background-3)]"
                    >
                      <MaterialIcon name="chevron_left" size={20} />
                    </button>
                  )}
                  <div>
                    <h2 className="text-sm font-semibold text-[var(--neutral-foreground-1)]">
                      {activeRoom.student?.student_name}さん
                    </h2>
                    {activeRoom.guardian && (
                      <p className="text-xs text-[var(--neutral-foreground-3)]">
                        保護者: {activeRoom.guardian.full_name}
                      </p>
                    )}
                  </div>
                </div>
                <div className="flex items-center gap-1">
                  <button
                    onClick={handleTogglePin}
                    className={cn(
                      'rounded-md p-1.5 transition-colors',
                      activeRoom.is_pinned
                        ? 'bg-[var(--brand-160)] text-[var(--brand-80)]'
                        : 'text-[var(--neutral-foreground-3)] hover:bg-[var(--neutral-background-3)]'
                    )}
                    title={activeRoom.is_pinned ? 'ピン解除' : 'ピン留め'}
                  >
                    <MaterialIcon name="push_pin" size={16} />
                  </button>
                  <button
                    onClick={() => setMeetingModal(true)}
                    className="rounded-md p-1.5 text-[var(--neutral-foreground-3)] hover:bg-[var(--neutral-background-3)] transition-colors"
                    title="面談予約"
                  >
                    <MaterialIcon name="event" size={16} />
                  </button>
                  <button
                    onClick={() => setSubmissionModal(true)}
                    className="rounded-md p-1.5 text-[var(--neutral-foreground-3)] hover:bg-[var(--neutral-background-3)] transition-colors"
                    title="提出期限"
                  >
                    <MaterialIcon name="checklist" size={16} />
                  </button>
                </div>
              </div>

              {/* Messages */}
              <div className="flex-1 overflow-y-auto bg-[var(--neutral-background-2)]">
                {isLoadingMessages ? (
                  <div className="p-4 space-y-3">
                    {[...Array(6)].map((_, i) => (
                      <Skeleton key={i} className={cn('h-12 rounded-lg', i % 2 === 0 ? 'w-2/3' : 'ml-auto w-1/2')} />
                    ))}
                  </div>
                ) : (
                  <ChatMessageList
                    messages={messages}
                    currentUserId={user?.id || 0}
                  />
                )}
              </div>

              {/* Input */}
              <ChatInput onSend={handleSend} isSending={isSending} />
            </>
          ) : (
            /* Empty state - only shown on desktop */
            <div className="flex flex-1 items-center justify-center text-[var(--neutral-foreground-4)]">
              <div className="text-center">
                <MaterialIcon name="chat" size={48} className="mx-auto mb-3" />
                <p className="text-sm">左のリストからチャットルームを選択してください</p>
              </div>
            </div>
          )}
        </div>
      )}

      {/* Modals */}
      <MeetingModal
        isOpen={meetingModal} onClose={() => setMeetingModal(false)}
        purpose={meetingPurpose} setPurpose={setMeetingPurpose}
        detail={meetingDetail} setDetail={setMeetingDetail}
        dates={meetingDates} setDates={setMeetingDates}
        onSubmit={handleMeetingSubmit} isSending={meetingSending}
      />
      <SubmissionModal
        isOpen={submissionModal} onClose={() => setSubmissionModal(false)}
        title={submissionTitle} setTitle={setSubmissionTitle}
        desc={submissionDesc} setDesc={setSubmissionDesc}
        dueDate={submissionDueDate} setDueDate={setSubmissionDueDate}
        onSubmit={handleSubmissionSubmit} isSending={submissionSending}
      />
      <BroadcastModal
        isOpen={broadcastModal} onClose={() => { setBroadcastModal(false); setBroadcastFile(null); }}
        message={broadcastMessage} setMessage={setBroadcastMessage}
        file={broadcastFile} setFile={setBroadcastFile}
        selectedRoomIds={broadcastRoomIds} setSelectedRoomIds={setBroadcastRoomIds}
        rooms={rooms}
        onSubmit={handleBroadcast} isSending={broadcastSending}
      />
      <QuickNotifyModal
        action={quickModal}
        onClose={() => setQuickModal(null)}
        rooms={rooms}
        selectedRoomIds={quickRoomIds}
        toggleRoom={toggleQuickRoom}
        selectAll={() => selectAllQuickRooms(rooms.map((r) => r.id))}
        selectNone={clearQuickRooms}
        onSubmit={handleQuickBroadcastSubmit}
        isSending={quickSending}
      />
    </div>
  );
}

// ---------------------------------------------------------------------------
// Meeting Modal
// ---------------------------------------------------------------------------

function MeetingModal({
  isOpen, onClose, purpose, setPurpose, detail, setDetail,
  dates, setDates, onSubmit, isSending,
}: {
  isOpen: boolean; onClose: () => void;
  purpose: string; setPurpose: (v: string) => void;
  detail: string; setDetail: (v: string) => void;
  dates: string[]; setDates: (v: string[]) => void;
  onSubmit: () => void; isSending: boolean;
}) {
  const updateDate = (i: number, val: string) => {
    const next = [...dates];
    next[i] = val;
    setDates(next);
  };
  return (
    <Modal isOpen={isOpen} onClose={onClose} title="面談予約" size="md">
      <div className="space-y-4">
        <div>
          <label className="mb-1 block text-sm font-medium text-[var(--neutral-foreground-2)]">目的 *</label>
          <input value={purpose} onChange={(e) => setPurpose(e.target.value)}
            className="block w-full rounded-lg border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-1)] px-3 py-2 text-sm text-[var(--neutral-foreground-1)]"
            placeholder="面談の目的..." />
        </div>
        <div>
          <label className="mb-1 block text-sm font-medium text-[var(--neutral-foreground-2)]">詳細</label>
          <textarea value={detail} onChange={(e) => setDetail(e.target.value)} rows={2}
            className="block w-full rounded-lg border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-1)] px-3 py-2 text-sm text-[var(--neutral-foreground-1)]"
            placeholder="補足事項..." />
        </div>
        <div>
          <label className="mb-1 block text-sm font-medium text-[var(--neutral-foreground-2)]">候補日時 *（最大3つ）</label>
          {[0, 1, 2].map((i) => (
            <input key={i} type="datetime-local" value={dates[i]}
              onChange={(e) => updateDate(i, e.target.value)}
              className="mt-1 block w-full rounded-lg border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-1)] px-3 py-2 text-sm text-[var(--neutral-foreground-1)]" />
          ))}
        </div>
        <div className="flex justify-end gap-2">
          <Button variant="secondary" onClick={onClose}>キャンセル</Button>
          <Button onClick={onSubmit} isLoading={isSending}
            disabled={!purpose || dates.every((d) => !d)}>送信</Button>
        </div>
      </div>
    </Modal>
  );
}

// ---------------------------------------------------------------------------
// Submission Modal
// ---------------------------------------------------------------------------

function SubmissionModal({
  isOpen, onClose, title, setTitle, desc, setDesc,
  dueDate, setDueDate, onSubmit, isSending,
}: {
  isOpen: boolean; onClose: () => void;
  title: string; setTitle: (v: string) => void;
  desc: string; setDesc: (v: string) => void;
  dueDate: string; setDueDate: (v: string) => void;
  onSubmit: () => void; isSending: boolean;
}) {
  return (
    <Modal isOpen={isOpen} onClose={onClose} title="提出期限の設定" size="md">
      <div className="space-y-4">
        <div>
          <label className="mb-1 block text-sm font-medium text-[var(--neutral-foreground-2)]">提出物名 *</label>
          <input value={title} onChange={(e) => setTitle(e.target.value)}
            className="block w-full rounded-lg border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-1)] px-3 py-2 text-sm text-[var(--neutral-foreground-1)]"
            placeholder="提出物の名称..." />
        </div>
        <div>
          <label className="mb-1 block text-sm font-medium text-[var(--neutral-foreground-2)]">説明</label>
          <textarea value={desc} onChange={(e) => setDesc(e.target.value)} rows={2}
            className="block w-full rounded-lg border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-1)] px-3 py-2 text-sm text-[var(--neutral-foreground-1)]"
            placeholder="補足事項..." />
        </div>
        <div>
          <label className="mb-1 block text-sm font-medium text-[var(--neutral-foreground-2)]">提出期限 *</label>
          <input type="date" value={dueDate} onChange={(e) => setDueDate(e.target.value)}
            className="block w-full rounded-lg border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-1)] px-3 py-2 text-sm text-[var(--neutral-foreground-1)]" />
        </div>
        <div className="flex justify-end gap-2">
          <Button variant="secondary" onClick={onClose}>キャンセル</Button>
          <Button onClick={onSubmit} isLoading={isSending}
            disabled={!title || !dueDate}>設定</Button>
        </div>
      </div>
    </Modal>
  );
}

// ---------------------------------------------------------------------------
// Broadcast Modal
// ---------------------------------------------------------------------------

function BroadcastModal({
  isOpen, onClose, message, setMessage, file, setFile,
  selectedRoomIds, setSelectedRoomIds, rooms, onSubmit, isSending,
}: {
  isOpen: boolean; onClose: () => void;
  message: string; setMessage: (v: string) => void;
  file: File | null; setFile: (f: File | null) => void;
  selectedRoomIds: Set<number>; setSelectedRoomIds: (ids: Set<number>) => void;
  rooms: ChatRoom[];
  onSubmit: () => void; isSending: boolean;
}) {
  const fileInputRef = useRef<HTMLInputElement>(null);

  // Initialize: select all rooms when modal opens
  useEffect(() => {
    if (isOpen && selectedRoomIds.size === 0 && rooms.length > 0) {
      setSelectedRoomIds(new Set(rooms.map((r) => r.id)));
    }
  }, [isOpen, rooms, selectedRoomIds.size, setSelectedRoomIds]);

  const toggleRoom = (id: number) => {
    const next = new Set(selectedRoomIds);
    if (next.has(id)) next.delete(id);
    else next.add(id);
    setSelectedRoomIds(next);
  };

  const selectAll = () => setSelectedRoomIds(new Set(rooms.map((r) => r.id)));
  const selectNone = () => setSelectedRoomIds(new Set());

  const handleFileSelect = (e: React.ChangeEvent<HTMLInputElement>) => {
    const f = e.target.files?.[0];
    if (f) {
      if (f.size > 3 * 1024 * 1024) {
        alert('ファイルサイズは3MB以下にしてください');
        return;
      }
      setFile(f);
    }
    if (fileInputRef.current) fileInputRef.current.value = '';
  };

  return (
    <Modal isOpen={isOpen} onClose={onClose} title="保護者に一斉送信" size="md">
      <div className="space-y-4">
        {/* Recipient selection */}
        <div>
          <div className="mb-1 flex items-center justify-between">
            <label className="text-sm font-medium text-[var(--neutral-foreground-2)]">送信先を選択</label>
            <div className="flex gap-2">
              <button onClick={selectAll} className="text-xs text-[var(--brand-80)] hover:underline">全選択</button>
              <button onClick={selectNone} className="text-xs text-[var(--neutral-foreground-4)] hover:underline">全解除</button>
            </div>
          </div>
          <div className="max-h-[200px] overflow-y-auto rounded-lg border border-[var(--neutral-stroke-2)] p-2">
            {rooms.map((room) => (
              <label key={room.id} className="flex items-center gap-2 rounded px-2 py-1 text-sm hover:bg-[var(--neutral-background-3)] cursor-pointer">
                <input
                  type="checkbox"
                  checked={selectedRoomIds.has(room.id)}
                  onChange={() => toggleRoom(room.id)}
                  className="rounded border-[var(--neutral-stroke-2)]"
                />
                <span className="text-[var(--neutral-foreground-1)]">
                  {room.student?.student_name || `ID: ${room.student_id}`}
                </span>
                {room.guardian?.full_name && (
                  <span className="text-xs text-[var(--neutral-foreground-4)]">({room.guardian.full_name})</span>
                )}
              </label>
            ))}
          </div>
          <p className="mt-1 text-xs text-[var(--neutral-foreground-3)]">
            {selectedRoomIds.size}/{rooms.length}件 選択中
          </p>
        </div>

        {/* Message */}
        <div>
          <label className="mb-1 block text-sm font-medium text-[var(--neutral-foreground-2)]">メッセージ</label>
          <textarea value={message} onChange={(e) => setMessage(e.target.value)} rows={4}
            className="block w-full rounded-lg border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-1)] px-3 py-2 text-sm text-[var(--neutral-foreground-1)]"
            placeholder="送信するメッセージを入力..." />
        </div>

        {/* File attachment */}
        <div>
          <label className="mb-1 block text-sm font-medium text-[var(--neutral-foreground-2)]">ファイル添付（任意）</label>
          {file ? (
            <div className="flex items-center gap-2 rounded-lg bg-[var(--neutral-background-3)] px-3 py-2">
              <MaterialIcon name="attach_file" size={16} className="text-[var(--neutral-foreground-4)]" />
              <span className="flex-1 truncate text-sm text-[var(--neutral-foreground-2)]">{file.name}</span>
              <span className="text-xs text-[var(--neutral-foreground-4)]">{(file.size / 1024).toFixed(0)}KB</span>
              <button onClick={() => setFile(null)} className="rounded p-1 text-[var(--neutral-foreground-4)] hover:text-red-500">
                <MaterialIcon name="close" size={16} />
              </button>
            </div>
          ) : (
            <button
              onClick={() => fileInputRef.current?.click()}
              className="flex w-full items-center gap-2 rounded-lg border border-dashed border-[var(--neutral-stroke-2)] px-3 py-2 text-sm text-[var(--neutral-foreground-3)] hover:bg-[var(--neutral-background-3)]"
            >
              <MaterialIcon name="attach_file" size={16} />
              ファイルを選択（3MB以下）
            </button>
          )}
          <input ref={fileInputRef} type="file" className="hidden"
            accept="image/*,.pdf,.doc,.docx,.xls,.xlsx,.txt,.csv" onChange={handleFileSelect} />
          <p className="mt-1 text-xs text-[var(--neutral-foreground-4)]">※ 1つのファイルを全員に共有します</p>
        </div>

        <div className="flex justify-end gap-2">
          <Button variant="secondary" onClick={onClose}>キャンセル</Button>
          <Button onClick={onSubmit} isLoading={isSending}
            disabled={(!message.trim() && !file) || selectedRoomIds.size === 0}
            leftIcon={<MaterialIcon name="send" size={16} />}>
            {selectedRoomIds.size}件に送信
          </Button>
        </div>
      </div>
    </Modal>
  );
}

// ---------------------------------------------------------------------------
// Quick Notify Modal (これから帰ります / 到着しました)
// ---------------------------------------------------------------------------

function QuickNotifyModal({
  action, onClose, rooms, selectedRoomIds, toggleRoom, selectAll, selectNone, onSubmit, isSending,
}: {
  action: 'departure' | 'arrival' | null;
  onClose: () => void;
  rooms: ChatRoom[];
  selectedRoomIds: Set<number>;
  toggleRoom: (id: number) => void;
  selectAll: () => void;
  selectNone: () => void;
  onSubmit: () => void;
  isSending: boolean;
}) {
  const label = action === 'departure' ? 'これから帰ります' : '到着しました';
  const iconName = action === 'departure' ? 'directions_bus' : 'check_circle';
  const bodyPreview =
    action === 'departure'
      ? '【これから帰ります】\n\nお迎え準備をお願いいたします。'
      : '【到着しました】\n\nご対応ありがとうございました。';

  return (
    <Modal isOpen={action !== null} onClose={onClose} title={`${label} を送信`} size="md">
      <div className="space-y-4">
        <div className="rounded border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-3)] p-3">
          <div className="flex items-center gap-2 text-sm font-semibold text-[var(--neutral-foreground-1)]">
            <MaterialIcon name={iconName} size={18} className="text-[var(--brand-80)]" />
            送信内容（固定）
          </div>
          <pre className="mt-2 whitespace-pre-wrap text-xs text-[var(--neutral-foreground-2)]">{bodyPreview}</pre>
        </div>

        <div>
          <div className="mb-1 flex items-center justify-between">
            <label className="text-sm font-medium text-[var(--neutral-foreground-2)]">送信先を選択</label>
            <div className="flex gap-2">
              <button onClick={selectAll} className="text-xs text-[var(--brand-80)] hover:underline">全選択</button>
              <button onClick={selectNone} className="text-xs text-[var(--neutral-foreground-4)] hover:underline">全解除</button>
            </div>
          </div>
          <div className="max-h-[250px] overflow-y-auto rounded-lg border border-[var(--neutral-stroke-2)] p-2">
            {rooms.length === 0 ? (
              <p className="p-2 text-center text-xs text-[var(--neutral-foreground-4)]">保護者チャットがありません</p>
            ) : (
              rooms.map((room) => (
                <label key={room.id} className="flex items-center gap-2 rounded px-2 py-1 text-sm hover:bg-[var(--neutral-background-3)] cursor-pointer">
                  <input
                    type="checkbox"
                    checked={selectedRoomIds.has(room.id)}
                    onChange={() => toggleRoom(room.id)}
                    className="rounded border-[var(--neutral-stroke-2)]"
                  />
                  <span className="text-[var(--neutral-foreground-1)]">
                    {room.student?.student_name || `ID: ${room.student_id}`}
                  </span>
                  {room.guardian?.full_name && (
                    <span className="text-xs text-[var(--neutral-foreground-4)]">({room.guardian.full_name})</span>
                  )}
                </label>
              ))
            )}
          </div>
          <p className="mt-1 text-xs text-[var(--neutral-foreground-3)]">
            {selectedRoomIds.size}/{rooms.length}件 選択中
          </p>
        </div>

        <div className="flex justify-end gap-2">
          <Button variant="secondary" onClick={onClose}>キャンセル</Button>
          <Button onClick={onSubmit} isLoading={isSending}
            disabled={selectedRoomIds.size === 0}
            leftIcon={<MaterialIcon name="send" size={16} />}>
            {selectedRoomIds.size}件に送信
          </Button>
        </div>
      </div>
    </Modal>
  );
}

// ---------------------------------------------------------------------------
// Room Item
// ---------------------------------------------------------------------------

function RoomItem({
  room,
  isActive,
  unread,
  onClick,
}: {
  room: ChatRoom;
  isActive: boolean;
  unread: number;
  onClick: () => void;
}) {
  return (
    <button
      onClick={onClick}
      className={cn(
        'flex w-full items-center gap-2.5 px-3 py-2 text-left transition-colors',
        isActive
          ? 'bg-[var(--brand-160)] border-l-2 border-[var(--brand-80)]'
          : 'hover:bg-[var(--neutral-background-3)]'
      )}
    >
      {/* Avatar */}
      <div className="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-[var(--brand-140)] text-xs font-semibold text-[var(--brand-80)]">
        {room.student?.student_name?.charAt(0) || '?'}
      </div>

      {/* Info */}
      <div className="flex-1 min-w-0">
        <div className="flex items-center gap-1">
          <span className={cn(
            'text-xs truncate',
            unread > 0 ? 'font-bold text-[var(--neutral-foreground-1)]' : 'font-medium text-[var(--neutral-foreground-1)]'
          )}>
            {room.student?.student_name}さん
          </span>
          {room.is_pinned && <MaterialIcon name="push_pin" size={18} className="h-2.5 w-2.5 text-[var(--neutral-foreground-4)]" />}
        </div>
        {room.last_message && (
          <p className="text-[10px] text-[var(--neutral-foreground-4)] truncate">
            {typeof room.last_message === 'string'
              ? truncate(room.last_message, 25)
              : (() => {
                  const msg = room.last_message;
                  const prefix = msg.message_type === 'absence_notification' ? '【欠席】'
                    : msg.message_type === 'meeting_request' ? '【面談】'
                    : msg.message_type === 'meeting_counter' ? '【面談】'
                    : msg.message_type === 'meeting_confirmed' ? '【面談確定】'
                    : msg.message_type === 'broadcast' ? '【一斉】'
                    : msg.message_type === 'event_registration' ? '【イベント】'
                    : '';
                  return truncate(prefix + (msg.message || ''), 25);
                })()}
          </p>
        )}
      </div>

      {/* Unread + time */}
      <div className="flex flex-col items-end gap-0.5 shrink-0">
        {room.last_message_at && (
          <span className="text-[9px] text-[var(--neutral-foreground-4)]">
            {formatRelativeTime(room.last_message_at)}
          </span>
        )}
        {unread > 0 && (
          <span className="flex h-4 min-w-[16px] items-center justify-center rounded-full bg-[var(--status-danger-fg)] px-1 text-[9px] font-bold text-white">
            {unread > 99 ? '99+' : unread}
          </span>
        )}
      </div>
    </button>
  );
}
