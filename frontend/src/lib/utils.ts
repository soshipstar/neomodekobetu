import { clsx, type ClassValue } from 'clsx';
import { twMerge } from 'tailwind-merge';
import { format, formatDistanceToNow, parseISO } from 'date-fns';
import { ja } from 'date-fns/locale';

/**
 * Merge class names with Tailwind CSS conflict resolution
 */
export function cn(...inputs: ClassValue[]): string {
  return twMerge(clsx(inputs));
}

/**
 * Format date in Japanese style
 * @example formatDate('2026-03-10') => '2026年3月10日'
 * @example formatDate('2026-03-10', 'yyyy/MM/dd') => '2026/03/10'
 */
export function formatDate(
  date: string | Date,
  formatStr: string = 'yyyy年M月d日'
): string {
  const d = typeof date === 'string' ? parseISO(date) : date;
  return format(d, formatStr, { locale: ja });
}

/**
 * Format date with time in Japanese style
 * @example formatDateTime('2026-03-10T14:30:00') => '2026年3月10日 14:30'
 */
export function formatDateTime(date: string | Date): string {
  const d = typeof date === 'string' ? parseISO(date) : date;
  return format(d, 'yyyy年M月d日 HH:mm', { locale: ja });
}

/**
 * Format relative time in Japanese
 * @example formatRelativeTime('2026-03-10T14:30:00') => '3時間前'
 */
export function formatRelativeTime(date: string | Date | null | undefined): string {
  if (!date) return '';
  const d = typeof date === 'string' ? parseISO(date) : date;
  if (isNaN(d.getTime())) return '';
  return formatDistanceToNow(d, { addSuffix: true, locale: ja });
}

/**
 * Truncate text to a maximum length
 */
export function truncate(text: string, maxLength: number = 50): string {
  if (text.length <= maxLength) return text;
  return text.slice(0, maxLength) + '...';
}

/**
 * Format file size in human-readable format
 */
export function formatFileSize(bytes: number): string {
  if (bytes === 0) return '0 B';
  const units = ['B', 'KB', 'MB', 'GB'];
  const i = Math.floor(Math.log(bytes) / Math.log(1024));
  return `${(bytes / Math.pow(1024, i)).toFixed(i === 0 ? 0 : 1)} ${units[i]}`;
}

/**
 * Get Japanese day of week
 */
export function getJapaneseDayOfWeek(date: string | Date): string {
  const d = typeof date === 'string' ? parseISO(date) : date;
  return format(d, 'EEEE', { locale: ja });
}

/**
 * Generate initials from a name (supports Japanese)
 */
export function getInitials(name: string): string {
  if (!name) return '';
  const parts = name.split(/\s+/);
  if (parts.length >= 2) {
    return parts[0].charAt(0) + parts[1].charAt(0);
  }
  return name.slice(0, 2);
}

/**
 * Normalize literal \n / \r\n sequences in DB text to real newlines.
 * Use this for any text from the database that might contain escaped newlines.
 */
export function nl(text: string | null | undefined): string {
  if (!text) return '';
  return text.replace(/\\r\\n/g, '\n').replace(/\\n/g, '\n').replace(/\\r/g, '');
}
