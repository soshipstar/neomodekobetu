'use client';

import { useEffect, useState, type ReactNode } from 'react';
import Link from 'next/link';
import { usePathname } from 'next/navigation';
import api from '@/lib/api';
import { useAuth } from '@/hooks/useAuth';
import { useAuthStore } from '@/stores/authStore';
import { useToast } from '@/components/ui/Toast';
import { MaterialIcon } from '@/components/ui/MaterialIcon';

interface ClassroomOption {
  id: number;
  classroom_name: string;
  service_type?: string;
}

/**
 * タブレット用ヘッダー + ナビゲーション。
 *
 * ボタンはアイコン中心 + 小ラベル (横幅が狭い時はラベル折り畳み)。
 * 教室切替は登録された教室が複数あるときだけ表示。
 */
export default function TabletLayout({ children }: { children: ReactNode }) {
  const { user, logout } = useAuth();
  const { fetchUser } = useAuthStore();
  const toast = useToast();
  const pathname = usePathname();
  const [classrooms, setClassrooms] = useState<ClassroomOption[]>([]);
  const [switching, setSwitching] = useState(false);

  useEffect(() => {
    if (!user) return;
    (async () => {
      try {
        const res = await api.get('/api/my-classrooms');
        setClassrooms(res.data?.data?.classrooms ?? []);
      } catch {
        // silent
      }
    })();
  }, [user]);

  const handleSwitch = async (classroomId: number) => {
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

  // ナビゲーション項目: アイコン + 小ラベル
  const navItems = [
    { href: '/tablet',            icon: 'home',          label: 'トップ',   color: 'bg-[var(--brand-80)]' },
    { href: '/tablet/attendance', icon: 'how_to_reg',    label: '出欠',     color: 'bg-green-600' },
    { href: '/tablet/chat',       icon: 'forum',         label: 'チャット', color: 'bg-purple-600' },
    { href: '/tablet/photos',     icon: 'photo_library', label: '写真',     color: 'bg-[var(--brand-80)]' },
  ];

  const isActive = (href: string) => {
    if (href === '/tablet') return pathname === '/tablet';
    return pathname?.startsWith(href);
  };

  return (
    <div className="min-h-screen bg-[var(--neutral-background-4)]">
      <header className="sticky top-0 z-20 bg-white shadow-md">
        {/* 上段: ロゴ / 教室切替 / ログアウト */}
        <div className="flex flex-wrap items-center justify-between gap-3 px-4 py-3 sm:px-6">
          <div className="flex flex-wrap items-center gap-3">
            <Link
              href="/tablet"
              className="flex items-center gap-2 text-[var(--neutral-foreground-1)]"
              title="本日の記録"
            >
              <MaterialIcon name="dashboard" size={28} className="text-[var(--brand-60)]" />
              <span className="hidden text-2xl font-bold sm:inline">本日の記録</span>
            </Link>
            {classrooms.length > 1 ? (
              <select
                value={user?.classroom_id ?? ''}
                onChange={(e) => handleSwitch(Number(e.target.value))}
                disabled={switching}
                className="min-w-[180px] max-w-[280px] rounded-lg border-2 border-[var(--brand-80)] bg-white px-3 py-2 text-base font-bold text-[var(--neutral-foreground-1)]"
                title="教室を切り替え"
              >
                {classrooms.map((c) => (
                  <option key={c.id} value={c.id}>
                    {c.classroom_name}
                  </option>
                ))}
              </select>
            ) : user?.classroom?.classroom_name ? (
              <span className="rounded-md bg-[var(--neutral-background-4)] px-3 py-1 text-base font-semibold text-[var(--neutral-foreground-2)]">
                {user.classroom.classroom_name}
              </span>
            ) : null}
            {user?.full_name && (
              <span className="hidden text-sm text-[var(--neutral-foreground-3)] md:inline">
                {user.full_name}
              </span>
            )}
          </div>
          <button
            onClick={logout}
            className="flex items-center gap-1 rounded-lg bg-red-500 px-3 py-2 text-sm font-bold text-white hover:bg-red-600"
            title="ログアウト"
          >
            <MaterialIcon name="logout" size={20} />
            <span className="hidden sm:inline">ログアウト</span>
          </button>
        </div>

        {/* 下段: ナビゲーション (アイコン + ラベル) */}
        <nav className="flex flex-wrap gap-2 border-t border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-3)] px-4 py-2 sm:px-6">
          {navItems.map((item) => {
            const active = isActive(item.href);
            return (
              <Link
                key={item.href}
                href={item.href}
                className={`flex min-w-[80px] flex-1 max-w-[200px] items-center justify-center gap-2 rounded-lg px-3 py-2 text-white shadow-sm transition-all
                  ${item.color} ${active ? 'ring-4 ring-[var(--brand-160)]' : 'opacity-90 hover:opacity-100'}`}
                title={item.label}
              >
                <MaterialIcon name={item.icon} size={22} />
                <span className="text-base font-bold">{item.label}</span>
              </Link>
            );
          })}
        </nav>
      </header>
      <main className="p-4 sm:p-6">
        {children}
      </main>
    </div>
  );
}
