'use client';

import { useEffect } from 'react';
import { useRouter } from 'next/navigation';
import { format } from 'date-fns';

/**
 * /tablet/activity へのアクセスは /tablet/activity/edit にリダイレクト
 */
export default function TabletActivityRedirect() {
  const router = useRouter();

  useEffect(() => {
    const today = format(new Date(), 'yyyy-MM-dd');
    router.replace(`/tablet/activity/edit?date=${today}`);
  }, [router]);

  return (
    <div className="py-12 text-center text-xl text-gray-400">
      リダイレクト中...
    </div>
  );
}
