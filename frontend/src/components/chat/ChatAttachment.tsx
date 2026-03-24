'use client';

import { cn, formatFileSize } from '@/lib/utils';
import type { ChatAttachment as ChatAttachmentType } from '@/types/chat';
import { MaterialIcon } from '@/components/ui/MaterialIcon';

interface ChatAttachmentProps {
  attachment: ChatAttachmentType;
  isMine: boolean;
}

export function ChatAttachment({ attachment, isMine }: ChatAttachmentProps) {
  const isImage = attachment.mime.startsWith('image/');

  if (isImage) {
    return (
      <a href={attachment.url} target="_blank" rel="noopener noreferrer" className="block">
        <img
          src={attachment.url}
          alt={attachment.name}
          className="max-h-48 max-w-full rounded-lg object-cover"
          loading="lazy"
        />
      </a>
    );
  }

  // Non-image file
  return (
    <a
      href={attachment.url}
      target="_blank"
      rel="noopener noreferrer"
      download={attachment.name}
      className={cn(
        'flex items-center gap-2 rounded-lg border px-3 py-2 transition-colors',
        isMine
          ? 'border-blue-400/50 bg-[var(--brand-80)]/20 hover:bg-[var(--brand-80)]/30'
          : 'border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-3)] hover:bg-[var(--neutral-background-4)]'
      )}
    >
      <FileText className={cn('h-5 w-5 shrink-0', isMine ? 'text-blue-200' : 'text-[var(--neutral-foreground-4)]')} />
      <div className="flex-1 min-w-0">
        <p className={cn('truncate text-xs font-medium', isMine ? 'text-white' : 'text-[var(--neutral-foreground-2)]')}>
          {attachment.name}
        </p>
        <p className={cn('text-[10px]', isMine ? 'text-blue-200' : 'text-[var(--neutral-foreground-4)]')}>
          {formatFileSize(attachment.size)}
        </p>
      </div>
      <Download className={cn('h-4 w-4 shrink-0', isMine ? 'text-blue-200' : 'text-[var(--neutral-foreground-4)]')} />
    </a>
  );
}

export default ChatAttachment;
