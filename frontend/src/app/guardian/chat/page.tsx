'use client';

import { useEffect, useState } from 'react';
import { useRouter } from 'next/navigation';
import { useChat } from '@/hooks/useChat';
import { useDebounce } from '@/hooks/useDebounce';
import { ChatRoomList } from '@/components/chat/ChatRoomList';
import { Input } from '@/components/ui/Input';
import { SkeletonList } from '@/components/ui/Skeleton';
import { Search } from 'lucide-react';

export default function GuardianChatListPage() {
  const router = useRouter();
  const { rooms, isLoadingRooms, fetchRooms, unreadCounts } = useChat();
  const [search, setSearch] = useState('');
  const debouncedSearch = useDebounce(search, 300);

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
    <div className="space-y-4">
      <h1 className="text-2xl font-bold text-gray-900">チャット</h1>

      <div className="relative">
        <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
        <Input
          placeholder="お子様の名前で検索..."
          value={search}
          onChange={(e) => setSearch(e.target.value)}
          className="pl-10"
        />
      </div>

      {isLoadingRooms ? (
        <SkeletonList items={4} />
      ) : filteredRooms.length === 0 ? (
        <div className="py-12 text-center text-sm text-gray-500">
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
