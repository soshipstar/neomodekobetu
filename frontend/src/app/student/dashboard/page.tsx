'use client';

import { useQuery } from '@tanstack/react-query';
import api from '@/lib/api';
import { Card, CardHeader, CardTitle, CardBody } from '@/components/ui/Card';
import { Badge } from '@/components/ui/Badge';
import { SkeletonCard } from '@/components/ui/Skeleton';
import { MessageCircle, ArrowRight } from 'lucide-react';
import Link from 'next/link';

interface StudentDashboard {
  student_name: string;
  unread_messages: number;
  today_schedule: string | null;
  announcements: { id: number; title: string; created_at: string }[];
}

export default function StudentDashboardPage() {
  const { data, isLoading } = useQuery({
    queryKey: ['student', 'dashboard'],
    queryFn: async () => {
      const response = await api.get<{ data: StudentDashboard }>('/api/student/dashboard');
      return response.data.data;
    },
  });

  if (isLoading) {
    return <div className="space-y-6"><h1 className="text-2xl font-bold text-gray-900">ホーム</h1><SkeletonCard /><SkeletonCard /></div>;
  }

  return (
    <div className="space-y-6">
      <h1 className="text-2xl font-bold text-gray-900">
        {data?.student_name ? `${data.student_name}さん、こんにちは!` : 'ホーム'}
      </h1>

      {/* Quick actions */}
      <div className="grid gap-3 sm:grid-cols-2">
        <Link href="/student/chat">
          <Card className="flex items-center justify-between transition-shadow hover:shadow-md">
            <div className="flex items-center gap-3">
              <MessageCircle className="h-5 w-5 text-blue-600" />
              <span className="text-sm font-medium text-gray-700">チャット</span>
            </div>
            {data?.unread_messages && data.unread_messages > 0 ? (
              <Badge variant="danger">{data.unread_messages}件</Badge>
            ) : (
              <ArrowRight className="h-4 w-4 text-gray-400" />
            )}
          </Card>
        </Link>
      </div>

      {data?.today_schedule && (
        <Card>
          <CardHeader><CardTitle>今日の予定</CardTitle></CardHeader>
          <CardBody><p className="text-sm text-gray-600">{data.today_schedule}</p></CardBody>
        </Card>
      )}
    </div>
  );
}
