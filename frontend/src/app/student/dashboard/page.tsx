'use client';

import { useQuery } from '@tanstack/react-query';
import api from '@/lib/api';
import { Card } from '@/components/ui/Card';
import { Badge } from '@/components/ui/Badge';
import { SkeletonCard } from '@/components/ui/Skeleton';
import Link from 'next/link';
import { MaterialIcon } from '@/components/ui/MaterialIcon';

interface StudentDashboardData {
  student: { id: number; student_name: string; grade_level: string };
  classroom: { id: number; classroom_name: string } | null;
  unread_messages: number;
  pending_submissions: number;
  is_scheduled_today: boolean;
  recent_newsletters: { id: number; title: string; published_at: string }[];
}

export default function StudentDashboardPage() {
  const { data, isLoading } = useQuery({
    queryKey: ['student', 'dashboard'],
    queryFn: async () => {
      const response = await api.get<{ data: StudentDashboardData }>('/api/student/dashboard');
      return response.data.data;
    },
  });

  if (isLoading) {
    return (
      <div className="space-y-6">
        <h1 className="text-2xl font-bold text-[var(--neutral-foreground-1)]">マイページ</h1>
        <SkeletonCard />
        <SkeletonCard />
      </div>
    );
  }

  const studentName = data?.student?.student_name || '';

  return (
    <div className="space-y-6">
      {/* Page header */}
      <div>
        <h1 className="text-2xl font-bold text-[var(--neutral-foreground-1)]">マイページ</h1>
        {studentName && (
          <p className="mt-1 text-sm text-[var(--neutral-foreground-3)]">
            ようこそ、{studentName}さん
          </p>
        )}
      </div>

      {/* Menu grid - matches legacy layout */}
      <div className="grid gap-3 grid-cols-2 sm:grid-cols-3">
        <Link href="/student/schedule">
          <Card className="flex flex-col items-center gap-2 p-4 text-center transition-shadow hover:shadow-md cursor-pointer">
            <MaterialIcon name="calendar_month" size={24} className="text-[var(--brand-80)]" />
            <h3 className="text-sm font-semibold text-[var(--neutral-foreground-1)]">スケジュール</h3>
            <p className="text-xs text-[var(--neutral-foreground-3)]">出席日、イベント、休日を確認</p>
          </Card>
        </Link>

        <Link href="/student/chat" className="relative">
          <Card className="flex flex-col items-center gap-2 p-4 text-center transition-shadow hover:shadow-md cursor-pointer relative">
            {data?.unread_messages != null && data.unread_messages > 0 && (
              <Badge variant="danger" className="absolute top-2 right-2">{data.unread_messages}</Badge>
            )}
            <MaterialIcon name="chat" size={24} className="text-[var(--brand-80)]" />
            <h3 className="text-sm font-semibold text-[var(--neutral-foreground-1)]">チャット</h3>
            <p className="text-xs text-[var(--neutral-foreground-3)]">スタッフとメッセージをやり取り</p>
          </Card>
        </Link>

        <Link href="/student/weekly-plans">
          <Card className="flex flex-col items-center gap-2 p-4 text-center transition-shadow hover:shadow-md cursor-pointer">
            <MaterialIcon name="checklist" size={24} className="text-[var(--brand-80)]" />
            <h3 className="text-sm font-semibold text-[var(--neutral-foreground-1)]">週間計画表</h3>
            <p className="text-xs text-[var(--neutral-foreground-3)]">今週の計画を立てる・確認する</p>
          </Card>
        </Link>

        <Link href="/student/submissions" className="relative">
          <Card className="flex flex-col items-center gap-2 p-4 text-center transition-shadow hover:shadow-md cursor-pointer relative">
            {data?.pending_submissions != null && data.pending_submissions > 0 && (
              <Badge variant="danger" className="absolute top-2 right-2">{data.pending_submissions}</Badge>
            )}
            <MaterialIcon name="description" size={24} className="text-[var(--brand-80)]" />
            <h3 className="text-sm font-semibold text-[var(--neutral-foreground-1)]">提出物</h3>
            <p className="text-xs text-[var(--neutral-foreground-3)]">提出物の確認と管理</p>
          </Card>
        </Link>

        <Link href="/student/profile">
          <Card className="flex flex-col items-center gap-2 p-4 text-center transition-shadow hover:shadow-md cursor-pointer">
            <MaterialIcon name="lock" size={24} className="text-[var(--brand-80)]" />
            <h3 className="text-sm font-semibold text-[var(--neutral-foreground-1)]">パスワード変更</h3>
            <p className="text-xs text-[var(--neutral-foreground-3)]">ログインパスワードを変更する</p>
          </Card>
        </Link>
      </div>

      {/* Today's schedule info */}
      {data?.is_scheduled_today && (
        <Card className="border-l-4 border-l-[var(--brand-80)]">
          <div className="p-4 flex items-center gap-3">
            <MaterialIcon name="calendar_month" size={20} className="text-[var(--brand-80)]" />
            <p className="text-sm text-[var(--neutral-foreground-1)]">今日は通所日です</p>
          </div>
        </Card>
      )}

      {/* Pending submissions alert */}
      {data?.pending_submissions != null && data.pending_submissions > 0 && (
        <Card className="border-l-4 border-l-[var(--status-warning-fg)]">
          <div className="p-4 flex items-center justify-between">
            <div className="flex items-center gap-3">
              <MaterialIcon name="warning" size={20} className="text-[var(--status-warning-fg)]" />
              <div>
                <p className="text-sm font-medium text-[var(--neutral-foreground-1)]">
                  未提出の提出物が{data.pending_submissions}件あります
                </p>
              </div>
            </div>
            <Link href="/student/submissions" className="flex items-center gap-1 text-sm text-[var(--brand-80)] hover:underline">
              確認する <MaterialIcon name="arrow_forward" size={16} className="h-4 w-4" />
            </Link>
          </div>
        </Card>
      )}
    </div>
  );
}
