'use client';

import { useEffect } from 'react';
import { useRouter } from 'next/navigation';

// Redirect to the integrated student-chats page
export default function StudentChatDetailRedirect() {
  const router = useRouter();
  useEffect(() => {
    router.replace('/staff/student-chats');
  }, [router]);
  return null;
}
