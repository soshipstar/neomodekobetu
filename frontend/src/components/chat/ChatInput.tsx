'use client';

import { useState, useRef, type KeyboardEvent } from 'react';
import { cn } from '@/lib/utils';
import { Button } from '@/components/ui/Button';
import { Paperclip, Send, X } from 'lucide-react';
import { formatFileSize } from '@/lib/utils';

interface ChatInputProps {
  onSend: (message: string, attachment?: File) => Promise<void>;
  isSending: boolean;
  disabled?: boolean;
}

export function ChatInput({ onSend, isSending, disabled = false }: ChatInputProps) {
  const [message, setMessage] = useState('');
  const [attachment, setAttachment] = useState<File | null>(null);
  const fileInputRef = useRef<HTMLInputElement>(null);
  const textareaRef = useRef<HTMLTextAreaElement>(null);

  const handleSend = async () => {
    const trimmed = message.trim();
    if (!trimmed && !attachment) return;

    await onSend(trimmed, attachment || undefined);
    setMessage('');
    setAttachment(null);

    // Focus back on textarea
    textareaRef.current?.focus();
  };

  const handleKeyDown = (e: KeyboardEvent<HTMLTextAreaElement>) => {
    // Send on Enter (without shift)
    if (e.key === 'Enter' && !e.shiftKey) {
      e.preventDefault();
      handleSend();
    }
  };

  const handleFileSelect = (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0];
    if (file) {
      // 10MB limit
      if (file.size > 10 * 1024 * 1024) {
        alert('ファイルサイズは10MB以下にしてください');
        return;
      }
      setAttachment(file);
    }
    // Reset input
    if (fileInputRef.current) {
      fileInputRef.current.value = '';
    }
  };

  const removeAttachment = () => {
    setAttachment(null);
  };

  return (
    <div className="border-t border-gray-200 bg-white p-3">
      {/* Attachment preview */}
      {attachment && (
        <div className="mb-2 flex items-center gap-2 rounded-lg bg-gray-50 px-3 py-2">
          <Paperclip className="h-4 w-4 text-gray-400" />
          <span className="flex-1 truncate text-sm text-gray-700">{attachment.name}</span>
          <span className="text-xs text-gray-400">{formatFileSize(attachment.size)}</span>
          <button onClick={removeAttachment} className="rounded p-1 text-gray-400 hover:text-red-500">
            <X className="h-4 w-4" />
          </button>
        </div>
      )}

      {/* Input area */}
      <div className="flex items-end gap-2">
        {/* File attachment button */}
        <button
          onClick={() => fileInputRef.current?.click()}
          disabled={disabled}
          className="shrink-0 rounded-lg p-2 text-gray-400 hover:bg-gray-100 hover:text-gray-600 disabled:cursor-not-allowed disabled:opacity-50"
        >
          <Paperclip className="h-5 w-5" />
        </button>
        <input
          ref={fileInputRef}
          type="file"
          className="hidden"
          accept="image/*,.pdf,.doc,.docx,.xls,.xlsx"
          onChange={handleFileSelect}
        />

        {/* Text input */}
        <textarea
          ref={textareaRef}
          value={message}
          onChange={(e) => setMessage(e.target.value)}
          onKeyDown={handleKeyDown}
          placeholder="メッセージを入力..."
          disabled={disabled}
          rows={1}
          className={cn(
            'flex-1 resize-none rounded-xl border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm',
            'placeholder-gray-400 focus:border-blue-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-blue-500/20',
            'disabled:cursor-not-allowed disabled:opacity-50',
            'max-h-32'
          )}
          style={{ minHeight: '40px' }}
          onInput={(e) => {
            const target = e.target as HTMLTextAreaElement;
            target.style.height = 'auto';
            target.style.height = `${Math.min(target.scrollHeight, 128)}px`;
          }}
        />

        {/* Send button */}
        <Button
          onClick={handleSend}
          disabled={disabled || (!message.trim() && !attachment)}
          isLoading={isSending}
          size="md"
          className="shrink-0 rounded-xl"
        >
          <Send className="h-4 w-4" />
        </Button>
      </div>

      <p className="mt-1 text-center text-[10px] text-gray-300">
        Shift+Enterで改行
      </p>
    </div>
  );
}

export default ChatInput;
