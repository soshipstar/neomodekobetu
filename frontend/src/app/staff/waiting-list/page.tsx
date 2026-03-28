'use client';

import { useState, useMemo, useCallback } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import api from '@/lib/api';
import { Card, CardBody } from '@/components/ui/Card';
import { Button } from '@/components/ui/Button';
import { Modal } from '@/components/ui/Modal';
import { Skeleton } from '@/components/ui/Skeleton';
import { useToast } from '@/components/ui/Toast';
import { format } from 'date-fns';
import Link from 'next/link';
import { MaterialIcon } from '@/components/ui/MaterialIcon';

// ---------------------------------------------------------------------------
// Types & Constants
// ---------------------------------------------------------------------------

const DAYS = [
  { key: 'desired_monday', label: '月', day: 'monday', dow: 1 },
  { key: 'desired_tuesday', label: '火', day: 'tuesday', dow: 2 },
  { key: 'desired_wednesday', label: '水', day: 'wednesday', dow: 3 },
  { key: 'desired_thursday', label: '木', day: 'thursday', dow: 4 },
  { key: 'desired_friday', label: '金', day: 'friday', dow: 5 },
  { key: 'desired_saturday', label: '土', day: 'saturday', dow: 6 },
  { key: 'desired_sunday', label: '日', day: 'sunday', dow: 0 },
] as const;

const DAY_LABELS: Record<string, string> = {
  monday: '月', tuesday: '火', wednesday: '水', thursday: '木',
  friday: '金', saturday: '土', sunday: '日',
};

const DAY_LONG_LABELS: Record<string, string> = {
  monday: '月曜日', tuesday: '火曜日', wednesday: '水曜日', thursday: '木曜日',
  friday: '金曜日', saturday: '土曜日', sunday: '日曜日',
};

const GRADE_LABELS: Record<string, string> = {
  preschool: '未就学',
  elementary: '小学生',
  elementary_1: '小1', elementary_2: '小2', elementary_3: '小3',
  elementary_4: '小4', elementary_5: '小5', elementary_6: '小6',
  junior_high: '中学生',
  junior_high_1: '中1', junior_high_2: '中2', junior_high_3: '中3',
  high_school: '高校生',
  high_school_1: '高1', high_school_2: '高2', high_school_3: '高3',
};

interface WaitingStudent {
  id: number;
  student_name: string;
  birth_date: string | null;
  grade_level: string | null;
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
  max_capacity: number;
  is_open: boolean;
  available: number;
}

interface DayStudentEntry {
  id: number;
  student_name: string;
  grade_level: string | null;
  status?: string;
  desired_start_date?: string | null;
  guardian_name: string;
}

interface Summary {
  days: DaySummary[];
  total_waiting: number;
  day_students: Record<string, { enrolled: DayStudentEntry[]; waiting: DayStudentEntry[] }>;
}

// ---------------------------------------------------------------------------
// Main Component
// ---------------------------------------------------------------------------

export default function WaitingListPage() {
  const queryClient = useQueryClient();
  const toast = useToast();
  const [selectedDay, setSelectedDay] = useState<string>('monday');
  const [showCapacitySettings, setShowCapacitySettings] = useState(false);
  const [capacityForm, setCapacityForm] = useState<Record<number, { max_capacity: number; is_open: boolean }>>({});

  // Fetch waiting students
  const { data: students = [], isLoading } = useQuery({
    queryKey: ['staff', 'waiting-list'],
    queryFn: async () => {
      const res = await api.get('/api/staff/waiting-list');
      const payload = res.data?.data;
      return Array.isArray(payload) ? payload as WaitingStudent[] : [];
    },
  });

  // Fetch summary (day-by-day counts + capacity + day student lists)
  const { data: summary } = useQuery({
    queryKey: ['staff', 'waiting-list', 'summary'],
    queryFn: async () => {
      const res = await api.get<{ data: Summary }>('/api/staff/waiting-list/summary');
      return res.data.data;
    },
  });

  // Initialize capacity form from summary data
  const initCapacityForm = useCallback(() => {
    if (!summary) return;
    const form: Record<number, { max_capacity: number; is_open: boolean }> = {};
    for (const d of summary.days) {
      const dow = DAYS.find(dd => dd.day === d.day)?.dow ?? 0;
      form[dow] = { max_capacity: d.max_capacity, is_open: d.is_open };
    }
    setCapacityForm(form);
  }, [summary]);

  // Withdrawal modal state
  const [withdrawalTarget, setWithdrawalTarget] = useState<WaitingStudent | null>(null);
  const [withdrawalReason, setWithdrawalReason] = useState('');

  // Enroll mutation
  const enrollMutation = useMutation({
    mutationFn: (id: number) => api.put(`/api/staff/waiting-list/${id}`, { status: 'active' }),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['staff', 'waiting-list'] });
      queryClient.invalidateQueries({ queryKey: ['staff', 'students'] });
      toast.success('入所処理しました（希望曜日が利用曜日に自動設定されました）');
    },
    onError: () => toast.error('入所処理に失敗しました'),
  });

  // Withdrawal mutation
  const withdrawalMutation = useMutation({
    mutationFn: ({ id, reason }: { id: number; reason: string }) =>
      api.put(`/api/staff/waiting-list/${id}`, { status: 'pre_withdrawal', withdrawal_reason: reason }),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['staff', 'waiting-list'] });
      toast.success('入所前辞退として処理しました');
      setWithdrawalTarget(null);
      setWithdrawalReason('');
    },
    onError: () => toast.error('辞退処理に失敗しました'),
  });

  // Save capacity mutation
  const capacityMutation = useMutation({
    mutationFn: (capacities: { day_of_week: number; max_capacity: number; is_open: boolean }[]) =>
      api.put('/api/staff/waiting-list-capacity', { capacities }),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['staff', 'waiting-list', 'summary'] });
      toast.success('営業日・定員設定を更新しました');
      setShowCapacitySettings(false);
    },
    onError: () => toast.error('設定の保存に失敗しました'),
  });

  // Group students by desired_start_date (legacy behavior)
  const groupedStudents = useMemo(() => {
    const groups: Record<string, WaitingStudent[]> = {};
    for (const s of students) {
      const dateKey = s.desired_start_date || '9999-99-99';
      if (!groups[dateKey]) groups[dateKey] = [];
      groups[dateKey].push(s);
    }
    // Sort keys chronologically
    return Object.entries(groups).sort(([a], [b]) => a.localeCompare(b));
  }, [students]);

  const handleSaveCapacity = () => {
    const capacities = Object.entries(capacityForm).map(([dow, val]) => ({
      day_of_week: Number(dow),
      max_capacity: val.max_capacity,
      is_open: val.is_open,
    }));
    capacityMutation.mutate(capacities);
  };

  // Selected day detail data
  const selectedDaySummary = summary?.days.find(d => d.day === selectedDay);
  const selectedDayStudents = summary?.day_students?.[selectedDay];

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold text-[var(--neutral-foreground-1)]">待機児童管理</h1>
          <p className="text-sm text-[var(--neutral-foreground-3)]">空き状況の確認と待機児童の管理</p>
        </div>
        <Link href="/staff/students">
          <Button variant="outline" size="sm" leftIcon={<MaterialIcon name="person_add" size={16} />}>
            生徒管理で新規登録
          </Button>
        </Link>
      </div>

      {/* ================================================================= */}
      {/* Capacity Settings (営業日・定員設定)                               */}
      {/* ================================================================= */}
      <Card>
        <CardBody>
          <div className="flex items-center justify-between mb-3">
            <h3 className="text-sm font-semibold text-[var(--neutral-foreground-2)]">営業日・定員設定</h3>
            <Button
              variant="outline"
              size="sm"
              leftIcon={<MaterialIcon name="settings" size={14} />}
              onClick={() => { setShowCapacitySettings(!showCapacitySettings); if (!showCapacitySettings) initCapacityForm(); }}
            >
              {showCapacitySettings ? '閉じる' : '設定変更'}
            </Button>
          </div>
          {showCapacitySettings && (
            <div>
              <div className="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-7 gap-3 mb-4">
                {DAYS.map(({ label, dow, day }) => (
                  <div key={day} className="rounded-lg border border-[var(--neutral-stroke-2)] p-3 text-center">
                    <p className={`text-sm font-bold mb-2 ${day === 'sunday' ? 'text-red-500' : day === 'saturday' ? 'text-[var(--brand-80)]' : 'text-[var(--neutral-foreground-1)]'}`}>
                      {DAY_LONG_LABELS[day]}
                    </p>
                    <label className="flex items-center justify-center gap-1.5 text-xs mb-2 cursor-pointer">
                      <input
                        type="checkbox"
                        checked={capacityForm[dow]?.is_open ?? true}
                        onChange={(e) => setCapacityForm(prev => ({
                          ...prev,
                          [dow]: { ...prev[dow], is_open: e.target.checked },
                        }))}
                      />
                      営業日
                    </label>
                    <div>
                      <label className="text-[10px] text-[var(--neutral-foreground-3)]">定員</label>
                      <input
                        type="number"
                        min={0}
                        max={100}
                        value={capacityForm[dow]?.max_capacity ?? 10}
                        onChange={(e) => setCapacityForm(prev => ({
                          ...prev,
                          [dow]: { ...prev[dow], max_capacity: Number(e.target.value) },
                        }))}
                        className="w-16 mx-auto block rounded border border-[var(--neutral-stroke-2)] px-2 py-1 text-center text-sm"
                      />
                      <span className="text-[10px] text-[var(--neutral-foreground-3)]">名</span>
                    </div>
                  </div>
                ))}
              </div>
              <div className="text-right">
                <Button
                  variant="primary"
                  size="sm"
                  leftIcon={<MaterialIcon name="save" size={14} />}
                  onClick={handleSaveCapacity}
                  isLoading={capacityMutation.isPending}
                >
                  設定を保存
                </Button>
              </div>
            </div>
          )}
        </CardBody>
      </Card>

      {/* ================================================================= */}
      {/* Day-by-day availability (曜日別 空き状況)                          */}
      {/* ================================================================= */}
      {summary && (
        <Card>
          <CardBody>
            <h3 className="text-sm font-semibold text-[var(--neutral-foreground-2)] mb-1">曜日別 空き状況</h3>
            <p className="text-[10px] text-[var(--neutral-foreground-4)] mb-3">カードをクリックすると、その曜日の利用者一覧が表示されます</p>
            <div className="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-7 gap-2 mb-4">
              {summary.days.map((d) => {
                const isOpen = d.is_open;
                const isFull = isOpen && d.available === 0;
                const isSelected = d.day === selectedDay;
                return (
                  <div
                    key={d.day}
                    onClick={() => setSelectedDay(d.day)}
                    className={`rounded-lg border-2 p-3 text-center cursor-pointer transition-all hover:shadow-md ${
                      !isOpen
                        ? 'border-[var(--neutral-stroke-3)] opacity-60 bg-[var(--neutral-background-3)]'
                        : isFull
                          ? 'border-red-300 bg-red-50'
                          : 'border-green-300 bg-green-50'
                    } ${isSelected ? 'ring-2 ring-[var(--brand-80)] ring-offset-1' : ''}`}
                  >
                    <p className={`text-lg font-bold mb-1 ${
                      d.day === 'sunday' ? 'text-red-500' : d.day === 'saturday' ? 'text-[var(--brand-80)]' : 'text-[var(--neutral-foreground-1)]'
                    }`}>
                      {DAY_LABELS[d.day]}
                    </p>
                    {isOpen ? (
                      <>
                        <p className="text-[10px] text-[var(--neutral-foreground-3)]">
                          利用中: {d.active_count} / {d.max_capacity}名
                        </p>
                        <p className={`text-sm font-bold mt-1 px-2 py-0.5 rounded inline-block ${
                          d.available > 0 ? 'bg-green-500 text-white' : 'bg-red-500 text-white'
                        }`}>
                          {d.available > 0 ? `空き${d.available}名` : '満員'}
                        </p>
                        {d.waiting_count > 0 && (
                          <p className="text-xs text-orange-500 mt-1 font-medium">待機 {d.waiting_count}名</p>
                        )}
                      </>
                    ) : (
                      <p className="text-sm font-bold mt-1 px-2 py-0.5 rounded inline-block bg-gray-400 text-white">休業日</p>
                    )}
                  </div>
                );
              })}
            </div>

            {/* Day tabs */}
            <div className="flex flex-wrap gap-1 mb-3 border-t border-[var(--neutral-stroke-3)] pt-3">
              {DAYS.map(({ day, label }) => (
                <button
                  key={day}
                  onClick={() => setSelectedDay(day)}
                  className={`px-3 py-1.5 rounded-lg border-2 text-sm font-semibold transition-all ${
                    selectedDay === day
                      ? 'bg-[var(--brand-80)] text-white border-[var(--brand-80)]'
                      : `border-[var(--neutral-stroke-3)] bg-[var(--neutral-background-3)] hover:border-[var(--brand-80)] ${
                        day === 'sunday' ? 'text-red-500' : day === 'saturday' ? 'text-[var(--brand-80)]' : ''
                      }`
                  }`}
                >
                  {DAY_LONG_LABELS[day]}
                </button>
              ))}
            </div>

            {/* Day detail content */}
            {selectedDaySummary && (
              <div>
                {!selectedDaySummary.is_open ? (
                  <p className="text-center py-6 text-[var(--neutral-foreground-4)]">この曜日は休業日です</p>
                ) : (
                  <>
                    <h4 className="text-sm font-semibold mb-2">
                      {DAY_LONG_LABELS[selectedDay]}の利用者 ({selectedDayStudents?.enrolled.length ?? 0}名)
                    </h4>
                    {selectedDayStudents && selectedDayStudents.enrolled.length > 0 ? (
                      <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-2 mb-3">
                        {selectedDayStudents.enrolled.map((s) => (
                          <div key={s.id} className="bg-[var(--neutral-background-3)] px-3 py-2 rounded-lg text-sm">
                            <span className="font-semibold">{s.student_name}</span>
                            {s.status === 'trial' && (
                              <span className="ml-1 text-[10px] px-1.5 py-0.5 rounded bg-orange-100 text-orange-600">体験</span>
                            )}
                            {s.status === 'short_term' && (
                              <span className="ml-1 text-[10px] px-1.5 py-0.5 rounded bg-[var(--brand-160)] text-[var(--brand-80)]">短期</span>
                            )}
                            <p className="text-xs text-[var(--neutral-foreground-3)]">
                              {GRADE_LABELS[s.grade_level || ''] || s.grade_level || '-'}
                            </p>
                          </div>
                        ))}
                      </div>
                    ) : (
                      <p className="text-sm text-[var(--neutral-foreground-4)] py-3">利用者がいません</p>
                    )}

                    {selectedDayStudents && selectedDayStudents.waiting.length > 0 && (
                      <div className="border-t border-dashed border-[var(--neutral-stroke-3)] pt-3 mt-3">
                        <h4 className="text-sm font-semibold text-orange-500 mb-2">
                          待機中 ({selectedDayStudents.waiting.length}名)
                        </h4>
                        <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-2">
                          {selectedDayStudents.waiting.map((s) => (
                            <div key={s.id} className="bg-[var(--neutral-background-3)] px-3 py-2 rounded-lg text-sm border-l-[3px] border-orange-400">
                              <span className="font-semibold">{s.student_name}</span>
                              <p className="text-xs text-[var(--neutral-foreground-3)]">
                                {GRADE_LABELS[s.grade_level || ''] || s.grade_level || '-'}
                                {s.desired_start_date && (
                                  <> / 希望: {format(new Date(s.desired_start_date), 'M/d')}</>
                                )}
                              </p>
                            </div>
                          ))}
                        </div>
                      </div>
                    )}
                  </>
                )}
              </div>
            )}
          </CardBody>
        </Card>
      )}

      {/* ================================================================= */}
      {/* Summary Stats                                                     */}
      {/* ================================================================= */}
      {summary && (
        <div className="grid grid-cols-2 gap-3 sm:grid-cols-3">
          <Card>
            <CardBody>
              <div className="flex items-center gap-3">
                <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-orange-100">
                  <MaterialIcon name="schedule" size={20} className="text-orange-600" />
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
                <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-[var(--brand-160)]">
                  <MaterialIcon name="group" size={20} className="text-[var(--brand-80)]" />
                </div>
                <div>
                  <p className="text-2xl font-bold text-[var(--neutral-foreground-1)]">
                    {summary.days.filter(d => d.is_open).reduce((s, d) => s + d.active_count, 0) > 0
                      ? Math.round(summary.days.filter(d => d.is_open).reduce((s, d) => s + d.active_count, 0) / summary.days.filter(d => d.is_open).length)
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
                <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-green-100">
                  <MaterialIcon name="check_circle" size={20} className="text-green-600" />
                </div>
                <div>
                  <p className="text-2xl font-bold text-[var(--neutral-foreground-1)]">
                    {summary.days.filter(d => d.is_open && d.available > 0).length}
                  </p>
                  <p className="text-xs text-[var(--neutral-foreground-3)]">空きのある曜日数</p>
                </div>
              </div>
            </CardBody>
          </Card>
        </div>
      )}

      {/* ================================================================= */}
      {/* Waiting Students List (入所希望日別一覧)                           */}
      {/* ================================================================= */}
      <Card>
        <CardBody>
          <h3 className="text-base font-semibold text-[var(--neutral-foreground-1)] mb-3">
            待機児童一覧 ({students.length}名)
          </h3>

          {isLoading ? (
            <div className="space-y-2">{[...Array(4)].map((_, i) => <Skeleton key={i} className="h-20 rounded-lg" />)}</div>
          ) : students.length === 0 ? (
            <div className="py-12 text-center text-[var(--neutral-foreground-4)]">
              <MaterialIcon name="person_add" size={48} className="mx-auto mb-3" />
              <p className="text-sm">現在、待機児童はいません。</p>
              <p className="mt-1 text-xs">生徒管理ページで状態を「待機」にすると、ここに表示されます</p>
            </div>
          ) : (
            <>
              {/* 曜日別待機人数サマリー */}
              {summary && (
                <div className="mb-4">
                  <h4 className="text-xs font-semibold text-[var(--neutral-foreground-3)] mb-2">曜日別待機人数</h4>
                  <div className="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-7 gap-2">
                    {summary.days.map((d) => (
                      <div
                        key={d.day}
                        className={`rounded-lg bg-[var(--neutral-background-3)] p-2 text-center ${!d.is_open ? 'opacity-50' : ''}`}
                      >
                        <p className={`text-sm font-bold mb-0.5 ${
                          d.day === 'sunday' ? 'text-red-500' : d.day === 'saturday' ? 'text-[var(--brand-80)]' : ''
                        }`}>
                          {DAY_LABELS[d.day]}
                        </p>
                        {d.is_open ? (
                          d.waiting_count > 0 ? (
                            <p className="text-lg font-bold text-orange-500">{d.waiting_count}名</p>
                          ) : (
                            <p className="text-xs text-[var(--neutral-foreground-4)]">待機なし</p>
                          )
                        ) : (
                          <p className="text-xs text-[var(--neutral-foreground-4)]">休業日</p>
                        )}
                      </div>
                    ))}
                  </div>
                </div>
              )}

              {/* 入所希望日別一覧 (legacy grouped display) */}
              <h4 className="text-xs font-semibold text-[var(--neutral-foreground-3)] mb-2">入所希望日別一覧</h4>
              {groupedStudents.map(([dateKey, groupStudents]) => (
                <div key={dateKey} className="mb-4 rounded-lg border border-[var(--neutral-stroke-2)] overflow-hidden">
                  {/* Group header */}
                  <div className="bg-orange-500 text-white px-3 py-2 text-sm font-semibold">
                    {dateKey === '9999-99-99'
                      ? '入所希望日 未定'
                      : `${format(new Date(dateKey), 'yyyy年M月d日')} 入所希望`}
                    <span className="ml-2 opacity-80">({groupStudents.length}名)</span>
                  </div>
                  <div className="p-3 space-y-2">
                    {groupStudents.map((s) => (
                      <div
                        key={s.id}
                        className="flex flex-wrap items-center gap-3 py-2 border-b border-[var(--neutral-stroke-3)] last:border-b-0"
                      >
                        {/* Name + grade */}
                        <div className="min-w-[120px]">
                          <p className="font-semibold text-sm text-[var(--neutral-foreground-1)]">{s.student_name}</p>
                          <p className="text-xs text-[var(--neutral-foreground-3)]">
                            {GRADE_LABELS[s.grade_level || ''] || s.grade_level || '-'}
                          </p>
                        </div>
                        {/* Desired weekly count */}
                        <div className="min-w-[80px]">
                          {s.desired_weekly_count ? (
                            <span className="bg-[var(--brand-80)] text-white px-2 py-0.5 rounded text-xs font-semibold">
                              週{s.desired_weekly_count}回
                            </span>
                          ) : (
                            <span className="text-xs text-[var(--neutral-foreground-4)]">回数未定</span>
                          )}
                        </div>
                        {/* Desired days */}
                        <div className="flex gap-1 flex-1">
                          {DAYS.map(({ key, label }) =>
                            s[key as keyof WaitingStudent] ? (
                              <span key={key} className="bg-[var(--brand-80)] text-white px-2 py-0.5 rounded text-xs font-medium">
                                {label}
                              </span>
                            ) : null
                          )}
                        </div>
                        {/* Notes */}
                        {s.waiting_notes && (
                          <div className="basis-full text-xs text-[var(--neutral-foreground-3)] pl-1 mt-0.5">
                            備考: {s.waiting_notes}
                          </div>
                        )}
                        {/* Action buttons */}
                        <div className="flex gap-2">
                          <Button
                            variant="primary"
                            size="sm"
                            leftIcon={<MaterialIcon name="check_circle" size={14} />}
                            onClick={() => {
                              if (confirm(`${s.student_name}さんを入所させますか？\n希望曜日が利用曜日に自動設定されます。`))
                                enrollMutation.mutate(s.id);
                            }}
                            isLoading={enrollMutation.isPending}
                          >
                            入所
                          </Button>
                          <Button
                            variant="outline"
                            size="sm"
                            leftIcon={<MaterialIcon name="cancel" size={14} />}
                            onClick={() => {
                              setWithdrawalTarget(s);
                              setWithdrawalReason('');
                            }}
                          >
                            辞退
                          </Button>
                        </div>
                      </div>
                    ))}
                  </div>
                </div>
              ))}
            </>
          )}
        </CardBody>
      </Card>

      {/* Withdrawal modal */}
      <Modal
        isOpen={!!withdrawalTarget}
        onClose={() => setWithdrawalTarget(null)}
        title="入所前辞退"
        size="sm"
      >
        {withdrawalTarget && (
          <div className="space-y-4">
            <p className="text-sm text-[var(--neutral-foreground-2)]">
              <span className="font-semibold">{withdrawalTarget.student_name}</span>さんを入所前辞退にします。
            </p>
            <div>
              <label className="mb-1 block text-xs font-medium text-[var(--neutral-foreground-2)]">
                辞退理由
              </label>
              <textarea
                rows={3}
                value={withdrawalReason}
                onChange={(e) => setWithdrawalReason(e.target.value)}
                placeholder="辞退理由を入力してください..."
                className="block w-full rounded-md border border-[var(--neutral-stroke-1)] bg-[var(--neutral-background-1)] px-3 py-1.5 text-sm text-[var(--neutral-foreground-1)] placeholder-[var(--neutral-foreground-4)] focus:border-[var(--brand-80)] focus:outline-none focus:ring-1 focus:ring-[var(--brand-80)]"
              />
            </div>
            <div className="flex justify-end gap-2">
              <Button variant="secondary" size="sm" onClick={() => setWithdrawalTarget(null)}>
                キャンセル
              </Button>
              <Button
                variant="danger"
                size="sm"
                leftIcon={<MaterialIcon name="cancel" size={14} />}
                onClick={() => withdrawalMutation.mutate({ id: withdrawalTarget.id, reason: withdrawalReason })}
                isLoading={withdrawalMutation.isPending}
              >
                辞退確定
              </Button>
            </div>
          </div>
        )}
      </Modal>
    </div>
  );
}
