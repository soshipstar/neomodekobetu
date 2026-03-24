'use client';

import { useState, useMemo, useCallback } from 'react';
import { useQuery } from '@tanstack/react-query';
import api from '@/lib/api';
import { Card, CardHeader, CardTitle, CardBody } from '@/components/ui/Card';
import { Badge } from '@/components/ui/Badge';
import { SkeletonCard } from '@/components/ui/Skeleton';
import { formatDate } from '@/lib/utils';
import Link from 'next/link';
import { MaterialIcon } from '@/components/ui/MaterialIcon';

// ---- Type definitions matching the legacy PHP dashboard ----

interface DashboardChild {
  id: number;
  student_name: string;
  grade_level: string;
  status: string;
  scheduled_days: number[]; // 0=Sun..6=Sat
}

interface UnreadChat {
  room_id: number;
  student_name: string;
  unread_count: number;
  last_message_at: string;
}

interface KakehashiItem {
  period_id: number;
  period_name: string;
  submission_deadline: string;
  days_left: number;
  student_name: string;
  student_id: number;
}

interface SubmissionItem {
  id: number;
  title: string;
  description: string;
  due_date: string;
  student_name: string;
  days_left: number;
}

interface NoteItem {
  id: number;
  integrated_content: string;
  sent_at: string;
  guardian_confirmed: boolean;
  guardian_confirmed_at: string | null;
  activity_name: string;
  record_date: string;
}

interface MeetingRequest {
  id: number;
  student_name: string;
  staff_name: string;
  status: string;
  purpose: string;
  purpose_detail: string;
  confirmed_date: string | null;
}

interface SupportPlanAlert {
  id: number;
  student_name: string;
  student_id: number;
  created_date: string;
}

interface MonitoringAlert {
  id: number;
  student_name: string;
  student_id: number;
  monitoring_date: string;
}

interface StaffKakehashiAlert {
  id: number;
  student_name: string;
  student_id: number;
  period_name: string;
  submitted_at: string;
}

interface FacilityEvaluation {
  id: number;
  title: string;
  guardian_deadline: string | null;
}

interface HolidayInfo {
  name: string;
  type: string;
}

interface EventInfo {
  id: number;
  name: string;
  description: string;
  guardian_message: string;
  target_audience: string;
  color: string;
}

interface CalendarNoteInfo {
  student_id: number;
  student_name: string;
  guardian_confirmed: boolean;
}

interface CalendarMeetingInfo {
  id: number;
  student_name: string;
  staff_name: string;
  purpose: string;
  purpose_detail: string;
  meeting_notes: string;
  time: string;
  confirmed_date: string;
  is_completed: boolean;
}

interface StudentDayInfo {
  student_id: number;
  student_name: string;
  reason?: string;
}

interface CalendarData {
  holidays: Record<string, HolidayInfo>;
  events: Record<string, EventInfo[]>;
  schedules: Record<string, StudentDayInfo[]>;
  notes: Record<string, CalendarNoteInfo[]>;
  makeup_days: Record<string, StudentDayInfo[]>;
  absence_days: Record<string, StudentDayInfo[]>;
  additional_days: Record<string, StudentDayInfo[]>;
  meetings: Record<string, CalendarMeetingInfo[]>;
  school_holiday_activities: Record<string, boolean>;
}

interface GuardianDashboardData {
  children: DashboardChild[];
  unread_chat_messages: UnreadChat[];
  overdue_kakehashi: KakehashiItem[];
  urgent_kakehashi: KakehashiItem[];
  pending_kakehashi: KakehashiItem[];
  overdue_submissions: SubmissionItem[];
  urgent_submissions: SubmissionItem[];
  pending_submissions: SubmissionItem[];
  notes_data: Record<number, NoteItem[]>;
  pending_meeting_requests: MeetingRequest[];
  confirmed_meetings: MeetingRequest[];
  pending_support_plans: SupportPlanAlert[];
  signature_pending_plans: SupportPlanAlert[];
  pending_monitoring_records: MonitoringAlert[];
  signature_pending_monitoring: MonitoringAlert[];
  pending_staff_kakehashi: StaffKakehashiAlert[];
  pending_facility_evaluations: FacilityEvaluation[];
  calendar: CalendarData;
}

// ---- Helper: grade label ----
function getGradeLabel(grade: string): string {
  const labels: Record<string, string> = {
    elementary: '小学生',
    junior_high: '中学生',
    high_school: '高校生',
  };
  return labels[grade] ?? '';
}

/** Normalize escaped newlines from API */
function nl(text: string | null | undefined): string {
  if (!text) return '';
  return text.replace(/\\r\\n|\\n|\\r/g, '\n').replace(/\r\n|\r/g, '\n');
}

// ---- Helper: build calendar grid ----
function getCalendarDays(year: number, month: number) {
  const firstDay = new Date(year, month - 1, 1);
  const startDow = firstDay.getDay();
  const daysInMonth = new Date(year, month, 0).getDate();
  const today = new Date();
  const todayStr =
    today.getFullYear() +
    '-' +
    String(today.getMonth() + 1).padStart(2, '0') +
    '-' +
    String(today.getDate()).padStart(2, '0');

  const cells: { date: string; day: number; dow: number; isToday: boolean }[] = [];
  for (let d = 1; d <= daysInMonth; d++) {
    const dateStr = `${year}-${String(month).padStart(2, '0')}-${String(d).padStart(2, '0')}`;
    const dow = new Date(year, month - 1, d).getDay();
    cells.push({ date: dateStr, day: d, dow, isToday: dateStr === todayStr });
  }
  return { cells, startDow, daysInMonth };
}

// ---- Empty default for calendar data ----
const emptyCalendar: CalendarData = {
  holidays: {},
  events: {},
  schedules: {},
  notes: {},
  makeup_days: {},
  absence_days: {},
  additional_days: {},
  meetings: {},
  school_holiday_activities: {},
};

// ---- Main component ----
export default function GuardianDashboardPage() {
  const now = new Date();
  const [calYear, setCalYear] = useState(now.getFullYear());
  const [calMonth, setCalMonth] = useState(now.getMonth() + 1);

  // Modal states
  const [eventModal, setEventModal] = useState<EventInfo | null>(null);
  const [meetingModal, setMeetingModal] = useState<CalendarMeetingInfo | null>(null);

  const { data, isLoading, error } = useQuery({
    queryKey: ['guardian', 'dashboard', calYear, calMonth],
    queryFn: async () => {
      const response = await api.get<{ data: GuardianDashboardData }>(
        `/api/guardian/dashboard?year=${calYear}&month=${calMonth}`
      );
      return response.data.data;
    },
  });

  const goToPrevMonth = useCallback(() => {
    setCalMonth((m) => {
      if (m === 1) {
        setCalYear((y) => y - 1);
        return 12;
      }
      return m - 1;
    });
  }, []);

  const goToNextMonth = useCallback(() => {
    setCalMonth((m) => {
      if (m === 12) {
        setCalYear((y) => y + 1);
        return 1;
      }
      return m + 1;
    });
  }, []);

  const goToCurrentMonth = useCallback(() => {
    const n = new Date();
    setCalYear(n.getFullYear());
    setCalMonth(n.getMonth() + 1);
  }, []);

  const calendarGrid = useMemo(() => getCalendarDays(calYear, calMonth), [calYear, calMonth]);

  // Use safe defaults when API fails or is loading
  const children = data?.children ?? [];
  const unreadChat = data?.unread_chat_messages ?? [];
  const overdueKakehashi = data?.overdue_kakehashi ?? [];
  const urgentKakehashi = data?.urgent_kakehashi ?? [];
  const pendingKakehashi = data?.pending_kakehashi ?? [];
  const overdueSubmissions = data?.overdue_submissions ?? [];
  const urgentSubmissions = data?.urgent_submissions ?? [];
  const pendingSupportPlans = data?.pending_support_plans ?? [];
  const signaturePendingPlans = data?.signature_pending_plans ?? [];
  const pendingMonitoringRecords = data?.pending_monitoring_records ?? [];
  const signaturePendingMonitoring = data?.signature_pending_monitoring ?? [];
  const pendingStaffKakehashi = data?.pending_staff_kakehashi ?? [];
  const pendingMeetingRequests = data?.pending_meeting_requests ?? [];
  const confirmedMeetings = data?.confirmed_meetings ?? [];
  const pendingFacilityEvaluations = data?.pending_facility_evaluations ?? [];
  const notesData = data?.notes_data ?? {};
  const cal = data?.calendar ?? emptyCalendar;

  const totalUnreadMessages = unreadChat.reduce((sum, c) => sum + c.unread_count, 0);

  const hasAlerts =
    pendingSupportPlans.length > 0 ||
    signaturePendingPlans.length > 0 ||
    pendingMonitoringRecords.length > 0 ||
    signaturePendingMonitoring.length > 0 ||
    pendingStaffKakehashi.length > 0 ||
    overdueKakehashi.length > 0 ||
    urgentKakehashi.length > 0 ||
    pendingKakehashi.length > 0 ||
    overdueSubmissions.length > 0 ||
    urgentSubmissions.length > 0 ||
    pendingMeetingRequests.length > 0 ||
    pendingFacilityEvaluations.length > 0 ||
    totalUnreadMessages > 0 ||
    Object.values(notesData).some((notes) => notes.length > 0);

  if (isLoading) {
    return (
      <div className="space-y-6">
        <h1 className="text-2xl font-bold text-[var(--neutral-foreground-1)]">連絡帳ダッシュボード</h1>
        <div className="grid gap-4 sm:grid-cols-2">
          {[...Array(4)].map((_, i) => (
            <SkeletonCard key={i} />
          ))}
        </div>
      </div>
    );
  }

  if (error && !data) {
    return (
      <div className="space-y-6">
        <h1 className="text-2xl font-bold text-[var(--neutral-foreground-1)]">連絡帳ダッシュボード</h1>
        <Card>
          <CardBody>
            <p className="text-[var(--neutral-foreground-3)]">
              ダッシュボードの読み込みに失敗しました。ページを再読み込みしてください。
            </p>
          </CardBody>
        </Card>
      </div>
    );
  }

  const todayStr = (() => {
    const t = new Date();
    return `${t.getFullYear()}-${String(t.getMonth() + 1).padStart(2, '0')}-${String(t.getDate()).padStart(2, '0')}`;
  })();

  return (
    <div className="space-y-6">
      {/* Page header */}
      <h1 className="text-2xl font-bold text-[var(--neutral-foreground-1)]">連絡帳ダッシュボード</h1>

      {/* ==================== ALERTS SECTION ==================== */}
      {hasAlerts && (
        <section>
          <h2 className="mb-3 flex items-center gap-2 text-lg font-semibold text-[var(--neutral-foreground-1)]">
            <Bell className="h-5 w-5 text-orange-500" />
            確認が必要な項目
          </h2>

          <div className="grid gap-3">
            {/* Unread chat messages */}
            {totalUnreadMessages > 0 && (
              <AlertCard
                icon={<MaterialIcon name="chat" size={20} />}
                title="未読チャットメッセージ"
                borderColor="border-l-blue-500"
                bgColor="bg-[var(--brand-160)]"
                textColor="text-[var(--brand-70)]"
                link="/guardian/chat"
                linkText="チャットを開く"
              >
                <p>
                  <span className="font-semibold text-[var(--brand-70)]">{totalUnreadMessages}件</span>
                  の未読メッセージがあります
                </p>
                <ul className="mt-1 list-disc pl-5 text-xs text-[var(--neutral-foreground-3)]">
                  {unreadChat.map((c) => (
                    <li key={c.room_id}>
                      {c.student_name}さん: {c.unread_count}件
                    </li>
                  ))}
                </ul>
              </AlertCard>
            )}

            {/* Support Plans */}
            {(pendingSupportPlans.length > 0 || signaturePendingPlans.length > 0) && (
              <AlertCard
                icon={<ClipboardCheck className="h-5 w-5" />}
                title="個別支援計画書"
                borderColor="border-l-purple-500"
                bgColor="bg-[var(--brand-160)]"
                textColor="text-[var(--brand-60)]"
                link="/guardian/support-plan"
                linkText="確認する"
              >
                {pendingSupportPlans.length > 0 && (
                  <div className="mb-1">
                    <span className="font-semibold text-red-600">
                      {pendingSupportPlans.length}件
                    </span>
                    の確認待ちがあります
                    <ul className="mt-1 list-disc pl-5 text-xs text-[var(--neutral-foreground-3)]">
                      {pendingSupportPlans.map((p) => (
                        <li key={p.id}>
                          {p.student_name}さんの計画書案（{formatDate(p.created_date, 'yyyy/MM/dd')}
                          作成）
                        </li>
                      ))}
                    </ul>
                  </div>
                )}
                {signaturePendingPlans.length > 0 && (
                  <div>
                    <span className="font-semibold text-green-600">
                      {signaturePendingPlans.length}件
                    </span>
                    の署名待ちがあります
                    <ul className="mt-1 list-disc pl-5 text-xs text-[var(--neutral-foreground-3)]">
                      {signaturePendingPlans.map((p) => (
                        <li key={p.id}>{p.student_name}さんの正式版計画書</li>
                      ))}
                    </ul>
                  </div>
                )}
              </AlertCard>
            )}

            {/* Monitoring Records */}
            {(pendingMonitoringRecords.length > 0 || signaturePendingMonitoring.length > 0) && (
              <AlertCard
                icon={<MaterialIcon name="analytics" size={20} />}
                title="モニタリング表"
                borderColor="border-l-teal-500"
                bgColor="bg-teal-50"
                textColor="text-teal-700"
                link="/guardian/monitoring"
                linkText="確認する"
              >
                {pendingMonitoringRecords.length > 0 && (
                  <div className="mb-1">
                    <span className="font-semibold text-red-600">
                      {pendingMonitoringRecords.length}件
                    </span>
                    の確認待ちがあります
                    <ul className="mt-1 list-disc pl-5 text-xs text-[var(--neutral-foreground-3)]">
                      {pendingMonitoringRecords.map((r) => (
                        <li key={r.id}>
                          {r.student_name}さん（{formatDate(r.monitoring_date, 'yyyy/MM/dd')}）
                        </li>
                      ))}
                    </ul>
                  </div>
                )}
                {signaturePendingMonitoring.length > 0 && (
                  <div>
                    <span className="font-semibold text-green-600">
                      {signaturePendingMonitoring.length}件
                    </span>
                    の署名待ちがあります
                  </div>
                )}
              </AlertCard>
            )}

            {/* Staff Kakehashi */}
            {pendingStaffKakehashi.length > 0 && (
              <AlertCard
                icon={<MaterialIcon name="description" size={20} />}
                title="スタッフからのかけはし"
                borderColor="border-l-blue-500"
                bgColor="bg-[var(--brand-160)]"
                textColor="text-[var(--brand-70)]"
                link="/guardian/kakehashi"
                linkText="確認する"
              >
                <p>
                  <span className="font-semibold text-[var(--brand-70)]">
                    {pendingStaffKakehashi.length}件
                  </span>
                  の確認待ちがあります
                </p>
                <ul className="mt-1 list-disc pl-5 text-xs text-[var(--neutral-foreground-3)]">
                  {pendingStaffKakehashi.map((sk) => (
                    <li key={sk.id}>
                      {sk.student_name}さん「{sk.period_name}」
                    </li>
                  ))}
                </ul>
              </AlertCard>
            )}

            {/* Guardian Kakehashi (pending submission) */}
            {(overdueKakehashi.length > 0 ||
              urgentKakehashi.length > 0 ||
              pendingKakehashi.length > 0) && (
              <AlertCard
                icon={<MaterialIcon name="handshake" size={20} />}
                title="かけはしの提出"
                borderColor="border-l-orange-500"
                bgColor="bg-orange-50"
                textColor="text-orange-700"
                link="/guardian/kakehashi"
                linkText="かけはしを作成する"
              >
                {overdueKakehashi.length > 0 && (
                  <div className="mb-1 text-red-600">
                    <span className="font-semibold">{overdueKakehashi.length}件</span>
                    が期限を過ぎています
                    <ul className="mt-1 list-disc pl-5 text-xs">
                      {overdueKakehashi.map((k) => (
                        <li key={k.period_id}>
                          {k.student_name}さん「{k.period_name}」（期限:{' '}
                          {formatDate(k.submission_deadline, 'yyyy/MM/dd')}）
                        </li>
                      ))}
                    </ul>
                  </div>
                )}
                {urgentKakehashi.length > 0 && (
                  <div className="mb-1">
                    <span className="font-semibold text-orange-600">
                      {urgentKakehashi.length}件
                    </span>
                    の提出期限が近づいています（7日以内）
                    <ul className="mt-1 list-disc pl-5 text-xs text-[var(--neutral-foreground-3)]">
                      {urgentKakehashi.map((k) => (
                        <li key={k.period_id}>
                          {k.student_name}さん「{k.period_name}」（期限:{' '}
                          {formatDate(k.submission_deadline, 'yyyy/MM/dd')}）
                        </li>
                      ))}
                    </ul>
                  </div>
                )}
                {pendingKakehashi.length > 0 && (
                  <div>
                    <span className="font-semibold text-[var(--neutral-foreground-3)]">
                      {pendingKakehashi.length}件
                    </span>
                    の提出期限が1ヶ月以内です
                    <ul className="mt-1 list-disc pl-5 text-xs text-[var(--neutral-foreground-3)]">
                      {pendingKakehashi.map((k) => (
                        <li key={k.period_id}>
                          {k.student_name}さん「{k.period_name}」（期限:{' '}
                          {formatDate(k.submission_deadline, 'yyyy/MM/dd')}）
                        </li>
                      ))}
                    </ul>
                  </div>
                )}
              </AlertCard>
            )}

            {/* Submissions */}
            {(overdueSubmissions.length > 0 || urgentSubmissions.length > 0) && (
              <AlertCard
                icon={<MaterialIcon name="upload" size={20} />}
                title="提出物"
                borderColor="border-l-red-500"
                bgColor="bg-red-50"
                textColor="text-red-700"
                link="/guardian/kakehashi"
                linkText="提出物を確認する"
              >
                {overdueSubmissions.length > 0 && (
                  <div className="mb-1 text-red-600">
                    <span className="font-semibold">{overdueSubmissions.length}件</span>
                    が期限を過ぎています
                  </div>
                )}
                {urgentSubmissions.length > 0 && (
                  <div>
                    <span className="font-semibold text-orange-600">
                      {urgentSubmissions.length}件
                    </span>
                    の提出期限が近づいています（3日以内）
                  </div>
                )}
              </AlertCard>
            )}

            {/* Meeting Requests */}
            {pendingMeetingRequests.length > 0 && (
              <AlertCard
                icon={<MaterialIcon name="calendar_month" size={20} />}
                title="面談予約"
                borderColor="border-l-purple-500"
                bgColor="bg-[var(--brand-160)]"
                textColor="text-[var(--brand-60)]"
                link="/guardian/meetings"
                linkText="回答する"
              >
                <p>
                  <span className="font-semibold text-[var(--brand-60)]">
                    {pendingMeetingRequests.length}件
                  </span>
                  の回答待ちがあります
                </p>
              </AlertCard>
            )}

            {/* Confirmed Meetings */}
            {confirmedMeetings.length > 0 && (
              <AlertCard
                icon={<MaterialIcon name="event" size={20} />}
                title="確定済み面談"
                borderColor="border-l-purple-400"
                bgColor="bg-[var(--brand-160)]"
                textColor="text-[var(--brand-70)]"
                link="/guardian/meetings"
                linkText="面談一覧を見る"
              >
                <p>
                  <span className="font-semibold text-[var(--brand-70)]">
                    {confirmedMeetings.length}件
                  </span>
                  の面談予定があります
                </p>
                <ul className="mt-1 list-disc pl-5 text-xs text-[var(--neutral-foreground-3)]">
                  {confirmedMeetings.map((m) => (
                    <li key={m.id}>
                      {m.student_name}さん
                      {m.confirmed_date ? `（${formatDate(m.confirmed_date, 'M/d HH:mm')}）` : ''}
                    </li>
                  ))}
                </ul>
              </AlertCard>
            )}

            {/* Facility Evaluations */}
            {pendingFacilityEvaluations.length > 0 && (
              <AlertCard
                icon={<Star className="h-5 w-5" />}
                title="事業所評価アンケート"
                borderColor="border-l-green-500"
                bgColor="bg-green-50"
                textColor="text-green-700"
                link="/guardian/evaluation"
                linkText="アンケートに回答する"
              >
                <p>
                  <span className="font-semibold text-green-700">
                    {pendingFacilityEvaluations.length}件
                  </span>
                  のアンケート回答をお願いしています
                </p>
                <ul className="mt-1 list-disc pl-5 text-xs text-[var(--neutral-foreground-3)]">
                  {pendingFacilityEvaluations.map((ev) => (
                    <li key={ev.id}>
                      {ev.title}（期限:{' '}
                      {ev.guardian_deadline
                        ? formatDate(ev.guardian_deadline, 'yyyy/MM/dd')
                        : '未設定'}
                      ）
                    </li>
                  ))}
                </ul>
              </AlertCard>
            )}

            {/* Unconfirmed notes per child */}
            {Object.entries(notesData).map(([studentIdStr, notes]) => {
              if (notes.length === 0) return null;
              const child = children.find((c) => c.id === Number(studentIdStr));
              const studentName = child?.student_name ?? '生徒';
              return (
                <AlertCard
                  key={studentIdStr}
                  icon={<MaterialIcon name="edit" size={20} />}
                  title={`${studentName}さんの未確認連絡帳`}
                  borderColor="border-l-amber-500"
                  bgColor="bg-amber-50"
                  textColor="text-amber-700"
                  link="/guardian/notes"
                  linkText="連絡帳を確認する"
                >
                  <p>
                    <span className="font-semibold text-amber-700">{notes.length}件</span>
                    の未確認連絡帳があります
                  </p>
                </AlertCard>
              );
            })}
          </div>
        </section>
      )}

      {/* ==================== CALENDAR SECTION ==================== */}
      <section>
        <Card padding={false}>
          {/* Calendar header with navigation */}
          <div className="flex items-center justify-between border-b border-[var(--neutral-stroke-2)] px-4 py-3 sm:px-6">
            <h2 className="flex items-center gap-2 text-lg font-semibold text-[var(--neutral-foreground-1)]">
              <MaterialIcon name="event" size={20} className="text-[var(--neutral-foreground-3)]" />
              {calYear}年 {calMonth}月のカレンダー
            </h2>
            <div className="flex items-center gap-1">
              <button
                onClick={goToPrevMonth}
                className="rounded-lg p-2 text-[var(--neutral-foreground-3)] hover:bg-[var(--neutral-background-4)]"
                aria-label="前月"
              >
                <MaterialIcon name="chevron_left" size={20} />
              </button>
              <button
                onClick={goToCurrentMonth}
                className="rounded-lg px-3 py-1 text-sm font-medium text-[var(--brand-80)] hover:bg-[var(--brand-160)]"
              >
                今月
              </button>
              <button
                onClick={goToNextMonth}
                className="rounded-lg p-2 text-[var(--neutral-foreground-3)] hover:bg-[var(--neutral-background-4)]"
                aria-label="次月"
              >
                <MaterialIcon name="chevron_right" size={20} />
              </button>
            </div>
          </div>

          {/* Calendar grid */}
          <div className="overflow-x-auto">
            <div className="min-w-[700px]">
              {/* Day of week headers */}
              <div className="grid grid-cols-7 border-b border-[var(--neutral-stroke-2)]">
                {['日', '月', '火', '水', '木', '金', '土'].map((d, i) => (
                  <div
                    key={d}
                    className={`py-2 text-center text-xs font-semibold ${
                      i === 0 ? 'text-red-500' : i === 6 ? 'text-[var(--brand-80)]' : 'text-[var(--neutral-foreground-3)]'
                    }`}
                  >
                    {d}
                  </div>
                ))}
              </div>

              {/* Calendar cells */}
              <div className="grid grid-cols-7">
                {/* Empty cells for offset */}
                {Array.from({ length: calendarGrid.startDow }).map((_, i) => (
                  <div key={`empty-${i}`} className="min-h-[100px] border-b border-r border-[var(--neutral-stroke-3)] bg-[var(--neutral-background-3)]/50" />
                ))}

                {calendarGrid.cells.map((cell) => {
                  const isHoliday = !!cal.holidays[cell.date];
                  const holidayInfo = cal.holidays[cell.date];
                  const events = cal.events[cell.date] ?? [];
                  const schedules = cal.schedules[cell.date] ?? [];
                  const notes = cal.notes[cell.date] ?? [];
                  const makeups = cal.makeup_days[cell.date] ?? [];
                  const absences = cal.absence_days[cell.date] ?? [];
                  const additional = cal.additional_days[cell.date] ?? [];
                  const meetings = cal.meetings[cell.date] ?? [];
                  const isSchoolHolidayActivity = !!cal.school_holiday_activities[cell.date];
                  const isPast = cell.date < todayStr;

                  return (
                    <div
                      key={cell.date}
                      className={`min-h-[100px] border-b border-r border-[var(--neutral-stroke-3)] p-1 ${
                        cell.isToday ? 'bg-green-50 ring-2 ring-inset ring-green-400' : ''
                      } ${isHoliday ? 'bg-[var(--neutral-background-4)]' : ''}`}
                    >
                      {/* Day number */}
                      <div
                        className={`mb-0.5 text-right text-xs font-semibold ${
                          cell.dow === 0 || isHoliday
                            ? 'text-red-500'
                            : cell.dow === 6
                              ? 'text-[var(--brand-80)]'
                              : 'text-[var(--neutral-foreground-2)]'
                        }`}
                      >
                        {cell.day}
                      </div>

                      {/* Content area */}
                      <div className="space-y-0.5 text-[10px] leading-tight">
                        {/* Holiday name */}
                        {isHoliday && holidayInfo && (
                          <div className="rounded bg-[var(--neutral-background-5)] px-1 py-0.5 font-medium text-[var(--neutral-foreground-3)]">
                            {holidayInfo.name}
                          </div>
                        )}

                        {/* Activity type (weekday or school holiday) */}
                        {!isHoliday && (
                          <div
                            className={`rounded px-1 py-0.5 font-medium ${
                              isSchoolHolidayActivity
                                ? 'bg-yellow-100 text-yellow-700'
                                : 'bg-[var(--brand-160)] text-[var(--brand-80)]'
                            }`}
                          >
                            {isSchoolHolidayActivity ? '学休' : '平日'}
                          </div>
                        )}

                        {/* Events */}
                        {events.map((ev) => (
                          <button
                            key={ev.id}
                            onClick={() => setEventModal(ev)}
                            className="flex w-full items-center gap-0.5 rounded px-1 py-0.5 text-left hover:bg-[var(--neutral-background-4)]"
                          >
                            <span
                              className="inline-block h-2 w-2 shrink-0 rounded-full"
                              style={{ backgroundColor: ev.color || '#22c55e' }}
                            />
                            <span className="truncate">{ev.name}</span>
                          </button>
                        ))}

                        {/* Scheduled attendance */}
                        {schedules.map((s) => {
                          const hasNote = notes.some(
                            (n) => n.student_id === s.student_id
                          );
                          if (isPast && !hasNote) {
                            return (
                              <div
                                key={`sched-${s.student_id}`}
                                className="flex items-center gap-0.5 rounded bg-[var(--neutral-background-4)] px-1 py-0.5 text-[var(--neutral-foreground-3)]"
                              >
                                <MaterialIcon name="person" size={12} />
                                <span className="truncate">
                                  {s.student_name}さん活動日（連絡帳なし）
                                </span>
                              </div>
                            );
                          }
                          if (!isPast) {
                            return (
                              <div
                                key={`sched-${s.student_id}`}
                                className="flex items-center gap-0.5 rounded bg-green-50 px-1 py-0.5 text-green-700"
                              >
                                <MaterialIcon name="person" size={12} />
                                <span className="truncate">{s.student_name}さん活動予定日</span>
                              </div>
                            );
                          }
                          return null;
                        })}

                        {/* Notes */}
                        {notes.map((n) => {
                          const isConfirmed = n.guardian_confirmed;
                          if (isPast) {
                            return (
                              <div
                                key={`note-${n.student_id}`}
                                className={`flex items-center gap-0.5 rounded px-1 py-0.5 ${
                                  isConfirmed
                                    ? 'bg-green-50 text-green-700'
                                    : 'bg-red-50 text-red-600'
                                }`}
                              >
                                <MaterialIcon name="edit" size={12} />
                                <span className="truncate">
                                  {n.student_name}さん{isConfirmed ? '活動日（確認済み）' : '活動日（要確認）'}
                                </span>
                              </div>
                            );
                          }
                          return (
                            <div
                              key={`note-${n.student_id}`}
                              className={`flex items-center gap-0.5 rounded px-1 py-0.5 ${
                                isConfirmed
                                  ? 'bg-green-50 text-green-700'
                                  : 'bg-orange-50 text-orange-600'
                              }`}
                            >
                              <MaterialIcon name="edit" size={12} />
                              <span className="truncate">
                                {n.student_name}さん{isConfirmed ? '連絡帳あり' : '連絡帳あり（確認してください）'}
                              </span>
                            </div>
                          );
                        })}

                        {/* Makeup days */}
                        {makeups.map((m) => (
                          <div
                            key={`makeup-${m.student_id}`}
                            className="flex items-center gap-0.5 rounded bg-[var(--brand-160)] px-1 py-0.5 text-[var(--brand-80)]"
                          >
                            <RefreshCw className="h-3 w-3 shrink-0" />
                            <span className="truncate">{m.student_name}さん振替活動日</span>
                          </div>
                        ))}

                        {/* Absence days */}
                        {absences.map((a) => (
                          <div
                            key={`absence-${a.student_id}`}
                            className="flex items-center gap-0.5 rounded bg-red-50 px-1 py-0.5 text-red-600"
                          >
                            <XCircle className="h-3 w-3 shrink-0" />
                            <span className="truncate">{a.student_name}さん欠席</span>
                          </div>
                        ))}

                        {/* Additional usage days */}
                        {additional.map((ad) => (
                          <div
                            key={`add-${ad.student_id}`}
                            className="flex items-center gap-0.5 rounded bg-green-50 px-1 py-0.5 text-green-600"
                          >
                            <MaterialIcon name="add" size={12} />
                            <span className="truncate">{ad.student_name}さん追加利用</span>
                          </div>
                        ))}

                        {/* Meetings */}
                        {meetings.map((mt) => (
                          <button
                            key={`meet-${mt.id}`}
                            onClick={() => setMeetingModal(mt)}
                            className="flex w-full items-center gap-0.5 rounded bg-[var(--brand-160)] px-1 py-0.5 text-left text-[var(--brand-60)] hover:bg-[var(--brand-150)]"
                          >
                            <MaterialIcon name="calendar_month" size={12} />
                            <span className="truncate">{mt.time} 面談</span>
                          </button>
                        ))}
                      </div>
                    </div>
                  );
                })}
              </div>
            </div>
          </div>

          {/* Legend */}
          <div className="flex flex-wrap gap-x-4 gap-y-1 border-t border-[var(--neutral-stroke-2)] px-4 py-3 text-xs text-[var(--neutral-foreground-3)] sm:px-6">
            <LegendItem>
              <span className="inline-block h-3 w-3 rounded border border-[var(--neutral-stroke-1)] bg-[var(--neutral-background-4)]" />
              休日
            </LegendItem>
            <LegendItem>
              <span className="inline-block h-3 w-3 rounded border-2 border-green-400 bg-green-50" />
              今日
            </LegendItem>
            <LegendItem>
              <MaterialIcon name="menu_book" size={14} className="text-[var(--brand-80)]" />
              平日活動
            </LegendItem>
            <LegendItem>
              <GraduationCap className="h-3.5 w-3.5 text-yellow-600" />
              学校休業日活動
            </LegendItem>
            <LegendItem>
              <span className="inline-block h-2.5 w-2.5 rounded-full bg-green-500" />
              イベント
            </LegendItem>
            <LegendItem>
              <MaterialIcon name="person" size={14} className="text-green-600" />
              活動予定日
            </LegendItem>
            <LegendItem>
              <MaterialIcon name="edit" size={14} className="text-green-600" />
              連絡帳（確認済み）
            </LegendItem>
            <LegendItem>
              <MaterialIcon name="edit" size={14} className="text-red-500" />
              連絡帳（未確認）
            </LegendItem>
            <LegendItem>
              <RefreshCw className="h-3.5 w-3.5 text-[var(--brand-80)]" />
              振替活動日
            </LegendItem>
            <LegendItem>
              <XCircle className="h-3.5 w-3.5 text-red-500" />
              欠席日
            </LegendItem>
            <LegendItem>
              <MaterialIcon name="add" size={14} className="text-green-600" />
              追加利用
            </LegendItem>
            <LegendItem>
              <MaterialIcon name="calendar_month" size={14} className="text-[var(--brand-70)]" />
              面談予定
            </LegendItem>
          </div>
        </Card>
      </section>

      {/* ==================== CHILDREN SECTION ==================== */}
      <section>
        {children.length === 0 ? (
          <Card>
            <CardBody>
              <h2 className="mb-1 text-base font-semibold text-[var(--neutral-foreground-1)]">
                お子様の情報が登録されていません
              </h2>
              <p className="text-sm text-[var(--neutral-foreground-3)]">管理者にお問い合わせください。</p>
            </CardBody>
          </Card>
        ) : (
          <div className="space-y-4">
            {children.map((child) => {
              const childNotes = notesData[child.id] ?? [];
              return (
                <Card key={child.id}>
                  {/* Student header */}
                  <div className="mb-3 flex items-center gap-3">
                    <div className="flex h-10 w-10 items-center justify-center rounded-full bg-[var(--brand-160)] text-sm font-semibold text-[var(--brand-70)]">
                      {child.student_name.charAt(0)}
                    </div>
                    <div>
                      <span className="text-base font-semibold text-[var(--neutral-foreground-1)]">
                        {child.student_name}
                      </span>
                      {getGradeLabel(child.grade_level) && (
                        <Badge variant="default" className="ml-2">
                          {getGradeLabel(child.grade_level)}
                        </Badge>
                      )}
                    </div>
                  </div>

                  <div className="mb-2 text-right">
                    <Link
                      href="/guardian/notes"
                      className="text-xs font-medium text-[var(--brand-80)] hover:underline"
                    >
                      すべての連絡帳を見る <ArrowRight className="inline h-3 w-3" />
                    </Link>
                  </div>

                  {childNotes.length === 0 ? (
                    <p className="text-sm text-green-600">
                      確認が必要な連絡帳はありません
                    </p>
                  ) : (
                    <div className="space-y-3">
                      {childNotes.map((note) => (
                        <div
                          key={note.id}
                          className="rounded-lg border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-3)] p-3"
                        >
                          <div className="mb-1 flex items-center justify-between">
                            <span className="text-sm font-medium text-[var(--neutral-foreground-1)]">
                              {note.activity_name}
                            </span>
                            <span className="text-xs text-[var(--neutral-foreground-4)]">
                              {formatDate(note.record_date, 'yyyy年M月d日')}
                              {note.sent_at && `（送信: ${note.sent_at.slice(11, 16)}）`}
                            </span>
                          </div>
                          <p className="mb-2 whitespace-pre-wrap text-xs text-[var(--neutral-foreground-3)]">
                            {nl(note.integrated_content)}
                          </p>
                          <div className="flex items-center gap-2">
                            {note.guardian_confirmed ? (
                              <Badge variant="success">確認済み</Badge>
                            ) : (
                              <Badge variant="warning">未確認</Badge>
                            )}
                            {note.guardian_confirmed && note.guardian_confirmed_at && (
                              <span className="text-xs text-[var(--neutral-foreground-4)]">
                                確認日時: {formatDate(note.guardian_confirmed_at, 'yyyy年M月d日 HH:mm')}
                              </span>
                            )}
                          </div>
                        </div>
                      ))}
                    </div>
                  )}
                </Card>
              );
            })}
          </div>
        )}
      </section>

      {/* ==================== QUICK LINKS ==================== */}
      <section>
        <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
          <QuickLink href="/guardian/chat" label="チャット" icon={MessageCircle} />
          <QuickLink href="/guardian/support-plan" label="個別支援計画" icon={FileText} />
          <QuickLink href="/guardian/meetings" label="面談" icon={CalendarDays} />
          <QuickLink href="/guardian/evaluation" label="事業所評価" icon={Star} />
          <QuickLink href="/guardian/kakehashi" label="かけはし" icon={Handshake} />
          <QuickLink href="/guardian/notes" label="連絡帳" icon={Pencil} />
          <QuickLink href="/guardian/monitoring" label="モニタリング" icon={BarChart3} />
          <QuickLink href="/guardian/absence" label="欠席連絡" icon={XCircle} />
        </div>
      </section>

      {/* ==================== MODALS ==================== */}

      {/* Event Modal */}
      {eventModal && (
        <ModalOverlay onClose={() => setEventModal(null)}>
          <h2 className="mb-4 text-lg font-semibold text-[var(--neutral-foreground-1)]">{eventModal.name}</h2>
          {eventModal.description && (
            <div className="mb-3">
              <h4 className="mb-1 text-sm font-medium text-[var(--neutral-foreground-2)]">説明</h4>
              <p className="whitespace-pre-wrap text-sm text-[var(--neutral-foreground-3)]">
                {nl(eventModal.description)}
              </p>
            </div>
          )}
          {eventModal.guardian_message && (
            <div className="mb-3">
              <h4 className="mb-1 text-sm font-medium text-[var(--neutral-foreground-2)]">保護者・生徒連絡用</h4>
              <p className="whitespace-pre-wrap text-sm text-[var(--neutral-foreground-3)]">
                {nl(eventModal.guardian_message)}
              </p>
            </div>
          )}
          {eventModal.target_audience && (
            <div>
              <h4 className="mb-1 text-sm font-medium text-[var(--neutral-foreground-2)]">対象者</h4>
              <p className="text-sm text-[var(--neutral-foreground-3)]">
                {eventModal.target_audience
                  .split(',')
                  .map((a) => {
                    const labels: Record<string, string> = {
                      all: '全体',
                      preschool: '未就学児',
                      elementary: '小学生',
                      junior_high: '中学生',
                      high_school: '高校生',
                      guardian: '保護者',
                      other: 'その他',
                    };
                    return labels[a.trim()] ?? a.trim();
                  })
                  .join('、')}
              </p>
            </div>
          )}
        </ModalOverlay>
      )}

      {/* Meeting Modal */}
      {meetingModal && (
        <ModalOverlay onClose={() => setMeetingModal(null)}>
          <h2 className="mb-4 flex items-center gap-2 text-lg font-semibold text-[var(--brand-60)]">
            <MaterialIcon name="calendar_month" size={20} />
            面談予定
          </h2>

          <div className="mb-4 rounded-lg bg-[var(--brand-160)] p-4">
            <div className="mb-1 text-lg font-semibold text-[var(--brand-60)]">
              {(() => {
                const d = new Date(meetingModal.confirmed_date);
                const dow = ['日', '月', '火', '水', '木', '金', '土'];
                return `${d.getFullYear()}年${d.getMonth() + 1}月${d.getDate()}日（${dow[d.getDay()]}） ${meetingModal.time}`;
              })()}
            </div>
            <div className="text-sm text-[var(--neutral-foreground-3)]">
              {meetingModal.student_name}さんの面談
            </div>
          </div>

          <div className="mb-3">
            <h4 className="mb-1 text-sm font-medium text-[var(--brand-60)]">面談目的</h4>
            <p className="text-sm text-[var(--neutral-foreground-3)]">{meetingModal.purpose}</p>
          </div>

          {meetingModal.purpose_detail && (
            <div className="mb-3">
              <h4 className="mb-1 text-sm font-medium text-[var(--brand-60)]">
                詳細・ご相談内容
              </h4>
              <p className="whitespace-pre-wrap text-sm text-[var(--neutral-foreground-3)]">
                {nl(meetingModal.purpose_detail)}
              </p>
            </div>
          )}

          {meetingModal.staff_name && (
            <div className="mb-3">
              <h4 className="mb-1 text-sm font-medium text-[var(--brand-60)]">担当スタッフ</h4>
              <p className="text-sm text-[var(--neutral-foreground-3)]">{meetingModal.staff_name}</p>
            </div>
          )}

          <div className="rounded-lg border-l-4 border-[var(--brand-90)] bg-[var(--neutral-background-3)] p-4">
            <h4 className="mb-2 text-sm font-medium text-[var(--brand-60)]">
              面談当日のご案内
            </h4>
            {meetingModal.meeting_notes?.trim() ? (
              <p className="whitespace-pre-wrap text-sm text-[var(--neutral-foreground-3)]">
                {nl(meetingModal.meeting_notes)}
              </p>
            ) : (
              <ul className="list-disc pl-5 text-sm leading-relaxed text-[var(--neutral-foreground-3)]">
                <li>ご予約時間の5分前にはお越しください</li>
                <li>印鑑をお持ちください（計画書への署名に必要です）</li>
                <li>
                  ご質問やご相談事項があれば事前にメモをご用意いただくとスムーズです
                </li>
                <li>
                  ご都合が悪くなった場合は、お早めにチャットでご連絡ください
                </li>
              </ul>
            )}
          </div>
        </ModalOverlay>
      )}
    </div>
  );
}

// ---- Sub-components ----

function AlertCard({
  icon,
  title,
  borderColor,
  bgColor,
  textColor,
  link,
  linkText,
  children,
}: {
  icon: React.ReactNode;
  title: string;
  borderColor: string;
  bgColor: string;
  textColor: string;
  link: string;
  linkText: string;
  children: React.ReactNode;
}) {
  return (
    <div className={`rounded-r-lg border-l-4 ${borderColor} ${bgColor} p-4`}>
      <div className={`mb-2 flex items-center gap-2 font-semibold ${textColor}`}>
        {icon}
        {title}
      </div>
      <div className="text-sm text-[var(--neutral-foreground-2)]">{children}</div>
      <Link
        href={link}
        className={`mt-2 inline-flex items-center gap-1 text-xs font-medium ${textColor} hover:underline`}
      >
        {linkText} <ArrowRight className="h-3 w-3" />
      </Link>
    </div>
  );
}

function QuickLink({
  href,
  label,
  icon: Icon,
}: {
  href: string;
  label: string;
  icon: typeof MessageCircle;
}) {
  return (
    <Link href={href}>
      <Card className="flex items-center gap-3 transition-shadow hover:shadow-md">
        <Icon className="h-5 w-5 text-[var(--brand-80)]" />
        <span className="text-sm font-medium text-[var(--neutral-foreground-2)]">{label}</span>
      </Card>
    </Link>
  );
}

function LegendItem({ children }: { children: React.ReactNode }) {
  return <div className="flex items-center gap-1.5">{children}</div>;
}

function ModalOverlay({
  onClose,
  children,
}: {
  onClose: () => void;
  children: React.ReactNode;
}) {
  return (
    <div
      className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
      onClick={(e) => {
        if (e.target === e.currentTarget) onClose();
      }}
    >
      <div className="relative max-h-[80vh] w-full max-w-lg overflow-y-auto rounded-xl bg-white p-6 shadow-xl">
        <button
          onClick={onClose}
          className="absolute right-3 top-3 text-2xl leading-none text-[var(--neutral-foreground-4)] hover:text-[var(--neutral-foreground-3)]"
          aria-label="閉じる"
        >
          &times;
        </button>
        {children}
      </div>
    </div>
  );
}
