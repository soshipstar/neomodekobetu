'use client';

import { LegalDocumentView } from '@/components/legal/LegalDocumentView';

export default function AiUsagePage() {
  return (
    <div className="mx-auto max-w-3xl p-4 sm:p-6">
      <h1 className="mb-4 text-2xl font-bold text-[var(--neutral-foreground-1)]">
        AI 利用方針
      </h1>
      <LegalDocumentView type="ai_usage" />
    </div>
  );
}
