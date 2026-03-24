'use client';

import { useEffect, useState } from 'react';
import { useRouter } from 'next/navigation';
import { useChat } from '@/hooks/useChat';
import { useChatStore } from '@/stores/chatStore';
import { useDebounce } from '@/hooks/useDebounce';
import { ChatRoomList } from '@/components/chat/ChatRoomList';
import { Input } from '@/components/ui/Input';
import { SkeletonList } from '@/components/ui/Skeleton';
import { MaterialIcon } from '@/components/ui/MaterialIcon';

export default function GuardianChatListPage() {
  const router = useRouter();
  const { rooms, isLoadingRooms, fetchRooms, unreadCounts } = useChat();
  const [search, setSearch] = useState('');
  const debouncedSearch = useDebounce(search, 300);

  // Set guardian API prefix before fetching
  useEffect(() => {
    useChatStore.getState().setApiPrefix('/api/guardian');
  }, []);

  useEffect(() => {
    fetchRooms();
  }, [fetchRooms]);

  const filteredRooms = rooms.filter((room) => {
    if (!debouncedSearch) return true;
    const query = debouncedSearch.toLowerCase();
    const studentName = room.student?.student_name?.toLowerCase() || '';
    return studentName.includes(query);
  });

  return (
    <div className="space-y-3 px-2 sm:space-y-4 sm:px-0">
      <h1 className="text-xl font-bold text-[var(--neutral-foreground-1)] sm:text-2xl">チャット</h1>

      <div className="relative">
        <MaterialIcon name="search" size={16} className="absolute left-3 top-1/2 -translate-y-1/2 text-[var(--neutral-foreground-4)]" />
        <Input
          placeholder="お子様の名前で検索..."
          value={search}
          onChange={(e) => setSearch(e.target.value)}
          className="h-9 pl-10 text-sm sm:h-10 sm:text-base"
        />
      </div>

      {isLoadingRooms ? (
        <SkeletonList items={4} />
      ) : filteredRooms.length === 0 ? (
        <div className="py-12 text-center text-sm text-[var(--neutral-foreground-3)]">
          チャットルームがありません
        </div>
      ) : (
        <ChatRoomList
          rooms={filteredRooms}
          unreadCounts={unreadCounts}
          onSelectRoom={(room) => router.push(`/guardian/chat/${room.id}`)}
        />
      )}
    </div>
  );
}
