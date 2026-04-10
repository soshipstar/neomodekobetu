'use client';

import { useEffect, useState } from 'react';
import { useAuthStore } from '@/stores/authStore';
import { useUiStore } from '@/stores/uiStore';
import { useAuth } from '@/hooks/useAuth';
import { NotificationBell } from './NotificationBell';
import { MaterialIcon } from '@/components/ui/MaterialIcon';
import { getInitials } from '@/lib/utils';
import api from '@/lib/api';
import { useToast } from '@/components/ui/Toast';

interface ClassroomOption {
  id: number;
  classroom_name: string;
}

export function Header() {
  const { user, fetchUser } = useAuthStore();
  const { toggleSidebar } = useUiStore();
  const { logout } = useAuth();
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
      // 画面をリロードして全クエリを再取得
      window.location.reload();
    } catch {
      toast.error('教室の切り替えに失敗しました');
    } finally {
      setSwitching(false);
    }
  };

  if (!user) return null;

  return (
    <header className="sticky top-0 z-30 flex h-12 items-center justify-between border-b border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-1)] px-4 lg:px-6">
      {/* Left side */}
      <div className="flex items-center gap-3">
        <button
          onClick={toggleSidebar}
          className="rounded-md p-1.5 text-[var(--neutral-foreground-3)] hover:bg-[var(--neutral-background-3)] hover:text-[var(--neutral-foreground-1)] transition-colors"
          aria-label="メニュー切替"
        >
          <MaterialIcon name="menu" size={20} />
        </button>
        {classrooms.length > 1 ? (
          <select
            value={user.classroom_id || ''}
            onChange={(e) => handleSwitchClassroom(Number(e.target.value))}
            disabled={switching}
            className="hidden rounded-md border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-1)] px-2 py-1 text-sm font-medium text-[var(--neutral-foreground-2)] sm:block"
            title="教室を切り替え"
          >
            {classrooms.map((c) => (
              <option key={c.id} value={c.id}>{c.classroom_name}</option>
            ))}
          </select>
        ) : user.classroom ? (
          <span className="hidden text-sm font-medium text-[var(--neutral-foreground-2)] sm:block">
            {user.classroom.classroom_name}
          </span>
        ) : null}
      </div>

      {/* Right side */}
      <div className="flex items-center gap-2">
        <NotificationBell />

        {/* User menu */}
        <div className="flex items-center gap-2">
          <div className="hidden text-right sm:block">
            <p className="text-sm font-medium text-[var(--neutral-foreground-1)]">{user.full_name}</p>
          </div>
          <div className="flex h-8 w-8 items-center justify-center rounded-full bg-[var(--brand-160)] text-xs font-semibold text-[var(--brand-60)]">
            {getInitials(user.full_name)}
          </div>
          <button
            onClick={logout}
            className="rounded-md p-1.5 text-[var(--neutral-foreground-4)] hover:bg-[var(--neutral-background-3)] hover:text-[var(--status-danger-fg)] transition-colors"
            title="ログアウト"
          >
            <MaterialIcon name="logout" size={18} />
          </button>
        </div>
      </div>
    </header>
  );
}

export default Header;
