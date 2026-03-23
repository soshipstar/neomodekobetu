'use client';

import { cn } from '@/lib/utils';

export interface MaterialIconProps {
  /** Material Symbols icon name (e.g., 'home', 'edit', 'check_circle') */
  name: string;
  /** Size in pixels (default: 20) */
  size?: number;
  /** Filled variant (default: false = outlined) */
  filled?: boolean;
  /** Additional CSS classes */
  className?: string;
}

/**
 * Material Design 3 icon component using Material Symbols Outlined font.
 * See: https://fonts.google.com/icons
 */
export function MaterialIcon({ name, size = 20, filled = false, className }: MaterialIconProps) {
  return (
    <span
      className={cn('material-symbols-outlined select-none', className)}
      style={{
        fontSize: size,
        lineHeight: 1,
        fontVariationSettings: `'FILL' ${filled ? 1 : 0}, 'wght' 400, 'GRAD' 0, 'opsz' ${size}`,
      }}
      aria-hidden="true"
    >
      {name}
    </span>
  );
}

export default MaterialIcon;
