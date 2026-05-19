'use client';

import { useState, useRef, type KeyboardEvent } from 'react';
import { cn } from '@/lib/utils';
import { Button } from '@/components/ui/Button';
import { formatFileSize } from '@/lib/utils';
import { MaterialIcon } from '@/components/ui/MaterialIcon';
import { compressImageToBytes, DEFAULT_TARGET_BYTES } from '@/lib/imageCompress';

interface ChatInputProps {
  onSend: (message: string, attachment?: File) => Promise<void>;
  isSending: boolean;
  disabled?: boolean;
}

// 画像は 300KB 以下に自動圧縮、その他のファイルは 3MB の従来制限を維持
const NON_IMAGE_MAX_BYTES = 3 * 1024 * 1024;

export function ChatInput({ onSend, isSending, disabled = false }: ChatInputProps) {
  const [message, setMessage] = useState('');
  const [attachment, setAttachment] = useState<File | null>(null);
  // 画像圧縮中の進捗表示 + 元サイズと圧縮後サイズの差分を表示するための補助 state
  const [compressing, setCompressing] = useState(false);
  const [compressInfo, setCompressInfo] = useState<{ originalBytes: number; resultBytes: number } | null>(null);
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

  const handleFileSelect = async (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0];
    // ファイル選択ダイアログを次回も同じファイルで開けるよう、まず input をクリア
    if (fileInputRef.current) fileInputRef.current.value = '';
    if (!file) return;

    const isImage = file.type.startsWith('image/');

    // 画像以外は従来通り 3MB ハード制限
    if (!isImage) {
      if (file.size > NON_IMAGE_MAX_BYTES) {
        alert('ファイルサイズは 3MB 以下にしてください');
        return;
      }
      setAttachment(file);
      setCompressInfo(null);
      return;
    }

    // 画像は 300KB 以下に自動圧縮
    setCompressing(true);
    try {
      const result = await compressImageToBytes(file, DEFAULT_TARGET_BYTES);
      setAttachment(result.file);
      setCompressInfo(
        result.compressed
          ? { originalBytes: result.originalBytes, resultBytes: result.resultBytes }
          : null,
      );
    } catch (err) {
      const msg = err instanceof Error ? err.message : '画像の圧縮に失敗しました';
      alert(msg + '\n\nお手数ですが別の画像をお試しください。');
    } finally {
      setCompressing(false);
    }
  };

  const removeAttachment = () => {
    setAttachment(null);
    setCompressInfo(null);
  };

  return (
    <div className="border-t border-[var(--neutral-stroke-2)] bg-white p-3">
      {/* 圧縮中インジケータ */}
      {compressing && (
        <div className="mb-2 flex items-center gap-2 rounded-lg bg-[var(--brand-160)] px-3 py-2 text-sm text-[var(--brand-60)]">
          <MaterialIcon name="autorenew" size={16} className="animate-spin" />
          <span>画像を 300KB 以下に圧縮しています…</span>
        </div>
      )}

      {/* Attachment preview */}
      {attachment && !compressing && (
        <div className="mb-2 flex flex-wrap items-center gap-2 rounded-lg bg-[var(--neutral-background-3)] px-3 py-2">
          <MaterialIcon name="attach_file" size={16} className="text-[var(--neutral-foreground-4)]" />
          <span className="flex-1 truncate text-sm text-[var(--neutral-foreground-2)]">{attachment.name}</span>
          <span className="text-xs text-[var(--neutral-foreground-4)]">{formatFileSize(attachment.size)}</span>
          {compressInfo && (
            <span className="text-[10px] text-[var(--status-success-fg)]">
              {formatFileSize(compressInfo.originalBytes)} → {formatFileSize(compressInfo.resultBytes)} に圧縮済
            </span>
          )}
          <button onClick={removeAttachment} className="rounded p-1 text-[var(--neutral-foreground-4)] hover:text-red-500">
            <MaterialIcon name="close" size={16} />
          </button>
        </div>
      )}

      {/* Input area */}
      <div className="flex items-end gap-2">
        {/* File attachment button */}
        <button
          onClick={() => fileInputRef.current?.click()}
          disabled={disabled}
          className="shrink-0 rounded-lg p-2 text-[var(--neutral-foreground-4)] hover:bg-[var(--neutral-background-4)] hover:text-[var(--neutral-foreground-3)] disabled:cursor-not-allowed disabled:opacity-50"
        >
          <MaterialIcon name="attach_file" size={20} />
        </button>
        <input
          ref={fileInputRef}
          type="file"
          className="hidden"
          accept="image/*,.pdf,.doc,.docx,.xls,.xlsx,.txt,.csv,.mp4,.mov,.mp3"
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
            'flex-1 resize-none rounded-xl border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-3)] px-4 py-2.5 text-sm',
            'placeholder-gray-400 focus:border-[var(--brand-80)] focus:bg-white focus:outline-none focus:ring-2 focus:ring-[var(--brand-80)]/20',
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
          disabled={disabled || compressing || (!message.trim() && !attachment)}
          isLoading={isSending}
          size="md"
          className="shrink-0 rounded-xl"
        >
          <MaterialIcon name="send" size={16} />
        </Button>
      </div>

      <p className="mt-1 text-center text-[10px] text-[var(--neutral-foreground-disabled)]">
        Shift+Enterで改行 / 画像は自動で 300KB 以下に圧縮されます
      </p>
    </div>
  );
}

export default ChatInput;
