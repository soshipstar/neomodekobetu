'use client';

import { useEffect, useState } from 'react';
import { useAuthStore } from '@/stores/authStore';
import api from '@/lib/api';
import { useToast } from '@/components/ui/Toast';

interface ClassroomOption {
  id: number;
  classroom_name: string;
}

interface ClassroomSwitcherProps {
  /** 表示バリアント。
   *  - 'header': ヘッダ内コンパクト表示 (max-w-[160px] + text-xs)
   *  - 'sidebar': サイドバーフル幅表示 (block w-full + text-sm) */
  variant?: 'header' | 'sidebar';
  className?: string;
}

/**
 * 教室切り替えセレクタ。
 *
 * 1教室しか持っていないユーザーには何も表示しない。
 * 切替成功時は window.location.reload() で全クエリを再取得する。
 *
 * R4 / R4-bis: スマホでヘッダ内の select がメニューボタン等と被って押せない
 * 問題を解決するため、ヘッダではなくサイドバー (mobile drawer / desktop persistent)
 * 内にも配置できる共通コンポーネントとして切り出した。
 */
export function ClassroomSwitcher({ variant = 'header', className = '' }: ClassroomSwitcherProps) {
  const { user, fetchUser } = useAuthStore();
  const toast = useToast();
  const [classrooms, setClassrooms] = useState<ClassroomOption[]>([]);
  const [switching, setSwitching] = useState(false);

  useEffect(() => {
    if (!user) return;
    (async () => {
      try {
        const res = await api.get('/api/my-classrooms');
        setClassrooms(res.data.data.classrooms || []);
      } catch {
        // silent fail
      }
    })();
  }, [user]);

  const handleSwitchClassroom = async (classroomId: number) => {
    if (!user || user.classroom_id === classroomId || switching) return;
    setSwitching(true);
    try {
      await api.post('/api/switch-classroom', { classroom_id: classroomId });
      await fetchUser();
      toast.success('教室を切り替えました');
      window.location.reload();
    } catch {
      toast.error('教室の切り替えに失敗しました');
    } finally {
      setSwitching(false);
    }
  };

  if (!user || classrooms.length <= 1) return null;

  const baseClasses =
    'rounded-md border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-1)] text-[var(--neutral-foreground-2)] focus:outline-none focus:ring-1 focus:ring-[var(--brand-80)]';
  const variantClasses =
    variant === 'sidebar'
      ? 'block w-full px-3 py-2 text-sm font-medium'
      : 'block max-w-[160px] truncate px-2 py-1 text-xs font-medium sm:max-w-none sm:text-sm';

  return (
    <select
      value={user.classroom_id || ''}
      onChange={(e) => handleSwitchClassroom(Number(e.target.value))}
      disabled={switching}
      className={`${baseClasses} ${variantClasses} ${className}`}
      title="教室を切り替え"
      aria-label="教室を切り替え"
    >
      {classrooms.map((c) => (
        <option key={c.id} value={c.id}>
          {c.classroom_name}
        </option>
      ))}
    </select>
  );
}

export default ClassroomSwitcher;
