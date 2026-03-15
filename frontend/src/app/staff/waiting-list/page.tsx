'use client';

import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import api from '@/lib/api';
import { Card, CardBody } from '@/components/ui/Card';
import { Button } from '@/components/ui/Button';
import { Badge } from '@/components/ui/Badge';
import { Skeleton } from '@/components/ui/Skeleton';
import { useToast } from '@/components/ui/Toast';
import { UserPlus, Calendar, Pencil, CheckCircle2 } from 'lucide-react';
import { format } from 'date-fns';
import Link from 'next/link';

// ---------------------------------------------------------------------------
// Types
// ---------------------------------------------------------------------------

const DAYS = [
  { key: 'desired_monday', label: '月' },
  { key: 'desired_tuesday', label: '火' },
  { key: 'desired_wednesday', label: '水' },
  { key: 'desired_thursday', label: '木' },
  { key: 'desired_friday', label: '金' },
  { key: 'desired_saturday', label: '土' },
  { key: 'desired_sunday', label: '日' },
] as const;

interface WaitingStudent {
  id: number;
  student_name: string;
  birth_date: string | null;
  guardian_name: string;
  guardian_email: string | null;
  desired_start_date: string | null;
  desired_weekly_count: number | null;
  waiting_notes: string | null;
  desired_monday: boolean;
  desired_tuesday: boolean;
  desired_wednesday: boolean;
  desired_thursday: boolean;
  desired_friday: boolean;
  desired_saturday: boolean;
  desired_sunday: boolean;
  status: string;
  created_at: string;
}

export default function WaitingListPage() {
  const queryClient = useQueryClient();
  const toast = useToast();

  const { data: students = [], isLoading } = useQuery({
    queryKey: ['staff', 'waiting-list'],
    queryFn: async () => {
      const res = await api.get('/api/staff/waiting-list');
      const payload = res.data?.data;
      return Array.isArray(payload) ? payload as WaitingStudent[] : [];
    },
  });

  // Enroll (waiting → active)
  const enrollMutation = useMutation({
    mutationFn: (id: number) => api.put(`/api/staff/waiting-list/${id}`, { status: 'active' }),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['staff', 'waiting-list'] });
      queryClient.invalidateQueries({ queryKey: ['staff', 'students'] });
      toast.success('入所処理しました（希望曜日が参加予定にコピーされました）');
    },
    onError: () => toast.error('入所処理に失敗しました'),
  });

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold text-[var(--neutral-foreground-1)]">待機児童管理</h1>
          <p className="text-sm text-[var(--neutral-foreground-3)]">待機中の児童一覧（{students.length}名）</p>
        </div>
        <Link href="/staff/students">
          <Button variant="outline" size="sm" leftIcon={<UserPlus className="h-4 w-4" />}>
            生徒管理で新規登録
          </Button>
        </Link>
      </div>

      {isLoading ? (
        <div className="space-y-2">{[...Array(4)].map((_, i) => <Skeleton key={i} className="h-20 rounded-lg" />)}</div>
      ) : students.length === 0 ? (
        <Card>
          <CardBody>
            <div className="py-12 text-center text-[var(--neutral-foreground-4)]">
              <UserPlus className="mx-auto mb-3 h-12 w-12" />
              <p className="text-sm">待機児童はいません</p>
              <p className="mt-1 text-xs">生徒管理ページで状態を「待機」にすると、ここに表示されます</p>
            </div>
          </CardBody>
        </Card>
      ) : (
        <div className="overflow-x-auto rounded-lg border border-[var(--neutral-stroke-2)]">
          <table className="w-full text-sm">
            <thead>
              <tr className="border-b border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-3)]">
                <th className="px-3 py-2 text-left text-xs font-semibold text-[var(--neutral-foreground-3)]">児童名</th>
                <th className="px-3 py-2 text-left text-xs font-semibold text-[var(--neutral-foreground-3)]">保護者</th>
                <th className="px-3 py-2 text-left text-xs font-semibold text-[var(--neutral-foreground-3)]">希望開始日</th>
                <th className="px-3 py-2 text-left text-xs font-semibold text-[var(--neutral-foreground-3)]">希望曜日</th>
                <th className="px-3 py-2 text-left text-xs font-semibold text-[var(--neutral-foreground-3)]">備考</th>
                <th className="px-3 py-2 text-left text-xs font-semibold text-[var(--neutral-foreground-3)]">登録日</th>
                <th className="px-3 py-2 text-left text-xs font-semibold text-[var(--neutral-foreground-3)]">操作</th>
              </tr>
            </thead>
            <tbody>
              {students.map((s) => (
                <tr key={s.id} className="border-b border-[var(--neutral-stroke-3)] hover:bg-[var(--neutral-background-3)]">
                  <td className="px-3 py-2 font-medium text-[var(--neutral-foreground-1)]">{s.student_name}</td>
                  <td className="px-3 py-2 text-[var(--neutral-foreground-2)]">{s.guardian_name}</td>
                  <td className="px-3 py-2 text-[var(--neutral-foreground-2)]">
                    {s.desired_start_date ? format(new Date(s.desired_start_date), 'yyyy/MM/dd') : '-'}
                  </td>
                  <td className="px-3 py-2">
                    <div className="flex gap-1">
                      {DAYS.map(({ key, label }) => (
                        <span
                          key={key}
                          className={`inline-flex h-6 w-6 items-center justify-center rounded-full text-[10px] font-bold ${
                            s[key as keyof WaitingStudent]
                              ? 'bg-[var(--brand-80)] text-white'
                              : 'bg-[var(--neutral-background-3)] text-[var(--neutral-foreground-4)]'
                          }`}
                        >
                          {label}
                        </span>
                      ))}
                    </div>
                  </td>
                  <td className="px-3 py-2 text-xs text-[var(--neutral-foreground-3)] max-w-[200px] truncate">
                    {s.waiting_notes || '-'}
                  </td>
                  <td className="px-3 py-2 text-xs text-[var(--neutral-foreground-3)]">
                    {format(new Date(s.created_at), 'yyyy/MM/dd')}
                  </td>
                  <td className="px-3 py-2">
                    <Button
                      variant="primary"
                      size="sm"
                      leftIcon={<CheckCircle2 className="h-3.5 w-3.5" />}
                      onClick={() => {
                        if (confirm(`${s.student_name}を入所させますか？希望曜日が参加予定曜日にコピーされます。`))
                          enrollMutation.mutate(s.id);
                      }}
                      isLoading={enrollMutation.isPending}
                    >
                      入所
                    </Button>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </div>
  );
}
