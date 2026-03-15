'use client';

import type { ReactNode } from 'react';
import { AuthenticatedLayout } from '@/components/layout/AuthenticatedLayout';

export default function GuardianLayout({ children }: { children: ReactNode }) {
  return (
    <AuthenticatedLayout requiredUserType="guardian">
      {children}
    </AuthenticatedLayout>
  );
}
