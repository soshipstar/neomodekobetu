'use client';

import { useState, useEffect, useCallback, useMemo } from 'react';
import api from '@/lib/api';
import { Card, CardHeader, CardTitle, CardBody } from '@/components/ui/Card';
import { Badge } from '@/components/ui/Badge';
import { Button } from '@/components/ui/Button';
import { Skeleton } from '@/components/ui/Skeleton';
import Link from 'next/link';
import { MaterialIcon } from '@/components/ui/MaterialIcon';

// ---------------------------------------------------------------------------
// Types
// ---------------------------------------------------------------------------

interface DashboardSummary {
  unread_chat: { count: number; rooms: { room_id: number; guardian_name: string; count: number }[] };
  pending_makeup: number;
  pending_meeting_counter: number;
  unconfirmed_renrakucho: number;
  plan_deadlines: { overdue: number; urgent: number };
  monitoring_deadlines: { overdue: number; urgent: number };
  kakehashi_deadlines: { guardian_pending: number; staff_pending: number };
  unsubmitted_documents: number;
  facility_evaluation_incomplete: boolean;
}

interface MeetingInfo {
  date: string;
  student_name: string;
  guardian_name: string;
  purpose: string;
}

interface CalendarData {
  activity_dates: string[];   // YYYY-MM-DD
  holiday_dates: string[];    // YYYY-MM-DD
  event_dates: { date: string; label: string; color: string }[];
  meeting_dates: MeetingInfo[] | string[];
}

interface Activity {
  id: number;
  name: string;
  common_activity?: string;
  staff_name: string;
  participant_count: number;
  sent_count: number;
  unsent_count: number;
}

interface AttendanceStudent {
  id: number;
  name: string;
  grade_group: '未就学' | '小学生' | '中学生' | '高校生';
  type: 'regular' | 'makeup' | 'additional';
  is_absent: boolean;
}

// ---------------------------------------------------------------------------
// Empty defaults (used when APIs fail or return unexpected data)
// ---------------------------------------------------------------------------

const emptyDashboardSummary: DashboardSummary = {
  unread_chat: { count: 0, rooms: [] },
  pending_makeup: 0,
  pending_meeting_counter: 0,
  unconfirmed_renrakucho: 0,
  plan_deadlines: { overdue: 0, urgent: 0 },
  monitoring_deadlines: { overdue: 0, urgent: 0 },
  kakehashi_deadlines: { guardian_pending: 0, staff_pending: 0 },
  unsubmitted_documents: 0,
  facility_evaluation_incomplete: false,
};

const emptyCalendarData: CalendarData = {
  activity_dates: [],
  holiday_dates: [],
  event_dates: [],
  meeting_dates: [],
};

// ---------------------------------------------------------------------------
// API fetch helpers (gracefully default to empty data on error)
// ---------------------------------------------------------------------------

async function fetchSummary(): Promise<DashboardSummary> {
  try {
    const res = await api.get('/api/staff/dashboard/summary');
    const data = res.data?.data;
    if (!data) return emptyDashboardSummary;
    return {
      ...emptyDashboardSummary,
      ...data,
      unread_chat: {
        count: data.unread_chat?.count ?? 0,
        rooms: Array.isArray(data.unread_chat?.rooms) ? data.unread_chat.rooms : [],
      },
      plan_deadlines: {
        overdue: data.plan_deadlines?.overdue ?? 0,
        urgent: data.plan_deadlines?.urgent ?? 0,
      },
      monitoring_deadlines: {
        overdue: data.monitoring_deadlines?.overdue ?? 0,
        urgent: data.monitoring_deadlines?.urgent ?? 0,
      },
      kakehashi_deadlines: {
        guardian_pending: data.kakehashi_deadlines?.guardian_pending ?? 0,
        staff_pending: data.kakehashi_deadlines?.staff_pending ?? 0,
      },
    };
  } catch {
    return emptyDashboardSummary;
  }
}

async function fetchCalendar(year: number, month: number): Promise<CalendarData> {
  try {
    const res = await api.get('/api/staff/dashboard/calendar', { params: { year, month } });
    const data = res.data?.data;
    if (!data) return emptyCalendarData;
    return {
      activity_dates: Array.isArray(data.activity_dates) ? data.activity_dates : [],
      holiday_dates: Array.isArray(data.holiday_dates) ? data.holiday_dates : [],
      event_dates: Array.isArray(data.event_dates) ? data.event_dates : [],
      meeting_dates: Array.isArray(data.meeting_dates) ? data.meeting_dates : [],
    };
  } catch {
    return emptyCalendarData;
  }
}

async function fetchAttendance(date: string): Promise<{ activities: Activity[]; students: AttendanceStudent[] }> {
  try {
    const res = await api.get('/api/staff/dashboard/attendance', { params: { date } });
    const payload = res.data?.data ?? res.data;
    // Handle both old flat array format and new {students, activities} format
    if (Array.isArray(payload)) {
      return { activities: [], students: payload };
    }
    return {
      activities: Array.isArray(payload?.activities) ? payload.activities : [],
      students: Array.isArray(payload?.students) ? payload.students : [],
    };
  } catch {
    return { activities: [], students: [] };
  }
}

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function nl(text: string | null | undefined): string {
  if (!text) return '';
  return text.replace(/\\r\\n|\\n|\\r/g, '\n').replace(/\r\n|\r/g, '\n');
}

function formatDate(y: number, m: number, d: number): string {
  return `${y}-${String(m).padStart(2, '0')}-${String(d).padStart(2, '0')}`;
}

const WEEKDAY_LABELS = ['日', '月', '火', '水', '木', '金', '土'];

const GRADE_ORDER: AttendanceStudent['grade_group'][] = ['未就学', '小学生', '中学生', '高校生'];

function typeLabel(type: AttendanceStudent['type']): string {
  switch (type) {
    case 'regular': return '通常';
    case 'makeup': return '振替';
    case 'additional': return '加算';
  }
}

function typeBadgeVariant(type: AttendanceStudent['type']) {
  switch (type) {
    case 'regular': return 'default' as const;
    case 'makeup': return 'info' as const;
    case 'additional': return 'warning' as const;
  }
}

// ---------------------------------------------------------------------------
// Component
// ---------------------------------------------------------------------------

export default function StaffDashboardPage() {
  const today = new Date();
  const [year, setYear] = useState(today.getFullYear());
  const [month, setMonth] = useState(today.getMonth() + 1); // 1-based
  const [selectedDate, setSelectedDate] = useState(
    formatDate(today.getFullYear(), today.getMonth() + 1, today.getDate())
  );

  const [summary, setSummary] = useState<DashboardSummary>(emptyDashboardSummary);
  const [calendar, setCalendar] = useState<CalendarData>(emptyCalendarData);
  const [activities, setActivities] = useState<Activity[]>([]);
  const [attendance, setAttendance] = useState<AttendanceStudent[]>([]);
  const [loadingSummary, setLoadingSummary] = useState(true);
  const [loadingCalendar, setLoadingCalendar] = useState(true);
  const [loadingAttendance, setLoadingAttendance] = useState(true);

  // Fetch summary once on mount
  useEffect(() => {
    setLoadingSummary(true);
    fetchSummary().then((d) => {
      setSummary(d);
      setLoadingSummary(false);
    });
  }, []);

  // Fetch calendar when year/month changes
  useEffect(() => {
    setLoadingCalendar(true);
    fetchCalendar(year, month).then((d) => {
      setCalendar(d);
      setLoadingCalendar(false);
    });
  }, [year, month]);

  // Fetch activities + attendance when selectedDate changes
  useEffect(() => {
    setLoadingAttendance(true);
    fetchAttendance(selectedDate).then((d) => {
      setActivities(d.activities);
      setAttendance(d.students);
      setLoadingAttendance(false);
    });
  }, [selectedDate]);

  // Month navigation
  const goToPrevMonth = useCallback(() => {
    setMonth((m) => {
      if (m === 1) { setYear((y) => y - 1); return 12; }
      return m - 1;
    });
  }, []);

  const goToNextMonth = useCallback(() => {
    setMonth((m) => {
      if (m === 12) { setYear((y) => y + 1); return 1; }
      return m + 1;
    });
  }, []);

  const goToToday = useCallback(() => {
    const now = new Date();
    setYear(now.getFullYear());
    setMonth(now.getMonth() + 1);
    setSelectedDate(formatDate(now.getFullYear(), now.getMonth() + 1, now.getDate()));
  }, []);

  // Build calendar grid
  const calendarGrid = useMemo(() => {
    const firstDay = new Date(year, month - 1, 1).getDay(); // 0=Sun
    const daysInMonth = new Date(year, month, 0).getDate();
    const rows: (number | null)[][] = [];
    let week: (number | null)[] = Array(firstDay).fill(null);
    for (let d = 1; d <= daysInMonth; d++) {
      week.push(d);
      if (week.length === 7) { rows.push(week); week = []; }
    }
    if (week.length > 0) {
      while (week.length < 7) week.push(null);
      rows.push(week);
    }
    return rows;
  }, [year, month]);

  // Sets for quick lookup in calendar
  const activityDateSet = useMemo(() => new Set(calendar.activity_dates), [calendar]);
  const holidayDateSet = useMemo(() => new Set(calendar.holiday_dates), [calendar]);
  const eventDateMap = useMemo(() => {
    const m = new Map<string, { label: string; color: string }[]>();
    calendar.event_dates.forEach((e) => {
      const arr = m.get(e.date) || [];
      arr.push(e);
      m.set(e.date, arr);
    });
    return m;
  }, [calendar]);
  const meetingDateMap = useMemo(() => {
    const m = new Map<string, MeetingInfo[]>();
    if (Array.isArray(calendar.meeting_dates)) {
      calendar.meeting_dates.forEach((item) => {
        if (typeof item === 'string') {
          // Legacy format (just date string)
          const arr = m.get(item) || [];
          arr.push({ date: item, student_name: '', guardian_name: '', purpose: '' });
          m.set(item, arr);
        } else {
          // New format with details
          const arr = m.get(item.date) || [];
          arr.push(item);
          m.set(item.date, arr);
        }
      });
    }
    return m;
  }, [calendar]);

  // Group attendance by grade
  const attendanceByGrade = useMemo(() => {
    const grouped: Record<string, AttendanceStudent[]> = {};
    GRADE_ORDER.forEach((g) => { grouped[g] = []; });
    (Array.isArray(attendance) ? attendance : []).forEach((s) => {
      if (grouped[s.grade_group]) grouped[s.grade_group].push(s);
    });
    return grouped;
  }, [attendance]);

  const totalAttendance = Array.isArray(attendance) ? attendance.length : 0;
  const absentCount = (Array.isArray(attendance) ? attendance : []).filter((s) => s.is_absent).length;

  // ---------------------------------------------------------------------------
  // Render
  // ---------------------------------------------------------------------------

  return (
    <div className="space-y-6">
      {/* Page title */}
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-bold text-[var(--neutral-foreground-1)]">活動管理</h1>
        <Button variant="ghost" size="sm" onClick={goToToday} leftIcon={<MaterialIcon name="calendar_month" size={16} />}>
          今日
        </Button>
      </div>

      {/* ================================================================= */}
      {/* Notification / Alert area                                          */}
      {/* ================================================================= */}
      <Card padding={false}>
        <div className="px-6 py-4 border-b border-[var(--neutral-stroke-3)]">
          <h2 className="text-sm font-semibold text-[var(--neutral-foreground-3)] uppercase tracking-wider">通知・アラート</h2>
        </div>
        {loadingSummary ? (
          <div className="p-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            {[...Array(9)].map((_, i) => <Skeleton key={i} className="h-10 w-full rounded-lg" />)}
          </div>
        ) : (
          <NotificationGrid summary={summary} />
        )}
      </Card>

      {/* ================================================================= */}
      {/* Two-column layout: Calendar (70%) | Attendance (30%)               */}
      {/* ================================================================= */}
      <div className="grid grid-cols-1 gap-6 lg:grid-cols-10">
        {/* ---------- Left: Calendar ---------- */}
        <div className="lg:col-span-7 space-y-6">
          <Card>
            <CardHeader>
              <div className="flex items-center gap-3">
                <button
                  onClick={goToPrevMonth}
                  className="rounded-lg p-1.5 hover:bg-[var(--neutral-background-3)] transition-colors"
                >
                  <MaterialIcon name="chevron_left" size={20} className="text-[var(--neutral-foreground-3)]" />
                </button>
                <CardTitle className="min-w-[120px] text-center">
                  {year}年{month}月
                </CardTitle>
                <button
                  onClick={goToNextMonth}
                  className="rounded-lg p-1.5 hover:bg-[var(--neutral-background-3)] transition-colors"
                >
                  <MaterialIcon name="chevron_right" size={20} className="text-[var(--neutral-foreground-3)]" />
                </button>
              </div>
            </CardHeader>
            <CardBody>
              {loadingCalendar ? (
                <div className="space-y-2">
                  {[...Array(5)].map((_, i) => <Skeleton key={i} className="h-14 w-full rounded" />)}
                </div>
              ) : (
                <table className="w-full table-fixed border-collapse">
                  <thead>
                    <tr>
                      {WEEKDAY_LABELS.map((wd, i) => (
                        <th
                          key={wd}
                          className={`py-2 text-center text-xs font-medium ${
                            i === 0
                              ? 'text-[var(--status-danger-fg)]'
                              : i === 6
                                ? 'text-[var(--brand-80)]'
                                : 'text-[var(--neutral-foreground-3)]'
                          }`}
                        >
                          {wd}
                        </th>
                      ))}
                    </tr>
                  </thead>
                  <tbody>
                    {calendarGrid.map((week, wi) => (
                      <tr key={wi}>
                        {week.map((day, di) => {
                          if (day === null) {
                            return <td key={di} className="border border-[var(--neutral-stroke-3)] p-1" />;
                          }
                          const dateStr = formatDate(year, month, day);
                          const isSelected = dateStr === selectedDate;
                          const isToday =
                            day === today.getDate() &&
                            month === today.getMonth() + 1 &&
                            year === today.getFullYear();
                          const isHoliday = holidayDateSet.has(dateStr);
                          const hasActivity = activityDateSet.has(dateStr);
                          const eventInfos = eventDateMap.get(dateStr);
                          const meetingInfos = meetingDateMap.get(dateStr);
                          const hasMeeting = !!meetingInfos && meetingInfos.length > 0;
                          const isSunday = di === 0;
                          const isSaturday = di === 6;

                          return (
                            <td
                              key={di}
                              className={`border border-[var(--neutral-stroke-3)] p-1 align-top cursor-pointer transition-colors ${
                                isSelected
                                  ? 'bg-[var(--brand-160)] ring-2 ring-inset ring-[var(--brand-80)]'
                                  : isHoliday
                                    ? 'bg-[var(--status-danger-bg)]'
                                    : 'hover:bg-[var(--neutral-background-3)]'
                              }`}
                              onClick={() => setSelectedDate(dateStr)}
                            >
                              <div className="flex flex-col items-center gap-0.5 min-h-[48px]">
                                <span
                                  className={`text-sm font-medium leading-6 ${
                                    isToday
                                      ? 'inline-flex h-6 w-6 items-center justify-center rounded-full bg-[var(--brand-80)] text-white'
                                      : isHoliday || isSunday
                                        ? 'text-[var(--status-danger-fg)]'
                                        : isSaturday
                                          ? 'text-[var(--brand-80)]'
                                          : 'text-[var(--neutral-foreground-2)]'
                                  }`}
                                >
                                  {day}
                                </span>
                                {/* Indicator dots */}
                                <div className="flex flex-wrap justify-center gap-0.5">
                                  {hasActivity && (
                                    <span className="h-1.5 w-1.5 rounded-full bg-[var(--status-success-fg)]" title="活動あり" />
                                  )}
                                </div>
                                {/* Meeting labels */}
                                {meetingInfos && meetingInfos.map((mi, mi_i) => (
                                  <div
                                    key={`m-${mi_i}`}
                                    className="mt-0.5 w-full truncate rounded px-0.5 text-[8px] leading-tight text-white lg:text-[9px]"
                                    style={{ backgroundColor: '#f59e0b' }}
                                    title={`面談: ${mi.student_name}（${mi.guardian_name}）${mi.purpose ? ' - ' + mi.purpose : ''}`}
                                  >
                                    面談:{mi.student_name || '未定'}
                                  </div>
                                ))}
                                {/* Event labels */}
                                {eventInfos && eventInfos.map((ev, ei) => (
                                  <div
                                    key={ei}
                                    className="mt-0.5 w-full truncate rounded px-0.5 text-[8px] leading-tight text-white lg:text-[9px] cursor-pointer hover:opacity-80"
                                    style={{ backgroundColor: ev.color || '#6366f1' }}
                                    title={ev.label}
                                    onClick={(e) => { e.stopPropagation(); setSelectedDate(dateStr); }}
                                  >
                                    {ev.label}
                                  </div>
                                ))}
                              </div>
                            </td>
                          );
                        })}
                      </tr>
                    ))}
                  </tbody>
                </table>
              )}
              {/* Legend */}
              <div className="mt-3 flex flex-wrap gap-4 text-xs text-[var(--neutral-foreground-3)]">
                <span className="flex items-center gap-1">
                  <span className="h-2 w-2 rounded-full bg-[var(--status-success-fg)]" /> 活動
                </span>
                <span className="flex items-center gap-1">
                  <span className="h-2 w-2 rounded-full bg-[#6366f1]" /> イベント
                </span>
                <span className="flex items-center gap-1">
                  <span className="h-2 w-2 rounded-full bg-[#f59e0b]" /> 面談
                </span>
                <span className="flex items-center gap-1">
                  <span className="inline-block h-3 w-3 rounded bg-[var(--status-danger-bg)] border border-[var(--status-danger-fg)]" /> 祝日
                </span>
              </div>
            </CardBody>
          </Card>

          {/* ---------- Events for selected date (immediately below calendar) ---------- */}
          {(() => {
            const selectedEvents = eventDateMap.get(selectedDate);
            if (!selectedEvents || selectedEvents.length === 0) return null;
            return (
              <Card>
                <CardHeader>
                  <CardTitle>{selectedDate.replace(/-/g, '/')} のイベント</CardTitle>
                </CardHeader>
                <CardBody>
                  <div className="space-y-3">
                    {selectedEvents.map((ev: any, i: number) => (
                      <div key={i} className="rounded-lg border border-[var(--neutral-stroke-2)] p-4">
                        <div className="flex items-center gap-3 mb-2">
                          <span className="h-3 w-3 shrink-0 rounded-full" style={{ backgroundColor: ev.color || '#6366f1' }} />
                          <span className="font-semibold text-[var(--neutral-foreground-1)]">{ev.label}</span>
                          {ev.target_audience && (
                            <span className="text-[10px] rounded-full bg-[var(--neutral-background-3)] px-2 py-0.5 text-[var(--neutral-foreground-3)]">
                              {ev.target_audience === 'all' ? '全員' : ev.target_audience}
                            </span>
                          )}
                        </div>
                        {ev.description && (
                          <p className="text-sm text-[var(--neutral-foreground-2)] whitespace-pre-wrap mb-2">{nl(ev.description)}</p>
                        )}
                        {ev.guardian_message && (
                          <div className="rounded-lg bg-[var(--brand-160)] border border-[var(--brand-130)] p-3 mb-2">
                            <p className="text-[10px] font-semibold text-[var(--brand-80)] mb-1">保護者向けメッセージ</p>
                            <p className="text-sm text-blue-900 whitespace-pre-wrap">{nl(ev.guardian_message)}</p>
                          </div>
                        )}
                        {ev.staff_comment && (
                          <div className="rounded-lg bg-amber-50 border border-amber-200 p-3">
                            <p className="text-[10px] font-semibold text-amber-600 mb-1">スタッフメモ（内部用）</p>
                            <p className="text-sm text-amber-900 whitespace-pre-wrap">{nl(ev.staff_comment)}</p>
                          </div>
                        )}
                      </div>
                    ))}
                  </div>
                </CardBody>
              </Card>
            );
          })()}

          {/* ---------- Meetings for selected date ---------- */}
          {(() => {
            const selectedMeetings = meetingDateMap.get(selectedDate);
            if (!selectedMeetings || selectedMeetings.length === 0) return null;
            return (
              <Card>
                <CardHeader>
                  <CardTitle>{selectedDate.replace(/-/g, '/')} の面談</CardTitle>
                </CardHeader>
                <CardBody>
                  <div className="space-y-2">
                    {selectedMeetings.map((mi, i) => (
                      <div key={i} className="flex items-center gap-3 rounded-lg border border-[var(--neutral-stroke-2)] p-3">
                        <span className="h-3 w-3 shrink-0 rounded-full bg-[var(--status-warning-fg)]" />
                        <div>
                          <span className="font-medium text-[var(--neutral-foreground-1)]">
                            {mi.student_name}（保護者: {mi.guardian_name || '未定'}）
                          </span>
                          {mi.purpose && (
                            <p className="text-xs text-[var(--neutral-foreground-3)] mt-0.5">{nl(mi.purpose)}</p>
                          )}
                        </div>
                      </div>
                    ))}
                  </div>
                </CardBody>
              </Card>
            );
          })()}

          {/* ---------- Activity list for selected date ---------- */}
          <Card>
            <CardHeader>
              <CardTitle>{selectedDate.replace(/-/g, '/')} の活動</CardTitle>
              <div className="flex items-center gap-2">
                <Link href="/staff/support-plans">
                  <Button variant="outline" size="sm" leftIcon={<PenSquare className="h-4 w-4" />}>
                    支援案管理
                  </Button>
                </Link>
                <Link href={`/staff/activities/new?date=${selectedDate}`}>
                  <Button size="sm" leftIcon={<MaterialIcon name="add" size={16} />}>
                    新規活動
                  </Button>
                </Link>
              </div>
            </CardHeader>
            <CardBody>
              {loadingAttendance ? (
                <div className="space-y-3">
                  {[...Array(3)].map((_, i) => <Skeleton key={i} className="h-20 w-full rounded-lg" />)}
                </div>
              ) : activities.length === 0 ? (
                <div className="py-8 text-center text-[var(--neutral-foreground-4)]">
                  <MaterialIcon name="checklist" size={40} className="mx-auto mb-2 text-[var(--neutral-foreground-4)]" />
                  <p>この日の活動はありません</p>
                </div>
              ) : (
                <div className="space-y-3">
                  {activities.map((act) => (
                    <Link
                      key={act.id}
                      href={`/staff/renrakucho?date=${selectedDate}&record=${act.id}`}
                      className="block rounded-lg border border-[var(--neutral-stroke-2)] p-4 hover:shadow-[var(--shadow-4)] transition-shadow"
                    >
                      <div className="flex items-start justify-between">
                        <div className="min-w-0 flex-1">
                          <h4 className="font-medium text-[var(--neutral-foreground-1)]">{act.name}</h4>
                          <p className="mt-0.5 text-xs text-[var(--neutral-foreground-3)]">担当: {act.staff_name}</p>
                          {act.common_activity && (
                            <p className="mt-1 text-xs text-[var(--neutral-foreground-2)] line-clamp-2">{act.common_activity}</p>
                          )}
                        </div>
                        <div className="flex flex-col items-end gap-1 text-sm shrink-0 ml-3">
                          <span className="flex items-center gap-1 text-[var(--neutral-foreground-2)]">
                            <MaterialIcon name="group" size={16} />
                            {act.participant_count}名
                          </span>
                          <div className="flex items-center gap-2">
                            {act.sent_count > 0 && (
                              <span className="flex items-center gap-1 text-xs text-[var(--status-success-fg)]">
                                <MaterialIcon name="send" size={12} />
                                送信済 {act.sent_count}
                              </span>
                            )}
                            {act.unsent_count > 0 && (
                              <Badge variant="danger" dot>未送信 {act.unsent_count}</Badge>
                            )}
                          </div>
                        </div>
                      </div>
                    </Link>
                  ))}
                </div>
              )}
            </CardBody>
          </Card>

        </div>

        {/* ---------- Right column ---------- */}
        <div className="lg:col-span-3 space-y-6">
          {/* Work Diary quick access */}
          <div className="rounded-lg bg-[var(--brand-80)] p-4 text-white">
            <div className="flex items-start justify-between">
              <div>
                <div className="flex items-center gap-1.5 font-bold text-sm">
                  <MaterialIcon name="menu_book" size={16} /> 業務日誌
                </div>
                <p className="mt-1 text-xs opacity-90">
                  {selectedDate.replace(/-/g, '/')}の業務記録
                </p>
              </div>
              <div className="flex gap-2">
                <Link href={`/staff/work-diary?date=${selectedDate}`}>
                  <Button size="sm" className="bg-white/20 text-white hover:bg-white/30 border-0">
                    作成・編集
                  </Button>
                </Link>
                <Link href="/staff/work-diary">
                  <Button size="sm" className="bg-white/10 text-white hover:bg-white/20 border-0">
                    履歴
                  </Button>
                </Link>
              </div>
            </div>
          </div>

          <Card className="sticky top-4">
            <CardHeader>
              <CardTitle className="text-base">
                本日の参加予定者
              </CardTitle>
              <div className="flex items-center gap-2 text-xs text-[var(--neutral-foreground-3)]">
                <span className="flex items-center gap-1">
                  <MaterialIcon name="how_to_reg" size={14} className="text-[var(--status-success-fg)]" />
                  {totalAttendance - absentCount}
                </span>
                <span className="flex items-center gap-1">
                  <UserX className="h-3.5 w-3.5 text-[var(--status-danger-fg)]" />
                  {absentCount}
                </span>
              </div>
            </CardHeader>
            <CardBody>
              {loadingAttendance ? (
                <div className="space-y-3">
                  {[...Array(4)].map((_, i) => (
                    <div key={i}>
                      <Skeleton className="h-4 w-20 mb-2" />
                      <Skeleton className="h-8 w-full rounded" />
                      <Skeleton className="h-8 w-full rounded mt-1" />
                    </div>
                  ))}
                </div>
              ) : totalAttendance === 0 ? (
                <div className="py-6 text-center text-[var(--neutral-foreground-4)]">
                  <MaterialIcon name="group" size={32} className="mx-auto mb-2 text-[var(--neutral-foreground-4)]" />
                  <p className="text-sm">参加予定者はいません</p>
                </div>
              ) : (
                <div className="space-y-4">
                  {GRADE_ORDER.map((grade) => {
                    const students = attendanceByGrade[grade];
                    if (!students || students.length === 0) return null;
                    return (
                      <div key={grade}>
                        <div className="mb-1.5 flex items-center gap-2">
                          <h4 className="text-xs font-semibold text-[var(--neutral-foreground-3)] uppercase tracking-wider">
                            {grade}
                          </h4>
                          <span className="text-xs text-[var(--neutral-foreground-4)]">{students.length}名</span>
                        </div>
                        <ul className="space-y-1">
                          {students.map((s) => (
                            <li
                              key={s.id}
                              className={`flex items-center justify-between rounded-md px-2.5 py-1.5 text-sm ${
                                s.is_absent
                                  ? 'bg-[var(--status-danger-bg)] text-[var(--status-danger-fg)] line-through'
                                  : 'bg-[var(--neutral-background-3)] text-[var(--neutral-foreground-2)]'
                              }`}
                            >
                              <Link
                                href={`/staff/students/${s.id}`}
                                className="hover:underline truncate mr-2"
                              >
                                {s.name}
                              </Link>
                              <div className="flex items-center gap-1 shrink-0">
                                {s.type !== 'regular' && (
                                  <Badge variant={typeBadgeVariant(s.type)} className="text-[10px] px-1.5 py-0">
                                    {typeLabel(s.type)}
                                  </Badge>
                                )}
                                {s.is_absent && (
                                  <Badge variant="danger" className="text-[10px] px-1.5 py-0">欠席</Badge>
                                )}
                              </div>
                            </li>
                          ))}
                        </ul>
                      </div>
                    );
                  })}
                </div>
              )}
            </CardBody>
          </Card>
        </div>
      </div>
    </div>
  );
}

// ---------------------------------------------------------------------------
// Notification Grid sub-component
// ---------------------------------------------------------------------------

function NotificationGrid({ summary }: { summary: DashboardSummary }) {
  const items = [
    {
      borderColor: 'border-l-[var(--status-success-fg)]',
      icon: <MaterialIcon name="chat" size={16} className="text-[var(--status-success-fg)]" />,
      label: '未読チャットメッセージ',
      count: summary.unread_chat.count,
      href: '/staff/chat',
      detail:
        summary.unread_chat.rooms.length > 0
          ? summary.unread_chat.rooms.map((r) => `${r.guardian_name}(${r.count})`).join(', ')
          : undefined,
    },
    {
      borderColor: 'border-l-[var(--status-warning-fg)]',
      icon: <RefreshCw className="h-4 w-4 text-[var(--status-warning-fg)]" />,
      label: '承認待ち振替依頼',
      count: summary.pending_makeup,
      href: '/staff/attendance',
    },
    {
      borderColor: 'border-l-[var(--status-warning-fg)]',
      icon: <MaterialIcon name="group" size={16} className="text-[var(--status-warning-fg)]" />,
      label: '面談対案待ち',
      count: summary.pending_meeting_counter,
      href: '/staff/meetings',
    },
    {
      borderColor: 'border-l-[var(--status-success-fg)]',
      icon: <MaterialIcon name="checklist" size={16} className="text-[var(--status-success-fg)]" />,
      label: '未確認連絡帳',
      count: summary.unconfirmed_renrakucho,
      href: '/staff/unconfirmed-notes',
    },
    {
      borderColor: 'border-l-[var(--brand-80)]',
      icon: <MaterialIcon name="description" size={16} className="text-[var(--brand-80)]" />,
      label: '個別支援計画期限',
      count: summary.plan_deadlines.overdue + summary.plan_deadlines.urgent,
      href: '/staff/pending-tasks',
      detail:
        summary.plan_deadlines.overdue > 0 || summary.plan_deadlines.urgent > 0
          ? `期限超過: ${summary.plan_deadlines.overdue}件 / 緊急: ${summary.plan_deadlines.urgent}件`
          : undefined,
    },
    {
      borderColor: 'border-l-[var(--status-info-fg)]',
      icon: <MaterialIcon name="schedule" size={16} className="text-[var(--status-info-fg)]" />,
      label: 'モニタリング期限',
      count: summary.monitoring_deadlines.overdue + summary.monitoring_deadlines.urgent,
      href: '/staff/pending-tasks',
      detail:
        summary.monitoring_deadlines.overdue > 0 || summary.monitoring_deadlines.urgent > 0
          ? `期限超過: ${summary.monitoring_deadlines.overdue}件 / 緊急: ${summary.monitoring_deadlines.urgent}件`
          : undefined,
    },
    {
      borderColor: 'border-l-[var(--status-warning-fg)]',
      icon: <MaterialIcon name="warning" size={16} className="text-[var(--status-warning-fg)]" />,
      label: 'かけはし期限',
      count: summary.kakehashi_deadlines.guardian_pending + summary.kakehashi_deadlines.staff_pending,
      href: '/staff/pending-tasks',
      detail:
        summary.kakehashi_deadlines.guardian_pending > 0 || summary.kakehashi_deadlines.staff_pending > 0
          ? `保護者: ${summary.kakehashi_deadlines.guardian_pending}件 / 職員: ${summary.kakehashi_deadlines.staff_pending}件`
          : undefined,
    },
    {
      borderColor: 'border-l-[var(--status-danger-fg)]',
      icon: <MaterialIcon name="description" size={16} className="text-[var(--status-danger-fg)]" />,
      label: '未提出提出物',
      count: summary.unsubmitted_documents,
      href: '/staff/submission-management',
    },
  ];

  // Add facility evaluation if incomplete
  if (summary.facility_evaluation_incomplete) {
    items.push({
      borderColor: 'border-l-[var(--status-danger-fg)]',
      icon: <MaterialIcon name="warning" size={16} className="text-[var(--status-danger-fg)]" />,
      label: '事業所評価未提出',
      count: 1,
      href: '/staff/facility-evaluation',
      detail: undefined,
    });
  }

  const visibleItems = items.filter((item) => item.count > 0);

  if (visibleItems.length === 0) {
    return (
      <div className="px-6 py-8 text-center text-[var(--neutral-foreground-4)]">
        <MaterialIcon name="check_circle" size={32} className="mx-auto mb-2" />
        <p className="text-sm">未対応の通知はありません</p>
      </div>
    );
  }

  return (
    <div className="p-4 grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
      {visibleItems.map((item) => (
        <NotificationItem key={item.label} {...item} highlight={item.label === '事業所評価未提出'} />
      ))}
    </div>
  );
}

// ---------------------------------------------------------------------------
// Notification Item sub-component
// ---------------------------------------------------------------------------

function NotificationItem({
  borderColor,
  icon,
  label,
  count,
  href,
  detail,
  highlight,
}: {
  borderColor: string;
  icon: React.ReactNode;
  label: string;
  count: number;
  href: string;
  detail?: string;
  highlight?: boolean;
}) {
  return (
    <Link
      href={href}
      className={`flex items-start gap-3 rounded-lg border-l-4 ${borderColor} bg-[var(--neutral-background-1)] px-3 py-2.5 shadow-sm transition-shadow hover:shadow-md ${
        highlight ? 'ring-1 ring-[var(--status-danger-fg)] animate-pulse' : ''
      }`}
    >
      <div className="mt-0.5 shrink-0">{icon}</div>
      <div className="min-w-0 flex-1">
        <div className="flex items-center justify-between gap-2">
          <span className="text-sm font-medium text-[var(--neutral-foreground-2)] truncate">{label}</span>
          <Badge variant={count > 0 ? 'danger' : 'default'} className="shrink-0">
            {count}
          </Badge>
        </div>
        {detail && (
          <p className="mt-0.5 text-xs text-[var(--neutral-foreground-3)] truncate">{detail}</p>
        )}
      </div>
    </Link>
  );
}

