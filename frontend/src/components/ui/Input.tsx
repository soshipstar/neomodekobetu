'use client';

import { forwardRef, type InputHTMLAttributes } from 'react';
import { cn } from '@/lib/utils';

interface InputProps extends InputHTMLAttributes<HTMLInputElement> {
  label?: string;
  error?: string;
  helperText?: string;
}

const Input = forwardRef<HTMLInputElement, InputProps>(
  ({ label, error, helperText, className, id, ...props }, ref) => {
    const inputId = id || label?.replace(/\s+/g, '-').toLowerCase();

    return (
      <div className="w-full">
        {label && (
          <label
            htmlFor={inputId}
            className="mb-1 block text-sm font-medium text-[var(--neutral-foreground-1)]"
          >
            {label}
          </label>
        )}
        <input
          ref={ref}
          id={inputId}
          className={cn(
            'block w-full rounded-md border border-[var(--neutral-stroke-1)] bg-[var(--neutral-background-1)] px-3 py-1.5 text-sm text-[var(--neutral-foreground-1)] placeholder-[var(--neutral-foreground-4)]',
            'transition-all duration-100',
            'hover:border-[var(--neutral-foreground-3)]',
            'focus:border-[var(--brand-80)] focus:outline-none focus:ring-1 focus:ring-[var(--brand-80)]',
            'disabled:cursor-not-allowed disabled:bg-[var(--neutral-background-3)] disabled:text-[var(--neutral-foreground-disabled)]',
            error && 'border-[var(--status-danger-fg)] focus:border-[var(--status-danger-fg)] focus:ring-[var(--status-danger-fg)]',
            className
          )}
          {...props}
        />
        {error && (
          <p className="mt-1 text-xs text-[var(--status-danger-fg)]">{error}</p>
        )}
        {helperText && !error && (
          <p className="mt-1 text-xs text-[var(--neutral-foreground-3)]">{helperText}</p>
        )}
      </div>
    );
  }
);

Input.displayName = 'Input';

export { Input };
export default Input;
