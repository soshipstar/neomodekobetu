'use client';

import { usePathname } from 'next/navigation';
import Link from 'next/link';
import { cn } from '@/lib/utils';
import { useUiStore } from '@/stores/uiStore';
import { useAuthStore } from '@/stores/authStore';
import { useAuth } from '@/hooks/useAuth';
import { useIsDesktop } from '@/hooks/useMediaQuery';
import { MaterialIcon } from '@/components/ui/MaterialIcon';
import type { UserType } from '@/types/user';

interface NavLink {
  type: 'link';
  label: string;
  href: string;
  icon: string; // Material Symbols icon name
  badge?: number;
  visibility?: 'all' | 'master_only' | 'non_master' | 'company_admin_or_master';
}

interface NavDivider {
  type: 'divider';
  label: string;
}

type NavItem = NavLink | NavDivider;

const staffNav: NavItem[] = [
  { type: 'divider', label: '日常業務' },
  { type: 'link', label: '活動管理', href: '/staff/dashboard', icon: 'home' },
  { type: 'link', label: '振替管理', href: '/staff/attendance', icon: 'sync_alt' },
  { type: 'link', label: '保留タスク', href: '/staff/pending-tasks', icon: 'pending_actions' },
  { type: 'link', label: '未送信日誌一覧', href: '/staff/unsent-records', icon: 'assignment_late' },
  { type: 'link', label: '欠席時対応加算', href: '/staff/absence-responses', icon: 'fact_check' },
  { type: 'divider', label: 'チャット' },
  { type: 'link', label: '保護者チャット', href: '/staff/chat', icon: 'chat' },
  { type: 'link', label: '生徒チャット', href: '/staff/student-chats', icon: 'forum' },
  { type: 'link', label: 'スタッフ間チャット', href: '/staff/staff-chat', icon: 'groups' },
  { type: 'divider', label: '利用者情報' },
  { type: 'link', label: '生徒情報', href: '/staff/students', icon: 'school' },
  { type: 'link', label: '保護者情報', href: '/staff/guardians', icon: 'family_restroom' },
  { type: 'divider', label: 'かけはし' },
  { type: 'link', label: 'かけはし（職員）', href: '/staff/kakehashi-staff', icon: 'handshake' },
  { type: 'link', label: 'かけはし（保護者）', href: '/staff/kakehashi-guardian', icon: 'menu_book' },
  { type: 'divider', label: '計画・支援' },
  { type: 'link', label: '支援案', href: '/staff/support-plans', icon: 'description' },
  { type: 'link', label: '週間計画', href: '/staff/weekly-plans', icon: 'checklist' },
  { type: 'link', label: '生徒面談記録', href: '/staff/student-interviews', icon: 'edit_note' },
  { type: 'link', label: '面談管理', href: '/staff/meetings', icon: 'event' },
  { type: 'link', label: '個別支援計画', href: '/staff/kobetsu-plan', icon: 'folder_special' },
  { type: 'link', label: 'モニタリング', href: '/staff/kobetsu-monitoring', icon: 'monitoring' },
  { type: 'divider', label: '提出物' },
  { type: 'link', label: '生徒提出物', href: '/staff/submissions', icon: 'assignment_turned_in' },
  { type: 'link', label: '提出物管理', href: '/staff/submission-management', icon: 'folder_open' },
  { type: 'link', label: '非表示書類', href: '/staff/hidden-documents', icon: 'visibility_off' },
  { type: 'divider', label: '情報発信' },
  { type: 'link', label: 'お知らせ', href: '/staff/announcements', icon: 'notifications' },
  { type: 'link', label: '施設通信', href: '/staff/newsletters', icon: 'campaign' },
  { type: 'link', label: '施設通信設定', href: '/staff/newsletter-settings', icon: 'tune' },
  { type: 'link', label: 'イベント', href: '/staff/events', icon: 'celebration' },
  { type: 'divider', label: '記録・日誌' },
  { type: 'link', label: '連絡帳', href: '/staff/renrakucho', icon: 'auto_stories' },
  { type: 'link', label: '未確認連絡帳', href: '/staff/unconfirmed-notes', icon: 'mark_email_unread' },
  { type: 'link', label: '業務日誌', href: '/staff/work-diary', icon: 'edit_document' },
  { type: 'divider', label: '管理・設定' },
  { type: 'link', label: '待機児童管理', href: '/staff/waiting-list', icon: 'hourglass_top' },
  { type: 'link', label: 'ヒヤリハット', href: '/staff/hiyari-hatto', icon: 'report_problem' },
  { type: 'link', label: '写真ライブラリ', href: '/staff/classroom-photos', icon: 'photo_library' },
  { type: 'link', label: '利用日一括変更', href: '/staff/additional-usage', icon: 'event_repeat' },
  { type: 'link', label: '学校休業日活動設定', href: '/staff/school-holiday-activities', icon: 'calendar_month' },
  { type: 'link', label: '休日設定', href: '/staff/holidays', icon: 'event_busy' },
  { type: 'link', label: '日課設定', href: '/staff/daily-routines', icon: 'schedule' },
  { type: 'link', label: 'タグ設定', href: '/staff/tag-settings', icon: 'label' },
  { type: 'link', label: '事業所評価', href: '/staff/facility-evaluation', icon: 'analytics' },
  { type: 'link', label: '利用者一括登録', href: '/staff/bulk-register', icon: 'upload_file' },
  { type: 'link', label: 'マニュアル', href: '/staff/manual', icon: 'help_center' },
  { type: 'link', label: 'プロフィール', href: '/staff/profile', icon: 'account_circle' },
  { type: 'divider', label: 'サポート' },
  { type: 'link', label: 'バグ報告', href: '/staff/bug-reports', icon: 'bug_report' },
];

const guardianNav: NavItem[] = [
  { type: 'link', label: 'ダッシュボード', href: '/guardian/dashboard', icon: 'home' },
  { type: 'link', label: '連絡帳一覧', href: '/guardian/notes', icon: 'menu_book' },
  { type: 'link', label: '連絡帳検索', href: '/guardian/communication-logs', icon: 'search' },
  { type: 'link', label: 'チャット', href: '/guardian/chat', icon: 'chat' },
  { type: 'link', label: '面談予約', href: '/guardian/meetings', icon: 'event' },
  { type: 'link', label: '週間計画表', href: '/guardian/weekly-plans', icon: 'checklist' },
  { type: 'link', label: 'かけはし入力', href: '/guardian/kakehashi', icon: 'handshake' },
  { type: 'link', label: 'かけはし履歴', href: '/guardian/kakehashi-history', icon: 'history' },
  { type: 'link', label: 'お知らせ', href: '/guardian/announcements', icon: 'notifications' },
  { type: 'link', label: '施設通信', href: '/guardian/newsletters', icon: 'campaign' },
  { type: 'link', label: '個別支援計画書', href: '/guardian/support-plan', icon: 'description' },
  { type: 'link', label: 'モニタリング表', href: '/guardian/monitoring', icon: 'monitoring' },
  { type: 'link', label: '事業所評価', href: '/guardian/evaluation', icon: 'star' },
  { type: 'link', label: 'ご利用ガイド', href: '/guardian/manual', icon: 'help_center' },
  { type: 'link', label: 'プロフィール', href: '/guardian/profile', icon: 'account_circle' },
  { type: 'link', label: 'パスワード変更', href: '/guardian/change-password', icon: 'lock' },
];

const studentNav: NavItem[] = [
  { type: 'link', label: 'マイページ', href: '/student/dashboard', icon: 'home' },
  { type: 'link', label: 'チャット', href: '/student/chat', icon: 'chat' },
  { type: 'link', label: '週間計画', href: '/student/weekly-plans', icon: 'checklist' },
  { type: 'link', label: '提出物', href: '/student/submissions', icon: 'assignment_turned_in' },
  { type: 'link', label: 'スケジュール', href: '/student/schedule', icon: 'calendar_month' },
  { type: 'link', label: 'パスワード変更', href: '/student/profile', icon: 'lock' },
];

const adminNav: NavItem[] = [
  { type: 'link', label: 'ダッシュボード', href: '/admin/dashboard', icon: 'dashboard' },
  { type: 'link', label: '生徒情報', href: '/admin/students', icon: 'school', visibility: 'non_master' },
  { type: 'link', label: '保護者情報', href: '/admin/guardians', icon: 'family_restroom', visibility: 'non_master' },
  { type: 'link', label: '待機児童管理', href: '/admin/waiting-list', icon: 'hourglass_top', visibility: 'non_master' },
  { type: 'link', label: 'スタッフ管理', href: '/admin/staff-management', icon: 'supervisor_account', visibility: 'non_master' },
  { type: 'link', label: 'タブレットユーザー', href: '/admin/tablet-accounts', icon: 'tablet', visibility: 'non_master' },
  { type: 'link', label: 'イベント管理', href: '/admin/events', icon: 'celebration', visibility: 'non_master' },
  { type: 'link', label: '休日管理', href: '/admin/holidays', icon: 'event_busy', visibility: 'non_master' },
  { type: 'link', label: '教室基本設定', href: '/admin/settings', icon: 'settings', visibility: 'non_master' },
  { type: 'link', label: '企業管理', href: '/admin/companies', icon: 'business', visibility: 'master_only' },
  { type: 'link', label: '教室管理', href: '/admin/classrooms', icon: 'apartment', visibility: 'master_only' },
  { type: 'link', label: '管理者アカウント', href: '/admin/admin-accounts', icon: 'shield_person', visibility: 'master_only' },
  { type: 'link', label: 'スタッフアカウント', href: '/admin/staff-accounts', icon: 'badge', visibility: 'master_only' },
  { type: 'link', label: '事業所評価', href: '/admin/facility-evaluation', icon: 'analytics', visibility: 'non_master' },
  { type: 'link', label: '請求・契約', href: '/admin/billing', icon: 'receipt_long', visibility: 'company_admin_or_master' },
  { type: 'link', label: '企業課金管理', href: '/admin/master-billing', icon: 'payments', visibility: 'master_only' },
  { type: 'divider', label: 'システム' },
  { type: 'link', label: 'エラーログ', href: '/admin/error-logs', icon: 'warning', visibility: 'master_only' },
  { type: 'link', label: 'バグ報告', href: '/staff/bug-reports', icon: 'bug_report' },
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
  const { logout } = useAuth();
  const isDesktop = useIsDesktop();

  if (!user) return null;

  // 通常管理者がスタッフ画面にいる場合、スタッフメニューを表示
  const isAdminOnStaffPage = user.user_type === 'admin' && !user.is_master && pathname.startsWith('/staff');
  const navItems = isAdminOnStaffPage ? staffNav : getNavItems(user.user_type as UserType);

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
            isAdminOnStaffView={isAdminOnStaffPage}
            onClose={() => setSidebarOpen(false)}
            onLogout={logout}
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
        isAdminOnStaffView={isAdminOnStaffPage}
        collapsed={!sidebarOpen}
        onToggle={toggleSidebar}
        onLogout={logout}
      />
    </aside>
  );
}

interface SidebarContentProps {
  navItems: NavItem[];
  pathname: string;
  user: { full_name: string; user_type: string; is_master?: boolean; is_company_admin?: boolean; classroom?: { classroom_name: string; logo_path: string | null } | null };
  isAdminOnStaffView?: boolean;
  collapsed?: boolean;
  onClose?: () => void;
  onToggle?: () => void;
  onLogout?: () => void | Promise<void>;
}

function SidebarContent({
  navItems,
  pathname,
  user,
  isAdminOnStaffView = false,
  collapsed = false,
  onClose,
  onToggle,
  onLogout,
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
            <MaterialIcon name="close" size={20} />
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
          {navItems.filter((item) => {
            if (item.type === 'divider') return true;
            const vis = item.visibility ?? 'all';
            const isMaster = user.user_type === 'admin' && !!user.is_master;
            const isCompanyAdmin = user.user_type === 'admin' && !!user.is_company_admin;
            if (vis === 'master_only' && !isMaster) return false;
            if (vis === 'non_master' && isMaster) return false;
            if (vis === 'company_admin_or_master' && !isMaster && !isCompanyAdmin) return false;
            return true;
          }).map((item, index) => {
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
                  <MaterialIcon name={item.icon} size={20} className="shrink-0" />
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

      {/* Switch between admin/staff view (non-master admin only) */}
      {user.user_type === 'admin' && !user.is_master && (
        <div className="border-t border-[var(--neutral-stroke-2)] p-3">
          <Link
            href={isAdminOnStaffView ? '/admin/dashboard' : '/staff/dashboard'}
            onClick={onClose}
            className={cn(
              'flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium text-[var(--neutral-foreground-2)] hover:bg-[var(--neutral-background-3)] hover:text-[var(--neutral-foreground-1)] transition-colors',
              collapsed && 'justify-center px-2'
            )}
            title={collapsed ? (isAdminOnStaffView ? '管理者画面へ移動' : 'スタッフ画面へ移動') : undefined}
          >
            <MaterialIcon name="swap_horiz" size={20} className="shrink-0" />
            {!collapsed && <span>{isAdminOnStaffView ? '管理者画面へ移動' : 'スタッフ画面へ移動'}</span>}
          </Link>
        </div>
      )}

      {/* Logout */}
      {onLogout && (
        <div className="border-t border-[var(--neutral-stroke-2)] p-3">
          <button
            type="button"
            onClick={async () => {
              onClose?.();
              await onLogout();
            }}
            className={cn(
              'flex w-full items-center gap-3 rounded-md px-3 py-2 text-sm font-medium text-[var(--neutral-foreground-2)] hover:bg-[var(--status-danger-bg)] hover:text-[var(--status-danger-fg)] transition-colors',
              collapsed && 'justify-center px-2'
            )}
            title={collapsed ? 'ログアウト' : undefined}
          >
            <MaterialIcon name="logout" size={20} className="shrink-0" />
            {!collapsed && <span>ログアウト</span>}
          </button>
        </div>
      )}

      {/* Collapse toggle (desktop only) */}
      {onToggle && (
        <div className="border-t border-[var(--neutral-stroke-2)] p-3">
          <button
            onClick={onToggle}
            className="flex w-full items-center justify-center rounded-md p-2 text-[var(--neutral-foreground-4)] hover:bg-[var(--neutral-background-3)] hover:text-[var(--neutral-foreground-1)]"
          >
            {collapsed ? <MaterialIcon name="chevron_right" size={20} /> : <MaterialIcon name="chevron_left" size={20} />}
          </button>
        </div>
      )}
    </div>
  );
}

export default Sidebar;
