import type { ReactNode } from 'react';

export default function AuthLayout({ children }: { children: ReactNode }) {
  return (
    <div className="flex min-h-screen items-center justify-center bg-[var(--neutral-background-2)] p-4">
      <div className="w-full max-w-sm">
        {/* Logo */}
        <div className="mb-8 flex flex-col items-center">
          <div className="mb-3 flex h-12 w-12 items-center justify-center rounded-lg bg-[var(--brand-80)] shadow-[var(--shadow-4)]">
            <span className="text-xl font-bold text-white">K</span>
          </div>
          <h1 className="text-xl font-semibold text-[var(--neutral-foreground-1)]">KIDURI</h1>
          <p className="text-sm text-[var(--neutral-foreground-3)]">個別支援連絡帳システム</p>
        </div>
        {/* Content card */}
        <div className="rounded-lg border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-1)] p-8 shadow-[var(--shadow-4)]">
          {children}
        </div>
      </div>
    </div>
  );
}
