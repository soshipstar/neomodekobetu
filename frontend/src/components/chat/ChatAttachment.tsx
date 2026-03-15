'use client';

import { cn, formatFileSize } from '@/lib/utils';
import { FileText, Image as ImageIcon, Download } from 'lucide-react';
import type { ChatAttachment as ChatAttachmentType } from '@/types/chat';

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
          ? 'border-blue-400/50 bg-blue-500/20 hover:bg-blue-500/30'
          : 'border-gray-200 bg-gray-50 hover:bg-gray-100'
      )}
    >
      <FileText className={cn('h-5 w-5 shrink-0', isMine ? 'text-blue-200' : 'text-gray-400')} />
      <div className="flex-1 min-w-0">
        <p className={cn('truncate text-xs font-medium', isMine ? 'text-white' : 'text-gray-700')}>
          {attachment.name}
        </p>
        <p className={cn('text-[10px]', isMine ? 'text-blue-200' : 'text-gray-400')}>
          {formatFileSize(attachment.size)}
        </p>
      </div>
      <Download className={cn('h-4 w-4 shrink-0', isMine ? 'text-blue-200' : 'text-gray-400')} />
    </a>
  );
}

export default ChatAttachment;
