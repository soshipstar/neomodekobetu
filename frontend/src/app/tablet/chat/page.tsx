'use client';

import { useState, useEffect, useRef } from 'react';
import Link from 'next/link';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import api from '@/lib/api';
import { Button } from '@/components/ui/Button';
import { useToast } from '@/components/ui/Toast';
import { MaterialIcon } from '@/components/ui/MaterialIcon';
import { formatRelativeTime, nl } from '@/lib/utils';
import { MessageBody } from '@/components/chat/MessageBody';

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
  // バグ報告: 保護者が添付した写真がタブレットでは見えなかった (= attachment_*
  // フィールドを interface に含めず、レンダリングも省いていた)
  attachment_path?: string | null;
  attachment_name?: string | null;
  attachment_size?: number | null;
  attachment_mime?: string | null;
}

/**
 * 添付ファイルの URL を組み立てる。
 * Staff/Guardian チャットと同じ規約 (/storage/{path}) で BE 配下のストレージから配信。
 */
function attachmentUrl(path: string): string {
  const base = process.env.NEXT_PUBLIC_BACKEND_URL || '';
  return `${base}/storage/${path}`;
}

/** 添付がイメージかどうか判定 (mime を優先、無ければ拡張子) */
function isImageAttachment(msg: Pick<ChatMessage, 'attachment_mime' | 'attachment_path' | 'attachment_name'>): boolean {
  if (msg.attachment_mime?.startsWith('image/')) return true;
  const name = (msg.attachment_name || msg.attachment_path || '').toLowerCase();
  return /\.(png|jpe?g|gif|webp|heic|heif|bmp|svg)$/.test(name);
}

function formatBytes(n?: number | null): string {
  if (n == null) return '';
  if (n < 1024) return `${n} B`;
  if (n < 1024 * 1024) return `${(n / 1024).toFixed(1)} KB`;
  return `${(n / 1024 / 1024).toFixed(1)} MB`;
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
    // 新着メッセージで unread_count が増えたとき、別ルームを開いたままでも
    // 一覧側のバッジが追従するように定期 refetch する (messages と同じ 5 秒)。
    refetchInterval: 5000,
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
  // バグ報告: 「タブレットユーザーで保護者チャットを見た後も未読バッジが
  //  付いたまま」だった。原因は POST /read 後に rooms クエリを invalidate
  //  していなかったため、サイドの一覧 (unread_count) が更新されなかった。
  //  read 成功後に rooms を invalidate して即時バッジを消す。
  useEffect(() => {
    if (!activeRoomId) return;
    api
      .post(`/api/tablet/chat/rooms/${activeRoomId}/read`)
      .then(() => {
        queryClient.invalidateQueries({ queryKey: ['tablet', 'chat', 'rooms'] });
      })
      .catch(() => {
        /* silent */
      });
  }, [activeRoomId, messages.length, queryClient]);

  // メッセージ末尾にスクロール
  useEffect(() => {
    messagesEndRef.current?.scrollIntoView({ behavior: 'smooth' });
  }, [messages.length]);

  const activeRoom = rooms.find((r) => r.id === activeRoomId);

  return (
    // バグ報告: 「戻るボタンが一番最初のメッセージまでスクロールして戻らないと
    //  名前の左横にある < のマークを押せない」原因は、ここで `h-full` を指定
    //  していたが親 (<main className="p-4 sm:p-6">) に高さ指定がないため
    //  実質 auto 扱いになり、ページ全体が縦方向にスクロールしていた。
    //  → chat ヘッダ (戻るボタンを含む) もページと一緒にスクロールアウト。
    //  ビューポートからレイアウトヘッダ + サブヘッダ + main padding を引いた
    //  高さに固定し、メッセージ部分だけが内部スクロールするように修正。
    //  100dvh は iOS Safari のアドレスバー伸縮にも追随する。
    <div
      className="flex flex-col gap-3 sm:flex-row"
      style={{ height: 'calc(100dvh - 140px)', minHeight: '420px' }}
    >
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
            {/* 念のため sticky top-0: たとえ page-level scroll が発生しても
                戻るボタンが常にビューポート最上部に残る。bg-white で背景透過防止。 */}
            <div className="sticky top-0 z-10 flex items-center gap-2 border-b border-[var(--neutral-stroke-2)] bg-white px-3 py-2 sm:px-4">
              <button
                type="button"
                onClick={() => setActiveRoomId(null)}
                // sm 以上では aside と main が常に side-by-side で見えているため
                // 戻るボタンは不要 (元設計どおり)。mobile (< sm) でのみ表示。
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
                  {messages.map((msg, index) => {
                    const isFromStaff = msg.sender_type === 'staff';
                    const hasAttachment = !!msg.attachment_path;
                    const isImage = hasAttachment && isImageAttachment(msg);
                    const url = hasAttachment ? attachmentUrl(msg.attachment_path!) : null;
                    const prev = index > 0 ? messages[index - 1] : null;
                    const showDate = isNewChatDay(msg.created_at, prev?.created_at ?? null);
                    return (
                      <div key={msg.id}>
                        {/* 日付区切り */}
                        {showDate && (
                          <div className="my-3 flex items-center justify-center">
                            <span className="rounded-full bg-[var(--neutral-background-4)] px-3 py-1 text-xs text-[var(--neutral-foreground-3)]">
                              {formatChatDate(msg.created_at)}
                            </span>
                          </div>
                        )}
                      <div
                        className={`flex ${isFromStaff ? 'justify-end' : 'justify-start'}`}
                      >
                        <div
                          className={`max-w-[80%] rounded-2xl px-3 py-2 text-sm break-words whitespace-pre-wrap ${
                            isFromStaff
                              ? 'rounded-br-md bg-[var(--brand-80)] text-white'
                              : 'rounded-bl-md bg-white text-[var(--neutral-foreground-1)] shadow-sm'
                          }`}
                        >
                          {/* URL は「リンクを開く」ボタンに置換 (現場要望) */}
                          {msg.message && (
                            <MessageBody text={nl(msg.message)} tone={isFromStaff ? 'mine' : 'other'} />
                          )}

                          {/* 添付ファイル描画 — 旧仕様では tablet チャットで非表示だった
                              (バグ報告: 保護者が添付した写真がタブレットで見られない) */}
                          {hasAttachment && url && (
                            isImage ? (
                              // 画像はインラインサムネ。タップで原寸を別タブで開く
                              <a
                                href={url}
                                target="_blank"
                                rel="noopener noreferrer"
                                className={`mt-2 block ${msg.message ? '' : '-mx-1'}`}
                              >
                                {/* eslint-disable-next-line @next/next/no-img-element */}
                                <img
                                  src={url}
                                  alt={msg.attachment_name || '画像'}
                                  className="max-h-[280px] rounded-lg border border-black/10 object-contain"
                                  loading="lazy"
                                />
                              </a>
                            ) : (
                              // 画像以外はファイル名 + サイズのリンク (ダウンロード可能)
                              <a
                                href={url}
                                target="_blank"
                                rel="noopener noreferrer"
                                download={msg.attachment_name || undefined}
                                className={`mt-2 flex items-center gap-2 rounded-lg px-2 py-1.5 text-xs ${
                                  isFromStaff
                                    ? 'bg-white/15 text-white hover:bg-white/25'
                                    : 'bg-[var(--neutral-background-3)] text-[var(--neutral-foreground-1)] hover:bg-[var(--neutral-background-4)]'
                                }`}
                              >
                                <MaterialIcon name="attach_file" size={14} />
                                <span className="truncate">{msg.attachment_name || 'ファイル'}</span>
                                <span className="ml-auto shrink-0 opacity-70">{formatBytes(msg.attachment_size)}</span>
                              </a>
                            )
                          )}
                        </div>
                      </div>
                      {/* 時刻 */}
                      <div className={`mt-0.5 flex ${isFromStaff ? 'justify-end' : 'justify-start'}`}>
                        <span className="px-1 text-[10px] text-[var(--neutral-foreground-4)]">
                          {formatChatTime(msg.created_at)}
                        </span>
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
