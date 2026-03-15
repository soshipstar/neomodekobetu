import { create } from 'zustand';
import api from '@/lib/api';

export interface Notification {
  id: number;
  type: string;
  title: string;
  message: string;
  link: string | null;
  is_read: boolean;
  created_at: string;
}

interface NotificationState {
  notifications: Notification[];
  unreadCount: number;
  isLoading: boolean;

  fetchNotifications: () => Promise<void>;
  markAsRead: (id: number) => Promise<void>;
  markAllAsRead: () => Promise<void>;
  addNotification: (notification: Notification) => void;
}

export const useNotificationStore = create<NotificationState>((set) => ({
  notifications: [],
  unreadCount: 0,
  isLoading: false,

  fetchNotifications: async () => {
    set({ isLoading: true });
    try {
      const response = await api.get('/api/notifications');
      // API returns: { success, data: { notifications: { data: [...] }, unread_count } }
      const payload = response.data?.data ?? response.data;
      const notificationsData = payload?.notifications?.data ?? payload?.notifications ?? payload?.data ?? payload;
      const notifications = Array.isArray(notificationsData) ? notificationsData : [];
      const unreadCount = payload?.unread_count ?? notifications.filter((n: Notification) => !n.is_read).length;
      set({
        notifications,
        unreadCount,
        isLoading: false,
      });
    } catch {
      set({ notifications: [], isLoading: false });
    }
  },

  markAsRead: async (id: number) => {
    try {
      await api.post(`/api/notifications/${id}/read`);
      set((state) => ({
        notifications: state.notifications.map((n) =>
          n.id === id ? { ...n, is_read: true } : n
        ),
        unreadCount: Math.max(0, state.unreadCount - 1),
      }));
    } catch {
      // Silently fail
    }
  },

  markAllAsRead: async () => {
    try {
      await api.post('/api/notifications/read-all');
      set((state) => ({
        notifications: state.notifications.map((n) => ({ ...n, is_read: true })),
        unreadCount: 0,
      }));
    } catch {
      // Silently fail
    }
  },

  addNotification: (notification: Notification) => {
    set((state) => ({
      notifications: [notification, ...state.notifications],
      unreadCount: state.unreadCount + 1,
    }));
  },
}));
