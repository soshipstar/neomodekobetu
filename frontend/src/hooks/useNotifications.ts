'use client';

import { useEffect } from 'react';
import { useNotificationStore, type Notification } from '@/stores/notificationStore';
import { useAuthStore } from '@/stores/authStore';
import { useWebSocket } from './useWebSocket';

/**
 * Notification hook with real-time updates via WebSocket
 */
export function useNotifications() {
  const {
    notifications,
    unreadCount,
    isLoading,
    fetchNotifications,
    markAsRead,
    markAllAsRead,
    addNotification,
  } = useNotificationStore();

  const { user, isAuthenticated } = useAuthStore();

  // Fetch notifications on mount
  useEffect(() => {
    if (isAuthenticated) {
      fetchNotifications();
    }
  }, [isAuthenticated, fetchNotifications]);

  // Subscribe to real-time notification channel
  useWebSocket({
    channel: user ? `user.${user.id}` : '',
    event: '.notification.created',
    onMessage: (data) => {
      // Backend (NotificationCreated.broadcastWith) sends a flat shape:
      //   { id, type, title, body, data, created_at }
      // フロントの Notification 型 ({ id, type, title, message, link, is_read, created_at })
      // にマッピングする。ラップ構造を仮定して .notification を読むと undefined になり、
      // store に undefined が混入して NotificationBell が is_read で TypeError になる。
      const raw = data as Record<string, unknown> | null | undefined;
      if (!raw || typeof raw.id !== 'number') return;

      const linkData = raw.data as Record<string, unknown> | null | undefined;
      const link = (linkData && typeof linkData.url === 'string') ? linkData.url : null;

      addNotification({
        id: raw.id,
        type: typeof raw.type === 'string' ? raw.type : '',
        title: typeof raw.title === 'string' ? raw.title : '',
        message: typeof raw.body === 'string'
          ? raw.body
          : (typeof raw.message === 'string' ? raw.message : ''),
        link,
        is_read: false, // 新規通知は常に未読
        created_at: typeof raw.created_at === 'string' ? raw.created_at : new Date().toISOString(),
      });
    },
    enabled: !!user,
  });

  return {
    notifications,
    unreadCount,
    isLoading,
    fetchNotifications,
    markAsRead,
    markAllAsRead,
  };
}
