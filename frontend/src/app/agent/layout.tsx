'use client';

import type { ReactNode } from 'react';
import { AuthenticatedLayout } from '@/components/layout/AuthenticatedLayout';

export default function AgentLayout({ children }: { children: ReactNode }) {
  return (
    <AuthenticatedLayout requiredUserType="agent">
      {children}
    </AuthenticatedLayout>
  );
}
