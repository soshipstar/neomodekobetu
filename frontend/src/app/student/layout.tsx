'use client';

import type { ReactNode } from 'react';
import { AuthenticatedLayout } from '@/components/layout/AuthenticatedLayout';

export default function StudentLayout({ children }: { children: ReactNode }) {
  return (
    <AuthenticatedLayout requiredUserType="student">
      {children}
    </AuthenticatedLayout>
  );
}
