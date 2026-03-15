'use client';

import type { ReactNode } from 'react';
import { AuthenticatedLayout } from '@/components/layout/AuthenticatedLayout';

export default function StaffLayout({ children }: { children: ReactNode }) {
  return (
    <AuthenticatedLayout requiredUserType={['staff', 'admin']}>
      {children}
    </AuthenticatedLayout>
  );
}
