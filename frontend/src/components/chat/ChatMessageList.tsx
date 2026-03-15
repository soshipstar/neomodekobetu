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
        <p className="text-sm text-gray-400">メッセージはまだありません</p>
      </div>
    );
  }

  // Group messages by date
  let lastDate = '';

  return (
    <div ref={containerRef} className="flex flex-col gap-1 p-4">
      {messages.map((message) => {
        const messageDate = new Date(message.created_at).toLocaleDateString('ja-JP', {
          year: 'numeric',
          month: 'long',
          day: 'numeric',
        });
        const showDateSeparator = messageDate !== lastDate;
        lastDate = messageDate;

        return (
          <div key={message.id}>
            {showDateSeparator && (
              <div className="my-4 flex items-center justify-center">
                <span className="rounded-full bg-gray-200 px-3 py-1 text-xs text-gray-500">
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
