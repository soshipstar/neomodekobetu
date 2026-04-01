'use client';

import { useEffect, useCallback, useState } from 'react';
import { useParams, useRouter } from 'next/navigation';
import { useChat } from '@/hooks/useChat';
import { useChatStore } from '@/stores/chatStore';
import { useAuthStore } from '@/stores/authStore';
import { ChatMessageList } from '@/components/chat/ChatMessageList';
import { ChatInput } from '@/components/chat/ChatInput';
import { SkeletonList } from '@/components/ui/Skeleton';
import Link from 'next/link';
import { Button } from '@/components/ui/Button';
import { MaterialIcon } from '@/components/ui/MaterialIcon';
import type { ChatMessage } from '@/types/chat';

export default function StaffChatRoomPage() {
  const params = useParams();
  const router = useRouter();
  const roomId = Number(params.roomId);
  const { user } = useAuthStore();
  const [loadingOlder, setLoadingOlder] = useState(false);
  const [showArchived, setShowArchived] = useState(false);
  const [archivedMessages, setArchivedMessages] = useState<ChatMessage[]>([]);
  const [loadingArchived, setLoadingArchived] = useState(false);

  const {
    activeRoom,
    messages,
    isLoadingMessages,
    isSending,
    fetchRooms,
    setActiveRoom,
    fetchMessages,
    fetchOlderMessages,
    hasMoreMessages,
    sendMessage,
    markAsRead,
    rooms,
  } = useChat(roomId);

  useEffect(() => {
    if (rooms.length === 0) {
      fetchRooms();
    }
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

  // Clean up on unmount
  useEffect(() => {
    return () => {
      setActiveRoom(null);
    };
  }, [setActiveRoom]);

  const handleSend = async (message: string, attachment?: File) => {
    await sendMessage(message, attachment);
  };

  const handleLoadOlder = useCallback(async () => {
    setLoadingOlder(true);
    await fetchOlderMessages(roomId);
    setLoadingOlder(false);
  }, [roomId, fetchOlderMessages]);

  const handleToggleArchived = useCallback(async () => {
    if (!showArchived) {
      setLoadingArchived(true);
      try {
        const msgs = await useChatStore.getState().fetchArchivedMessages(roomId);
        setArchivedMessages(msgs);
      } catch {
        // silently fail
      } finally {
        setLoadingArchived(false);
      }
    }
    setShowArchived((prev) => !prev);
  }, [showArchived, roomId]);

  return (
    <div className="flex h-[calc(100vh-8rem)] flex-col lg:h-[calc(100vh-5rem)]">
      {/* Chat header */}
      <div className="flex items-center gap-3 border-b border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-1)] px-4 py-3">
        <Link
          href="/staff/chat"
          className="rounded-lg p-1 text-[var(--neutral-foreground-4)] hover:text-[var(--neutral-foreground-2)] lg:hidden"
        >
          <MaterialIcon name="arrow_back" size={20} />
        </Link>
        <div className="flex-1">
          <h2 className="text-sm font-semibold text-[var(--neutral-foreground-1)]">
            {activeRoom?.student?.student_name || 'チャット'}
          </h2>
          <p className="text-xs text-[var(--neutral-foreground-3)]">
            {activeRoom?.guardian?.full_name && `保護者: ${activeRoom.guardian.full_name}`}
          </p>
        </div>
        <button
          onClick={handleToggleArchived}
          className={`flex items-center gap-1 rounded-lg px-2.5 py-1.5 text-xs font-medium transition-colors ${
            showArchived
              ? 'bg-[var(--brand-80)] text-white'
              : 'text-[var(--neutral-foreground-3)] hover:bg-[var(--neutral-background-3)]'
          }`}
        >
          <MaterialIcon name="bookmark" size={14} />
          アーカイブ
        </button>
        {activeRoom && (
          <Link
            href={`/staff/meetings?action=create&student_id=${activeRoom.student_id}&guardian_id=${activeRoom.guardian_id}`}
          >
            <Button variant="outline" size="sm" leftIcon={<MaterialIcon name="event" size={16} />}>
              面談予約
            </Button>
          </Link>
        )}
      </div>

      {/* Messages */}
      <div className="flex-1 overflow-y-auto bg-[var(--neutral-background-2)]">
        {showArchived ? (
          loadingArchived ? (
            <div className="p-4"><SkeletonList items={5} /></div>
          ) : archivedMessages.length === 0 ? (
            <div className="flex h-full items-center justify-center">
              <p className="text-sm text-[var(--neutral-foreground-4)]">アーカイブされたメッセージはありません</p>
            </div>
          ) : (
            <ChatMessageList messages={archivedMessages} currentUserId={user?.id || 0} />
          )
        ) : isLoadingMessages ? (
          <div className="p-4">
            <SkeletonList items={5} />
          </div>
        ) : (
          <>
            {hasMoreMessages && (
              <div className="flex justify-center py-3">
                <button
                  onClick={handleLoadOlder}
                  disabled={loadingOlder}
                  className="flex items-center gap-1 rounded-full bg-white px-4 py-1.5 text-xs text-[var(--neutral-foreground-3)] shadow-sm hover:bg-[var(--neutral-background-3)] transition-colors disabled:opacity-50"
                >
                  <MaterialIcon name="expand_less" size={14} />
                  {loadingOlder ? '読み込み中...' : '過去のメッセージを読み込む'}
                </button>
              </div>
            )}
            <ChatMessageList
              messages={messages}
              currentUserId={user?.id || 0}
            />
          </>
        )}
      </div>

      {/* Input */}
      <ChatInput
        onSend={handleSend}
        isSending={isSending}
        disabled={!activeRoom}
      />
    </div>
  );
}
