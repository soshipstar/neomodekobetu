'use client';

import { useState, useEffect, useRef, useCallback } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import api from '@/lib/api';
import { Button } from '@/components/ui/Button';
import { SkeletonList } from '@/components/ui/Skeleton';
import { formatFileSize, nl } from '@/lib/utils';
import { MaterialIcon } from '@/components/ui/MaterialIcon';

interface StudentChatMessage {
  id: number;
  room_id: number;
  sender_id: number;
  sender_type: 'student' | 'staff';
  message: string;
  attachment_path: string | null;
  attachment_original_name: string | null;
  attachment_size: number | null;
  is_read: boolean;
  created_at: string;
  sender_name?: string;
}

interface ChatResponse {
  room_id: number;
  messages: StudentChatMessage[];
}

export default function StudentChatPage() {
  const queryClient = useQueryClient();
  const [message, setMessage] = useState('');
  const [attachment, setAttachment] = useState<File | null>(null);
  const bottomRef = useRef<HTMLDivElement>(null);
  const fileInputRef = useRef<HTMLInputElement>(null);
  const textareaRef = useRef<HTMLTextAreaElement>(null);

  const { data, isLoading } = useQuery({
    queryKey: ['student', 'chat', 'messages'],
    queryFn: async () => {
      const res = await api.get<{ data: ChatResponse }>('/api/student/chat/messages');
      return res.data.data;
    },
    refetchInterval: 5000, // Poll every 5 seconds for new messages
  });

  const messages = data?.messages || [];
  const roomId = data?.room_id;

  const sendMutation = useMutation({
    mutationFn: async ({ msg, file }: { msg: string; file?: File }) => {
      const formData = new FormData();
      formData.append('message', msg);
      if (file) formData.append('attachment', file);
      return api.post('/api/student/chat/messages', formData, {
        headers: { 'Content-Type': 'multipart/form-data' },
      });
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['student', 'chat', 'messages'] });
      setMessage('');
      setAttachment(null);
    },
  });

  // Scroll to bottom when messages change
  useEffect(() => {
    bottomRef.current?.scrollIntoView({ behavior: 'smooth' });
  }, [messages.length]);

  const handleSend = useCallback(async () => {
    const trimmed = message.trim();
    if (!trimmed && !attachment) return;
    sendMutation.mutate({ msg: trimmed, file: attachment || undefined });
  }, [message, attachment, sendMutation]);

  const handleKeyDown = (e: React.KeyboardEvent<HTMLTextAreaElement>) => {
    if (e.key === 'Enter' && !e.shiftKey) {
      e.preventDefault();
      handleSend();
    }
  };

  const handleFileSelect = (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0];
    if (!file) return;
    if (file.size > 3 * 1024 * 1024) {
      alert('ファイルサイズは3MB以下にしてください');
      return;
    }
    setAttachment(file);
  };

  const formatTime = (dateStr: string) => {
    const d = new Date(dateStr);
    return `${(d.getMonth() + 1).toString().padStart(2, '0')}/${d.getDate().toString().padStart(2, '0')} ${d.getHours().toString().padStart(2, '0')}:${d.getMinutes().toString().padStart(2, '0')}`;
  };

  if (isLoading) {
    return (
      <div className="space-y-4">
        <h1 className="text-2xl font-bold text-[var(--neutral-foreground-1)]">スタッフとのチャット</h1>
        <SkeletonList items={5} />
      </div>
    );
  }

  return (
    <div className="flex h-[calc(100vh-6rem)] flex-col sm:h-[calc(100vh-7rem)] lg:h-[calc(100vh-5rem)]">
      {/* Header */}
      <div className="border-b border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-1)] px-3 py-2 sm:px-4 sm:py-3 flex-shrink-0">
        <h2 className="text-sm font-semibold text-[var(--neutral-foreground-1)]">スタッフとのチャット</h2>
        <p className="text-xs text-[var(--neutral-foreground-3)]">質問や相談があればメッセージを送ってください</p>
      </div>

      {/* Messages area */}
      <div className="flex-1 overflow-y-auto bg-[var(--neutral-background-2)] px-3 py-4 sm:px-4">
        {messages.length === 0 ? (
          <div className="flex flex-col items-center justify-center h-full text-center">
            <MaterialIcon name="chat" size={48} className="text-[var(--neutral-foreground-4)] mb-3" />
            <h3 className="text-sm font-medium text-[var(--neutral-foreground-2)]">まだメッセージがありません</h3>
            <p className="text-xs text-[var(--neutral-foreground-3)] mt-1">スタッフにメッセージを送ってみましょう</p>
          </div>
        ) : (
          <div className="space-y-3">
            {messages.map((msg) => {
              const isSent = msg.sender_type === 'student';
              return (
                <div key={msg.id} className={`flex ${isSent ? 'justify-end' : 'justify-start'}`}>
                  <div className={`max-w-[80%] ${isSent ? 'order-1' : 'order-1'}`}>
                    {/* Sender name */}
                    <p className={`text-xs mb-0.5 ${isSent ? 'text-right' : 'text-left'} text-[var(--neutral-foreground-3)]`}>
                      {msg.sender_name || (isSent ? '自分' : 'スタッフ')}
                    </p>
                    {/* Message bubble */}
                    <div className={`rounded-2xl px-4 py-2 text-sm break-words whitespace-pre-wrap ${
                      isSent
                        ? 'bg-[var(--brand-80)] text-white rounded-br-md'
                        : 'bg-[var(--neutral-background-1)] text-[var(--neutral-foreground-1)] rounded-bl-md shadow-sm'
                    }`}>
                      {nl(msg.message)}
                      {msg.attachment_path && (
                        <div className={`mt-2 flex items-center gap-1 text-xs ${isSent ? 'text-white/80' : 'text-[var(--brand-80)]'}`}>
                          <MaterialIcon name="attach_file" size={12} />
                          <span>{msg.attachment_original_name}</span>
                          {msg.attachment_size && (
                            <span>({formatFileSize(msg.attachment_size)})</span>
                          )}
                        </div>
                      )}
                    </div>
                    {/* Time and read status */}
                    <div className={`flex items-center gap-1 mt-0.5 text-[10px] text-[var(--neutral-foreground-4)] ${isSent ? 'justify-end' : 'justify-start'}`}>
                      {isSent && msg.is_read && (
                        <span className="text-[var(--brand-80)]">既読</span>
                      )}
                      <span>{formatTime(msg.created_at)}</span>
                    </div>
                  </div>
                </div>
              );
            })}
            <div ref={bottomRef} />
          </div>
        )}
      </div>

      {/* Input area */}
      <div className="border-t border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-1)] p-3 flex-shrink-0">
        {/* File preview */}
        {attachment && (
          <div className="flex items-center gap-2 mb-2 rounded-lg bg-[var(--neutral-background-2)] px-3 py-2 text-sm">
            <MaterialIcon name="attach_file" size={16} className="text-[var(--neutral-foreground-3)]" />
            <span className="flex-1 truncate text-[var(--neutral-foreground-2)]">{attachment.name}</span>
            <span className="text-xs text-[var(--neutral-foreground-3)]">{formatFileSize(attachment.size)}</span>
            <button type="button" onClick={() => setAttachment(null)} className="text-[var(--neutral-foreground-3)] hover:text-[var(--status-danger-fg)]">
              <MaterialIcon name="close" size={16} />
            </button>
          </div>
        )}
        <div className="flex items-end gap-2">
          <button
            type="button"
            onClick={() => fileInputRef.current?.click()}
            className="flex-shrink-0 rounded-full p-2 text-[var(--neutral-foreground-3)] hover:bg-[var(--neutral-background-2)]"
            title="ファイルを添付"
          >
            <MaterialIcon name="attach_file" size={20} />
          </button>
          <input
            ref={fileInputRef}
            type="file"
            className="hidden"
            accept=".pdf,.jpg,.jpeg,.png,.doc,.docx"
            onChange={handleFileSelect}
          />
          <textarea
            ref={textareaRef}
            value={message}
            onChange={(e) => setMessage(e.target.value)}
            onKeyDown={handleKeyDown}
            placeholder="メッセージを入力..."
            className="flex-1 resize-none rounded-2xl border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-1)] px-4 py-2 text-sm text-[var(--neutral-foreground-1)] placeholder:text-[var(--neutral-foreground-4)] focus:outline-none focus:ring-2 focus:ring-[var(--brand-80)]"
            rows={1}
          />
          <Button
            size="sm"
            className="flex-shrink-0 rounded-full !p-2"
            onClick={handleSend}
            disabled={sendMutation.isPending || (!message.trim() && !attachment)}
            isLoading={sendMutation.isPending}
          >
            <MaterialIcon name="send" size={20} />
          </Button>
        </div>
      </div>
    </div>
  );
}
