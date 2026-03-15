'use client';

import type { ReactNode } from 'react';
import { AuthenticatedLayout } from '@/components/layout/AuthenticatedLayout';

export default function AdminLayout({ children }: { children: ReactNode }) {
  return (
    <AuthenticatedLayout requiredUserType="admin">
      {children}
    </AuthenticatedLayout>
  );
}
