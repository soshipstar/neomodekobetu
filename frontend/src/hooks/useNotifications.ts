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
      // Backend broadcasts a flat payload (see NotificationCreated::broadcastWith):
      // { id, type, title, body, data, created_at }
      // Older code expected { notification: ... }; tolerate both shapes and
      // drop anything without an id so map()/is_read access cannot throw.
      const raw = data as Record<string, unknown> | null | undefined;
      if (!raw) return;
      const src = (raw as { notification?: Record<string, unknown> }).notification
        ?? (raw as Record<string, unknown>);
      if (!src || typeof (src as { id?: unknown }).id !== 'number') return;
      const payloadData = (src as { data?: unknown }).data;
      const link = typeof payloadData === 'object' && payloadData !== null
        && typeof (payloadData as { url?: unknown }).url === 'string'
        ? (payloadData as { url: string }).url
        : (typeof (src as { link?: unknown }).link === 'string'
          ? (src as { link: string }).link
          : null);
      const notification: Notification = {
        id: (src as { id: number }).id,
        type: String((src as { type?: unknown }).type ?? ''),
        title: String((src as { title?: unknown }).title ?? ''),
        message: String(
          (src as { message?: unknown }).message
          ?? (src as { body?: unknown }).body
          ?? '',
        ),
        link,
        is_read: Boolean((src as { is_read?: unknown }).is_read ?? false),
        created_at: String(
          (src as { created_at?: unknown }).created_at ?? new Date().toISOString(),
        ),
      };
      addNotification(notification);
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
