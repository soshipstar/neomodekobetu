'use client';

import { useState, useRef, useEffect } from 'react';
import Link from 'next/link';
import { Bell } from 'lucide-react';
import { cn, formatRelativeTime, truncate } from '@/lib/utils';
import { useNotifications } from '@/hooks/useNotifications';

export function NotificationBell() {
  const [isOpen, setIsOpen] = useState(false);
  const dropdownRef = useRef<HTMLDivElement>(null);
  const { notifications, unreadCount, markAsRead, markAllAsRead } = useNotifications();

  // Close on outside click
  useEffect(() => {
    function handleClickOutside(event: MouseEvent) {
      if (dropdownRef.current && !dropdownRef.current.contains(event.target as Node)) {
        setIsOpen(false);
      }
    }
    document.addEventListener('mousedown', handleClickOutside);
    return () => document.removeEventListener('mousedown', handleClickOutside);
  }, []);

  const recentNotifications = Array.isArray(notifications) ? notifications.slice(0, 10) : [];

  return (
    <div className="relative" ref={dropdownRef}>
      <button
        onClick={() => setIsOpen(!isOpen)}
        className="relative rounded-md p-1.5 text-[var(--neutral-foreground-3)] hover:bg-[var(--neutral-background-3)] hover:text-[var(--neutral-foreground-1)] transition-colors"
        aria-label="通知"
      >
        <Bell className="h-5 w-5" />
        {unreadCount > 0 && (
          <span className="absolute -right-0.5 -top-0.5 flex h-4 w-4 items-center justify-center rounded-full bg-[var(--status-danger-fg)] text-[9px] font-bold text-white">
            {unreadCount > 99 ? '99+' : unreadCount}
          </span>
        )}
      </button>

      {/* Dropdown */}
      {isOpen && (
        <div className="absolute right-0 top-full mt-1 w-80 rounded-lg border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-1)] shadow-[var(--shadow-16)] z-50">
          {/* Header */}
          <div className="flex items-center justify-between border-b border-[var(--neutral-stroke-2)] px-4 py-2.5">
            <h3 className="text-sm font-semibold text-[var(--neutral-foreground-1)]">通知</h3>
            {unreadCount > 0 && (
              <button
                onClick={() => markAllAsRead()}
                className="text-xs text-[var(--brand-80)] hover:text-[var(--brand-70)]"
              >
                すべて既読にする
              </button>
            )}
          </div>

          {/* Notification list */}
          <div className="max-h-96 overflow-y-auto">
            {recentNotifications.length === 0 ? (
              <div className="px-4 py-8 text-center text-sm text-[var(--neutral-foreground-3)]">
                通知はありません
              </div>
            ) : (
              recentNotifications.map((notification) => (
                <div
                  key={notification.id}
                  className={cn(
                    'border-b border-[var(--neutral-stroke-3)] px-4 py-2.5 transition-colors hover:bg-[var(--neutral-background-3)]',
                    !notification.is_read && 'bg-[var(--brand-160)]'
                  )}
                >
                  {notification.link ? (
                    <Link
                      href={notification.link}
                      onClick={() => {
                        markAsRead(notification.id);
                        setIsOpen(false);
                      }}
                      className="block"
                    >
                      <NotificationItem notification={notification} />
                    </Link>
                  ) : (
                    <div
                      onClick={() => markAsRead(notification.id)}
                      className="cursor-pointer"
                    >
                      <NotificationItem notification={notification} />
                    </div>
                  )}
                </div>
              ))
            )}
          </div>
        </div>
      )}
    </div>
  );
}

function NotificationItem({
  notification,
}: {
  notification: { title: string; message: string; is_read: boolean; created_at: string };
}) {
  return (
    <div>
      <div className="flex items-start justify-between gap-2">
        <p className={cn('text-sm', !notification.is_read ? 'font-semibold text-[var(--neutral-foreground-1)]' : 'text-[var(--neutral-foreground-2)]')}>
          {notification.title}
        </p>
        {!notification.is_read && (
          <span className="mt-1 h-2 w-2 shrink-0 rounded-full bg-[var(--brand-80)]" />
        )}
      </div>
      <p className="mt-0.5 text-xs text-[var(--neutral-foreground-3)]">{truncate(notification.message, 60)}</p>
      <p className="mt-1 text-xs text-[var(--neutral-foreground-4)]">{formatRelativeTime(notification.created_at)}</p>
    </div>
  );
}

export default NotificationBell;
