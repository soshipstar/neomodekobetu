'use client';

import Link from 'next/link';
import { usePathname } from 'next/navigation';
import { cn } from '@/lib/utils';
import { useAuthStore } from '@/stores/authStore';
import { useChatStore } from '@/stores/chatStore';
import {
  LayoutDashboard,
  MessageCircle,
  Users,
  BookOpen,
  Menu,
  Calendar,
  FileText,
  ClipboardList,
} from 'lucide-react';
import { useUiStore } from '@/stores/uiStore';
import type { UserType } from '@/types/user';
import type { LucideIcon } from 'lucide-react';

interface MobileNavItem {
  label: string;
  href: string;
  icon: LucideIcon;
  badge?: number;
}

function getMobileNavItems(userType: UserType, chatUnread: number): MobileNavItem[] {
  switch (userType) {
    case 'staff':
      return [
        { label: 'ホーム', href: '/staff/dashboard', icon: LayoutDashboard },
        { label: 'チャット', href: '/staff/chat', icon: MessageCircle, badge: chatUnread },
        { label: '生徒', href: '/staff/students', icon: Users },
        { label: '連絡帳', href: '/staff/renrakucho', icon: BookOpen },
        { label: 'メニュー', href: '#menu', icon: Menu },
      ];
    case 'guardian':
      return [
        { label: 'ホーム', href: '/guardian/dashboard', icon: LayoutDashboard },
        { label: 'チャット', href: '/guardian/chat', icon: MessageCircle, badge: chatUnread },
        { label: '支援計画', href: '/guardian/support-plan', icon: BookOpen },
        { label: 'メニュー', href: '#menu', icon: Menu },
      ];
    case 'admin':
      return [
        { label: 'ホーム', href: '/admin/dashboard', icon: LayoutDashboard },
        { label: '事業所', href: '/admin/classrooms', icon: Users },
        { label: 'ユーザー', href: '/admin/users', icon: Users },
        { label: 'メニュー', href: '#menu', icon: Menu },
      ];
    default:
      return [
        { label: 'ホーム', href: '/student/dashboard', icon: LayoutDashboard },
        { label: 'チャット', href: '/student/chat', icon: MessageCircle, badge: chatUnread },
        { label: '計画', href: '/student/weekly-plans', icon: ClipboardList },
        { label: '提出物', href: '/student/submissions', icon: FileText },
        { label: 'スケジュール', href: '/student/schedule', icon: Calendar },
      ];
  }
}

export function MobileNav() {
  const pathname = usePathname();
  const { user } = useAuthStore();
  const { unreadCounts } = useChatStore();
  const { toggleSidebar } = useUiStore();

  if (!user) return null;

  const totalUnread = Object.values(unreadCounts).reduce((sum, c) => sum + c, 0);
  const navItems = getMobileNavItems(user.user_type as UserType, totalUnread);

  return (
    <nav className="fixed bottom-0 left-0 right-0 z-40 border-t border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-1)] lg:hidden">
      <div className="flex items-center justify-around">
        {navItems.map((item) => {
          const isMenu = item.href === '#menu';
          const isActive = !isMenu && (pathname === item.href || pathname.startsWith(item.href + '/'));
          const Icon = item.icon;

          if (isMenu) {
            return (
              <button
                key="menu"
                onClick={toggleSidebar}
                className="flex flex-1 flex-col items-center gap-1 py-2 text-[var(--neutral-foreground-4)]"
              >
                <Icon className="h-5 w-5" />
                <span className="text-[10px]">{item.label}</span>
              </button>
            );
          }

          return (
            <Link
              key={item.href}
              href={item.href}
              className={cn(
                'relative flex flex-1 flex-col items-center gap-1 py-2',
                isActive ? 'text-[var(--brand-80)]' : 'text-[var(--neutral-foreground-4)]'
              )}
            >
              <div className="relative">
                <Icon className="h-5 w-5" />
                {item.badge !== undefined && item.badge > 0 && (
                  <span className="absolute -right-2 -top-1 flex h-4 w-4 items-center justify-center rounded-full bg-[var(--status-danger-fg)] text-[10px] font-bold text-white">
                    {item.badge > 9 ? '9+' : item.badge}
                  </span>
                )}
              </div>
              <span className="text-[10px]">{item.label}</span>
            </Link>
          );
        })}
      </div>
      {/* Safe area padding for iOS */}
      <div className="h-[env(safe-area-inset-bottom)]" />
    </nav>
  );
}

export default MobileNav;
