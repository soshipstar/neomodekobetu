'use client';

import { useState, useMemo } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import api from '@/lib/api';
import { Card, CardBody } from '@/components/ui/Card';
import { Button } from '@/components/ui/Button';
import { Badge } from '@/components/ui/Badge';
import { Skeleton } from '@/components/ui/Skeleton';
import { useToast } from '@/components/ui/Toast';
import { UserPlus, CheckCircle2, Users, Clock } from 'lucide-react';
import { format } from 'date-fns';
import Link from 'next/link';

// ---------------------------------------------------------------------------
// Types & Constants
// ---------------------------------------------------------------------------

const DAYS = [
  { key: 'desired_monday', label: '月', day: 'monday' },
  { key: 'desired_tuesday', label: '火', day: 'tuesday' },
  { key: 'desired_wednesday', label: '水', day: 'wednesday' },
  { key: 'desired_thursday', label: '木', day: 'thursday' },
  { key: 'desired_friday', label: '金', day: 'friday' },
  { key: 'desired_saturday', label: '土', day: 'saturday' },
  { key: 'desired_sunday', label: '日', day: 'sunday' },
] as const;

const DAY_LABELS: Record<string, string> = {
  monday: '月', tuesday: '火', wednesday: '水', thursday: '木',
  friday: '金', saturday: '土', sunday: '日',
};

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

interface DaySummary {
  day: string;
  waiting_count: number;
  active_count: number;
}

interface Summary {
  days: DaySummary[];
  total_waiting: number;
}

// ---------------------------------------------------------------------------
// Main Component
// ---------------------------------------------------------------------------

export default function WaitingListPage() {
  const queryClient = useQueryClient();
  const toast = useToast();

  // Fetch waiting students
  const { data: students = [], isLoading } = useQuery({
    queryKey: ['staff', 'waiting-list'],
    queryFn: async () => {
      const res = await api.get('/api/staff/waiting-list');
      const payload = res.data?.data;
      return Array.isArray(payload) ? payload as WaitingStudent[] : [];
    },
  });

  // Fetch summary (day-by-day counts)
  const { data: summary } = useQuery({
    queryKey: ['staff', 'waiting-list', 'summary'],
    queryFn: async () => {
      const res = await api.get<{ data: Summary }>('/api/staff/waiting-list/summary');
      return res.data.data;
    },
  });

  // Total desired days across all waiting students
  const totalDesiredDays = useMemo(() => {
    return students.reduce((sum, s) => {
      return sum + DAYS.filter(d => s[d.key as keyof WaitingStudent]).length;
    }, 0);
  }, [students]);

  // Enroll mutation
  const enrollMutation = useMutation({
    mutationFn: (id: number) => api.put(`/api/staff/waiting-list/${id}`, { status: 'active' }),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['staff', 'waiting-list'] });
      queryClient.invalidateQueries({ queryKey: ['staff', 'students'] });
      toast.success('入所処理しました（希望曜日が参加予定曜日にコピーされました）');
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

      {/* ================================================================= */}
      {/* Summary Cards                                                     */}
      {/* ================================================================= */}
      {summary && (
        <div className="space-y-3">
          {/* Top-level stats */}
          <div className="grid grid-cols-2 gap-3 sm:grid-cols-3">
            <Card>
              <CardBody>
                <div className="flex items-center gap-3">
                  <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-orange-100">
                    <Clock className="h-5 w-5 text-orange-600" />
                  </div>
                  <div>
                    <p className="text-2xl font-bold text-[var(--neutral-foreground-1)]">{summary.total_waiting}</p>
                    <p className="text-xs text-[var(--neutral-foreground-3)]">待機児童数</p>
                  </div>
                </div>
              </CardBody>
            </Card>
            <Card>
              <CardBody>
                <div className="flex items-center gap-3">
                  <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-blue-100">
                    <Users className="h-5 w-5 text-blue-600" />
                  </div>
                  <div>
                    <p className="text-2xl font-bold text-[var(--neutral-foreground-1)]">
                      {summary.days.reduce((s, d) => s + d.active_count, 0) > 0
                        ? Math.round(summary.days.reduce((s, d) => s + d.active_count, 0) / summary.days.filter(d => d.active_count > 0).length)
                        : 0}
                    </p>
                    <p className="text-xs text-[var(--neutral-foreground-3)]">平均利用者数/日</p>
                  </div>
                </div>
              </CardBody>
            </Card>
            <Card>
              <CardBody>
                <div className="flex items-center gap-3">
                  <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-purple-100">
                    <Clock className="h-5 w-5 text-purple-600" />
                  </div>
                  <div>
                    <p className="text-2xl font-bold text-[var(--neutral-foreground-1)]">{totalDesiredDays}</p>
                    <p className="text-xs text-[var(--neutral-foreground-3)]">合計待機日数</p>
                  </div>
                </div>
              </CardBody>
            </Card>
          </div>

          {/* Day-by-day breakdown */}
          <Card>
            <CardBody>
              <h3 className="text-sm font-semibold text-[var(--neutral-foreground-2)] mb-3">曜日別 利用者数・待機数</h3>
              <div className="grid grid-cols-7 gap-2">
                {summary.days.map((d) => (
                  <div key={d.day} className="rounded-lg border border-[var(--neutral-stroke-2)] p-3 text-center">
                    <p className="text-sm font-bold text-[var(--neutral-foreground-1)] mb-2">
                      {DAY_LABELS[d.day] || d.day}
                    </p>
                    <div className="space-y-1.5">
                      <div>
                        <p className="text-lg font-bold text-blue-600">{d.active_count}</p>
                        <p className="text-[9px] text-[var(--neutral-foreground-4)]">利用者</p>
                      </div>
                      <div className="border-t border-[var(--neutral-stroke-3)] pt-1.5">
                        <p className={`text-lg font-bold ${d.waiting_count > 0 ? 'text-orange-600' : 'text-[var(--neutral-foreground-4)]'}`}>
                          {d.waiting_count}
                        </p>
                        <p className="text-[9px] text-[var(--neutral-foreground-4)]">待機</p>
                      </div>
                    </div>
                  </div>
                ))}
              </div>
            </CardBody>
          </Card>
        </div>
      )}

      {/* ================================================================= */}
      {/* Waiting Students Table                                            */}
      {/* ================================================================= */}
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
