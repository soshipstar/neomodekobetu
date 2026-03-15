'use client';

import { useEffect, useCallback } from 'react';
import { useChatStore } from '@/stores/chatStore';
import { useAuthStore } from '@/stores/authStore';
import { getEcho } from '@/lib/echo';
import type { ChatMessage } from '@/types/chat';

/**
 * Chat hook with WebSocket integration for real-time messaging
 */
export function useChat(roomId?: number) {
  const {
    rooms,
    activeRoom,
    messages,
    unreadCounts,
    isLoadingRooms,
    isLoadingMessages,
    isSending,
    fetchRooms,
    setActiveRoom,
    fetchMessages,
    sendMessage,
    markAsRead,
    addMessage,
    updateUnreadCount,
  } = useChatStore();

  const { user } = useAuthStore();

  // Subscribe to chat room channel via WebSocket
  useEffect(() => {
    if (!roomId || !user) return;

    const echo = getEcho();
    const channel = echo.private(`chat.room.${roomId}`);

    channel.listen('.message.sent', (data: { message: ChatMessage }) => {
      addMessage(data.message);
      // If message is from someone else, it counts as unread
      if (data.message.sender_id !== user.id) {
        // Auto mark as read since we're viewing
        markAsRead(roomId);
      }
    });

    return () => {
      echo.leave(`chat.room.${roomId}`);
    };
  }, [roomId, user, addMessage, markAsRead]);

  // Subscribe to user notifications channel for unread counts
  useEffect(() => {
    if (!user) return;

    const echo = getEcho();
    const channel = echo.private(`user.${user.id}`);

    channel.listen('.chat.unread', (data: { room_id: number; count: number }) => {
      updateUnreadCount(data.room_id, data.count);
    });

    return () => {
      echo.leave(`user.${user.id}`);
    };
  }, [user, updateUnreadCount]);

  const handleSendMessage = useCallback(
    async (message: string, attachment?: File) => {
      if (!roomId) return;
      await sendMessage(roomId, message, attachment);
    },
    [roomId, sendMessage]
  );

  const totalUnread = Object.values(unreadCounts).reduce((sum, count) => sum + count, 0);

  return {
    rooms,
    activeRoom,
    messages,
    unreadCounts,
    totalUnread,
    isLoadingRooms,
    isLoadingMessages,
    isSending,
    fetchRooms,
    setActiveRoom,
    fetchMessages,
    sendMessage: handleSendMessage,
    markAsRead,
  };
}
