'use client';

import { useEffect } from 'react';
import { useChat } from '@/hooks/useChat';
import { useAuthStore } from '@/stores/authStore';
import { ChatMessageList } from '@/components/chat/ChatMessageList';
import { ChatInput } from '@/components/chat/ChatInput';
import { SkeletonList } from '@/components/ui/Skeleton';

export default function StudentChatPage() {
  const { user } = useAuthStore();
  const {
    rooms,
    messages,
    isLoadingRooms,
    isLoadingMessages,
    isSending,
    fetchRooms,
    setActiveRoom,
    fetchMessages,
    sendMessage,
    markAsRead,
  } = useChat();

  useEffect(() => {
    fetchRooms();
  }, [fetchRooms]);

  // Auto-select the first (and likely only) room for students
  useEffect(() => {
    if (rooms.length > 0 && !isLoadingRooms) {
      const room = rooms[0];
      setActiveRoom(room);
      fetchMessages(room.id);
      markAsRead(room.id);
    }
  }, [rooms, isLoadingRooms, setActiveRoom, fetchMessages, markAsRead]);

  useEffect(() => {
    return () => { setActiveRoom(null); };
  }, [setActiveRoom]);

  if (isLoadingRooms || isLoadingMessages) {
    return (
      <div className="space-y-4">
        <h1 className="text-2xl font-bold text-gray-900">チャット</h1>
        <SkeletonList items={5} />
      </div>
    );
  }

  if (rooms.length === 0) {
    return (
      <div className="space-y-4">
        <h1 className="text-2xl font-bold text-gray-900">チャット</h1>
        <div className="py-12 text-center text-sm text-gray-500">チャットルームがありません</div>
      </div>
    );
  }

  return (
    <div className="flex h-[calc(100vh-8rem)] flex-col lg:h-[calc(100vh-5rem)]">
      <div className="border-b border-gray-200 bg-white px-4 py-3">
        <h2 className="text-sm font-semibold text-gray-900">チャット</h2>
      </div>
      <div className="flex-1 overflow-y-auto bg-gray-50">
        <ChatMessageList messages={messages} currentUserId={user?.id || 0} />
      </div>
      <ChatInput onSend={sendMessage} isSending={isSending} />
    </div>
  );
}
