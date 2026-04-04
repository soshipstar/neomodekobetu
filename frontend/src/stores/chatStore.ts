import { create } from 'zustand';
import api from '@/lib/api';
import type { ChatRoom, ChatMessage } from '@/types/chat';

interface ChatState {
  rooms: ChatRoom[];
  activeRoom: ChatRoom | null;
  messages: ChatMessage[];
  unreadCounts: Record<number, number>;
  isLoadingRooms: boolean;
  isLoadingMessages: boolean;
  isSending: boolean;
  apiPrefix: string; // '/api/staff' or '/api/guardian'

  setApiPrefix: (prefix: string) => void;
  fetchRooms: () => Promise<void>;
  setActiveRoom: (room: ChatRoom | null) => void;
  fetchMessages: (roomId: number) => Promise<void>;
  fetchOlderMessages: (roomId: number) => Promise<boolean>;
  hasMoreMessages: boolean;
  sendMessage: (roomId: number, message: string, attachment?: File) => Promise<void>;
  markAsRead: (roomId: number) => Promise<void>;
  deleteMessage: (roomId: number, messageId: number) => Promise<void>;
  toggleArchive: (roomId: number, messageId: number) => Promise<void>;
  fetchArchivedMessages: (roomId: number) => Promise<ChatMessage[]>;
  addMessage: (message: ChatMessage) => void;
  updateUnreadCount: (roomId: number, count: number) => void;
}

export const useChatStore = create<ChatState>((set, get) => ({
  rooms: [],
  activeRoom: null,
  messages: [],
  unreadCounts: {},
  isLoadingRooms: false,
  isLoadingMessages: false,
  isSending: false,
  apiPrefix: '/api/staff', // default for backward compatibility

  setApiPrefix: (prefix: string) => {
    set({ apiPrefix: prefix });
  },

  fetchRooms: async () => {
    set({ isLoadingRooms: true });
    try {
      const { apiPrefix } = get();
      const response = await api.get(`${apiPrefix}/chat/rooms`);
      const payload = response.data?.data ?? response.data;
      const rooms: ChatRoom[] = Array.isArray(payload) ? payload : [];
      const unreadCounts: Record<number, number> = {};
      rooms.forEach((room) => {
        unreadCounts[room.id] = room.unread_count || 0;
      });
      set({ rooms, unreadCounts, isLoadingRooms: false });
    } catch {
      set({ isLoadingRooms: false });
    }
  },

  setActiveRoom: (room: ChatRoom | null) => {
    const current = get().activeRoom;
    if (current?.id === room?.id) {
      // Same room — update room data without clearing messages
      set({ activeRoom: room });
    } else {
      // Different room — clear messages for fresh load
      set({ activeRoom: room, messages: [] });
    }
  },

  hasMoreMessages: false,

  fetchMessages: async (roomId: number) => {
    set({ isLoadingMessages: true });
    try {
      const { apiPrefix } = get();
      const response = await api.get<{ data: ChatMessage[]; has_more?: boolean }>(`${apiPrefix}/chat/rooms/${roomId}/messages`, { params: { limit: 100 } });
      set({ messages: response.data.data, isLoadingMessages: false, hasMoreMessages: response.data.has_more ?? false });
    } catch {
      set({ isLoadingMessages: false });
    }
  },

  fetchOlderMessages: async (roomId: number) => {
    const { messages, apiPrefix } = get();
    if (messages.length === 0) return false;
    const oldestId = messages[0].id;
    try {
      const response = await api.get<{ data: ChatMessage[]; has_more?: boolean }>(`${apiPrefix}/chat/rooms/${roomId}/messages`, { params: { before_id: oldestId, limit: 100 } });
      const older = response.data.data;
      if (older.length > 0) {
        set({ messages: [...older, ...messages], hasMoreMessages: response.data.has_more ?? false });
        return true;
      }
      set({ hasMoreMessages: false });
      return false;
    } catch {
      return false;
    }
  },

  sendMessage: async (roomId: number, message: string, attachment?: File) => {
    set({ isSending: true });
    try {
      const { apiPrefix } = get();
      const formData = new FormData();
      formData.append('message', message);
      formData.append('room_id', String(roomId));
      if (attachment) {
        formData.append('attachment', attachment);
      }
      const response = await api.post<{ data: ChatMessage }>(`${apiPrefix}/chat/rooms/${roomId}/messages`, formData);
      const newMessage = response.data.data;
      set((state) => ({
        messages: [...state.messages, newMessage],
        isSending: false,
      }));
    } catch {
      set({ isSending: false });
    }
  },

  markAsRead: async (roomId: number) => {
    try {
      const { apiPrefix } = get();
      await api.post(`${apiPrefix}/chat/rooms/${roomId}/read`);
      set((state) => ({
        unreadCounts: { ...state.unreadCounts, [roomId]: 0 },
      }));
    } catch {
      // Silently fail
    }
  },

  deleteMessage: async (roomId: number, messageId: number) => {
    try {
      const { apiPrefix } = get();
      await api.delete(`${apiPrefix}/chat/rooms/${roomId}/messages/${messageId}`);
      set((state) => ({
        messages: state.messages.map((msg) =>
          msg.id === messageId
            ? { ...msg, is_deleted: true, message: '' }
            : msg
        ),
      }));
    } catch {
      throw new Error('メッセージの削除に失敗しました');
    }
  },

  toggleArchive: async (roomId: number, messageId: number) => {
    try {
      const { apiPrefix } = get();
      const response = await api.post<{ is_archived: boolean }>(`${apiPrefix}/chat/rooms/${roomId}/messages/${messageId}/archive`);
      const isArchived = response.data.is_archived;
      set((state) => ({
        messages: state.messages.map((msg) =>
          msg.id === messageId ? { ...msg, is_archived: isArchived } : msg
        ),
      }));
    } catch {
      throw new Error('アーカイブの切り替えに失敗しました');
    }
  },

  fetchArchivedMessages: async (roomId: number) => {
    const { apiPrefix } = get();
    const response = await api.get<{ data: ChatMessage[] }>(`${apiPrefix}/chat/rooms/${roomId}/archived`);
    return response.data.data;
  },

  addMessage: (message: ChatMessage) => {
    const { activeRoom, messages } = get();
    if (activeRoom && message.room_id === activeRoom.id) {
      // Avoid duplicate: sendMessage already added this message from API response
      const isDuplicate = messages.some((m) => m.id === message.id);
      if (!isDuplicate) {
        set((state) => ({
          messages: [...state.messages, message],
        }));
      }
    }
    // Update room list with last message
    set((state) => ({
      rooms: state.rooms.map((room) =>
        room.id === message.room_id
          ? { ...room, last_message: message, last_message_at: message.created_at }
          : room
      ),
    }));
  },

  updateUnreadCount: (roomId: number, count: number) => {
    set((state) => ({
      unreadCounts: { ...state.unreadCounts, [roomId]: count },
    }));
  },
}));
