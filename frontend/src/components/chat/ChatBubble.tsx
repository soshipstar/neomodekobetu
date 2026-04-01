'use client';

import { useState } from 'react';
import { cn, nl } from '@/lib/utils';
import { ChatAttachment } from './ChatAttachment';
import type { ChatMessage, MessageType } from '@/types/chat';
import Link from 'next/link';
import { MaterialIcon } from '@/components/ui/MaterialIcon';
import { useChatStore } from '@/stores/chatStore';

interface ChatBubbleProps {
  message: ChatMessage;
  isMine: boolean;
}

const MESSAGE_TYPE_CONFIG: Record<string, {
  label: string;
  icon: string;
  bubbleClass: string;
  badgeClass: string;
}> = {
  absence_notification: {
    label: '欠席連絡',
    icon: "warning",
    bubbleClass: 'bg-[var(--status-danger-bg)] border-[var(--status-danger-fg)]/20 text-[var(--neutral-foreground-1)]',
    badgeClass: 'bg-[var(--status-danger-bg)] text-[var(--status-danger-fg)]',
  },
  event_registration: {
    label: 'イベント参加',
    icon: "calendar_month",
    bubbleClass: 'bg-[var(--status-success-bg)] border-[var(--status-success-fg)]/20 text-[var(--neutral-foreground-1)]',
    badgeClass: 'bg-[var(--status-success-bg)] text-[var(--status-success-fg)]',
  },
  meeting_request: {
    label: '面談申込',
    icon: "schedule",
    bubbleClass: 'bg-[var(--brand-160)] border-[var(--brand-80)]/20 text-[var(--neutral-foreground-1)]',
    badgeClass: 'bg-[var(--brand-150)] text-[var(--brand-60)]',
  },
  meeting_counter: {
    label: '面談日程対案',
    icon: "schedule",
    bubbleClass: 'bg-[var(--brand-160)] border-[var(--brand-80)]/20 text-[var(--neutral-foreground-1)]',
    badgeClass: 'bg-[var(--brand-150)] text-[var(--brand-60)]',
  },
  meeting_confirmed: {
    label: '面談日程確定',
    icon: "event_available",
    bubbleClass: 'bg-[var(--status-success-bg)] border-[var(--status-success-fg)]/20 text-[var(--neutral-foreground-1)]',
    badgeClass: 'bg-[var(--status-success-bg)] text-[var(--status-success-fg)]',
  },
  broadcast: {
    label: '一斉送信',
    icon: "campaign",
    bubbleClass: 'bg-[var(--status-warning-bg)] border-[var(--status-warning-fg)]/20 text-[var(--neutral-foreground-1)]',
    badgeClass: 'bg-[var(--status-warning-bg)] text-[var(--status-warning-fg)]',
  },
};

export function ChatBubble({ message, isMine }: ChatBubbleProps) {
  const [isDeleting, setIsDeleting] = useState(false);
  const [isArchiving, setIsArchiving] = useState(false);
  const deleteMessage = useChatStore((s) => s.deleteMessage);
  const toggleArchive = useChatStore((s) => s.toggleArchive);
  const apiPrefix = useChatStore((s) => s.apiPrefix);
  const isGuardian = apiPrefix.includes('guardian');

  if (message.is_deleted) {
    return (
      <div className={cn('flex', isMine ? 'justify-end' : 'justify-start')}>
        <div className="max-w-xs rounded-lg bg-[var(--neutral-background-4)] px-4 py-2 italic text-[var(--neutral-foreground-4)] text-sm">
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

  const handleDelete = async () => {
    if (!window.confirm('このメッセージを削除しますか？')) return;
    setIsDeleting(true);
    try {
      await deleteMessage(message.room_id, message.id);
    } catch {
      alert('メッセージの削除に失敗しました');
    } finally {
      setIsDeleting(false);
    }
  };

  const handleArchive = async () => {
    setIsArchiving(true);
    try {
      await toggleArchive(message.room_id, message.id);
    } catch {
      alert('アーカイブの切り替えに失敗しました');
    } finally {
      setIsArchiving(false);
    }
  };

  // 既読表示: 自分が送ったメッセージが相手に読まれたか
  const isReadByRecipient = isMine && (message.is_read_by_recipient || message.is_read_by_staff || message.is_read);

  return (
    <div className={cn('group flex chat-bubble-enter', isMine ? 'justify-end' : 'justify-start')}>
      <div className={cn('flex max-w-[75%] flex-col gap-1', isMine ? 'items-end' : 'items-start')}>
        {/* Sender name (for received messages) */}
        {!isMine && message.sender && (
          <span className="ml-1 text-xs font-medium text-[var(--neutral-foreground-3)]">
            {message.sender.full_name}
          </span>
        )}

        {/* Bubble with action buttons */}
        <div className="relative">
          {/* Delete button (own messages only) */}
          {isMine && (
            <button
              type="button"
              onClick={handleDelete}
              disabled={isDeleting}
              className={cn(
                'absolute -left-8 top-1/2 -translate-y-1/2 rounded-full p-1 transition-all',
                'text-[var(--neutral-foreground-4)] hover:text-[var(--status-danger-fg)] hover:bg-[var(--status-danger-bg)]',
                'opacity-0 group-hover:opacity-100 focus:opacity-100',
                isDeleting && 'opacity-50 cursor-not-allowed'
              )}
              title="メッセージを削除"
            >
              <MaterialIcon name="delete" size={16} />
            </button>
          )}
          {/* Archive button */}
          <button
            type="button"
            onClick={handleArchive}
            disabled={isArchiving}
            className={cn(
              'absolute top-1/2 -translate-y-1/2 rounded-full p-1 transition-all',
              isMine ? '-left-8' : '-right-8',
              isMine && 'top-[calc(50%+14px)]',
              message.is_archived
                ? 'text-[var(--brand-80)] opacity-100'
                : cn(
                    'text-[var(--neutral-foreground-4)] hover:text-[var(--brand-80)]',
                    'opacity-0 group-hover:opacity-100 focus:opacity-100'
                  ),
              isArchiving && 'opacity-50 cursor-not-allowed'
            )}
            title={message.is_archived ? 'アーカイブ解除' : 'アーカイブ'}
          >
            <MaterialIcon name={message.is_archived ? 'bookmark' : 'bookmark_border'} size={16} />
          </button>
          <div
            className={cn(
              'rounded-2xl px-4 py-2.5 text-sm leading-relaxed',
              typeConfig
                ? cn('rounded-tr-sm border', typeConfig.bubbleClass)
                : isMine
                  ? 'rounded-tr-sm bg-[var(--brand-80)] text-white'
                  : 'rounded-tl-sm bg-[var(--neutral-background-1)] text-[var(--neutral-foreground-1)] shadow-sm border border-[var(--neutral-stroke-2)]'
            )}
          >
            {/* Message type badge */}
            {typeConfig && (
              <div className={cn(
                'mb-2 inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[10px] font-semibold',
                typeConfig.badgeClass
              )}>
                <MaterialIcon name={typeConfig.icon} size={12} />
                {typeConfig.label}
              </div>
            )}

            {/* Message text */}
            <p className="whitespace-pre-wrap break-words">{nl(message.message)}</p>

            {/* Meeting action button */}
            {message.meeting_request_id && (message.message_type === 'meeting_request' || message.message_type === 'meeting_counter' || message.message_type === 'meeting_confirmed') && (
              <div className="mt-2">
                <Link
                  href={isGuardian ? `/guardian/meetings` : `/staff/meetings?meeting_id=${message.meeting_request_id}`}
                  className={cn(
                    'inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-semibold transition-colors',
                    message.message_type === 'meeting_confirmed'
                      ? 'bg-green-600 text-white hover:bg-green-700'
                      : 'bg-purple-600 text-white hover:bg-purple-700'
                  )}
                >
                  <MaterialIcon name="event_available" size={14} />
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
        </div>

        {/* Timestamp + Read receipt */}
        <div className={cn('mx-1 flex items-center gap-1', isMine ? 'flex-row-reverse' : '')}>
          <span className="text-[10px] text-[var(--neutral-foreground-4)]">{time}</span>
          {isReadByRecipient && (
            <span className="text-[10px] text-[var(--brand-80)] font-medium">既読</span>
          )}
        </div>
      </div>
    </div>
  );
}

export default ChatBubble;
