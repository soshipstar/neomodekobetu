'use client';

import { useMemo, useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { format } from 'date-fns';
import { ja } from 'date-fns/locale';
import api from '@/lib/api';
import { useToast } from '@/components/ui/Toast';

/**
 * タブレット用 出欠確認画面。
 *
 * 主要機能:
 *  - 在籍生徒一覧 (本人画面用タップしやすい大ボタン)
 *  - 各生徒を「出席 (チェックイン)」「退所 (チェックアウト)」できる
 *  - 現在出席中の生徒数を表示
 *  - 学年・名前による検索/フィルタ
 *
 * バックエンド:
 *  - GET  /api/tablet/students          : 教室の在籍生徒一覧
 *  - POST /api/tablet/students/{id}/check-in
 *  - POST /api/tablet/students/{id}/check-out
 *  - GET  /api/tablet/present-students  : 当日出席中の生徒
 */

interface Student {
  id: number;
  student_name: string;
  grade_level: string | null;
  classroom_id: number;
}

interface PresentStudent {
  id: number;
  student_name: string;
  classroom_id: number;
  check_in_time: string;
}

interface TodayStudent {
  id: number;
  name: string;
  grade_level: string | null;
  grade_group: string;
  type: 'regular' | 'makeup' | 'additional';
  is_absent: boolean;
  is_checked_in?: boolean;
  is_checked_out?: boolean;
  check_in_time?: string | null;
  check_out_time?: string | null;
}

const GRADE_LABELS: Record<string, string> = {
  preschool: '未就学',
  elementary_1: '小1', elementary_2: '小2', elementary_3: '小3',
  elementary_4: '小4', elementary_5: '小5', elementary_6: '小6',
  junior_high_1: '中1', junior_high_2: '中2', junior_high_3: '中3',
  high_school_1: '高1', high_school_2: '高2', high_school_3: '高3',
};

export default function TabletAttendancePage() {
  const queryClient = useQueryClient();
  const toast = useToast();
  const [search, setSearch] = useState('');

  const { data: students = [], isLoading } = useQuery({
    queryKey: ['tablet', 'students'],
    queryFn: async () => {
      const res = await api.get<{ data: Student[] }>('/api/tablet/students');
      return res.data.data;
    },
  });

  const { data: present = [], isLoading: presentLoading } = useQuery({
    queryKey: ['tablet', 'present-students'],
    queryFn: async () => {
      const res = await api.get<{ data: PresentStudent[] }>('/api/tablet/present-students');
      return res.data.data;
    },
    refetchInterval: 30 * 1000, // 30 秒ごとに自動更新 (他端末からの check-in を反映)
  });

  // 本日の予定一覧 (通常 / 振替 / 加算 + 欠席フラグ + 出欠状況)
  const { data: todayList = [], isLoading: todayLoading } = useQuery({
    queryKey: ['tablet', 'today-students'],
    queryFn: async () => {
      const res = await api.get<{ data: TodayStudent[] }>('/api/tablet/today-students');
      return res.data.data;
    },
    refetchInterval: 30 * 1000,
  });

  const presentIds = useMemo(() => new Set(present.map((p) => p.id)), [present]);
  const presentTimeById = useMemo(() => {
    const m = new Map<number, string>();
    for (const p of present) m.set(p.id, p.check_in_time);
    return m;
  }, [present]);

  const filtered = useMemo(() => {
    const q = search.trim().toLowerCase();
    if (!q) return students;
    return students.filter((s) => s.student_name.toLowerCase().includes(q));
  }, [students, search]);

  const checkInMutation = useMutation({
    mutationFn: (studentId: number) => api.post(`/api/tablet/students/${studentId}/check-in`),
    onSuccess: (res) => {
      toast.success(res.data?.message ?? '出席を登録しました');
      queryClient.invalidateQueries({ queryKey: ['tablet', 'present-students'] });
      queryClient.invalidateQueries({ queryKey: ['tablet', 'today-students'] });
    },
    onError: (err: { response?: { data?: { message?: string } } }) => {
      toast.error(err?.response?.data?.message ?? '出席登録に失敗しました');
    },
  });

  const checkOutMutation = useMutation({
    mutationFn: (studentId: number) => api.post(`/api/tablet/students/${studentId}/check-out`),
    onSuccess: (res) => {
      toast.success(res.data?.message ?? '退所を登録しました');
      queryClient.invalidateQueries({ queryKey: ['tablet', 'present-students'] });
      queryClient.invalidateQueries({ queryKey: ['tablet', 'today-students'] });
    },
    onError: (err: { response?: { data?: { message?: string } } }) => {
      toast.error(err?.response?.data?.message ?? '退所登録に失敗しました');
    },
  });

  const handleToggle = (s: Student) => {
    if (presentIds.has(s.id)) {
      if (window.confirm(`${s.student_name}さんの退所を登録しますか？`)) {
        checkOutMutation.mutate(s.id);
      }
    } else {
      checkInMutation.mutate(s.id);
    }
  };

  const today = new Date();
  const expectedCount = todayList.filter((t) => !t.is_absent).length;
  const absentCount   = todayList.filter((t) =>  t.is_absent).length;
  const arrivedCount  = todayList.filter((t) => t.is_checked_in).length;

  // 本日リストを表示用にソート: 種別(通常→振替→加算) → 名前
  const sortedTodayList = [...todayList].sort((a, b) => {
    const typeOrder: Record<string, number> = { regular: 0, makeup: 1, additional: 2 };
    const ta = typeOrder[a.type] ?? 9;
    const tb = typeOrder[b.type] ?? 9;
    if (ta !== tb) return ta - tb;
    return a.name.localeCompare(b.name, 'ja');
  });

  return (
    <div className="space-y-6">
      {/* サマリー */}
      <div className="rounded-xl bg-white p-6 shadow-md">
        <div className="flex flex-wrap items-center justify-between gap-4">
          <div>
            <h1 className="text-3xl font-bold text-[var(--neutral-foreground-1)]">出欠確認</h1>
            <p className="mt-1 text-lg text-[var(--neutral-foreground-3)]">
              {format(today, 'yyyy年M月d日(E)', { locale: ja })}
            </p>
          </div>
          <div className="flex flex-wrap items-center gap-4 sm:gap-6">
            <div className="text-center">
              <p className="text-xs text-[var(--neutral-foreground-3)]">在籍</p>
              <p className="text-3xl font-bold text-[var(--neutral-foreground-1)]">{students.length}</p>
            </div>
            <div className="text-center">
              <p className="text-xs text-[var(--neutral-foreground-3)]">本日予定</p>
              <p className="text-3xl font-bold text-[var(--brand-60)]">{expectedCount}</p>
            </div>
            <div className="text-center">
              <p className="text-xs text-[var(--neutral-foreground-3)]">出席中</p>
              <p className="text-3xl font-bold text-green-600">{arrivedCount}</p>
            </div>
            <div className="text-center">
              <p className="text-xs text-[var(--neutral-foreground-3)]">欠席</p>
              <p className="text-3xl font-bold text-orange-600">{absentCount}</p>
            </div>
          </div>
        </div>
      </div>

      {/* 本日の利用者一覧 (= 出欠連絡の対象) */}
      <div className="rounded-xl bg-white p-6 shadow-md">
        <h2 className="mb-3 text-xl font-bold text-[var(--neutral-foreground-1)]">
          本日の利用者
          <span className="ml-2 text-sm font-normal text-[var(--neutral-foreground-3)]">
            ({sortedTodayList.length}名)
          </span>
        </h2>
        {todayLoading ? (
          <p className="py-6 text-center text-[var(--neutral-foreground-4)]">読み込み中…</p>
        ) : sortedTodayList.length === 0 ? (
          <p className="py-6 text-center text-[var(--neutral-foreground-4)]">
            本日の利用予定はありません
          </p>
        ) : (
          <div className="grid grid-cols-2 gap-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5">
            {sortedTodayList.map((t) => {
              // ステータスバッジ
              const statusColor = t.is_absent
                ? 'bg-orange-100 text-orange-700 border-orange-400'
                : t.is_checked_in
                  ? 'bg-green-100 text-green-700 border-green-500'
                  : 'bg-gray-100 text-gray-600 border-gray-300';
              const statusLabel = t.is_absent
                ? '欠席連絡済'
                : t.is_checked_in
                  ? (t.is_checked_out ? '退所済' : '出席中')
                  : '未到着';
              const typeBadge = t.type === 'makeup' ? '振替' : t.type === 'additional' ? '加算' : null;
              return (
                <div
                  key={t.id}
                  className={`flex flex-col gap-1 rounded-lg border-2 p-3 text-sm ${statusColor}`}
                >
                  <div className="flex items-start justify-between gap-1">
                    <span className="font-bold leading-tight">{t.name}</span>
                    {typeBadge && (
                      <span className="rounded bg-white px-1.5 py-0.5 text-[10px] font-bold text-[var(--neutral-foreground-2)]">
                        {typeBadge}
                      </span>
                    )}
                  </div>
                  <div className="flex items-center justify-between">
                    <span className="text-xs">{statusLabel}</span>
                    {t.check_in_time && (
                      <span className="text-[10px]">
                        {format(new Date(t.check_in_time), 'HH:mm')}
                      </span>
                    )}
                  </div>
                </div>
              );
            })}
          </div>
        )}
      </div>

      {/* 検索 */}
      <div className="rounded-xl bg-white p-4 shadow-md">
        <input
          type="text"
          value={search}
          onChange={(e) => setSearch(e.target.value)}
          placeholder="名前で検索…"
          className="block w-full rounded-lg border-2 border-[var(--neutral-stroke-2)] bg-white px-4 py-3 text-xl"
        />
      </div>

      {/* 生徒タイル */}
      <div className="rounded-xl bg-white p-6 shadow-md">
        {isLoading || presentLoading ? (
          <p className="py-12 text-center text-xl text-[var(--neutral-foreground-4)]">読み込み中…</p>
        ) : filtered.length === 0 ? (
          <p className="py-12 text-center text-xl text-[var(--neutral-foreground-4)]">
            該当する利用者がいません
          </p>
        ) : (
          <div className="grid grid-cols-2 gap-4 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5">
            {filtered.map((s) => {
              const isPresent = presentIds.has(s.id);
              const checkedAt = presentTimeById.get(s.id);
              const gradeLabel = s.grade_level ? GRADE_LABELS[s.grade_level] ?? s.grade_level : '';
              const isPending =
                (checkInMutation.isPending && checkInMutation.variables === s.id) ||
                (checkOutMutation.isPending && checkOutMutation.variables === s.id);
              return (
                <button
                  key={s.id}
                  onClick={() => handleToggle(s)}
                  disabled={isPending}
                  className={`flex flex-col items-center justify-center gap-2 rounded-2xl border-4 p-6 text-center transition-all
                    ${isPresent
                      ? 'border-green-500 bg-green-50 text-green-800'
                      : 'border-[var(--neutral-stroke-2)] bg-white text-[var(--neutral-foreground-1)] hover:border-[var(--brand-80)] hover:bg-blue-50'}
                    ${isPending ? 'opacity-50' : ''}
                  `}
                >
                  <span className="text-2xl font-bold leading-tight">{s.student_name}</span>
                  {gradeLabel && (
                    <span className="rounded-full bg-[var(--neutral-background-4)] px-3 py-1 text-sm font-medium text-[var(--neutral-foreground-3)]">
                      {gradeLabel}
                    </span>
                  )}
                  {isPresent ? (
                    <div className="mt-2 space-y-1">
                      <p className="text-base font-bold text-green-700">✓ 出席中</p>
                      {checkedAt && (
                        <p className="text-xs text-green-700">
                          {format(new Date(checkedAt), 'HH:mm')} 着
                        </p>
                      )}
                      <p className="text-xs text-[var(--neutral-foreground-3)]">タップで退所</p>
                    </div>
                  ) : (
                    <p className="mt-2 text-base font-bold text-[var(--brand-60)]">タップで出席</p>
                  )}
                </button>
              );
            })}
          </div>
        )}
      </div>
    </div>
  );
}
