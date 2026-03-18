'use client';

import { useEffect, useState, useMemo, useCallback, useRef } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import api from '@/lib/api';
import { Input } from '@/components/ui/Input';
import { Button } from '@/components/ui/Button';
import { Badge } from '@/components/ui/Badge';
import { Modal } from '@/components/ui/Modal';
import { Skeleton } from '@/components/ui/Skeleton';
import { useToast } from '@/components/ui/Toast';
import { useDebounce } from '@/hooks/useDebounce';
import { useIsDesktop } from '@/hooks/useMediaQuery';
import { cn, formatRelativeTime, truncate, nl } from '@/lib/utils';
import {
  Search,
  ChevronLeft,
  Send,
  MessageCircle,
  Megaphone,
  Paperclip,
  X,
} from 'lucide-react';
import { formatFileSize } from '@/lib/utils';

// ---------------------------------------------------------------------------
// Types
// ---------------------------------------------------------------------------

interface StudentChatRoom {
  id: number;
  student_id: number;
  student_name: string;
  last_message: string | null;
  last_message_at: string | null;
  unread_count: number;
  is_active: boolean;
}

interface StudentChatMessage {
  id: number;
  sender_type: 'staff' | 'student';
  sender_name: string;
  message: string;
  attachment_url: string | null;
  attachment_name: string | null;
  is_read: boolean;
  created_at: string;
}

export default function StudentChatsPage() {
  const queryClient = useQueryClient();
  const toast = useToast();
  const isDesktop = useIsDesktop();

  const [search, setSearch] = useState('');
  const debouncedSearch = useDebounce(search, 300);
  const [activeRoomId, setActiveRoomId] = useState<number | null>(null);
  const [showChat, setShowChat] = useState(false);

  // Broadcast modal
  const [broadcastModal, setBroadcastModal] = useState(false);
  const [broadcastMessage, setBroadcastMessage] = useState('');

  // Message input
  const [message, setMessage] = useState('');
  const [attachment, setAttachment] = useState<File | null>(null);
  const fileInputRef = useRef<HTMLInputElement>(null);
  const messagesEndRef = useRef<HTMLDivElement>(null);

  // Fetch rooms
  const { data: rooms = [], isLoading: isLoadingRooms } = useQuery({
    queryKey: ['staff', 'student-chats'],
    queryFn: async () => {
      const res = await api.get<{ data: StudentChatRoom[] }>('/api/staff/student-chats');
      return res.data.data;
    },
  });

  // Filter rooms by search
  const filteredRooms = useMemo(() => {
    if (!debouncedSearch) return rooms;
    const q = debouncedSearch.toLowerCase();
    return rooms.filter((r) => r.student_name?.toLowerCase().includes(q));
  }, [rooms, debouncedSearch]);

  // Fetch messages for active room
  const { data: messages = [], isLoading: isLoadingMessages } = useQuery({
    queryKey: ['staff', 'student-chat-messages', activeRoomId],
    queryFn: async () => {
      const res = await api.get<{ data: StudentChatMessage[] }>(
        `/api/staff/student-chats/${activeRoomId}/messages`
      );
      return res.data.data;
    },
    enabled: !!activeRoomId,
    refetchInterval: 5000,
  });

  // Send message mutation
  const sendMutation = useMutation({
    mutationFn: async (data: FormData) => {
      return api.post(`/api/staff/student-chats/${activeRoomId}/messages`, data);
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['staff', 'student-chat-messages', activeRoomId] });
      queryClient.invalidateQueries({ queryKey: ['staff', 'student-chats'] });
      setMessage('');
      setAttachment(null);
    },
    onError: () => toast.error('送信に失敗しました'),
  });

  // Broadcast mutation
  const broadcastMutation = useMutation({
    mutationFn: async (msg: string) => {
      return api.post('/api/staff/student-chats/broadcast', { message: msg });
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['staff', 'student-chats'] });
      toast.success('一斉送信しました');
      setBroadcastModal(false);
      setBroadcastMessage('');
    },
    onError: () => toast.error('送信に失敗しました'),
  });

  const activeRoom = rooms.find((r) => r.id === activeRoomId) ?? null;

  // Auto-scroll to bottom
  useEffect(() => {
    messagesEndRef.current?.scrollIntoView({ behavior: 'smooth' });
  }, [messages]);

  const handleSelectRoom = useCallback((room: StudentChatRoom) => {
    setActiveRoomId(room.id);
    setShowChat(true);
  }, []);

  const handleBack = useCallback(() => {
    setShowChat(false);
  }, []);

  const handleSend = useCallback(() => {
    const trimmed = message.trim();
    if (!trimmed && !attachment) return;
    const formData = new FormData();
    formData.append('message', trimmed || (attachment?.name ?? ''));
    if (attachment) formData.append('attachment', attachment);
    sendMutation.mutate(formData);
  }, [message, attachment, sendMutation]);

  const handleFileSelect = (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0];
    if (file) {
      if (file.size > 10 * 1024 * 1024) {
        alert('ファイルサイズは10MB以下にしてください');
        return;
      }
      setAttachment(file);
    }
    if (fileInputRef.current) fileInputRef.current.value = '';
  };

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
          <div className="border-b border-[var(--neutral-stroke-2)] px-3 py-2.5 space-y-2">
            <div className="flex items-center justify-between">
              <span className="text-xs font-semibold text-[var(--neutral-foreground-2)]">生徒チャット</span>
              <button
                onClick={() => setBroadcastModal(true)}
                className="flex items-center gap-1 rounded-md px-2 py-1 text-[10px] font-medium text-[var(--neutral-foreground-3)] hover:bg-[var(--neutral-background-3)] transition-colors"
                title="一斉送信"
              >
                <Megaphone className="h-3.5 w-3.5" />
                一斉送信
              </button>
            </div>
            <div className="relative">
              <Search className="absolute left-2.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-[var(--neutral-foreground-4)]" />
              <Input
                placeholder="生徒名で検索..."
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
            ) : filteredRooms.length === 0 ? (
              <div className="px-4 py-12 text-center text-sm text-[var(--neutral-foreground-4)]">
                チャットルームがありません
              </div>
            ) : (
              filteredRooms.map((room) => (
                <button
                  key={room.id}
                  onClick={() => handleSelectRoom(room)}
                  className={cn(
                    'flex w-full items-center gap-2.5 px-3 py-2 text-left transition-colors',
                    activeRoomId === room.id
                      ? 'bg-[var(--brand-160)] border-l-2 border-[var(--brand-80)]'
                      : 'hover:bg-[var(--neutral-background-3)]'
                  )}
                >
                  {/* Avatar */}
                  <div className="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-[var(--brand-140)] text-xs font-semibold text-[var(--brand-80)]">
                    {room.student_name?.charAt(0) || '?'}
                  </div>

                  {/* Info */}
                  <div className="flex-1 min-w-0">
                    <div className="flex items-center gap-1">
                      <span className={cn(
                        'text-xs truncate',
                        room.unread_count > 0
                          ? 'font-bold text-[var(--neutral-foreground-1)]'
                          : 'font-medium text-[var(--neutral-foreground-1)]'
                      )}>
                        {room.student_name}
                      </span>
                      {!room.is_active && (
                        <Badge variant="default" className="text-[9px] px-1 py-0">無効</Badge>
                      )}
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
              ))
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
                  <div className="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-[var(--brand-140)] text-xs font-semibold text-[var(--brand-80)]">
                    {activeRoom.student_name?.charAt(0) || '?'}
                  </div>
                  <div>
                    <h2 className="text-sm font-semibold text-[var(--neutral-foreground-1)]">
                      {activeRoom.student_name}
                    </h2>
                    <p className="text-xs text-[var(--neutral-foreground-3)]">生徒チャット</p>
                  </div>
                  {!activeRoom.is_active && <Badge variant="danger">無効</Badge>}
                </div>
              </div>

              {/* Messages */}
              <div className="flex-1 overflow-y-auto bg-[var(--neutral-background-2)] px-4 py-4 space-y-3">
                {isLoadingMessages ? (
                  <div className="space-y-3">
                    {[...Array(6)].map((_, i) => (
                      <Skeleton key={i} className={cn('h-12 rounded-lg', i % 2 === 0 ? 'w-2/3' : 'ml-auto w-1/2')} />
                    ))}
                  </div>
                ) : messages.length === 0 ? (
                  <div className="flex h-full items-center justify-center text-[var(--neutral-foreground-4)]">
                    <div className="text-center">
                      <MessageCircle className="mx-auto mb-2 h-10 w-10" />
                      <p className="text-sm">メッセージはありません</p>
                    </div>
                  </div>
                ) : (
                  messages.map((msg) => {
                    const isStaff = msg.sender_type === 'staff';
                    return (
                      <div key={msg.id} className={`flex ${isStaff ? 'justify-end' : 'justify-start'}`}>
                        <div className="max-w-[75%]">
                          <div className={`flex items-center gap-1 mb-0.5 ${isStaff ? 'justify-end' : ''}`}>
                            <span className="text-[10px] text-[var(--neutral-foreground-3)]">
                              {msg.sender_name}
                            </span>
                          </div>
                          <div
                            className={cn(
                              'rounded-2xl px-4 py-2 text-sm whitespace-pre-wrap',
                              isStaff
                                ? 'bg-[var(--brand-80)] text-white'
                                : 'bg-[var(--neutral-background-1)] text-[var(--neutral-foreground-1)] border border-[var(--neutral-stroke-2)]'
                            )}
                          >
                            {nl(msg.message)}
                            {msg.attachment_url && (
                              <a
                                href={msg.attachment_url}
                                target="_blank"
                                rel="noopener noreferrer"
                                className={cn(
                                  'mt-1 flex items-center gap-1 text-xs underline',
                                  isStaff ? 'text-blue-100' : 'text-[var(--brand-80)]'
                                )}
                              >
                                <Paperclip className="h-3 w-3" />
                                {msg.attachment_name || '添付ファイル'}
                              </a>
                            )}
                          </div>
                          <div className={`flex items-center gap-1 mt-0.5 ${isStaff ? 'justify-end' : ''}`}>
                            <span className="text-[9px] text-[var(--neutral-foreground-4)]">
                              {formatRelativeTime(msg.created_at)}
                            </span>
                            {isStaff && msg.is_read && (
                              <span className="text-[9px] text-[var(--brand-80)]">既読</span>
                            )}
                          </div>
                        </div>
                      </div>
                    );
                  })
                )}
                <div ref={messagesEndRef} />
              </div>

              {/* Input */}
              <div className="border-t border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-1)] p-3">
                {attachment && (
                  <div className="mb-2 flex items-center gap-2 rounded-lg bg-[var(--neutral-background-3)] px-3 py-2">
                    <Paperclip className="h-4 w-4 text-[var(--neutral-foreground-4)]" />
                    <span className="flex-1 truncate text-sm text-[var(--neutral-foreground-2)]">{attachment.name}</span>
                    <span className="text-xs text-[var(--neutral-foreground-4)]">{formatFileSize(attachment.size)}</span>
                    <button onClick={() => setAttachment(null)} className="rounded p-1 text-[var(--neutral-foreground-4)] hover:text-[var(--status-danger-fg)]">
                      <X className="h-4 w-4" />
                    </button>
                  </div>
                )}
                <div className="flex items-end gap-2">
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
                    accept="image/*,.pdf,.doc,.docx"
                    onChange={handleFileSelect}
                  />
                  <textarea
                    value={message}
                    onChange={(e) => setMessage(e.target.value)}
                    onKeyDown={(e) => { if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); handleSend(); } }}
                    placeholder="メッセージを入力..."
                    rows={1}
                    className={cn(
                      'flex-1 resize-none rounded-xl border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-2)] px-4 py-2.5 text-sm',
                      'placeholder-[var(--neutral-foreground-4)] focus:border-[var(--brand-80)] focus:bg-[var(--neutral-background-1)] focus:outline-none focus:ring-2 focus:ring-[var(--brand-80)]/20',
                      'max-h-32'
                    )}
                    style={{ minHeight: '40px' }}
                    onInput={(e) => {
                      const target = e.target as HTMLTextAreaElement;
                      target.style.height = 'auto';
                      target.style.height = `${Math.min(target.scrollHeight, 128)}px`;
                    }}
                  />
                  <Button
                    onClick={handleSend}
                    disabled={!message.trim() && !attachment}
                    isLoading={sendMutation.isPending}
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
            <div className="flex flex-1 items-center justify-center text-[var(--neutral-foreground-4)]">
              <div className="text-center">
                <MessageCircle className="mx-auto mb-3 h-12 w-12" />
                <p className="text-sm">左のリストからチャットルームを選択してください</p>
              </div>
            </div>
          )}
        </div>
      )}

      {/* Broadcast Modal */}
      <Modal isOpen={broadcastModal} onClose={() => setBroadcastModal(false)} title="全生徒に一斉送信" size="md">
        <div className="space-y-4">
          <div>
            <label className="mb-1 block text-sm font-medium text-[var(--neutral-foreground-2)]">メッセージ</label>
            <textarea
              value={broadcastMessage}
              onChange={(e) => setBroadcastMessage(e.target.value)}
              className="block w-full rounded-lg border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-1)] px-3 py-2 text-sm text-[var(--neutral-foreground-1)]"
              rows={5}
              placeholder="全生徒に送信するメッセージを入力..."
              required
            />
          </div>
          <p className="text-sm text-[var(--neutral-foreground-3)]">
            {rooms.filter((r) => r.is_active).length}名の生徒全員にメッセージが送信されます。
          </p>
          <div className="flex justify-end gap-2">
            <Button variant="secondary" onClick={() => setBroadcastModal(false)}>キャンセル</Button>
            <Button
              onClick={() => broadcastMutation.mutate(broadcastMessage)}
              isLoading={broadcastMutation.isPending}
              disabled={!broadcastMessage.trim()}
              leftIcon={<Send className="h-4 w-4" />}
            >
              送信
            </Button>
          </div>
        </div>
      </Modal>
    </div>
  );
}
