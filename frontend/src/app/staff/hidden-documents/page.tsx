'use client';

import { Card, CardBody } from '@/components/ui/Card';
import { FileText } from 'lucide-react';

export default function HiddenDocumentsPage() {
  return (
    <div className="space-y-6">
      <h1 className="text-2xl font-bold text-[var(--neutral-foreground-1)]">書類表示管理</h1>

      <Card>
        <CardBody>
          <div className="flex flex-col items-center py-12">
            <FileText className="mb-3 h-12 w-12 text-[var(--neutral-foreground-4)]" />
            <p className="text-sm font-medium text-[var(--neutral-foreground-2)]">この機能は現在利用できません</p>
            <p className="mt-1 text-xs text-[var(--neutral-foreground-3)]">
              書類の表示/非表示管理は各書類の個別ページから行ってください
            </p>
          </div>
        </CardBody>
      </Card>
    </div>
  );
}
