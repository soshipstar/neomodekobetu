'use client';

import { useEffect, useRef } from 'react';
import { ChatBubble } from './ChatBubble';
import type { ChatMessage } from '@/types/chat';

interface ChatMessageListProps {
  messages: ChatMessage[];
  currentUserId: number;
}

export function ChatMessageList({ messages, currentUserId }: ChatMessageListProps) {
  const bottomRef = useRef<HTMLDivElement>(null);
  const containerRef = useRef<HTMLDivElement>(null);

  // Auto-scroll to bottom on new messages
  useEffect(() => {
    bottomRef.current?.scrollIntoView({ behavior: 'smooth' });
  }, [messages]);

  if (messages.length === 0) {
    return (
      <div className="flex h-full items-center justify-center">
        <p className="text-sm text-[var(--neutral-foreground-4)]">メッセージはまだありません</p>
      </div>
    );
  }

  // 日付グループ用に「直前のメッセージの日付」を事前計算する。
  // render 中の let 変数再代入は React 19 strict (react-hooks/immutability) で
  // エラーになるため、map の前に index 配列を組み立てる純粋計算に変更。
  const formatDate = (iso: string) =>
    new Date(iso).toLocaleDateString('ja-JP', {
      year: 'numeric',
      month: 'long',
      day: 'numeric',
    });
  const dateBoundaries = messages.map((m, i) => {
    const cur = formatDate(m.created_at);
    const prev = i === 0 ? '' : formatDate(messages[i - 1].created_at);
    return { messageDate: cur, showDateSeparator: cur !== prev };
  });

  return (
    <div ref={containerRef} className="flex flex-col gap-1 p-4">
      {messages.map((message, i) => {
        const { messageDate, showDateSeparator } = dateBoundaries[i];

        return (
          <div key={message.id}>
            {showDateSeparator && (
              <div className="my-4 flex items-center justify-center">
                <span className="rounded-full bg-[var(--neutral-background-5)] px-3 py-1 text-xs text-[var(--neutral-foreground-3)]">
                  {messageDate}
                </span>
              </div>
            )}
            <ChatBubble
              message={message}
              isMine={message.sender_id === currentUserId}
            />
          </div>
        );
      })}
      <div ref={bottomRef} />
    </div>
  );
}

export default ChatMessageList;
