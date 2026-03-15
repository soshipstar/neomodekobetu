'use client';

import { useParams } from 'next/navigation';
import { useQuery } from '@tanstack/react-query';
import api from '@/lib/api';
import { Card, CardHeader, CardTitle, CardBody } from '@/components/ui/Card';
import { Badge } from '@/components/ui/Badge';
import { Tabs, type TabItem } from '@/components/ui/Tabs';
import { SkeletonCard, SkeletonText } from '@/components/ui/Skeleton';
import { formatDate } from '@/lib/utils';
import {
  User,
  Calendar,
  FileText,
  BarChart3,
  ArrowLeftRight,
  ClipboardList,
} from 'lucide-react';
import Link from 'next/link';
import type { Student } from '@/types/user';

const statusLabels: Record<string, string> = {
  active: '在籍', trial: '体験', short_term: '短期', withdrawn: '退所', waiting: '待機',
};

const gradeLabels: Record<string, string> = {
  preschool: '未就学', elementary: '小学生', middle: '中学生', high: '高校生', other: 'その他',
};

const dayLabels = ['月', '火', '水', '木', '金', '土', '日'];
const dayKeys = [
  'scheduled_monday', 'scheduled_tuesday', 'scheduled_wednesday',
  'scheduled_thursday', 'scheduled_friday', 'scheduled_saturday', 'scheduled_sunday',
] as const;

export default function StudentDetailPage() {
  const params = useParams();
  const studentId = Number(params.id);

  const { data: student, isLoading } = useQuery({
    queryKey: ['staff', 'student', studentId],
    queryFn: async () => {
      const response = await api.get<{ data: Student }>(`/api/staff/students/${studentId}`);
      return response.data.data;
    },
    enabled: !!studentId,
  });

  if (isLoading) {
    return (
      <div className="space-y-4">
        <SkeletonCard />
        <SkeletonCard />
      </div>
    );
  }

  if (!student) {
    return <div className="py-12 text-center text-[var(--neutral-foreground-3)]">生徒が見つかりません</div>;
  }

  const tabItems: TabItem[] = [
    {
      key: 'info',
      label: '基本情報',
      icon: <User className="h-4 w-4" />,
      content: <StudentInfo student={student} />,
    },
    {
      key: 'schedule',
      label: '通所曜日',
      icon: <Calendar className="h-4 w-4" />,
      content: <StudentSchedule student={student} />,
    },
  ];

  const actionLinks = [
    { href: `/staff/students/${studentId}/support-plan`, label: '個別支援計画', icon: FileText },
    { href: `/staff/students/${studentId}/monitoring`, label: 'モニタリング', icon: BarChart3 },
    { href: `/staff/students/${studentId}/kakehashi`, label: 'かけはし', icon: ArrowLeftRight },
  ];

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex items-start justify-between">
        <div>
          <h1 className="text-2xl font-bold text-[var(--neutral-foreground-1)]">{student.student_name}</h1>
          <div className="mt-1 flex items-center gap-2">
            <Badge variant={student.status === 'active' ? 'success' : 'default'}>
              {statusLabels[student.status] || student.status}
            </Badge>
            <span className="text-sm text-[var(--neutral-foreground-3)]">{gradeLabels[student.grade_level]}</span>
          </div>
        </div>
      </div>

      {/* Quick action links */}
      <div className="grid gap-3 sm:grid-cols-3">
        {actionLinks.map((link) => (
          <Link key={link.href} href={link.href}>
            <Card className="flex items-center gap-3 transition-shadow hover:shadow-[var(--shadow-8)]">
              <link.icon className="h-5 w-5 text-[var(--brand-80)]" />
              <span className="text-sm font-medium text-[var(--neutral-foreground-2)]">{link.label}</span>
            </Card>
          </Link>
        ))}
      </div>

      {/* Tabs */}
      <Tabs items={tabItems} />
    </div>
  );
}

function StudentInfo({ student }: { student: Student }) {
  return (
    <Card>
      <CardBody>
        <dl className="grid gap-4 sm:grid-cols-2">
          <div>
            <dt className="text-xs font-medium text-[var(--neutral-foreground-3)]">生徒名</dt>
            <dd className="mt-1 text-sm text-[var(--neutral-foreground-1)]">{student.student_name}</dd>
          </div>
          <div>
            <dt className="text-xs font-medium text-[var(--neutral-foreground-3)]">生年月日</dt>
            <dd className="mt-1 text-sm text-[var(--neutral-foreground-1)]">
              {student.birth_date ? formatDate(student.birth_date) : '-'}
            </dd>
          </div>
          <div>
            <dt className="text-xs font-medium text-[var(--neutral-foreground-3)]">学年区分</dt>
            <dd className="mt-1 text-sm text-[var(--neutral-foreground-1)]">{gradeLabels[student.grade_level]}</dd>
          </div>
          <div>
            <dt className="text-xs font-medium text-[var(--neutral-foreground-3)]">保護者</dt>
            <dd className="mt-1 text-sm text-[var(--neutral-foreground-1)]">{student.guardian?.full_name || '-'}</dd>
          </div>
        </dl>
      </CardBody>
    </Card>
  );
}

function StudentSchedule({ student }: { student: Student }) {
  return (
    <Card>
      <CardBody>
        <div className="flex gap-2">
          {dayLabels.map((day, i) => {
            const isScheduled = student[dayKeys[i]];
            return (
              <div
                key={day}
                className={`flex h-10 w-10 items-center justify-center rounded-full text-sm font-medium ${
                  isScheduled
                    ? 'bg-[var(--brand-160)] text-[var(--brand-80)]'
                    : 'bg-[var(--neutral-background-3)] text-[var(--neutral-foreground-4)]'
                }`}
              >
                {day}
              </div>
            );
          })}
        </div>
      </CardBody>
    </Card>
  );
}
