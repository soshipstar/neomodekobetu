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
      const notification = data as { notification: Notification };
      addNotification(notification.notification);
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
