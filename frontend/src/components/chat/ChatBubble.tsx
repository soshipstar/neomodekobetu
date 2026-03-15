'use client';

import { cn } from '@/lib/utils';
import { ChatAttachment } from './ChatAttachment';
import type { ChatMessage } from '@/types/chat';

interface ChatBubbleProps {
  message: ChatMessage;
  isMine: boolean;
}

export function ChatBubble({ message, isMine }: ChatBubbleProps) {
  if (message.is_deleted) {
    return (
      <div className={cn('flex', isMine ? 'justify-end' : 'justify-start')}>
        <div className="max-w-xs rounded-lg bg-gray-100 px-4 py-2 italic text-gray-400 text-sm">
          このメッセージは削除されました
        </div>
      </div>
    );
  }

  const time = new Date(message.created_at).toLocaleTimeString('ja-JP', {
    hour: '2-digit',
    minute: '2-digit',
  });

  return (
    <div className={cn('flex chat-bubble-enter', isMine ? 'justify-end' : 'justify-start')}>
      <div className={cn('flex max-w-[75%] flex-col gap-1', isMine ? 'items-end' : 'items-start')}>
        {/* Sender name (for received messages) */}
        {!isMine && message.sender && (
          <span className="ml-1 text-xs font-medium text-gray-500">
            {message.sender.full_name}
          </span>
        )}

        {/* Bubble */}
        <div
          className={cn(
            'rounded-2xl px-4 py-2.5 text-sm leading-relaxed',
            isMine
              ? 'rounded-tr-sm bg-blue-600 text-white'
              : 'rounded-tl-sm bg-white text-gray-800 shadow-sm border border-gray-100'
          )}
        >
          {/* Message text */}
          <p className="whitespace-pre-wrap break-words">{message.message}</p>

          {/* Attachment */}
          {message.attachment_path && (
            <div className="mt-2">
              <ChatAttachment
                attachment={{
                  path: message.attachment_path,
                  name: message.attachment_name || 'ファイル',
                  size: message.attachment_size || 0,
                  mime: message.attachment_mime || '',
                  url: `${process.env.NEXT_PUBLIC_API_URL}/storage/${message.attachment_path}`,
                }}
                isMine={isMine}
              />
            </div>
          )}
        </div>

        {/* Timestamp */}
        <span className={cn('mx-1 text-[10px] text-gray-400')}>{time}</span>
      </div>
    </div>
  );
}

export default ChatBubble;
