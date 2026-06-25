import type { ReactNode } from 'react';

export default function AuthLayout({ children }: { children: ReactNode }) {
  return (
    <div className="flex min-h-screen items-center justify-center bg-[var(--neutral-background-2)] p-4">
      <div className="w-full max-w-sm">
        {/* Logo */}
        <div className="mb-8 flex flex-col items-center">
          {/* eslint-disable-next-line @next/next/no-img-element */}
          <img
            src="/assets/icons/icon-192x192.png"
            alt="KIDURI"
            width={64}
            height={64}
            className="mb-3 h-16 w-16 rounded-lg shadow-[var(--shadow-4)]"
          />
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
