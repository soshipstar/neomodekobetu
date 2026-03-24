'use client';

import { forwardRef, type ButtonHTMLAttributes } from 'react';
import { cn } from '@/lib/utils';
import { MaterialIcon } from '@/components/ui/MaterialIcon';

export type ButtonVariant = 'primary' | 'secondary' | 'danger' | 'ghost' | 'outline' | 'subtle';
export type ButtonSize = 'sm' | 'md' | 'lg';

interface ButtonProps extends ButtonHTMLAttributes<HTMLButtonElement> {
  variant?: ButtonVariant;
  size?: ButtonSize;
  isLoading?: boolean;
  leftIcon?: React.ReactNode;
  rightIcon?: React.ReactNode;
}

const variantStyles: Record<ButtonVariant, string> = {
  primary:
    'bg-[var(--brand-80)] text-white hover:bg-[var(--brand-70)] active:bg-[var(--brand-60)] shadow-[var(--shadow-2)]',
  secondary:
    'bg-[var(--neutral-background-3)] text-[var(--neutral-foreground-1)] hover:bg-[var(--neutral-background-4)] active:bg-[var(--neutral-background-5)]',
  danger:
    'bg-[var(--status-danger-fg)] text-white hover:bg-[#9b0a17] active:bg-[#860814] shadow-[var(--shadow-2)]',
  ghost:
    'text-[var(--neutral-foreground-2)] hover:bg-[var(--neutral-background-3)] active:bg-[var(--neutral-background-4)]',
  outline:
    'border border-[var(--neutral-stroke-1)] text-[var(--neutral-foreground-1)] hover:bg-[var(--neutral-background-3)] active:bg-[var(--neutral-background-4)]',
  subtle:
    'text-[var(--brand-80)] hover:bg-[var(--brand-160)] active:bg-[var(--brand-150)]',
};

const sizeStyles: Record<ButtonSize, string> = {
  sm: 'px-3 py-1 text-xs min-h-[24px]',
  md: 'px-3 py-1.5 text-sm min-h-[32px]',
  lg: 'px-4 py-2 text-sm min-h-[40px]',
};

const Button = forwardRef<HTMLButtonElement, ButtonProps>(
  (
    {
      variant = 'primary',
      size = 'md',
      isLoading = false,
      leftIcon,
      rightIcon,
      className,
      children,
      disabled,
      ...props
    },
    ref
  ) => {
    return (
      <button
        ref={ref}
        className={cn(
          'inline-flex items-center justify-center gap-1.5 rounded-md font-medium transition-all duration-100',
          'focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--brand-80)]',
          'disabled:cursor-not-allowed disabled:opacity-40',
          variantStyles[variant],
          sizeStyles[size],
          className
        )}
        disabled={disabled || isLoading}
        {...props}
      >
        {isLoading ? (
          <MaterialIcon name="progress_activity" size={16} className="animate-spin" />
        ) : (
          leftIcon
        )}
        {children}
        {!isLoading && rightIcon}
      </button>
    );
  }
);

Button.displayName = 'Button';

export { Button };
export default Button;
