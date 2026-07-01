'use client';

import { ChangelogView } from '@/components/changelog/ChangelogView';

export default function StaffChangelogPage() {
  return (
    <ChangelogView
      audience="all"
      title="更新履歴"
      description="きづりの追加・改善・修正の履歴です。すべての更新内容を表示しています。"
    />
  );
}
