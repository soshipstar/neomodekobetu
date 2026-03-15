'use client';

import { cn, formatRelativeTime, truncate } from '@/lib/utils';
import { Badge } from '@/components/ui/Badge';
import { Pin } from 'lucide-react';
import type { ChatRoom } from '@/types/chat';

interface ChatRoomListProps {
  rooms: ChatRoom[];
  unreadCounts: Record<number, number>;
  onSelectRoom: (room: ChatRoom) => void;
  activeRoomId?: number;
}

export function ChatRoomList({ rooms, unreadCounts, onSelectRoom, activeRoomId }: ChatRoomListProps) {
  return (
    <div className="space-y-2">
      {rooms.map((room) => {
        const unread = unreadCounts[room.id] || 0;
        const isActive = activeRoomId === room.id;

        return (
          <button
            key={room.id}
            onClick={() => onSelectRoom(room)}
            className={cn(
              'flex w-full items-center gap-3 rounded-xl border p-4 text-left transition-all',
              isActive
                ? 'border-blue-200 bg-blue-50'
                : 'border-gray-200 bg-white hover:border-gray-300 hover:shadow-sm'
            )}
          >
            {/* Avatar */}
            <div className="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-blue-100 text-sm font-semibold text-blue-700">
              {room.student?.student_name?.charAt(0) || '?'}
            </div>

            {/* Content */}
            <div className="flex-1 min-w-0">
              <div className="flex items-center gap-2">
                <p className="text-sm font-semibold text-gray-900 truncate">
                  {room.student?.student_name || 'チャットルーム'}
                </p>
                {room.is_pinned && <Pin className="h-3 w-3 text-gray-400" />}
              </div>
              {room.guardian && (
                <p className="text-xs text-gray-500 truncate">
                  保護者: {room.guardian.full_name}
                </p>
              )}
              {room.last_message && (
                <p className="mt-1 text-xs text-gray-400 truncate">
                  {truncate(typeof room.last_message === 'string' ? room.last_message : room.last_message.message, 40)}
                </p>
              )}
            </div>

            {/* Right side */}
            <div className="flex flex-col items-end gap-1 shrink-0">
              {room.last_message_at && (
                <span className="text-[10px] text-gray-400">
                  {formatRelativeTime(room.last_message_at)}
                </span>
              )}
              {unread > 0 && (
                <Badge variant="danger">
                  {unread > 99 ? '99+' : unread}
                </Badge>
              )}
            </div>
          </button>
        );
      })}
    </div>
  );
}

export default ChatRoomList;
