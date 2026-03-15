'use client';

import type { HTMLAttributes, ReactNode } from 'react';
import { cn } from '@/lib/utils';

export type BadgeVariant =
  | 'default'
  | 'primary'
  | 'success'
  | 'warning'
  | 'danger'
  | 'info';

interface BadgeProps extends HTMLAttributes<HTMLSpanElement> {
  variant?: BadgeVariant;
  children: ReactNode;
  dot?: boolean;
}

const variantStyles: Record<BadgeVariant, string> = {
  default: 'bg-[var(--neutral-background-4)] text-[var(--neutral-foreground-2)]',
  primary: 'bg-[var(--brand-160)] text-[var(--brand-60)]',
  success: 'bg-[var(--status-success-bg)] text-[var(--status-success-fg)]',
  warning: 'bg-[var(--status-warning-bg)] text-[var(--status-warning-fg)]',
  danger: 'bg-[var(--status-danger-bg)] text-[var(--status-danger-fg)]',
  info: 'bg-[var(--status-info-bg)] text-[var(--status-info-fg)]',
};

const dotStyles: Record<BadgeVariant, string> = {
  default: 'bg-[var(--neutral-foreground-3)]',
  primary: 'bg-[var(--brand-80)]',
  success: 'bg-[var(--status-success-fg)]',
  warning: 'bg-[var(--status-warning-fg)]',
  danger: 'bg-[var(--status-danger-fg)]',
  info: 'bg-[var(--status-info-fg)]',
};

export function Badge({
  variant = 'default',
  children,
  dot = false,
  className,
  ...props
}: BadgeProps) {
  return (
    <span
      className={cn(
        'inline-flex items-center gap-1 rounded-md px-2 py-0.5 text-xs font-medium',
        variantStyles[variant],
        className
      )}
      {...props}
    >
      {dot && (
        <span className={cn('h-1.5 w-1.5 rounded-full', dotStyles[variant])} />
      )}
      {children}
    </span>
  );
}

export default Badge;
