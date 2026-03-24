'use client';

import { useEffect } from 'react';
import { useParams } from 'next/navigation';
import { useChat } from '@/hooks/useChat';
import { useChatStore } from '@/stores/chatStore';
import { useAuthStore } from '@/stores/authStore';
import { ChatMessageList } from '@/components/chat/ChatMessageList';
import { ChatInput } from '@/components/chat/ChatInput';
import { SkeletonList } from '@/components/ui/Skeleton';
import { ArrowLeft } from 'lucide-react';
import Link from 'next/link';

export default function GuardianChatRoomPage() {
  const params = useParams();
  const roomId = Number(params.roomId);
  const { user } = useAuthStore();

  const {
    activeRoom,
    messages,
    isLoadingMessages,
    isSending,
    fetchRooms,
    setActiveRoom,
    fetchMessages,
    sendMessage,
    markAsRead,
    rooms,
  } = useChat(roomId);

  // Set guardian API prefix before fetching
  useEffect(() => {
    useChatStore.getState().setApiPrefix('/api/guardian');
  }, []);

  useEffect(() => {
    if (rooms.length === 0) fetchRooms();
  }, [rooms.length, fetchRooms]);

  useEffect(() => {
    if (roomId && rooms.length > 0) {
      const room = rooms.find((r) => r.id === roomId);
      if (room) {
        setActiveRoom(room);
        fetchMessages(roomId);
        markAsRead(roomId);
      }
    }
  }, [roomId, rooms, setActiveRoom, fetchMessages, markAsRead]);

  useEffect(() => {
    return () => { setActiveRoom(null); };
  }, [setActiveRoom]);

  return (
    <div className="flex h-[calc(100vh-6rem)] flex-col sm:h-[calc(100vh-7rem)] lg:h-[calc(100vh-5rem)]">
      <div className="flex items-center gap-2 border-b border-[var(--neutral-stroke-2)] bg-white px-3 py-2 sm:gap-3 sm:px-4 sm:py-3">
        <Link href="/guardian/chat" className="rounded-lg p-1 text-[var(--neutral-foreground-4)] hover:text-[var(--neutral-foreground-3)] lg:hidden">
          <ArrowLeft className="h-5 w-5" />
        </Link>
        <div className="flex-1 min-w-0">
          <h2 className="text-sm font-semibold text-[var(--neutral-foreground-1)] truncate">
            {activeRoom?.student?.student_name || 'チャット'}
          </h2>
        </div>
      </div>

      <div className="flex-1 overflow-y-auto bg-[var(--neutral-background-3)]">
        {isLoadingMessages ? (
          <div className="p-4"><SkeletonList items={5} /></div>
        ) : (
          <ChatMessageList messages={messages} currentUserId={user?.id || 0} />
        )}
      </div>

      <ChatInput onSend={sendMessage} isSending={isSending} disabled={!activeRoom} />
    </div>
  );
}
