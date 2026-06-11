'use client';

import { useQuery } from '@tanstack/react-query';
import api from '@/lib/api';
import { Modal } from '@/components/ui/Modal';
import { useChatStore } from '@/stores/chatStore';
import { GuardianMeetingDetail, type MeetingRequest } from './GuardianMeetingDetail';

/**
 * 保護者チャット用: 「面談予約を確認」ボタンで開く面談詳細モーダル(ページ遷移なし)。
 *
 * chatStore.meetingModalId を購読し、該当面談を1件取得してモーダルで表示・応答できる。
 * 保護者チャット画面に一度だけ配置する。
 */
export function GuardianMeetingModalHost() {
  const meetingId = useChatStore((s) => s.meetingModalId);
  const closeMeetingModal = useChatStore((s) => s.closeMeetingModal);

  const { data: meeting, isLoading } = useQuery({
    queryKey: ['guardian', 'meeting', meetingId],
    queryFn: async () => {
      const res = await api.get<{ data: MeetingRequest }>(`/api/guardian/meetings/${meetingId}`);
      return res.data.data;
    },
    enabled: meetingId !== null,
  });

  return (
    <Modal isOpen={meetingId !== null} onClose={closeMeetingModal} title="面談予約" size="lg">
      {isLoading || !meeting ? (
        <p className="py-8 text-center text-sm text-[var(--neutral-foreground-3)]">読み込み中...</p>
      ) : (
        <GuardianMeetingDetail meeting={meeting} onUpdated={closeMeetingModal} />
      )}
    </Modal>
  );
}
