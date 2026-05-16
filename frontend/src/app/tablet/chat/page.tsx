'use client';

import { useState, useEffect, useRef } from 'react';
import Link from 'next/link';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import api from '@/lib/api';
import { Button } from '@/components/ui/Button';
import { useToast } from '@/components/ui/Toast';
import { MaterialIcon } from '@/components/ui/MaterialIcon';
import { formatRelativeTime, nl } from '@/lib/utils';

interface ChatRoomItem {
  id: number;
  student_id: number;
  guardian_id: number | null;
  student?: { id: number; student_name: string; grade_level?: string };
  guardian?: { id: number; full_name: string } | null;
  last_message?: string | { message: string; message_type?: string; sender_type?: string } | null;
  last_message_at?: string | null;
  unread_count?: number;
}

interface ChatMessage {
  id: number;
  room_id: number;
  sender_id: number;
  sender_type: 'staff' | 'guardian';
  message: string;
  message_type: string;
  created_at: string;
  sender?: { id: number; full_name: string } | null;
}

/**
 * タブレット用 保護者チャット画面 (簡易版)
 *
 * Staff 画面の chat ページから「ピン留め」「アーカイブ」「一斉送信」など
 * 高度な機能を省き、当該教室の保護者ルームを一覧 → 選択 → 読む/送る
 * の最小ワークフローだけを提供する。BE は Staff\ChatController を再利用する
 * 既存ルート /api/tablet/chat/* を呼ぶ。
 */
export default function TabletChatPage() {
  const queryClient = useQueryClient();
  const toast = useToast();
  const [activeRoomId, setActiveRoomId] = useState<number | null>(null);
  const [draft, setDraft] = useState('');
  const messagesEndRef = useRef<HTMLDivElement>(null);

  const { data: rooms = [], isLoading: roomsLoading } = useQuery<ChatRoomItem[]>({
    queryKey: ['tablet', 'chat', 'rooms'],
    queryFn: async () => {
      const res = await api.get<{ data: ChatRoomItem[] }>('/api/tablet/chat/rooms');
      return res.data.data || [];
    },
  });

  const { data: messages = [], isLoading: msgsLoading } = useQuery<ChatMessage[]>({
    queryKey: ['tablet', 'chat', 'messages', activeRoomId],
    queryFn: async () => {
      if (!activeRoomId) return [];
      const res = await api.get<{ data: ChatMessage[] }>(`/api/tablet/chat/rooms/${activeRoomId}/messages`);
      return res.data.data || [];
    },
    enabled: !!activeRoomId,
    refetchInterval: 5000,
  });

  const sendMutation = useMutation({
    mutationFn: async (body: string) => {
      if (!activeRoomId) throw new Error('No active room');
      const fd = new FormData();
      fd.append('message', body);
      return api.post(`/api/tablet/chat/rooms/${activeRoomId}/messages`, fd, {
        headers: { 'Content-Type': 'multipart/form-data' },
      });
    },
    onSuccess: () => {
      setDraft('');
      queryClient.invalidateQueries({ queryKey: ['tablet', 'chat', 'messages', activeRoomId] });
      queryClient.invalidateQueries({ queryKey: ['tablet', 'chat', 'rooms'] });
    },
    onError: (err: unknown) => {
      const e = err as { response?: { data?: { message?: string } } };
      toast.error(e?.response?.data?.message || '送信に失敗しました');
    },
  });

  // 既読化
  useEffect(() => {
    if (activeRoomId) {
      api.post(`/api/tablet/chat/rooms/${activeRoomId}/read`).catch(() => {
        /* silent */
      });
    }
  }, [activeRoomId, messages.length]);

  // メッセージ末尾にスクロール
  useEffect(() => {
    messagesEndRef.current?.scrollIntoView({ behavior: 'smooth' });
  }, [messages.length]);

  const activeRoom = rooms.find((r) => r.id === activeRoomId);

  return (
    <div className="flex h-[calc(100dvh-9rem)] flex-col gap-3 sm:flex-row">
      {/* ルーム一覧 */}
      <aside
        className={`flex-shrink-0 overflow-y-auto rounded-xl bg-white shadow-md sm:w-[280px] ${activeRoomId ? 'hidden sm:block' : 'block flex-1'}`}
      >
        <div className="border-b border-[var(--neutral-stroke-2)] px-3 py-2 text-sm font-bold text-[var(--neutral-foreground-1)]">
          保護者チャット ({rooms.length}件)
        </div>
        {roomsLoading ? (
          <p className="p-4 text-sm text-[var(--neutral-foreground-4)]">読み込み中...</p>
        ) : rooms.length === 0 ? (
          <p className="p-4 text-sm text-[var(--neutral-foreground-4)]">
            この教室の保護者チャットはまだありません
          </p>
        ) : (
          <ul className="divide-y divide-[var(--neutral-stroke-3)]">
            {rooms.map((room) => {
              const lastMsgText = typeof room.last_message === 'string'
                ? room.last_message
                : room.last_message?.message || '';
              return (
                <li key={room.id}>
                  <button
                    type="button"
                    onClick={() => setActiveRoomId(room.id)}
                    className={`w-full px-3 py-3 text-left transition-colors ${
                      activeRoomId === room.id
                        ? 'bg-[var(--brand-160)]'
                        : 'hover:bg-[var(--neutral-background-3)]'
                    }`}
                  >
                    <div className="flex items-center justify-between gap-2">
                      <span className="truncate text-sm font-semibold text-[var(--neutral-foreground-1)]">
                        {room.student?.student_name || `ID:${room.id}`}
                      </span>
                      {(room.unread_count ?? 0) > 0 && (
                        <span className="rounded-full bg-[var(--status-danger-fg)] px-2 py-0.5 text-[10px] font-bold text-white">
                          {room.unread_count}
                        </span>
                      )}
                    </div>
                    {lastMsgText && (
                      <p className="mt-1 truncate text-xs text-[var(--neutral-foreground-4)]">
                        {lastMsgText}
                      </p>
                    )}
                    {room.last_message_at && (
                      <p className="text-[10px] text-[var(--neutral-foreground-4)]">
                        {formatRelativeTime(room.last_message_at)}
                      </p>
                    )}
                  </button>
                </li>
              );
            })}
          </ul>
        )}
      </aside>

      {/* メッセージ表示・送信 */}
      <main
        className={`flex flex-1 flex-col overflow-hidden rounded-xl bg-white shadow-md ${activeRoomId ? 'flex' : 'hidden sm:flex'}`}
      >
        {activeRoom ? (
          <>
            <div className="flex items-center gap-2 border-b border-[var(--neutral-stroke-2)] px-3 py-2 sm:px-4">
              <button
                type="button"
                onClick={() => setActiveRoomId(null)}
                className="rounded p-1 text-[var(--neutral-foreground-4)] hover:bg-[var(--neutral-background-3)] sm:hidden"
                aria-label="戻る"
              >
                <MaterialIcon name="arrow_back" size={20} />
              </button>
              <div className="flex-1">
                <p className="text-sm font-bold text-[var(--neutral-foreground-1)]">
                  {activeRoom.student?.student_name}
                </p>
                {activeRoom.guardian?.full_name && (
                  <p className="text-xs text-[var(--neutral-foreground-4)]">
                    保護者: {activeRoom.guardian.full_name}
                  </p>
                )}
              </div>
            </div>
            <div className="flex-1 overflow-y-auto bg-[var(--neutral-background-2)] p-3 sm:p-4">
              {msgsLoading ? (
                <p className="text-center text-sm text-[var(--neutral-foreground-4)]">読み込み中...</p>
              ) : messages.length === 0 ? (
                <p className="text-center text-sm text-[var(--neutral-foreground-4)]">
                  まだメッセージがありません
                </p>
              ) : (
                <div className="space-y-3">
                  {messages.map((msg) => {
                    const isFromStaff = msg.sender_type === 'staff';
                    return (
                      <div
                        key={msg.id}
                        className={`flex ${isFromStaff ? 'justify-end' : 'justify-start'}`}
                      >
                        <div
                          className={`max-w-[80%] rounded-2xl px-3 py-2 text-sm break-words whitespace-pre-wrap ${
                            isFromStaff
                              ? 'rounded-br-md bg-[var(--brand-80)] text-white'
                              : 'rounded-bl-md bg-white text-[var(--neutral-foreground-1)] shadow-sm'
                          }`}
                        >
                          {nl(msg.message)}
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
                  value={draft}
                  onChange={(e) => setDraft(e.target.value)}
                  rows={2}
                  placeholder="メッセージを入力..."
                  className="flex-1 resize-none rounded-2xl border border-[var(--neutral-stroke-2)] bg-white px-3 py-2 text-sm focus:border-[var(--brand-80)] focus:outline-none focus:ring-1 focus:ring-[var(--brand-80)]"
                />
                <Button
                  onClick={() => draft.trim() && sendMutation.mutate(draft.trim())}
                  disabled={sendMutation.isPending || !draft.trim()}
                  isLoading={sendMutation.isPending}
                  className="flex-shrink-0"
                  leftIcon={<MaterialIcon name="send" size={16} />}
                >
                  送信
                </Button>
              </div>
            </div>
          </>
        ) : (
          <div className="flex flex-1 items-center justify-center text-[var(--neutral-foreground-4)]">
            <div className="text-center">
              <MaterialIcon name="chat" size={48} className="mx-auto mb-3" />
              <p className="text-sm">左の一覧から保護者を選択してください</p>
              <Link href="/tablet" className="mt-3 inline-flex items-center gap-1 text-sm text-[var(--brand-80)] hover:underline">
                <MaterialIcon name="arrow_back" size={16} />
                ホームへ戻る
              </Link>
            </div>
          </div>
        )}
      </main>
    </div>
  );
}
