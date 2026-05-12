'use client';

import { useQuery } from '@tanstack/react-query';
import api from '@/lib/api';
import { StorageUsageBar } from '@/components/photos/StorageUsageBar';

/**
 * チャット添付ファイルの教室別容量を表示する共通バー。
 *
 * 写真ライブラリ用 StorageUsageBar をラップして「チャット添付」用に取得 URL と
 * デフォルトラベルだけ差し替えたもの。FE 各画面 (Staff / Guardian / Student) で
 * 同じ表示にするため、role 別の API パスを切り替える props を受け取る。
 *
 * 200MB を 1 教室で共有する設計のため、教室の運用人数によっては早めに警告色
 * (80% / 95%) に変わる。詳細は backend/app/Services/ChatAttachmentStorage.php を参照。
 */
export interface ChatStorageBarProps {
  /** どの role 用 API を叩くか。`guardian` / `student` は classroom が自動解決される。 */
  role: 'staff' | 'guardian' | 'student';
  /** staff の場合に切り替え対象教室を指定する。省略時は自分の所属教室。 */
  classroomId?: number;
  /** ラベル (省略時: 「チャット添付容量」) */
  label?: string;
  /** 小さく詰めて表示 */
  compact?: boolean;
  className?: string;
  /** 100% 到達時のみ表示 (常時バー出したくない画面用) */
  fullOnly?: boolean;
}

interface StorageUsageResponse {
  classroom_id: number;
  used_bytes: number;
  limit_bytes: number;
  used_mb: number;
  limit_mb: number;
  available_bytes: number;
  is_full: boolean;
}

export function ChatStorageBar({
  role,
  classroomId,
  label = 'チャット添付容量',
  compact = false,
  className,
  fullOnly = false,
}: ChatStorageBarProps) {
  const url =
    role === 'staff'
      ? `/api/staff/chat/storage-usage${classroomId ? `?classroom_id=${classroomId}` : ''}`
      : role === 'guardian'
        ? '/api/guardian/chat/storage-usage'
        : '/api/student/chat/storage-usage';

  const { data } = useQuery({
    queryKey: ['chat', 'storage-usage', role, classroomId ?? null],
    queryFn: async () => {
      const res = await api.get<{ data: StorageUsageResponse }>(url);
      return res.data.data;
    },
    // 添付ファイル送信後すぐにバーを更新したいので staleTime は短め
    staleTime: 30_000,
    refetchOnWindowFocus: false,
  });

  if (!data) return null;
  if (fullOnly && !data.is_full && data.used_bytes / data.limit_bytes < 0.95) {
    return null;
  }

  return (
    <StorageUsageBar
      usedBytes={data.used_bytes}
      limitBytes={data.limit_bytes}
      label={label}
      compact={compact}
      className={className}
    />
  );
}

export default ChatStorageBar;
