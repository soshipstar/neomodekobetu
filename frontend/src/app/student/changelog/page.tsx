'use client';

import { ChangelogView } from '@/components/changelog/ChangelogView';

export default function StudentChangelogPage() {
  return (
    <ChangelogView
      audience="student"
      title="更新のおしらせ"
      description="あたらしくなったところをおしらせします。"
    />
  );
}
