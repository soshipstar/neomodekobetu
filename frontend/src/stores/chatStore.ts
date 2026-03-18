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
  sendMessage: (roomId: number, message: string, attachment?: File) => Promise<void>;
  markAsRead: (roomId: number) => Promise<void>;
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
    set({ activeRoom: room, messages: [] });
  },

  fetchMessages: async (roomId: number) => {
    set({ isLoadingMessages: true });
    try {
      const { apiPrefix } = get();
      const response = await api.get<{ data: ChatMessage[] }>(`${apiPrefix}/chat/rooms/${roomId}/messages`);
      set({ messages: response.data.data, isLoadingMessages: false });
    } catch {
      set({ isLoadingMessages: false });
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

  addMessage: (message: ChatMessage) => {
    const { activeRoom } = get();
    if (activeRoom && message.room_id === activeRoom.id) {
      set((state) => ({
        messages: [...state.messages, message],
      }));
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
