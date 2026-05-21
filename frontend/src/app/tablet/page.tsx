'use client';

import { useState, useMemo, useEffect } from 'react';
import Link from 'next/link';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import api from '@/lib/api';
import { useToast } from '@/components/ui/Toast';
import { Modal } from '@/components/ui/Modal';
import { format, startOfMonth, endOfMonth, getDay, getDaysInMonth, addMonths, subMonths, isSameDay, isToday } from 'date-fns';
import { ja } from 'date-fns/locale';
import { MaterialIcon } from '@/components/ui/MaterialIcon';

interface ActivityRecord {
  id: number;
  activity_name: string;
  common_activity: string | null;
  record_date: string;
  participant_count: number;
  staff: { id: number; full_name: string } | null;
  student_records: Array<{
    id: number;
    student_id: number;
    student: { id: number; student_name: string };
  }>;
}

const DAY_HEADERS = ['日', '月', '火', '水', '木', '金', '土'];

interface QuickRoom {
  id: number;
  student?: { student_name?: string } | null;
  guardian?: { full_name?: string } | null;
}

interface AttendanceStudent {
  id: number;
  name: string;
  grade_group: '未就学' | '小学生' | '中学生' | '高校生';
  type: 'regular' | 'makeup' | 'additional';
  is_absent: boolean;
  chat_room_id: number | null;
  notified?: 'arrival' | 'departure' | null;
}

const GRADE_ORDER: AttendanceStudent['grade_group'][] = ['未就学', '小学生', '中学生', '高校生'];

function typeLabel(type: AttendanceStudent['type']): string {
  switch (type) {
    case 'regular': return '通常';
    case 'makeup': return '振替';
    case 'additional': return '加算';
  }
}

export default function TabletHomePage() {
  const queryClient = useQueryClient();
  const toast = useToast();
  const [selectedDate, setSelectedDate] = useState(new Date());
  const [calendarMonth, setCalendarMonth] = useState(new Date());

  // Quick broadcast (これから帰ります / 到着しました)
  const [quickModal, setQuickModal] = useState<'departure' | 'arrival' | null>(null);
  const [quickBody, setQuickBody] = useState('');
  const [quickRoomIds, setQuickRoomIds] = useState<Set<number>>(new Set());
  const [quickSending, setQuickSending] = useState(false);

  // 本日の利用者一覧 (per-student の通知状態は手元キャッシュで補強)
  const [notifiedLocal, setNotifiedLocal] = useState<Record<number, 'arrival' | 'departure'>>({});
  const [perStudentSending, setPerStudentSending] = useState<Record<string, boolean>>({});

  const selectedDateStr = format(selectedDate, 'yyyy-MM-dd');
  const calYear = calendarMonth.getFullYear();
  const calMonth = calendarMonth.getMonth() + 1;

  // 活動一覧
  const { data: activities = [], isLoading } = useQuery({
    queryKey: ['tablet', 'activities', selectedDateStr],
    queryFn: async () => {
      const res = await api.get<{ data: ActivityRecord[] }>(`/api/tablet/activities/${selectedDateStr}`);
      return res.data.data;
    },
  });

  // 活動がある日付一覧
  const { data: activeDates = [] } = useQuery({
    queryKey: ['tablet', 'active-dates', calYear, calMonth],
    queryFn: async () => {
      const res = await api.get<{ data: string[] }>('/api/tablet/active-dates', {
        params: { year: calYear, month: calMonth },
      });
      return res.data.data;
    },
  });

  // 削除
  const deleteMutation = useMutation({
    mutationFn: (id: number) => api.delete(`/api/tablet/activities/${id}`),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['tablet', 'activities', selectedDateStr] });
      queryClient.invalidateQueries({ queryKey: ['tablet', 'active-dates'] });
      toast.success('活動を削除しました');
    },
    onError: () => toast.error('削除に失敗しました'),
  });

  const handleDelete = (id: number) => {
    if (confirm('この活動を削除してもよろしいですか？')) {
      deleteMutation.mutate(id);
    }
  };

  // 保護者チャットルーム一覧 (クイック通知の送信先選択用)
  const { data: chatRooms = [] } = useQuery<QuickRoom[]>({
    queryKey: ['tablet', 'chat', 'rooms'],
    queryFn: async () => {
      const res = await api.get<{ data: QuickRoom[] }>('/api/tablet/chat/rooms');
      return res.data.data || [];
    },
  });

  // 本日の利用者一覧
  const todayStr = format(new Date(), 'yyyy-MM-dd');
  const { data: attendanceData, isLoading: attendanceLoading } = useQuery<{ students: AttendanceStudent[] }>({
    queryKey: ['tablet', 'attendance', todayStr],
    queryFn: async () => {
      const res = await api.get<{ data: { activities: unknown[]; students: AttendanceStudent[] } }>(
        '/api/tablet/dashboard/attendance',
        { params: { date: todayStr } }
      );
      // BE はそのままレスポンスを返す。data.data.students を取り出す
      const payload = res.data?.data ?? res.data;
      return {
        students: Array.isArray((payload as { students?: AttendanceStudent[] })?.students)
          ? (payload as { students: AttendanceStudent[] }).students
          : [],
      };
    },
  });
  const attendance = attendanceData?.students || [];

  // BE からの通知済 (notified) + ローカルキャッシュをマージ
  const mergedNotified = useMemo(() => {
    const merged: Record<number, 'arrival' | 'departure'> = { ...notifiedLocal };
    attendance.forEach((s) => {
      if (s.notified && !merged[s.id]) merged[s.id] = s.notified;
    });
    return merged;
  }, [attendance, notifiedLocal]);

  const attendanceByGrade = useMemo(() => {
    const grouped: Record<string, AttendanceStudent[]> = {};
    GRADE_ORDER.forEach((g) => { grouped[g] = []; });
    attendance.forEach((s) => { if (grouped[s.grade_group]) grouped[s.grade_group].push(s); });
    return grouped;
  }, [attendance]);

  // バグ報告: 到着・帰宅ボタンは誤タップで誤送信が起きやすいため、
  // 押下時に「本当によろしいですか？」確認ダイアログを挟む。
  const handlePerStudentNotify = async (
    studentId: number,
    chatRoomId: number,
    action: 'arrival' | 'departure',
    studentName?: string,
  ) => {
    const label = action === 'arrival' ? '到着しました' : 'これから帰ります';
    const who = studentName ?? 'この生徒';
    if (!window.confirm(`${who}さんの保護者に「${label}」を送信します。\n\n本当によろしいですか？`)) {
      return;
    }
    const key = `${studentId}-${action}`;
    setPerStudentSending((p) => ({ ...p, [key]: true }));
    try {
      await api.post('/api/tablet/chat/quick-broadcast', {
        action,
        room_ids: [chatRoomId],
      });
      setNotifiedLocal((p) => ({ ...p, [studentId]: action }));
      toast.success(`${label} を送信しました`);
    } catch (err: unknown) {
      const e = err as { response?: { data?: { message?: string } } };
      toast.error(e?.response?.data?.message || '送信に失敗しました');
    } finally {
      setPerStudentSending((p) => {
        const next = { ...p };
        delete next[key];
        return next;
      });
    }
  };

  // クイック通知テンプレート (教室別の保存値を BE から取得)
  type TplShape = { body: string; enabled: boolean };
  const { data: quickTemplates } = useQuery<{ arrival: TplShape; departure: TplShape } | null>({
    queryKey: ['tablet', 'chat', 'quick-broadcast-templates'],
    queryFn: async () => {
      const res = await api.get<{ data: { arrival: TplShape; departure: TplShape } }>(
        '/api/tablet/chat/quick-broadcast-templates'
      );
      return res.data.data;
    },
  });

  // モーダル open 時に body と送信先 (デフォルトは空 = 誤送信防止) を初期化。
  // ユーザーは「全選択」ボタンで一括選択、「全解除」で個別解除する運用。
  useEffect(() => {
    if (quickModal && quickTemplates) {
      setQuickBody(quickTemplates[quickModal].body);
      setQuickRoomIds(new Set());
    }
    if (!quickModal) {
      setQuickBody('');
      setQuickRoomIds(new Set());
    }
  }, [quickModal, quickTemplates]);

  const sendQuickBroadcast = async () => {
    if (!quickModal) return;
    if (quickRoomIds.size === 0) {
      toast.error('送信先を選択してください');
      return;
    }
    if (!quickBody.trim()) {
      toast.error('送信メッセージを入力してください');
      return;
    }
    setQuickSending(true);
    try {
      const res = await api.post('/api/tablet/chat/quick-broadcast', {
        action: quickModal,
        room_ids: Array.from(quickRoomIds),
        custom_body: quickBody,
      });
      toast.success(res.data?.message || '送信しました');
      setQuickModal(null);
    } catch (err: unknown) {
      const e = err as { response?: { data?: { message?: string } } };
      toast.error(e?.response?.data?.message || '送信に失敗しました');
    } finally {
      setQuickSending(false);
    }
  };

  // カレンダー生成
  const calendarDays = useMemo(() => {
    const first = startOfMonth(calendarMonth);
    const firstDow = getDay(first);
    const daysInMonth = getDaysInMonth(calendarMonth);
    const days: Array<{ day: number; date: Date; dateStr: string } | null> = [];

    for (let i = 0; i < firstDow; i++) {
      days.push(null);
    }
    for (let d = 1; d <= daysInMonth; d++) {
      const date = new Date(calYear, calMonth - 1, d);
      days.push({ day: d, date, dateStr: format(date, 'yyyy-MM-dd') });
    }
    return days;
  }, [calendarMonth, calYear, calMonth]);

  return (
    <div className="space-y-6">
      {/* クイック通知ボタン (これから帰ります / 到着しました) + 保護者チャット導線 */}
      <div className="flex flex-wrap items-center gap-2 rounded-xl bg-white p-3 shadow-md sm:p-4">
        <button
          type="button"
          onClick={() => setQuickModal('departure')}
          className="flex flex-1 items-center justify-center gap-2 rounded-lg bg-amber-500 px-3 py-3 text-sm font-bold text-white hover:bg-amber-600 sm:flex-none sm:px-5 sm:text-base"
          title="保護者全員に「これから帰ります」を送信"
        >
          <MaterialIcon name="directions_bus" size={20} />
          <span>これから帰ります</span>
        </button>
        <button
          type="button"
          onClick={() => setQuickModal('arrival')}
          className="flex flex-1 items-center justify-center gap-2 rounded-lg bg-blue-600 px-3 py-3 text-sm font-bold text-white hover:bg-blue-700 sm:flex-none sm:px-5 sm:text-base"
          title="保護者全員に「到着しました」を送信"
        >
          <MaterialIcon name="check_circle" size={20} />
          <span>到着しました</span>
        </button>
        <Link
          href="/tablet/chat"
          className="flex flex-1 items-center justify-center gap-2 rounded-lg border border-[var(--neutral-stroke-2)] bg-white px-3 py-3 text-sm font-bold text-[var(--neutral-foreground-2)] hover:bg-[var(--neutral-background-3)] sm:flex-none sm:px-5 sm:text-base"
          title="保護者チャット"
        >
          <MaterialIcon name="chat" size={20} />
          <span>保護者チャット</span>
        </Link>
      </div>

      {/* 本日の利用者一覧 (誰に通知済か可視化) */}
      <div className="rounded-xl bg-white p-3 shadow-md sm:p-5 lg:p-6">
        <div className="mb-3 flex flex-wrap items-center justify-between gap-2 sm:mb-4">
          <h2 className="text-base font-bold sm:text-lg lg:text-xl">
            本日の利用者一覧
            <span className="ml-2 text-xs font-normal text-[var(--neutral-foreground-3)] sm:text-sm">
              ({attendance.length}名)
            </span>
          </h2>
          <div className="flex items-center gap-3 text-xs text-[var(--neutral-foreground-3)] sm:text-sm">
            <span className="flex items-center gap-1">
              <MaterialIcon name="how_to_reg" size={14} className="text-[var(--status-success-fg)]" />
              出席 {attendance.filter((s) => !s.is_absent).length}
            </span>
            <span className="flex items-center gap-1">
              <MaterialIcon name="person_off" size={14} className="text-[var(--status-danger-fg)]" />
              欠席 {attendance.filter((s) => s.is_absent).length}
            </span>
          </div>
        </div>

        {attendanceLoading ? (
          <p className="py-6 text-center text-sm text-[var(--neutral-foreground-4)]">読み込み中...</p>
        ) : attendance.length === 0 ? (
          <p className="py-6 text-center text-sm text-[var(--neutral-foreground-4)]">
            本日の参加予定者はいません
          </p>
        ) : (
          <div className="space-y-4">
            {GRADE_ORDER.map((grade) => {
              const students = attendanceByGrade[grade];
              if (!students || students.length === 0) return null;
              return (
                <div key={grade}>
                  <div className="mb-1.5 flex items-center gap-2">
                    <h4 className="text-xs font-semibold uppercase tracking-wider text-[var(--neutral-foreground-3)]">
                      {grade}
                    </h4>
                    <span className="text-xs text-[var(--neutral-foreground-4)]">{students.length}名</span>
                  </div>
                  <ul className="space-y-1">
                    {students.map((s) => {
                      const notif = mergedNotified[s.id];
                      const arrivalSending = !!perStudentSending[`${s.id}-arrival`];
                      const departSending = !!perStudentSending[`${s.id}-departure`];
                      return (
                        <li
                          key={s.id}
                          className={`flex flex-wrap items-center justify-between gap-2 rounded-md px-3 py-2 text-sm ${
                            s.is_absent
                              ? 'bg-[var(--status-danger-bg)] text-[var(--status-danger-fg)] line-through'
                              : 'bg-[var(--neutral-background-3)] text-[var(--neutral-foreground-2)]'
                          }`}
                        >
                          <span className="font-medium">
                            {s.name}
                            {s.type !== 'regular' && (
                              <span className="ml-1.5 rounded bg-[var(--neutral-background-1)] px-1.5 py-0.5 text-[10px] font-normal text-[var(--neutral-foreground-3)]">
                                {typeLabel(s.type)}
                              </span>
                            )}
                            {s.is_absent && (
                              <span className="ml-1.5 rounded bg-[var(--status-danger-fg)] px-1.5 py-0.5 text-[10px] font-bold text-white">
                                欠席
                              </span>
                            )}
                          </span>
                          {!s.is_absent && s.chat_room_id && (
                            <div className="flex items-center gap-1.5">
                              {notif === 'arrival' ? (
                                <span className="flex items-center gap-0.5 rounded bg-[var(--status-success-bg)] px-2 py-1 text-xs font-medium text-[var(--status-success-fg)]">
                                  <MaterialIcon name="check" size={12} />
                                  到着済
                                </span>
                              ) : (
                                <button
                                  type="button"
                                  onClick={() => handlePerStudentNotify(s.id, s.chat_room_id!, 'arrival', s.name)}
                                  disabled={arrivalSending}
                                  className="flex items-center gap-1 rounded border border-blue-600/30 bg-blue-50 px-2 py-1 text-xs font-bold text-blue-700 hover:bg-blue-100 disabled:opacity-50"
                                >
                                  <MaterialIcon name="check_circle" size={12} />
                                  {arrivalSending ? '送信中…' : '到着'}
                                </button>
                              )}
                              {notif === 'departure' ? (
                                <span className="flex items-center gap-0.5 rounded bg-[var(--status-success-bg)] px-2 py-1 text-xs font-medium text-[var(--status-success-fg)]">
                                  <MaterialIcon name="check" size={12} />
                                  帰宅済
                                </span>
                              ) : (
                                <button
                                  type="button"
                                  onClick={() => handlePerStudentNotify(s.id, s.chat_room_id!, 'departure', s.name)}
                                  disabled={departSending}
                                  className="flex items-center gap-1 rounded border border-amber-600/30 bg-amber-50 px-2 py-1 text-xs font-bold text-amber-700 hover:bg-amber-100 disabled:opacity-50"
                                >
                                  <MaterialIcon name="directions_bus" size={12} />
                                  {departSending ? '送信中…' : '帰宅'}
                                </button>
                              )}
                            </div>
                          )}
                        </li>
                      );
                    })}
                  </ul>
                </div>
              );
            })}
          </div>
        )}
      </div>

      {/* カレンダー */}
      <div className="rounded-xl bg-white p-3 shadow-md sm:p-5 lg:p-6">
        {/* カレンダー上部コントロール: 月ナビ + 新規追加ボタン
            狭幅では縦並び、sm 以上で横並びにして折り返し回避 */}
        <div className="mb-4 flex flex-col gap-2 sm:mb-6 sm:flex-row sm:items-center sm:justify-between sm:gap-3">
          <div className="flex items-center justify-between gap-2 sm:justify-start sm:gap-3 lg:gap-4">
            <button
              onClick={() => setCalendarMonth(subMonths(calendarMonth, 1))}
              className="flex items-center justify-center rounded-lg bg-[var(--brand-80)] p-2 text-white hover:bg-blue-700 sm:p-2.5 lg:px-4"
              aria-label="前の月"
              title="前の月"
            >
              <MaterialIcon name="chevron_left" size={24} />
            </button>
            <span className="whitespace-nowrap text-lg font-bold sm:text-xl lg:text-2xl">
              {calYear}年{calMonth}月
            </span>
            <button
              onClick={() => setCalendarMonth(addMonths(calendarMonth, 1))}
              className="flex items-center justify-center rounded-lg bg-[var(--brand-80)] p-2 text-white hover:bg-blue-700 sm:p-2.5 lg:px-4"
              aria-label="次の月"
              title="次の月"
            >
              <MaterialIcon name="chevron_right" size={24} />
            </button>
          </div>
          <Link
            href={`/tablet/activity/edit?date=${selectedDateStr}`}
            className="flex items-center justify-center gap-1.5 rounded-lg bg-green-600 px-3 py-2 text-sm font-bold text-white hover:bg-green-700 sm:px-4 sm:text-base lg:px-5 lg:py-2.5 lg:text-lg"
            title="新しい活動を追加"
          >
            <MaterialIcon name="add" size={20} />
            <span>新しい活動を追加</span>
          </Link>
        </div>

        <div className="grid grid-cols-7 gap-1 sm:gap-2">
          {DAY_HEADERS.map((d) => (
            <div key={d} className="rounded-md bg-[var(--neutral-background-4)] py-2 text-center text-sm font-bold sm:py-3 sm:text-base lg:text-xl">
              {d}
            </div>
          ))}
          {calendarDays.map((cell, i) => {
            if (!cell) {
              return <div key={`e-${i}`} className="aspect-square" />;
            }
            const hasActivity = activeDates.includes(cell.dateStr);
            const isSelected = isSameDay(cell.date, selectedDate);
            const today = isToday(cell.date);
            return (
              <button
                key={cell.dateStr}
                onClick={() => setSelectedDate(cell.date)}
                className={`flex aspect-square items-center justify-center rounded-md text-sm font-medium transition-all sm:text-base lg:text-xl
                  ${hasActivity ? 'bg-green-100 font-bold' : 'bg-[var(--neutral-background-3)]'}
                  ${isSelected ? 'bg-[var(--brand-80)] text-white' : ''}
                  ${today && !isSelected ? 'ring-2 ring-blue-500' : ''}
                  hover:bg-[var(--neutral-background-5)]`}
              >
                {cell.day}
              </button>
            );
          })}
        </div>
      </div>

      {/* 活動一覧 */}
      <div className="rounded-xl bg-white p-3 shadow-md sm:p-5 lg:p-6">
        <h2 className="mb-4 text-lg font-bold sm:mb-6 sm:text-xl lg:text-2xl">
          {format(selectedDate, 'yyyy年M月d日', { locale: ja })}の活動
        </h2>

        {isLoading ? (
          <div className="py-12 text-center text-base text-[var(--neutral-foreground-4)] sm:text-xl">読み込み中...</div>
        ) : activities.length === 0 ? (
          <div className="py-12 text-center text-base text-[var(--neutral-foreground-4)] sm:text-xl">
            この日の活動はまだ登録されていません。<br />
            「新しい活動を追加」ボタンから登録してください。
          </div>
        ) : (
          <div className="space-y-3 sm:space-y-4">
            {activities.map((activity) => (
              <div
                key={activity.id}
                className="flex flex-col gap-3 rounded-lg border-2 border-[var(--neutral-stroke-2)] p-3 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between sm:gap-4 sm:p-5"
              >
                <div className="min-w-0 flex-1">
                  <div className="text-base font-bold sm:text-lg lg:text-xl">
                    {activity.activity_name || activity.common_activity}
                  </div>
                  <div className="mt-1 text-xs text-[var(--neutral-foreground-3)] sm:text-sm lg:text-base">
                    {activity.staff?.full_name} | {activity.participant_count ?? activity.student_records?.length ?? 0}名参加
                  </div>
                </div>
                <div className="flex flex-wrap gap-2 sm:gap-3">
                  <Link
                    href={`/tablet/activity/edit?id=${activity.id}&date=${selectedDateStr}`}
                    className="flex items-center gap-1 rounded-lg bg-[var(--brand-80)] px-3 py-2 text-sm font-bold text-white hover:bg-blue-700 sm:px-4 sm:text-base lg:px-5 lg:py-2.5 lg:text-lg"
                    title="編集"
                  >
                    <MaterialIcon name="edit" size={18} />
                    <span>編集</span>
                  </Link>
                  <Link
                    href={`/tablet/renrakucho?activity_id=${activity.id}`}
                    className="flex items-center gap-1 rounded-lg bg-green-600 px-3 py-2 text-sm font-bold text-white hover:bg-green-700 sm:px-4 sm:text-base lg:px-5 lg:py-2.5 lg:text-lg"
                    title="連絡帳入力"
                  >
                    <MaterialIcon name="edit_note" size={18} />
                    <span>連絡帳</span>
                  </Link>
                  {/* バグ報告 (タブレットユーザ): AI生成・送信・送信済確認 が
                      まとめてここから出来るようになったため、ラベルを「統合」から
                      「送信/確認」に変更。送信済の場合もこのページから内容を確認可能。 */}
                  <Link
                    href={`/tablet/integrate?id=${activity.id}&date=${selectedDateStr}`}
                    className="flex items-center gap-1 rounded-lg bg-purple-600 px-3 py-2 text-sm font-bold text-white hover:bg-purple-700 sm:px-4 sm:text-base lg:px-5 lg:py-2.5 lg:text-lg"
                    title="AIで連絡帳を作成、保護者に送信、送信済の内容確認"
                  >
                    <MaterialIcon name="send" size={18} />
                    <span>送信/確認</span>
                  </Link>
                  <button
                    onClick={() => handleDelete(activity.id)}
                    className="flex items-center gap-1 rounded-lg bg-red-500 px-3 py-2 text-sm font-bold text-white hover:bg-red-600 sm:px-4 sm:text-base lg:px-5 lg:py-2.5 lg:text-lg"
                    title="削除"
                  >
                    <MaterialIcon name="delete" size={18} />
                    <span>削除</span>
                  </button>
                </div>
              </div>
            ))}
          </div>
        )}
      </div>

      {/* クイック通知モーダル */}
      <Modal
        isOpen={quickModal !== null}
        onClose={() => setQuickModal(null)}
        title={`${quickModal === 'departure' ? 'これから帰ります' : '到着しました'} を送信`}
        size="md"
      >
        <div className="space-y-4">
          <div className="rounded border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-3)] p-3">
            <label className="mb-1 block text-sm font-semibold text-[var(--neutral-foreground-1)]">
              送信内容 (編集可能)
            </label>
            <textarea
              value={quickBody}
              onChange={(e) => setQuickBody(e.target.value)}
              rows={4}
              className="block w-full resize-none rounded border border-[var(--neutral-stroke-2)] bg-white px-2 py-1.5 text-sm text-[var(--neutral-foreground-1)] focus:border-[var(--brand-80)] focus:outline-none focus:ring-1 focus:ring-[var(--brand-80)]"
              placeholder="送信するメッセージを入力"
            />
          </div>

          <div>
            <div className="mb-1 flex items-center justify-between">
              <label className="text-sm font-medium text-[var(--neutral-foreground-2)]">
                送信先 ({quickRoomIds.size}/{chatRooms.length}件)
              </label>
              <div className="flex gap-2 text-xs">
                <button
                  type="button"
                  onClick={() => setQuickRoomIds(new Set(chatRooms.map((r) => r.id)))}
                  className="text-[var(--brand-80)] hover:underline"
                >
                  全選択
                </button>
                <button
                  type="button"
                  onClick={() => setQuickRoomIds(new Set())}
                  className="text-[var(--neutral-foreground-4)] hover:underline"
                >
                  全解除
                </button>
              </div>
            </div>
            <div className="max-h-[300px] overflow-y-auto rounded-lg border border-[var(--neutral-stroke-2)] p-2">
              {chatRooms.length === 0 ? (
                <p className="p-2 text-center text-xs text-[var(--neutral-foreground-4)]">
                  保護者チャットがありません
                </p>
              ) : (
                chatRooms.map((room) => {
                  const checked = quickRoomIds.has(room.id);
                  return (
                    <label
                      key={room.id}
                      className="flex cursor-pointer items-center gap-2 rounded px-2 py-1.5 text-sm hover:bg-[var(--neutral-background-3)]"
                    >
                      <input
                        type="checkbox"
                        checked={checked}
                        onChange={() => {
                          const next = new Set(quickRoomIds);
                          if (checked) next.delete(room.id);
                          else next.add(room.id);
                          setQuickRoomIds(next);
                        }}
                        className="rounded border-[var(--neutral-stroke-2)]"
                      />
                      <span>{room.student?.student_name || `ID:${room.id}`}</span>
                      {room.guardian?.full_name && (
                        <span className="text-xs text-[var(--neutral-foreground-4)]">
                          ({room.guardian.full_name})
                        </span>
                      )}
                    </label>
                  );
                })
              )}
            </div>
          </div>

          <div className="flex justify-end gap-2 pt-2">
            <button
              type="button"
              onClick={() => setQuickModal(null)}
              className="rounded-lg border border-[var(--neutral-stroke-2)] px-4 py-2 text-sm font-medium text-[var(--neutral-foreground-2)] hover:bg-[var(--neutral-background-3)]"
            >
              キャンセル
            </button>
            <button
              type="button"
              onClick={sendQuickBroadcast}
              disabled={quickSending || quickRoomIds.size === 0 || !quickBody.trim()}
              className="flex items-center gap-1.5 rounded-lg bg-[var(--brand-80)] px-4 py-2 text-sm font-bold text-white hover:bg-blue-700 disabled:opacity-50"
            >
              <MaterialIcon name="send" size={16} />
              {quickRoomIds.size}件に送信
            </button>
          </div>
        </div>
      </Modal>
    </div>
  );
}
