'use client';

import { useEffect, useState, useMemo, useCallback, useRef } from 'react';
import { useAuthStore } from '@/stores/authStore';
import { useDebounce } from '@/hooks/useDebounce';
import { useIsDesktop } from '@/hooks/useMediaQuery';
import { Input } from '@/components/ui/Input';
import { Badge } from '@/components/ui/Badge';
import { Skeleton } from '@/components/ui/Skeleton';
import { Button } from '@/components/ui/Button';
import { Modal } from '@/components/ui/Modal';
import { useToast } from '@/components/ui/Toast';
import { cn, formatRelativeTime, formatFileSize, truncate } from '@/lib/utils';
import {
  Search,
  ChevronLeft,
  Send,
  MessageCircle,
  Paperclip,
  X,
  Plus,
  Users,
  User,
  Download,
} from 'lucide-react';
import api from '@/lib/api';

// ---------------------------------------------------------------------------
// Types
// ---------------------------------------------------------------------------

interface StaffChatRoom {
  id: number;
  room_type: 'direct' | 'group';
  room_name: string | null;
  display_name: string;
  last_message: string | null;
  last_message_at: string | null;
  unread_count: number;
  members: { id: number; full_name: string }[];
}

interface StaffChatMessage {
  id: number;
  sender_id: number;
  sender_name: string;
  message: string | null;
  attachment_path: string | null;
  attachment_original_name: string | null;
  attachment_size: number | null;
  is_deleted: boolean;
  created_at: string;
}

interface StaffMember {
  id: number;
  full_name: string;
  classroom_id: number;
}

// ---------------------------------------------------------------------------
// Main Page
// ---------------------------------------------------------------------------

export default function StaffChatPage() {
  const { user } = useAuthStore();
  const isDesktop = useIsDesktop();
  const toast = useToast();

  // Room state
  const [rooms, setRooms] = useState<StaffChatRoom[]>([]);
  const [isLoadingRooms, setIsLoadingRooms] = useState(true);
  const [activeRoom, setActiveRoom] = useState<StaffChatRoom | null>(null);

  // Message state
  const [messages, setMessages] = useState<StaffChatMessage[]>([]);
  const [isLoadingMessages, setIsLoadingMessages] = useState(false);

  // Input state
  const [messageText, setMessageText] = useState('');
  const [attachment, setAttachment] = useState<File | null>(null);
  const [isSending, setIsSending] = useState(false);
  const fileInputRef = useRef<HTMLInputElement>(null);
  const textareaRef = useRef<HTMLTextAreaElement>(null);
  const messagesEndRef = useRef<HTMLDivElement>(null);

  // Search & mobile
  const [search, setSearch] = useState('');
  const debouncedSearch = useDebounce(search, 300);
  const [showChat, setShowChat] = useState(false);

  // Create room modal
  const [createModal, setCreateModal] = useState(false);

  // ---- Fetch rooms ----
  const fetchRooms = useCallback(async () => {
    try {
      const res = await api.get('/api/staff/staff-chat/rooms');
      setRooms(res.data?.data || res.data || []);
    } catch {
      /* ignore */
    } finally {
      setIsLoadingRooms(false);
    }
  }, []);

  useEffect(() => {
    fetchRooms();
  }, [fetchRooms]);

  // ---- Poll room list every 10s ----
  useEffect(() => {
    const interval = setInterval(() => {
      fetchRooms();
    }, 10000);
    return () => clearInterval(interval);
  }, [fetchRooms]);

  // ---- Fetch messages ----
  const fetchMessages = useCallback(async (roomId: number, lastId?: number) => {
    try {
      const params: Record<string, unknown> = {};
      if (lastId) params.last_id = lastId;
      const res = await api.get(`/api/staff/staff-chat/rooms/${roomId}/messages`, { params });
      const data: StaffChatMessage[] = res.data?.data || res.data || [];
      if (lastId) {
        // Append new messages
        setMessages((prev) => {
          const existingIds = new Set(prev.map((m) => m.id));
          const newMsgs = data.filter((m) => !existingIds.has(m.id));
          return newMsgs.length > 0 ? [...prev, ...newMsgs] : prev;
        });
      } else {
        setMessages(data);
      }
    } catch {
      /* ignore */
    }
  }, []);

  // ---- Select room ----
  const handleSelectRoom = useCallback(
    async (room: StaffChatRoom) => {
      setActiveRoom(room);
      setIsLoadingMessages(true);
      setShowChat(true);
      await fetchMessages(room.id);
      setIsLoadingMessages(false);
    },
    [fetchMessages]
  );

  // ---- Poll messages every 3s ----
  useEffect(() => {
    if (!activeRoom) return;
    const interval = setInterval(() => {
      const lastMsg = messages[messages.length - 1];
      fetchMessages(activeRoom.id, lastMsg?.id);
    }, 3000);
    return () => clearInterval(interval);
  }, [activeRoom, messages, fetchMessages]);

  // ---- Scroll to bottom on new messages ----
  useEffect(() => {
    messagesEndRef.current?.scrollIntoView({ behavior: 'smooth' });
  }, [messages]);

  // ---- Send message ----
  const handleSend = useCallback(async () => {
    const trimmed = messageText.trim();
    if (!trimmed && !attachment) return;
    if (!activeRoom) return;

    setIsSending(true);
    try {
      const formData = new FormData();
      if (trimmed) formData.append('message', trimmed);
      if (attachment) formData.append('attachment', attachment);

      await api.post(`/api/staff/staff-chat/rooms/${activeRoom.id}/messages`, formData, {
        headers: { 'Content-Type': 'multipart/form-data' },
      });

      setMessageText('');
      setAttachment(null);
      // Refetch all messages
      await fetchMessages(activeRoom.id);
      textareaRef.current?.focus();
    } catch {
      toast.error('メッセージの送信に失敗しました');
    } finally {
      setIsSending(false);
    }
  }, [messageText, attachment, activeRoom, fetchMessages, toast]);

  // ---- Key handler ----
  const handleKeyDown = useCallback(
    (e: React.KeyboardEvent<HTMLTextAreaElement>) => {
      if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        handleSend();
      }
    },
    [handleSend]
  );

  // ---- File select ----
  const handleFileSelect = (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0];
    if (file) {
      if (file.size > 10 * 1024 * 1024) {
        alert('ファイルサイズは10MB以下にしてください');
        return;
      }
      setAttachment(file);
    }
    if (fileInputRef.current) {
      fileInputRef.current.value = '';
    }
  };

  // ---- Back (mobile) ----
  const handleBack = useCallback(() => {
    setShowChat(false);
  }, []);

  // ---- Room created callback ----
  const handleRoomCreated = useCallback(
    (room: StaffChatRoom) => {
      setCreateModal(false);
      fetchRooms();
      handleSelectRoom(room);
    },
    [fetchRooms, handleSelectRoom]
  );

  // ---- Filter rooms ----
  const filteredRooms = useMemo(() => {
    if (!debouncedSearch) return rooms;
    const q = debouncedSearch.toLowerCase();
    return rooms.filter((room) => {
      const displayName = room.display_name?.toLowerCase() || '';
      const memberNames = room.members?.map((m) => m.full_name.toLowerCase()).join(' ') || '';
      return displayName.includes(q) || memberNames.includes(q);
    });
  }, [rooms, debouncedSearch]);

  // ---- Sort rooms by last_message_at ----
  const sortedRooms = useMemo(() => {
    return [...filteredRooms].sort((a, b) => {
      if (!a.last_message_at && !b.last_message_at) return 0;
      if (!a.last_message_at) return 1;
      if (!b.last_message_at) return -1;
      return new Date(b.last_message_at).getTime() - new Date(a.last_message_at).getTime();
    });
  }, [filteredRooms]);

  // Mobile: show room list or chat
  const showRoomList = isDesktop || !showChat;
  const showChatArea = isDesktop || showChat;

  return (
    <div className="flex h-[calc(100vh-4rem)] overflow-hidden rounded-lg border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-1)]">
      {/* ===== Left Panel: Room List ===== */}
      {showRoomList && (
        <div
          className={cn(
            'flex flex-col border-r border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-1)]',
            isDesktop ? 'w-[300px] shrink-0' : 'w-full'
          )}
        >
          {/* Header */}
          <div className="border-b border-[var(--neutral-stroke-2)] px-3 py-2.5 space-y-2">
            <div className="flex items-center justify-between">
              <span className="text-xs font-semibold text-[var(--neutral-foreground-2)]">
                スタッフチャット
              </span>
              <button
                onClick={() => setCreateModal(true)}
                className="flex items-center gap-1 rounded-md px-2 py-1 text-[10px] font-medium text-[var(--neutral-foreground-3)] hover:bg-[var(--neutral-background-3)] transition-colors"
                title="新規チャット"
              >
                <Plus className="h-3.5 w-3.5" />
                新規
              </button>
            </div>
            <div className="relative">
              <Search className="absolute left-2.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-[var(--neutral-foreground-4)]" />
              <Input
                placeholder="スタッフ名で検索..."
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
            ) : sortedRooms.length > 0 ? (
              sortedRooms.map((room) => (
                <StaffRoomItem
                  key={room.id}
                  room={room}
                  isActive={activeRoom?.id === room.id}
                  onClick={() => handleSelectRoom(room)}
                />
              ))
            ) : (
              <div className="px-4 py-12 text-center text-sm text-[var(--neutral-foreground-4)]">
                {debouncedSearch ? '検索結果がありません' : 'チャットルームがありません'}
              </div>
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
                  {!isDesktop && (
                    <button
                      onClick={handleBack}
                      className="rounded-md p-1 text-[var(--neutral-foreground-3)] hover:bg-[var(--neutral-background-3)]"
                    >
                      <ChevronLeft className="h-5 w-5" />
                    </button>
                  )}
                  <div className="flex items-center gap-2">
                    <h2 className="text-sm font-semibold text-[var(--neutral-foreground-1)]">
                      {activeRoom.display_name}
                    </h2>
                    <Badge variant={activeRoom.room_type === 'direct' ? 'info' : 'success'}>
                      {activeRoom.room_type === 'direct' ? '個人' : 'グループ'}
                    </Badge>
                  </div>
                </div>
              </div>

              {/* Messages */}
              <div className="flex-1 overflow-y-auto bg-[var(--neutral-background-2)] p-4">
                {isLoadingMessages ? (
                  <div className="space-y-3">
                    {[...Array(6)].map((_, i) => (
                      <Skeleton
                        key={i}
                        className={cn(
                          'h-12 rounded-lg',
                          i % 2 === 0 ? 'w-2/3' : 'ml-auto w-1/2'
                        )}
                      />
                    ))}
                  </div>
                ) : messages.length > 0 ? (
                  <div className="space-y-3">
                    {messages.map((msg) => {
                      const isMine = msg.sender_id === user?.id;
                      return (
                        <div
                          key={msg.id}
                          className={cn('flex', isMine ? 'justify-end' : 'justify-start')}
                        >
                          <div className={cn('max-w-[70%]')}>
                            {/* Sender name */}
                            {!isMine && (
                              <p className="mb-0.5 text-[10px] font-medium text-[var(--neutral-foreground-3)]">
                                {msg.sender_name}
                              </p>
                            )}
                            {/* Message bubble */}
                            <div
                              className={cn(
                                'rounded-xl px-3 py-2 text-sm',
                                isMine
                                  ? 'bg-[var(--brand-80)] text-white'
                                  : 'bg-[var(--neutral-background-3)] text-[var(--neutral-foreground-1)]'
                              )}
                            >
                              {msg.is_deleted ? (
                                <span className="italic text-xs opacity-60">
                                  このメッセージは削除されました
                                </span>
                              ) : (
                                <>
                                  {msg.message && (
                                    <p className="whitespace-pre-wrap break-words">
                                      {msg.message}
                                    </p>
                                  )}
                                  {msg.attachment_path && (
                                    <a
                                      href={`${api.defaults.baseURL}/storage/${msg.attachment_path}`}
                                      target="_blank"
                                      rel="noopener noreferrer"
                                      className={cn(
                                        'mt-1 flex items-center gap-1.5 rounded-lg border px-2.5 py-1.5 text-xs',
                                        isMine
                                          ? 'border-white/30 text-white hover:bg-white/10'
                                          : 'border-[var(--neutral-stroke-2)] text-[var(--neutral-foreground-2)] hover:bg-[var(--neutral-background-4)]'
                                      )}
                                    >
                                      <Download className="h-3.5 w-3.5 shrink-0" />
                                      <span className="truncate">
                                        {msg.attachment_original_name || 'ファイル'}
                                      </span>
                                      {msg.attachment_size && (
                                        <span className="shrink-0 opacity-70">
                                          ({formatFileSize(msg.attachment_size)})
                                        </span>
                                      )}
                                    </a>
                                  )}
                                </>
                              )}
                            </div>
                            {/* Timestamp */}
                            <p
                              className={cn(
                                'mt-0.5 text-[9px] text-[var(--neutral-foreground-4)]',
                                isMine ? 'text-right' : 'text-left'
                              )}
                            >
                              {formatRelativeTime(msg.created_at)}
                            </p>
                          </div>
                        </div>
                      );
                    })}
                    <div ref={messagesEndRef} />
                  </div>
                ) : (
                  <div className="flex h-full items-center justify-center text-sm text-[var(--neutral-foreground-4)]">
                    メッセージはまだありません
                  </div>
                )}
              </div>

              {/* Input area */}
              <div className="border-t border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-1)] p-3">
                {/* Attachment preview */}
                {attachment && (
                  <div className="mb-2 flex items-center gap-2 rounded-lg bg-[var(--neutral-background-3)] px-3 py-2">
                    <Paperclip className="h-4 w-4 text-[var(--neutral-foreground-4)]" />
                    <span className="flex-1 truncate text-sm text-[var(--neutral-foreground-2)]">
                      {attachment.name}
                    </span>
                    <span className="text-xs text-[var(--neutral-foreground-4)]">
                      {formatFileSize(attachment.size)}
                    </span>
                    <button
                      onClick={() => setAttachment(null)}
                      className="rounded p-1 text-[var(--neutral-foreground-4)] hover:text-[var(--status-danger-fg)]"
                    >
                      <X className="h-4 w-4" />
                    </button>
                  </div>
                )}

                <div className="flex items-end gap-2">
                  {/* File button */}
                  <button
                    onClick={() => fileInputRef.current?.click()}
                    className="shrink-0 rounded-lg p-2 text-[var(--neutral-foreground-4)] hover:bg-[var(--neutral-background-3)] hover:text-[var(--neutral-foreground-2)]"
                  >
                    <Paperclip className="h-5 w-5" />
                  </button>
                  <input
                    ref={fileInputRef}
                    type="file"
                    className="hidden"
                    accept="image/*,.pdf,.doc,.docx,.xls,.xlsx"
                    onChange={handleFileSelect}
                  />

                  {/* Textarea */}
                  <textarea
                    ref={textareaRef}
                    value={messageText}
                    onChange={(e) => setMessageText(e.target.value)}
                    onKeyDown={handleKeyDown}
                    placeholder="メッセージを入力..."
                    rows={1}
                    className={cn(
                      'flex-1 resize-none rounded-xl border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-3)] px-4 py-2.5 text-sm',
                      'text-[var(--neutral-foreground-1)] placeholder-[var(--neutral-foreground-4)]',
                      'focus:border-[var(--brand-80)] focus:bg-[var(--neutral-background-1)] focus:outline-none focus:ring-2 focus:ring-[var(--brand-80)]/20',
                      'max-h-32'
                    )}
                    style={{ minHeight: '40px' }}
                    onInput={(e) => {
                      const target = e.target as HTMLTextAreaElement;
                      target.style.height = 'auto';
                      target.style.height = `${Math.min(target.scrollHeight, 128)}px`;
                    }}
                  />

                  {/* Send button */}
                  <Button
                    onClick={handleSend}
                    disabled={!messageText.trim() && !attachment}
                    isLoading={isSending}
                    size="md"
                    className="shrink-0 rounded-xl"
                  >
                    <Send className="h-4 w-4" />
                  </Button>
                </div>

                <p className="mt-1 text-center text-[10px] text-[var(--neutral-foreground-4)]">
                  Shift+Enterで改行
                </p>
              </div>
            </>
          ) : (
            /* Empty state */
            <div className="flex flex-1 items-center justify-center text-[var(--neutral-foreground-4)]">
              <div className="text-center">
                <MessageCircle className="mx-auto mb-3 h-12 w-12" />
                <p className="text-sm">左のリストからチャットルームを選択してください</p>
              </div>
            </div>
          )}
        </div>
      )}

      {/* Create Room Modal */}
      <CreateRoomModal
        isOpen={createModal}
        onClose={() => setCreateModal(false)}
        onCreated={handleRoomCreated}
      />
    </div>
  );
}

// ---------------------------------------------------------------------------
// Room Item
// ---------------------------------------------------------------------------

function StaffRoomItem({
  room,
  isActive,
  onClick,
}: {
  room: StaffChatRoom;
  isActive: boolean;
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
      <div
        className={cn(
          'flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-xs font-semibold text-white',
          room.room_type === 'direct'
            ? 'bg-[var(--status-info-fg)]'
            : 'bg-[var(--status-success-fg)]'
        )}
      >
        {room.room_type === 'direct' ? (
          <User className="h-4 w-4" />
        ) : (
          <Users className="h-4 w-4" />
        )}
      </div>

      {/* Info */}
      <div className="flex-1 min-w-0">
        <div className="flex items-center gap-1">
          <span
            className={cn(
              'text-xs truncate',
              room.unread_count > 0
                ? 'font-bold text-[var(--neutral-foreground-1)]'
                : 'font-medium text-[var(--neutral-foreground-1)]'
            )}
          >
            {room.display_name}
          </span>
        </div>
        {room.last_message && (
          <p className="text-[10px] text-[var(--neutral-foreground-4)] truncate">
            {truncate(room.last_message, 25)}
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
        {room.unread_count > 0 && (
          <span className="flex h-4 min-w-[16px] items-center justify-center rounded-full bg-[var(--status-danger-fg)] px-1 text-[9px] font-bold text-white">
            {room.unread_count > 99 ? '99+' : room.unread_count}
          </span>
        )}
      </div>
    </button>
  );
}

// ---------------------------------------------------------------------------
// Create Room Modal
// ---------------------------------------------------------------------------

function CreateRoomModal({
  isOpen,
  onClose,
  onCreated,
}: {
  isOpen: boolean;
  onClose: () => void;
  onCreated: (room: StaffChatRoom) => void;
}) {
  const toast = useToast();
  const [mode, setMode] = useState<'direct' | 'group'>('direct');
  const [staffMembers, setStaffMembers] = useState<StaffMember[]>([]);
  const [selectedIds, setSelectedIds] = useState<Set<number>>(new Set());
  const [groupName, setGroupName] = useState('');
  const [isLoading, setIsLoading] = useState(false);
  const [isCreating, setIsCreating] = useState(false);

  // Fetch staff members when modal opens
  useEffect(() => {
    if (!isOpen) return;
    setIsLoading(true);
    setSelectedIds(new Set());
    setGroupName('');
    setMode('direct');
    api
      .get('/api/staff/staff-chat/staff-list')
      .then((res) => {
        const data = res.data?.data || res.data || [];
        setStaffMembers(Array.isArray(data) ? data : []);
      })
      .catch(() => {
        toast.error('スタッフ一覧の取得に失敗しました');
      })
      .finally(() => setIsLoading(false));
  }, [isOpen, toast]);

  const toggleMember = (id: number) => {
    setSelectedIds((prev) => {
      const next = new Set(prev);
      if (next.has(id)) next.delete(id);
      else next.add(id);
      return next;
    });
  };

  const handleCreate = async () => {
    if (selectedIds.size === 0) return;
    if (mode === 'direct' && selectedIds.size !== 1) {
      toast.error('個人チャットでは1人だけ選択してください');
      return;
    }
    if (mode === 'group' && selectedIds.size < 2) {
      toast.error('グループチャットでは2人以上選択してください');
      return;
    }
    if (mode === 'group' && !groupName.trim()) {
      toast.error('グループ名を入力してください');
      return;
    }

    setIsCreating(true);
    try {
      const payload: Record<string, unknown> = {
        room_type: mode,
        member_ids: Array.from(selectedIds),
      };
      if (mode === 'group') {
        payload.room_name = groupName.trim();
      }
      const res = await api.post('/api/staff/staff-chat/rooms', payload);
      const room: StaffChatRoom = res.data?.data || res.data;
      onCreated(room);
      toast.success('チャットルームを作成しました');
    } catch {
      toast.error('チャットルームの作成に失敗しました');
    } finally {
      setIsCreating(false);
    }
  };

  const canCreate =
    mode === 'direct'
      ? selectedIds.size === 1
      : selectedIds.size >= 2 && groupName.trim().length > 0;

  return (
    <Modal isOpen={isOpen} onClose={onClose} title="新規チャット" size="md">
      <div className="space-y-4">
        {/* Mode tabs */}
        <div className="flex border-b border-[var(--neutral-stroke-2)]">
          <button
            onClick={() => {
              setMode('direct');
              setSelectedIds(new Set());
            }}
            className={cn(
              'flex items-center gap-1.5 border-b-2 px-4 py-2.5 text-sm font-medium transition-colors',
              mode === 'direct'
                ? 'border-[var(--brand-80)] text-[var(--brand-80)]'
                : 'border-transparent text-[var(--neutral-foreground-3)] hover:text-[var(--neutral-foreground-1)]'
            )}
          >
            <User className="h-4 w-4" />
            個人チャット
          </button>
          <button
            onClick={() => {
              setMode('group');
              setSelectedIds(new Set());
            }}
            className={cn(
              'flex items-center gap-1.5 border-b-2 px-4 py-2.5 text-sm font-medium transition-colors',
              mode === 'group'
                ? 'border-[var(--brand-80)] text-[var(--brand-80)]'
                : 'border-transparent text-[var(--neutral-foreground-3)] hover:text-[var(--neutral-foreground-1)]'
            )}
          >
            <Users className="h-4 w-4" />
            グループチャット
          </button>
        </div>

        {/* Group name input */}
        {mode === 'group' && (
          <div>
            <label className="mb-1 block text-sm font-medium text-[var(--neutral-foreground-2)]">
              グループ名 *
            </label>
            <input
              value={groupName}
              onChange={(e) => setGroupName(e.target.value)}
              className="block w-full rounded-lg border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-1)] px-3 py-2 text-sm text-[var(--neutral-foreground-1)]"
              placeholder="グループ名を入力..."
            />
          </div>
        )}

        {/* Staff member list */}
        <div>
          <label className="mb-1 block text-sm font-medium text-[var(--neutral-foreground-2)]">
            {mode === 'direct' ? 'メンバーを選択' : 'メンバーを選択（2人以上）'}
          </label>
          <div className="max-h-60 overflow-y-auto rounded-lg border border-[var(--neutral-stroke-2)]">
            {isLoading ? (
              <div className="space-y-2 p-3">
                {[...Array(5)].map((_, i) => (
                  <Skeleton key={i} className="h-8 w-full rounded-md" />
                ))}
              </div>
            ) : staffMembers.length > 0 ? (
              staffMembers.map((staff) => (
                <label
                  key={staff.id}
                  className={cn(
                    'flex cursor-pointer items-center gap-3 px-3 py-2 transition-colors',
                    selectedIds.has(staff.id)
                      ? 'bg-[var(--brand-160)]'
                      : 'hover:bg-[var(--neutral-background-3)]'
                  )}
                >
                  <input
                    type={mode === 'direct' ? 'radio' : 'checkbox'}
                    name="staff-member"
                    checked={selectedIds.has(staff.id)}
                    onChange={() => {
                      if (mode === 'direct') {
                        setSelectedIds(new Set([staff.id]));
                      } else {
                        toggleMember(staff.id);
                      }
                    }}
                    className="shrink-0"
                  />
                  <span className="text-sm text-[var(--neutral-foreground-1)]">
                    {staff.full_name}
                  </span>
                </label>
              ))
            ) : (
              <div className="px-4 py-6 text-center text-sm text-[var(--neutral-foreground-4)]">
                スタッフが見つかりません
              </div>
            )}
          </div>
        </div>

        {/* Actions */}
        <div className="flex justify-end gap-2">
          <Button variant="secondary" onClick={onClose}>
            キャンセル
          </Button>
          <Button onClick={handleCreate} isLoading={isCreating} disabled={!canCreate}>
            作成
          </Button>
        </div>
      </div>
    </Modal>
  );
}
