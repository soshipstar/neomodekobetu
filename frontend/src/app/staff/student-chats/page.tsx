'use client';

import { useState } from 'react';
import { useRouter } from 'next/navigation';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import api from '@/lib/api';
import { Card, CardHeader, CardTitle } from '@/components/ui/Card';
import { Button } from '@/components/ui/Button';
import { Input } from '@/components/ui/Input';
import { Badge } from '@/components/ui/Badge';
import { Modal } from '@/components/ui/Modal';
import { SkeletonList } from '@/components/ui/Skeleton';
import { useToast } from '@/components/ui/Toast';
import { useDebounce } from '@/hooks/useDebounce';
import { Search, MessageCircle, Send, Users } from 'lucide-react';
import { format } from 'date-fns';
import { ja } from 'date-fns/locale';

interface ChatRoom {
  id: number;
  student_id: number;
  student_name: string;
  last_message: string | null;
  last_message_at: string | null;
  unread_count: number;
  is_active: boolean;
}

export default function StudentChatsPage() {
  const router = useRouter();
  const queryClient = useQueryClient();
  const toast = useToast();
  const [search, setSearch] = useState('');
  const [broadcastModal, setBroadcastModal] = useState(false);
  const [broadcastMessage, setBroadcastMessage] = useState('');
  const debouncedSearch = useDebounce(search, 300);

  const { data: rooms = [], isLoading } = useQuery({
    queryKey: ['staff', 'student-chats', debouncedSearch],
    queryFn: async () => {
      const res = await api.get<{ data: ChatRoom[] }>('/api/staff/student-chats', {
        params: { search: debouncedSearch || undefined },
      });
      return res.data.data;
    },
  });

  const broadcastMutation = useMutation({
    mutationFn: async (message: string) => {
      return api.post('/api/staff/student-chats/broadcast', { message });
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['staff', 'student-chats'] });
      toast.success('一斉送信しました');
      setBroadcastModal(false);
      setBroadcastMessage('');
    },
    onError: () => toast.error('送信に失敗しました'),
  });

  const totalUnread = rooms.reduce((sum, r) => sum + r.unread_count, 0);

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold text-[var(--neutral-foreground-1)]">生徒チャット管理</h1>
          {totalUnread > 0 && (
            <p className="mt-1 text-sm text-[var(--status-danger-fg)]">未読メッセージ: {totalUnread}件</p>
          )}
        </div>
        <Button onClick={() => setBroadcastModal(true)} leftIcon={<Users className="h-4 w-4" />}>
          一斉送信
        </Button>
      </div>

      {/* Search */}
      <div className="relative">
        <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-[var(--neutral-foreground-4)]" />
        <Input
          placeholder="生徒名で検索..."
          value={search}
          onChange={(e) => setSearch(e.target.value)}
          className="pl-10"
        />
      </div>

      {/* Chat room list */}
      {isLoading ? (
        <SkeletonList items={6} />
      ) : rooms.length === 0 ? (
        <div className="py-12 text-center text-sm text-[var(--neutral-foreground-3)]">
          チャットルームがありません
        </div>
      ) : (
        <div className="space-y-2">
          {rooms.map((room) => (
            <Card
              key={room.id}
              className="cursor-pointer hover:shadow-[var(--shadow-8)] transition-shadow"
              onClick={() => router.push(`/staff/student-chats/${room.id}`)}
            >
              <div className="flex items-center gap-4">
                <div className="flex h-12 w-12 items-center justify-center rounded-full bg-[var(--brand-160)] shrink-0">
                  <MessageCircle className="h-6 w-6 text-[var(--brand-80)]" />
                </div>
                <div className="flex-1 min-w-0">
                  <div className="flex items-center gap-2">
                    <h3 className="font-medium text-[var(--neutral-foreground-1)]">{room.student_name}</h3>
                    {!room.is_active && <Badge variant="default">無効</Badge>}
                  </div>
                  <p className="mt-0.5 truncate text-sm text-[var(--neutral-foreground-3)]">
                    {room.last_message || 'メッセージはありません'}
                  </p>
                </div>
                <div className="text-right shrink-0">
                  {room.last_message_at && (
                    <p className="text-xs text-[var(--neutral-foreground-4)]">
                      {format(new Date(room.last_message_at), 'M/d HH:mm', { locale: ja })}
                    </p>
                  )}
                  {room.unread_count > 0 && (
                    <span className="mt-1 inline-flex h-5 min-w-[20px] items-center justify-center rounded-full bg-[var(--status-danger-fg)] px-1.5 text-xs font-bold text-white">
                      {room.unread_count}
                    </span>
                  )}
                </div>
              </div>
            </Card>
          ))}
        </div>
      )}

      {/* Broadcast Modal */}
      <Modal isOpen={broadcastModal} onClose={() => setBroadcastModal(false)} title="全生徒に一斉送信" size="lg">
        <form
          onSubmit={(e) => { e.preventDefault(); broadcastMutation.mutate(broadcastMessage); }}
          className="space-y-4"
        >
          <div>
            <label className="mb-1 block text-sm font-medium text-[var(--neutral-foreground-2)]">メッセージ</label>
            <textarea
              value={broadcastMessage}
              onChange={(e) => setBroadcastMessage(e.target.value)}
              className="block w-full rounded-lg border border-[var(--neutral-stroke-2)] px-3 py-2 text-sm"
              rows={5}
              placeholder="全生徒に送信するメッセージを入力..."
              required
            />
          </div>
          <p className="text-sm text-[var(--neutral-foreground-3)]">
            この操作で {rooms.filter((r) => r.is_active).length} 名の生徒全員にメッセージが送信されます。
          </p>
          <div className="flex justify-end gap-2">
            <Button variant="secondary" type="button" onClick={() => setBroadcastModal(false)}>キャンセル</Button>
            <Button type="submit" isLoading={broadcastMutation.isPending} leftIcon={<Send className="h-4 w-4" />}>
              送信
            </Button>
          </div>
        </form>
      </Modal>
    </div>
  );
}
