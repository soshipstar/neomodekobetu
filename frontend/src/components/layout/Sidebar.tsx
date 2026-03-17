'use client';

import { usePathname } from 'next/navigation';
import Link from 'next/link';
import { cn } from '@/lib/utils';
import { useUiStore } from '@/stores/uiStore';
import { useAuthStore } from '@/stores/authStore';
import { useIsDesktop } from '@/hooks/useMediaQuery';
import {
  Home,
  MessageCircle,
  MessageSquare,
  Users,
  FileText,
  ClipboardList,
  CalendarDays,
  Megaphone,
  UserCheck,
  BookOpen,
  Building2,
  AlertTriangle,
  Settings,
  Star,
  BarChart3,
  ChevronLeft,
  ChevronRight,
  X,
  CalendarPlus,
  Upload,
  PartyPopper,
  CalendarOff,
  Clock,
  UserCircle,
  BarChart,
  Clipboard,
  Calendar,
  FileCheck,
  Tablet,
  UserCog,
  Hourglass,
  Handshake,
  FolderOpen,
  HelpCircle,
  Lock,
  Shield,
  BadgeCheck,
  CalendarSync,
  PenTool,
  NotebookPen,
  Eye,
  FolderCheck,
  Cog,
  BookOpenCheck,
  GraduationCap,
  MailWarning,
  Bell,
} from 'lucide-react';
import type { UserType } from '@/types/user';
import type { LucideIcon } from 'lucide-react';

interface NavLink {
  type: 'link';
  label: string;
  href: string;
  icon: LucideIcon;
  badge?: number;
  visibility?: 'all' | 'master_only' | 'non_master';
}

interface NavDivider {
  type: 'divider';
  label: string;
}

type NavItem = NavLink | NavDivider;

const staffNav: NavItem[] = [
  { type: 'divider', label: '日常業務' },
  { type: 'link', label: '活動管理', href: '/staff/dashboard', icon: Home },
  { type: 'link', label: '振替管理', href: '/staff/attendance', icon: CalendarSync },
  { type: 'divider', label: 'チャット' },
  { type: 'link', label: '保護者チャット', href: '/staff/chat', icon: MessageCircle },
  { type: 'link', label: '生徒チャット', href: '/staff/student-chats', icon: MessageSquare },
  { type: 'link', label: 'スタッフ間チャット', href: '/staff/staff-chat', icon: Users },
  { type: 'divider', label: 'かけはし' },
  { type: 'link', label: 'かけはし（職員）', href: '/staff/kakehashi-staff', icon: Handshake },
  { type: 'link', label: 'かけはし（保護者）', href: '/staff/kakehashi-guardian', icon: BookOpen },
  { type: 'divider', label: '計画・支援' },
  { type: 'link', label: '支援案', href: '/staff/support-plans', icon: FileText },
  { type: 'link', label: '週間計画', href: '/staff/weekly-plans', icon: ClipboardList },
  { type: 'link', label: '生徒面談記録', href: '/staff/student-interviews', icon: NotebookPen },
  { type: 'link', label: '個別支援計画', href: '/staff/kobetsu-plan', icon: FolderCheck },
  { type: 'link', label: 'モニタリング', href: '/staff/kobetsu-monitoring', icon: Eye },
  { type: 'divider', label: '提出物' },
  { type: 'link', label: '生徒提出物', href: '/staff/submissions', icon: FileCheck },
  { type: 'link', label: '提出物管理', href: '/staff/submission-management', icon: FolderOpen },
  { type: 'divider', label: '情報発信' },
  { type: 'link', label: 'お知らせ', href: '/staff/announcements', icon: Bell },
  { type: 'link', label: '施設通信', href: '/staff/newsletters', icon: Megaphone },
  { type: 'link', label: '施設通信設定', href: '/staff/newsletter-settings', icon: Cog },
  { type: 'link', label: 'イベント', href: '/staff/events', icon: PartyPopper },
  { type: 'divider', label: '記録・日誌' },
  { type: 'link', label: '連絡帳', href: '/staff/renrakucho', icon: ClipboardList },
  { type: 'link', label: '未確認連絡帳', href: '/staff/unconfirmed-notes', icon: MailWarning },
  { type: 'link', label: '業務日誌', href: '/staff/work-diary', icon: PenTool },
  { type: 'divider', label: '管理・設定' },
  { type: 'link', label: '生徒登録・変更', href: '/staff/students', icon: Users },
  { type: 'link', label: '保護者登録・変更', href: '/staff/guardians', icon: UserCog },
  { type: 'link', label: '待機児童管理', href: '/staff/waiting-list', icon: Hourglass },
  { type: 'link', label: '利用日一括変更', href: '/staff/additional-usage', icon: CalendarPlus },
  { type: 'link', label: '学校休業日活動設定', href: '/staff/school-holiday-activities', icon: Calendar },
  { type: 'link', label: '休日設定', href: '/staff/holidays', icon: CalendarOff },
  { type: 'link', label: '日課設定', href: '/staff/daily-routines', icon: Clock },
  { type: 'link', label: 'タグ設定', href: '/staff/tag-settings', icon: Settings },
  { type: 'link', label: '事業所評価', href: '/staff/facility-evaluation', icon: BarChart3 },
  { type: 'link', label: '利用者一括登録', href: '/staff/bulk-register', icon: Upload },
  { type: 'link', label: 'マニュアル', href: '/staff/manual', icon: BookOpenCheck },
  { type: 'link', label: 'プロフィール', href: '/staff/profile', icon: UserCircle },
];

const guardianNav: NavItem[] = [
  { type: 'link', label: 'ダッシュボード', href: '/guardian/dashboard', icon: Home },
  { type: 'link', label: '連絡帳一覧', href: '/guardian/notes', icon: BookOpen },
  { type: 'link', label: '連絡帳検索', href: '/guardian/communication-logs', icon: FolderOpen },
  { type: 'link', label: 'チャット', href: '/guardian/chat', icon: MessageCircle },
  { type: 'link', label: '週間計画表', href: '/guardian/weekly-plans', icon: ClipboardList },
  { type: 'link', label: 'かけはし入力', href: '/guardian/kakehashi', icon: Handshake },
  { type: 'link', label: 'かけはし履歴', href: '/guardian/kakehashi-history', icon: Clock },
  { type: 'link', label: 'お知らせ', href: '/guardian/announcements', icon: Bell },
  { type: 'link', label: '施設通信', href: '/guardian/newsletters', icon: Megaphone },
  { type: 'link', label: '個別支援計画書', href: '/guardian/support-plan', icon: FileText },
  { type: 'link', label: 'モニタリング表', href: '/guardian/monitoring', icon: Clipboard },
  { type: 'link', label: '事業所評価', href: '/guardian/evaluation', icon: Star },
  { type: 'link', label: 'ご利用ガイド', href: '/guardian/manual', icon: HelpCircle },
  { type: 'link', label: 'プロフィール', href: '/guardian/profile', icon: UserCircle },
  { type: 'link', label: 'パスワード変更', href: '/guardian/change-password', icon: Lock },
];

const studentNav: NavItem[] = [
  { type: 'link', label: 'マイページ', href: '/student/dashboard', icon: Home },
  { type: 'link', label: 'チャット', href: '/student/chat', icon: MessageCircle },
  { type: 'link', label: '週間計画', href: '/student/weekly-plans', icon: ClipboardList },
  { type: 'link', label: '提出物', href: '/student/submissions', icon: FileCheck },
  { type: 'link', label: 'スケジュール', href: '/student/schedule', icon: Calendar },
  { type: 'link', label: 'パスワード変更', href: '/student/change-password', icon: Lock },
];

const adminNav: NavItem[] = [
  { type: 'link', label: 'ダッシュボード', href: '/admin/dashboard', icon: Home },
  { type: 'link', label: '生徒登録・変更', href: '/admin/students', icon: Users, visibility: 'non_master' },
  { type: 'link', label: '保護者登録・変更', href: '/admin/guardians', icon: UserCog, visibility: 'non_master' },
  { type: 'link', label: '待機児童管理', href: '/admin/waiting-list', icon: Hourglass, visibility: 'non_master' },
  { type: 'link', label: 'スタッフ管理', href: '/admin/staff-management', icon: UserCheck, visibility: 'non_master' },
  { type: 'link', label: 'タブレットユーザー', href: '/admin/tablet-accounts', icon: Tablet, visibility: 'non_master' },
  { type: 'link', label: 'イベント管理', href: '/admin/events', icon: PartyPopper, visibility: 'non_master' },
  { type: 'link', label: '休日管理', href: '/admin/holidays', icon: CalendarOff, visibility: 'non_master' },
  { type: 'link', label: '教室管理', href: '/admin/classrooms', icon: Building2, visibility: 'master_only' },
  { type: 'link', label: '管理者アカウント', href: '/admin/admin-accounts', icon: Shield, visibility: 'master_only' },
  { type: 'link', label: 'スタッフアカウント', href: '/admin/staff-accounts', icon: BadgeCheck, visibility: 'master_only' },
  { type: 'divider', label: 'システム' },
  { type: 'link', label: 'エラーログ', href: '/admin/error-logs', icon: AlertTriangle },
];

function getNavItems(userType: UserType): NavItem[] {
  switch (userType) {
    case 'staff':
      return staffNav;
    case 'guardian':
      return guardianNav;
    case 'admin':
      return adminNav;
    default:
      return studentNav;
  }
}

export function Sidebar() {
  const pathname = usePathname();
  const { sidebarOpen, setSidebarOpen, toggleSidebar } = useUiStore();
  const { user } = useAuthStore();
  const isDesktop = useIsDesktop();

  if (!user) return null;

  const navItems = getNavItems(user.user_type as UserType);

  // Mobile overlay sidebar
  if (!isDesktop) {
    return (
      <>
        {/* Overlay */}
        {sidebarOpen && (
          <div
            className="fixed inset-0 z-40 bg-black/50 lg:hidden"
            onClick={() => setSidebarOpen(false)}
          />
        )}
        {/* Sidebar panel */}
        <aside
          className={cn(
            'fixed inset-y-0 left-0 z-50 w-64 transform bg-[var(--neutral-background-1)] shadow-[var(--shadow-28)] transition-transform duration-300 lg:hidden',
            sidebarOpen ? 'translate-x-0' : '-translate-x-full'
          )}
        >
          <SidebarContent
            navItems={navItems}
            pathname={pathname}
            user={user}
            onClose={() => setSidebarOpen(false)}
          />
        </aside>
      </>
    );
  }

  // Desktop sidebar
  return (
    <aside
      className={cn(
        'hidden lg:flex lg:flex-col bg-[var(--neutral-background-1)] border-r border-[var(--neutral-stroke-2)] transition-all duration-300',
        sidebarOpen ? 'lg:w-64' : 'lg:w-16'
      )}
    >
      <SidebarContent
        navItems={navItems}
        pathname={pathname}
        user={user}
        collapsed={!sidebarOpen}
        onToggle={toggleSidebar}
      />
    </aside>
  );
}

interface SidebarContentProps {
  navItems: NavItem[];
  pathname: string;
  user: { full_name: string; user_type: string; classroom?: { classroom_name: string; logo_path: string | null } | null };
  collapsed?: boolean;
  onClose?: () => void;
  onToggle?: () => void;
}

function SidebarContent({
  navItems,
  pathname,
  user,
  collapsed = false,
  onClose,
  onToggle,
}: SidebarContentProps) {
  const userTypeLabel: Record<string, string> = {
    admin: '管理者',
    staff: 'スタッフ',
    guardian: '保護者',
    student: '生徒',
    tablet: 'タブレット',
  };

  return (
    <div className="flex h-full flex-col">
      {/* Logo / Header */}
      <div className="flex h-16 items-center justify-between border-b border-[var(--neutral-stroke-2)] px-4">
        {!collapsed && (
          <div className="flex items-center gap-2">
            {user.classroom?.logo_path ? (
              <img
                src={`${process.env.NEXT_PUBLIC_BACKEND_URL ?? 'http://localhost:8000'}/storage/${user.classroom.logo_path}`}
                alt={user.classroom.classroom_name}
                className="h-8 w-8 rounded-lg object-contain"
              />
            ) : (
              <div className="h-8 w-8 rounded-lg bg-[var(--brand-80)] flex items-center justify-center">
                <span className="text-sm font-bold text-white">K</span>
              </div>
            )}
            <span className="font-bold text-[var(--neutral-foreground-1)]">
              {user.classroom?.classroom_name || 'KIDURI'}
            </span>
          </div>
        )}
        {collapsed && (
          user.classroom?.logo_path ? (
            <img
              src={`${process.env.NEXT_PUBLIC_BACKEND_URL ?? 'http://localhost:8000'}/storage/${user.classroom.logo_path}`}
              alt={user.classroom.classroom_name}
              className="mx-auto h-8 w-8 rounded-lg object-contain"
            />
          ) : (
            <div className="mx-auto h-8 w-8 rounded-lg bg-[var(--brand-80)] flex items-center justify-center">
              <span className="text-sm font-bold text-white">K</span>
            </div>
          )
        )}
        {onClose && (
          <button onClick={onClose} className="rounded-lg p-1 text-[var(--neutral-foreground-4)] hover:text-[var(--neutral-foreground-1)] lg:hidden">
            <X className="h-5 w-5" />
          </button>
        )}
      </div>

      {/* User info */}
      {!collapsed && (
        <div className="border-b border-[var(--neutral-stroke-2)] px-4 py-3">
          <p className="text-sm font-medium text-[var(--neutral-foreground-1)] truncate">{user.full_name}</p>
          <p className="text-xs text-[var(--neutral-foreground-3)]">{userTypeLabel[user.user_type] || user.user_type}</p>
          {user.classroom && (
            <p className="text-xs text-[var(--neutral-foreground-4)] truncate">{user.classroom.classroom_name}</p>
          )}
        </div>
      )}

      {/* Navigation */}
      <nav className="flex-1 overflow-y-auto p-3">
        <ul className="space-y-0.5">
          {navItems.map((item, index) => {
            if (item.type === 'divider') {
              if (collapsed) return null;
              return (
                <li key={`divider-${index}`} className="pt-3 pb-1">
                  <div className="flex items-center gap-2 px-3">
                    <span className="text-[10px] font-semibold uppercase tracking-wider text-[var(--neutral-foreground-4)]">
                      {item.label}
                    </span>
                    <div className="h-px flex-1 bg-[var(--neutral-stroke-3)]" />
                  </div>
                </li>
              );
            }

            const isActive = pathname === item.href || pathname.startsWith(item.href + '/');
            const Icon = item.icon;
            return (
              <li key={`${item.href}-${index}`}>
                <Link
                  href={item.href}
                  onClick={onClose}
                  className={cn(
                    'flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium transition-colors',
                    isActive
                      ? 'bg-[var(--brand-160)] text-[var(--brand-80)]'
                      : 'text-[var(--neutral-foreground-2)] hover:bg-[var(--neutral-background-3)] hover:text-[var(--neutral-foreground-1)]',
                    collapsed && 'justify-center px-2'
                  )}
                  title={collapsed ? item.label : undefined}
                >
                  <Icon className="h-4.5 w-4.5 shrink-0" />
                  {!collapsed && <span>{item.label}</span>}
                  {!collapsed && item.badge !== undefined && item.badge > 0 && (
                    <span className="ml-auto rounded-full bg-[var(--status-danger-fg)] px-2 py-0.5 text-[10px] font-semibold text-white">
                      {item.badge}
                    </span>
                  )}
                </Link>
              </li>
            );
          })}
        </ul>
      </nav>

      {/* Collapse toggle (desktop only) */}
      {onToggle && (
        <div className="border-t border-[var(--neutral-stroke-2)] p-3">
          <button
            onClick={onToggle}
            className="flex w-full items-center justify-center rounded-md p-2 text-[var(--neutral-foreground-4)] hover:bg-[var(--neutral-background-3)] hover:text-[var(--neutral-foreground-1)]"
          >
            {collapsed ? <ChevronRight className="h-5 w-5" /> : <ChevronLeft className="h-5 w-5" />}
          </button>
        </div>
      )}
    </div>
  );
}

export default Sidebar;
