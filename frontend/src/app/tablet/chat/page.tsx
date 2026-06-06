'use client';

import { useEffect, useMemo, useRef, useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { format } from 'date-fns';
import { ja } from 'date-fns/locale';
import api from '@/lib/api';
import { useToast } from '@/components/ui/Toast';
import { MaterialIcon } from '@/components/ui/MaterialIcon';

/**
 * タブレット用 保護者チャット画面。
 *
 * 機能:
 *  - 教室のチャットルーム一覧 (生徒+保護者) を左ペイン (大画面) / 上部 (狭画面) に表示
 *  - 選択したルームの直近メッセージを閲覧
 *  - メッセージ送信 (送信者は当該タブレットアカウント)
 *  - 30秒ごとに自動再取得 (リアルタイム配信は WebSocket がやってくれるが、ポーリングで補完)
 *
 * API: /api/tablet/chat/* (Staff\ChatController を流用)
 */

interface ChatRoom {
  id: number;
  student_id: number;
  guardian_id: number;
  last_message_at: string | null;
  student?: { id: number; student_name: string; classroom_id: number };
  guardian?: { id: number; full_name: string };
  unread_count?: number;
}

interface ChatMessage {
  id: number;
  room_id: number;
  sender_id: number;
  sender_type: 'guardian' | 'staff' | 'admin';
  message: string;
  message_type?: string;
  attachment_path?: string | null;
  attachment_name?: string | null;
  is_deleted?: boolean;
  created_at: string;
  sender?: { id: number; full_name?: string };
}

export default function TabletChatPage() {
  const queryClient = useQueryClient();
  const toast = useToast();
  const [selectedRoomId, setSelectedRoomId] = useState<number | null>(null);
  const [input, setInput] = useState('');
  const messagesEndRef = useRef<HTMLDivElement | null>(null);

  // ルーム一覧
  const { data: rooms = [], isLoading: roomsLoading } = useQuery({
    queryKey: ['tablet', 'chat', 'rooms'],
    queryFn: async () => {
      const res = await api.get<{ data: ChatRoom[] }>('/api/tablet/chat/rooms');
      return res.data.data;
    },
    refetchInterval: 30 * 1000,
  });

  // 初回ルーム自動選択
  useEffect(() => {
    if (!selectedRoomId && rooms.length > 0) {
      setSelectedRoomId(rooms[0].id);
    }
  }, [rooms, selectedRoomId]);

  // 選択ルームのメッセージ
  const { data: messages = [], isLoading: messagesLoading } = useQuery({
    queryKey: ['tablet', 'chat', 'messages', selectedRoomId],
    queryFn: async () => {
      if (!selectedRoomId) return [] as ChatMessage[];
      const res = await api.get<{ data: ChatMessage[] }>(
        `/api/tablet/chat/rooms/${selectedRoomId}/messages`,
        { params: { limit: 100 } },
      );
      return res.data.data;
    },
    enabled: !!selectedRoomId,
    refetchInterval: 15 * 1000,
  });

  // メッセージ追加時に末尾へスクロール
  useEffect(() => {
    if (messagesEndRef.current) {
      messagesEndRef.current.scrollIntoView({ behavior: 'smooth' });
    }
  }, [messages, selectedRoomId]);

  // 既読マーク (ルーム選択時)
  useEffect(() => {
    if (!selectedRoomId) return;
    api.post(`/api/tablet/chat/rooms/${selectedRoomId}/read`).catch(() => {
      // silent
    });
  }, [selectedRoomId]);

  const sendMutation = useMutation({
    mutationFn: (text: string) =>
      api.post(`/api/tablet/chat/rooms/${selectedRoomId}/messages`, { message: text }),
    onSuccess: () => {
      setInput('');
      queryClient.invalidateQueries({ queryKey: ['tablet', 'chat', 'messages', selectedRoomId] });
      queryClient.invalidateQueries({ queryKey: ['tablet', 'chat', 'rooms'] });
    },
    onError: (err: { response?: { data?: { message?: string } } }) =>
      toast.error(err?.response?.data?.message ?? '送信に失敗しました'),
  });

  const selectedRoom = useMemo(() => rooms.find((r) => r.id === selectedRoomId) ?? null, [rooms, selectedRoomId]);

  const handleSend = () => {
    const t = input.trim();
    if (!t || !selectedRoomId) return;
    sendMutation.mutate(t);
  };

  return (
    <div className="grid h-[calc(100vh-180px)] gap-4 lg:grid-cols-[320px_1fr]">
      {/* ルーム一覧 */}
      <div className="overflow-y-auto rounded-xl bg-white p-3 shadow-md">
        <h2 className="mb-2 px-2 text-lg font-bold text-[var(--neutral-foreground-1)]">
          <MaterialIcon name="forum" size={18} className="mr-1 inline align-middle" />
          チャット ({rooms.length})
        </h2>
        {roomsLoading ? (
          <p className="py-6 text-center text-sm text-[var(--neutral-foreground-4)]">読み込み中…</p>
        ) : rooms.length === 0 ? (
          <p className="py-6 text-center text-sm text-[var(--neutral-foreground-4)]">
            ルームがありません
          </p>
        ) : (
          <ul className="space-y-1">
            {rooms.map((room) => {
              const isSelected = room.id === selectedRoomId;
              const hasUnread = (room.unread_count ?? 0) > 0;
              return (
                <li key={room.id}>
                  <button
                    onClick={() => setSelectedRoomId(room.id)}
                    className={`w-full rounded-lg px-3 py-3 text-left transition-colors ${
                      isSelected
                        ? 'bg-[var(--brand-160)] ring-2 ring-[var(--brand-80)]'
                        : 'hover:bg-[var(--neutral-background-3)]'
                    }`}
                  >
                    <div className="flex items-center justify-between gap-2">
                      <span className="truncate text-base font-bold text-[var(--neutral-foreground-1)]">
                        {room.student?.student_name ?? `ルーム #${room.id}`}
                      </span>
                      {hasUnread && (
                        <span className="shrink-0 rounded-full bg-red-500 px-2 py-0.5 text-xs font-bold text-white">
                          {room.unread_count}
                        </span>
                      )}
                    </div>
                    <div className="mt-1 truncate text-xs text-[var(--neutral-foreground-3)]">
                      {room.guardian?.full_name ?? '保護者'}
                    </div>
                    {room.last_message_at && (
                      <div className="mt-1 text-[10px] text-[var(--neutral-foreground-4)]">
                        最新: {format(new Date(room.last_message_at), 'M/d HH:mm', { locale: ja })}
                      </div>
                    )}
                  </button>
                </li>
              );
            })}
          </ul>
        )}
      </div>

      {/* メッセージ表示 */}
      <div className="flex flex-col overflow-hidden rounded-xl bg-white shadow-md">
        {selectedRoom ? (
          <>
            <div className="border-b border-[var(--neutral-stroke-2)] px-4 py-3">
              <h2 className="text-xl font-bold text-[var(--neutral-foreground-1)]">
                {selectedRoom.student?.student_name ?? `ルーム #${selectedRoom.id}`}
              </h2>
              <p className="text-sm text-[var(--neutral-foreground-3)]">
                {selectedRoom.guardian?.full_name ?? '保護者'} とのやりとり
              </p>
            </div>

            <div className="flex-1 overflow-y-auto px-4 py-4">
              {messagesLoading ? (
                <p className="py-6 text-center text-sm text-[var(--neutral-foreground-4)]">読み込み中…</p>
              ) : messages.length === 0 ? (
                <p className="py-6 text-center text-sm text-[var(--neutral-foreground-4)]">
                  まだメッセージはありません
                </p>
              ) : (
                <div className="space-y-3">
                  {messages.map((m) => {
                    const isFromGuardian = m.sender_type === 'guardian';
                    return (
                      <div
                        key={m.id}
                        className={`flex ${isFromGuardian ? 'justify-start' : 'justify-end'}`}
                      >
                        <div
                          className={`max-w-[75%] rounded-2xl px-4 py-2 text-base ${
                            isFromGuardian
                              ? 'bg-[var(--neutral-background-3)] text-[var(--neutral-foreground-1)]'
                              : 'bg-[var(--brand-80)] text-white'
                          }`}
                        >
                          <div className="mb-1 flex items-center justify-between gap-3 text-xs opacity-80">
                            <span className="font-bold">
                              {isFromGuardian
                                ? selectedRoom.guardian?.full_name ?? '保護者'
                                : m.sender?.full_name ?? 'スタッフ'}
                            </span>
                            <span>{format(new Date(m.created_at), 'M/d HH:mm', { locale: ja })}</span>
                          </div>
                          <div className="whitespace-pre-wrap break-words">{m.message}</div>
                        </div>
                      </div>
                    );
                  })}
                  <div ref={messagesEndRef} />
                </div>
              )}
            </div>

            <div className="border-t border-[var(--neutral-stroke-2)] p-3">
              <div className="flex items-end gap-2">
                <textarea
                  value={input}
                  onChange={(e) => setInput(e.target.value)}
                  onKeyDown={(e) => {
                    // Ctrl+Enter または Cmd+Enter で送信
                    if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
                      e.preventDefault();
                      handleSend();
                    }
                  }}
                  placeholder="メッセージを入力… (Ctrl+Enter で送信)"
                  rows={2}
                  className="flex-1 resize-none rounded-lg border-2 border-[var(--neutral-stroke-2)] bg-white px-3 py-2 text-base"
                />
                <button
                  onClick={handleSend}
                  disabled={!input.trim() || sendMutation.isPending}
                  className="flex h-12 items-center gap-1 rounded-lg bg-[var(--brand-80)] px-4 text-base font-bold text-white hover:bg-blue-700 disabled:opacity-50"
                >
                  <MaterialIcon name="send" size={20} />
                  送信
                </button>
              </div>
            </div>
          </>
        ) : (
          <div className="flex flex-1 items-center justify-center p-6 text-[var(--neutral-foreground-4)]">
            <p>左のルームを選択してください</p>
          </div>
        )}
      </div>
    </div>
  );
}
