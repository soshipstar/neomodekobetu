'use client';

import { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import api from '@/lib/api';
import { Card, CardHeader, CardTitle, CardBody } from '@/components/ui/Card';
import { SkeletonTable } from '@/components/ui/Skeleton';
import { format } from 'date-fns';
import { ja } from 'date-fns/locale';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import { Button } from '@/components/ui/Button';

interface AbsenceNotification {
  id: number;
  student_id: number;
  absence_date: string;
  reason: string | null;
  created_at: string;
  student?: { id: number; student_name: string };
}

export default function AttendancePage() {
  const [selectedDate, setSelectedDate] = useState(new Date());
  const dateStr = format(selectedDate, 'yyyy-MM-dd');

  const { data: records, isLoading } = useQuery({
    queryKey: ['staff', 'attendance', dateStr],
    queryFn: async () => {
      const response = await api.get<{ data: AbsenceNotification[] }>('/api/staff/attendance', {
        params: { date: dateStr },
      });
      return response.data.data;
    },
  });

  const absentCount = records?.length || 0;

  return (
    <div className="space-y-6">
      <h1 className="text-2xl font-bold text-[var(--neutral-foreground-1)]">出欠管理</h1>

      {/* Date picker */}
      <div className="flex items-center gap-4">
        <Button variant="ghost" size="sm" onClick={() => setSelectedDate(new Date(selectedDate.getTime() - 86400000))}>
          <ChevronLeft className="h-4 w-4" />
        </Button>
        <div className="text-center">
          <input
            type="date"
            value={dateStr}
            onChange={(e) => setSelectedDate(new Date(e.target.value))}
            className="rounded-lg border border-[var(--neutral-stroke-2)] px-3 py-2 text-sm"
          />
          <p className="mt-1 text-sm text-[var(--neutral-foreground-3)]">
            {format(selectedDate, 'yyyy年M月d日(E)', { locale: ja })}
          </p>
        </div>
        <Button variant="ghost" size="sm" onClick={() => setSelectedDate(new Date(selectedDate.getTime() + 86400000))}>
          <ChevronRight className="h-4 w-4" />
        </Button>
      </div>

      {/* Summary */}
      <div className="grid gap-4 sm:grid-cols-1">
        <Card>
          <div className="text-center">
            <p className="text-sm text-[var(--neutral-foreground-3)]">欠席連絡</p>
            <p className="text-3xl font-bold text-[var(--status-danger-fg)]">{absentCount}</p>
          </div>
        </Card>
      </div>

      {/* Attendance table */}
      {isLoading ? (
        <SkeletonTable rows={8} cols={4} />
      ) : (
        <Card padding={false}>
          <div className="overflow-x-auto">
            <table className="min-w-full divide-y divide-[var(--neutral-stroke-2)]">
              <thead className="bg-[var(--neutral-background-2)]">
                <tr>
                  <th className="px-4 py-3 text-left text-xs font-semibold text-[var(--neutral-foreground-3)]">生徒名</th>
                  <th className="px-4 py-3 text-left text-xs font-semibold text-[var(--neutral-foreground-3)]">欠席日</th>
                  <th className="px-4 py-3 text-left text-xs font-semibold text-[var(--neutral-foreground-3)]">理由</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-[var(--neutral-stroke-2)]">
                {records && records.length > 0 ? (
                  records.map((record) => (
                    <tr key={record.id} className="hover:bg-[var(--neutral-background-2)]">
                      <td className="px-4 py-3 text-sm font-medium text-[var(--neutral-foreground-1)]">{record.student?.student_name || '-'}</td>
                      <td className="px-4 py-3 text-sm text-[var(--neutral-foreground-2)]">{record.absence_date}</td>
                      <td className="px-4 py-3 text-sm text-[var(--neutral-foreground-2)]">{record.reason || '-'}</td>
                    </tr>
                  ))
                ) : (
                  <tr>
                    <td colSpan={3} className="px-4 py-8 text-center text-sm text-[var(--neutral-foreground-3)]">
                      欠席連絡がありません
                    </td>
                  </tr>
                )}
              </tbody>
            </table>
          </div>
        </Card>
      )}
    </div>
  );
}
