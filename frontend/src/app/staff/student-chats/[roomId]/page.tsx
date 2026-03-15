'use client';

import { useState, useRef, useEffect } from 'react';
import { useParams } from 'next/navigation';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import api from '@/lib/api';
import { Card } from '@/components/ui/Card';
import { Button } from '@/components/ui/Button';
import { Badge } from '@/components/ui/Badge';
import { SkeletonList } from '@/components/ui/Skeleton';
import { useToast } from '@/components/ui/Toast';
import { Send, ArrowLeft, Paperclip } from 'lucide-react';
import { format } from 'date-fns';
import { ja } from 'date-fns/locale';
import Link from 'next/link';

interface Message {
  id: number;
  sender_type: 'staff' | 'student';
  sender_name: string;
  message: string;
  attachment_url: string | null;
  attachment_name: string | null;
  is_read: boolean;
  created_at: string;
}

interface RoomDetail {
  id: number;
  student_name: string;
  student_id: number;
  is_active: boolean;
}

export default function StudentChatDetailPage() {
  const params = useParams();
  const roomId = params.roomId as string;
  const queryClient = useQueryClient();
  const toast = useToast();
  const [message, setMessage] = useState('');
  const messagesEndRef = useRef<HTMLDivElement>(null);
  const fileInputRef = useRef<HTMLInputElement>(null);

  const { data: room } = useQuery({
    queryKey: ['staff', 'student-chat-room', roomId],
    queryFn: async () => {
      const res = await api.get<{ data: RoomDetail }>(`/api/staff/student-chats/${roomId}`);
      return res.data.data;
    },
  });

  const { data: messages = [], isLoading } = useQuery({
    queryKey: ['staff', 'student-chat-messages', roomId],
    queryFn: async () => {
      const res = await api.get<{ data: Message[] }>(`/api/staff/student-chats/${roomId}/messages`);
      return res.data.data;
    },
    refetchInterval: 5000,
  });

  const sendMutation = useMutation({
    mutationFn: async (data: FormData) => {
      return api.post(`/api/staff/student-chats/${roomId}/messages`, data);
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['staff', 'student-chat-messages', roomId] });
      setMessage('');
    },
    onError: () => toast.error('送信に失敗しました'),
  });

  useEffect(() => {
    messagesEndRef.current?.scrollIntoView({ behavior: 'smooth' });
  }, [messages]);

  const handleSend = (e: React.FormEvent) => {
    e.preventDefault();
    if (!message.trim()) return;
    const formData = new FormData();
    formData.append('message', message);
    sendMutation.mutate(formData);
  };

  const handleFileUpload = (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0];
    if (!file) return;
    const formData = new FormData();
    formData.append('message', message || file.name);
    formData.append('attachment', file);
    sendMutation.mutate(formData);
    if (fileInputRef.current) fileInputRef.current.value = '';
  };

  return (
    <div className="flex h-[calc(100vh-8rem)] flex-col">
      {/* Header */}
      <div className="flex items-center gap-3 border-b border-[var(--neutral-stroke-2)] pb-4">
        <Link href="/staff/student-chats">
          <Button variant="ghost" size="sm">
            <ArrowLeft className="h-4 w-4" />
          </Button>
        </Link>
        <div className="flex h-10 w-10 items-center justify-center rounded-full bg-[var(--brand-160)]">
          <span className="text-sm font-bold text-[var(--brand-80)]">
            {room?.student_name?.charAt(0) || '?'}
          </span>
        </div>
        <div>
          <h1 className="text-lg font-bold text-[var(--neutral-foreground-1)]">{room?.student_name || '読み込み中...'}</h1>
          <p className="text-xs text-[var(--neutral-foreground-3)]">生徒チャット</p>
        </div>
        {room && !room.is_active && <Badge variant="danger">無効</Badge>}
      </div>

      {/* Messages */}
      <div className="flex-1 overflow-y-auto py-4 space-y-4">
        {isLoading ? (
          <SkeletonList items={5} />
        ) : messages.length === 0 ? (
          <div className="flex h-full items-center justify-center">
            <p className="text-sm text-[var(--neutral-foreground-3)]">メッセージはありません</p>
          </div>
        ) : (
          messages.map((msg) => {
            const isStaff = msg.sender_type === 'staff';
            return (
              <div key={msg.id} className={`flex ${isStaff ? 'justify-end' : 'justify-start'}`}>
                <div className={`max-w-[75%] ${isStaff ? 'order-last' : ''}`}>
                  <div className={`flex items-center gap-1 mb-1 ${isStaff ? 'justify-end' : ''}`}>
                    <span className="text-xs font-medium text-[var(--neutral-foreground-2)]">
                      {msg.sender_name}
                    </span>
                    {isStaff && <Badge variant="primary" className="text-[10px]">スタッフ</Badge>}
                  </div>
                  <div
                    className={`rounded-2xl px-4 py-2 ${
                      isStaff
                        ? 'bg-[var(--brand-80)] text-white'
                        : 'bg-[var(--neutral-background-3)] text-[var(--neutral-foreground-1)]'
                    }`}
                  >
                    <p className="text-sm whitespace-pre-wrap">{msg.message}</p>
                    {msg.attachment_url && (
                      <a
                        href={msg.attachment_url}
                        target="_blank"
                        rel="noopener noreferrer"
                        className={`mt-1 flex items-center gap-1 text-xs underline ${isStaff ? 'text-[var(--brand-160)]' : 'text-[var(--brand-80)]'}`}
                      >
                        <Paperclip className="h-3 w-3" />
                        {msg.attachment_name || '添付ファイル'}
                      </a>
                    )}
                  </div>
                  <div className={`flex items-center gap-1 mt-1 ${isStaff ? 'justify-end' : ''}`}>
                    <span className="text-[10px] text-[var(--neutral-foreground-4)]">
                      {format(new Date(msg.created_at), 'M/d HH:mm', { locale: ja })}
                    </span>
                    {isStaff && msg.is_read && (
                      <span className="text-[10px] text-[var(--brand-80)]">既読</span>
                    )}
                  </div>
                </div>
              </div>
            );
          })
        )}
        <div ref={messagesEndRef} />
      </div>

      {/* Input */}
      <form onSubmit={handleSend} className="flex items-center gap-2 border-t border-[var(--neutral-stroke-2)] pt-4">
        <input
          type="file"
          ref={fileInputRef}
          onChange={handleFileUpload}
          className="hidden"
          accept="image/*,.pdf,.doc,.docx"
        />
        <Button
          type="button"
          variant="ghost"
          size="sm"
          onClick={() => fileInputRef.current?.click()}
        >
          <Paperclip className="h-5 w-5" />
        </Button>
        <input
          type="text"
          value={message}
          onChange={(e) => setMessage(e.target.value)}
          placeholder="メッセージを入力..."
          className="flex-1 rounded-full border border-[var(--neutral-stroke-2)] px-4 py-2 text-sm focus:border-[var(--brand-80)] focus:outline-none focus:ring-2 focus:ring-[var(--brand-80)]/20"
        />
        <Button
          type="submit"
          size="sm"
          disabled={!message.trim()}
          isLoading={sendMutation.isPending}
          className="rounded-full"
        >
          <Send className="h-4 w-4" />
        </Button>
      </form>
    </div>
  );
}
