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
import { Search, Pin, ChevronDown, ChevronRight, ChevronLeft, Send, MessageCircle } from 'lucide-react';
import api from '@/lib/api';
import type { ChatRoom } from '@/types/chat';

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
  const [expandedGroups, setExpandedGroups] = useState<Set<string>>(
    new Set(GRADE_GROUPS.map((g) => g.key))
  );
  // Mobile: track whether we're showing chat view
  const [showChat, setShowChat] = useState(false);

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
    <div className="flex h-[calc(100vh-4rem)] overflow-hidden rounded-lg border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-1)]">
      {/* ===== Left Panel: Room List ===== */}
      {showRoomList && (
        <div className={cn(
          'flex flex-col border-r border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-1)]',
          isDesktop ? 'w-[300px] shrink-0' : 'w-full'
        )}>
          {/* Header */}
          <div className="border-b border-[var(--neutral-stroke-2)] px-3 py-2.5">
            <div className="relative">
              <Search className="absolute left-2.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-[var(--neutral-foreground-4)]" />
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
                            <ChevronDown className="h-3.5 w-3.5 text-[var(--neutral-foreground-3)]" />
                          ) : (
                            <ChevronRight className="h-3.5 w-3.5 text-[var(--neutral-foreground-3)]" />
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
                      <ChevronLeft className="h-5 w-5" />
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
                <div className="flex items-center gap-2">
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
                    <Pin className="h-4 w-4" />
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
                <MessageCircle className="mx-auto mb-3 h-12 w-12" />
                <p className="text-sm">左のリストからチャットルームを選択してください</p>
              </div>
            </div>
          )}
        </div>
      )}
    </div>
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
          {room.is_pinned && <Pin className="h-2.5 w-2.5 text-[var(--neutral-foreground-4)]" />}
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
