'use client';

import { cn, nl } from '@/lib/utils';
import { ChatAttachment } from './ChatAttachment';
import type { ChatMessage, MessageType } from '@/types/chat';
import { AlertTriangle, Calendar, Megaphone, CalendarCheck, CalendarClock } from 'lucide-react';
import Link from 'next/link';

interface ChatBubbleProps {
  message: ChatMessage;
  isMine: boolean;
}

const MESSAGE_TYPE_CONFIG: Record<string, {
  label: string;
  icon: React.ComponentType<{ className?: string }>;
  bubbleClass: string;
  badgeClass: string;
}> = {
  absence_notification: {
    label: '欠席連絡',
    icon: AlertTriangle,
    bubbleClass: 'bg-red-50 border-red-200 text-red-900',
    badgeClass: 'bg-red-100 text-red-700',
  },
  event_registration: {
    label: 'イベント参加',
    icon: Calendar,
    bubbleClass: 'bg-emerald-50 border-emerald-200 text-emerald-900',
    badgeClass: 'bg-emerald-100 text-emerald-700',
  },
  meeting_request: {
    label: '面談申込',
    icon: CalendarClock,
    bubbleClass: 'bg-purple-50 border-purple-200 text-purple-900',
    badgeClass: 'bg-purple-100 text-purple-700',
  },
  meeting_counter: {
    label: '面談日程対案',
    icon: CalendarClock,
    bubbleClass: 'bg-purple-50 border-purple-200 text-purple-900',
    badgeClass: 'bg-purple-100 text-purple-700',
  },
  meeting_confirmed: {
    label: '面談日程確定',
    icon: CalendarCheck,
    bubbleClass: 'bg-purple-50 border-purple-200 text-purple-900',
    badgeClass: 'bg-purple-100 text-purple-700',
  },
  broadcast: {
    label: '一斉送信',
    icon: Megaphone,
    bubbleClass: 'bg-amber-50 border-amber-200 text-amber-900',
    badgeClass: 'bg-amber-100 text-amber-700',
  },
};

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

  const typeConfig = message.message_type !== 'normal'
    ? MESSAGE_TYPE_CONFIG[message.message_type]
    : null;

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
            typeConfig
              ? cn('rounded-tr-sm border', typeConfig.bubbleClass)
              : isMine
                ? 'rounded-tr-sm bg-blue-600 text-white'
                : 'rounded-tl-sm bg-white text-gray-800 shadow-sm border border-gray-100'
          )}
        >
          {/* Message type badge */}
          {typeConfig && (
            <div className={cn(
              'mb-2 inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[10px] font-semibold',
              typeConfig.badgeClass
            )}>
              <typeConfig.icon className="h-3 w-3" />
              {typeConfig.label}
            </div>
          )}

          {/* Message text */}
          <p className="whitespace-pre-wrap break-words">{nl(message.message)}</p>

          {/* Meeting action button */}
          {message.meeting_request_id && (message.message_type === 'meeting_request' || message.message_type === 'meeting_counter' || message.message_type === 'meeting_confirmed') && (
            <div className="mt-2">
              <Link
                href={`/staff/meetings?meeting_id=${message.meeting_request_id}`}
                className={cn(
                  'inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-semibold transition-colors',
                  message.message_type === 'meeting_confirmed'
                    ? 'bg-green-600 text-white hover:bg-green-700'
                    : 'bg-purple-600 text-white hover:bg-purple-700'
                )}
              >
                <CalendarCheck className="h-3.5 w-3.5" />
                {message.message_type === 'meeting_confirmed' ? '確定内容を確認' : '面談予約を確認'}
              </Link>
            </div>
          )}

          {/* Attachment */}
          {message.attachment_path && (
            <div className="mt-2">
              <ChatAttachment
                attachment={{
                  path: message.attachment_path,
                  name: message.attachment_name || 'ファイル',
                  size: message.attachment_size || 0,
                  mime: message.attachment_mime || '',
                  url: `${process.env.NEXT_PUBLIC_BACKEND_URL || ''}/storage/${message.attachment_path}`,
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
