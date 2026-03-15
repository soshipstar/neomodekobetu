'use client';

import { useState, type ReactNode } from 'react';
import { cn } from '@/lib/utils';

export interface TabItem {
  key: string;
  label: string;
  icon?: ReactNode;
  content: ReactNode;
  badge?: number;
}

interface TabsProps {
  items: TabItem[];
  defaultTab?: string;
  activeTab?: string;
  onChange?: (key: string) => void;
  className?: string;
}

export function Tabs({
  items,
  defaultTab,
  activeTab: controlledTab,
  onChange,
  className,
}: TabsProps) {
  const [internalTab, setInternalTab] = useState(defaultTab || items[0]?.key || '');
  const activeTab = controlledTab ?? internalTab;

  const handleTabChange = (key: string) => {
    if (!controlledTab) {
      setInternalTab(key);
    }
    onChange?.(key);
  };

  const activeContent = items.find((item) => item.key === activeTab)?.content;

  return (
    <div className={cn('w-full', className)}>
      {/* Tab Headers - Fluent 2 style */}
      <div className="border-b border-[var(--neutral-stroke-2)]">
        <nav className="-mb-px flex gap-0 overflow-x-auto" aria-label="Tabs">
          {items.map((item) => (
            <button
              key={item.key}
              onClick={() => handleTabChange(item.key)}
              className={cn(
                'flex shrink-0 items-center gap-1.5 border-b-2 px-4 py-2.5 text-sm font-medium transition-colors',
                activeTab === item.key
                  ? 'border-[var(--brand-80)] text-[var(--brand-80)]'
                  : 'border-transparent text-[var(--neutral-foreground-3)] hover:border-[var(--neutral-stroke-1)] hover:text-[var(--neutral-foreground-1)]'
              )}
            >
              {item.icon}
              {item.label}
              {item.badge !== undefined && item.badge > 0 && (
                <span className="ml-1 rounded-md bg-[var(--brand-160)] px-1.5 py-0.5 text-xs font-semibold text-[var(--brand-60)]">
                  {item.badge}
                </span>
              )}
            </button>
          ))}
        </nav>
      </div>

      {/* Tab Content */}
      <div className="pt-4">{activeContent}</div>
    </div>
  );
}

export default Tabs;
