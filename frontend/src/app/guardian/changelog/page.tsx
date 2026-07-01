'use client';

import { ChangelogView } from '@/components/changelog/ChangelogView';

export default function GuardianChangelogPage() {
  return (
    <ChangelogView
      audience="guardian"
      title="更新のお知らせ"
      description="保護者の皆様に関係する更新内容をお知らせします。"
    />
  );
}
