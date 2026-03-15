'use client';

import type { ReactNode } from 'react';

export default function TabletLayout({ children }: { children: ReactNode }) {
  return (
    <div className="min-h-screen bg-gray-100">
      {/* Fullscreen layout - no sidebar, large touch targets */}
      <header className="flex h-14 items-center justify-between bg-blue-600 px-6 shadow-md">
        <div className="flex items-center gap-3">
          <div className="h-8 w-8 rounded-lg bg-white flex items-center justify-center">
            <span className="text-sm font-bold text-blue-600">K</span>
          </div>
          <span className="text-lg font-bold text-white">KIDURI タブレット</span>
        </div>
        <div className="text-sm text-blue-100">
          {new Date().toLocaleDateString('ja-JP', { year: 'numeric', month: 'long', day: 'numeric', weekday: 'long' })}
        </div>
      </header>
      <main className="p-4 sm:p-6">
        {children}
      </main>
    </div>
  );
}
