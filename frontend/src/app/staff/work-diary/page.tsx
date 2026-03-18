'use client';

import { useState, useEffect, useCallback, useMemo } from 'react';
import api from '@/lib/api';
import { Button } from '@/components/ui/Button';
import { Skeleton } from '@/components/ui/Skeleton';
import { useToast } from '@/components/ui/Toast';
import { Calendar, Save } from 'lucide-react';

interface WorkDiary {
  id: number;
  diary_date: string;
  previous_day_review: string | null;
  daily_communication: string | null;
  daily_roles: string | null;
  prev_day_children_status: string | null;
  children_special_notes: string | null;
  other_notes: string | null;
  created_by: number;
  updated_by: number | null;
  creator?: { id: number; full_name: string };
  updater?: { id: number; full_name: string };
  created_at: string;
  updated_at: string;
}

const SECTIONS = [
  { key: 'previous_day_review', label: '前日の振り返り', placeholder: '昨日の活動の振り返り、反省点、良かった点などを記入してください' },
  { key: 'daily_communication', label: '本日の伝達事項', placeholder: 'スタッフ間で共有すべき情報、保護者からの連絡、注意事項などを記入してください' },
  { key: 'daily_roles', label: '本日の役割分担', placeholder: '各スタッフの担当業務、配置、送迎担当などを記入してください' },
  { key: 'prev_day_children_status', label: '前日の児童の状況', placeholder: '前日の児童の体調、出席状況、気になった様子などを記入してください' },
  { key: 'children_special_notes', label: '児童に関する特記事項', placeholder: '本日注意すべき児童の情報、トラブル、成長の記録、保護者からの連絡などを記入してください' },
  { key: 'other_notes', label: 'その他メモ', placeholder: '備品の補充、施設の修繕、その他共有事項などを記入してください' },
] as const;

type SectionKey = typeof SECTIONS[number]['key'];

type DiaryFormData = Record<SectionKey, string>;

const emptyForm: DiaryFormData = {
  previous_day_review: '',
  daily_communication: '',
  daily_roles: '',
  prev_day_children_status: '',
  children_special_notes: '',
  other_notes: '',
};

const DAY_NAMES = ['日', '月', '火', '水', '木', '金', '土'];

function formatDateWithDow(dateStr: string): string {
  const d = new Date(dateStr + 'T00:00:00');
  const y = d.getFullYear();
  const m = d.getMonth() + 1;
  const day = d.getDate();
  const dow = DAY_NAMES[d.getDay()];
  return `${y}年${m}月${day}日（${dow}）`;
}

function toDateStr(d: Date): string {
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
}

function addDays(dateStr: string, days: number): string {
  const d = new Date(dateStr + 'T00:00:00');
  d.setDate(d.getDate() + days);
  return toDateStr(d);
}

function formatDateTime(dt: string): string {
  const d = new Date(dt);
  const y = d.getFullYear();
  const m = String(d.getMonth() + 1).padStart(2, '0');
  const day = String(d.getDate()).padStart(2, '0');
  const h = String(d.getHours()).padStart(2, '0');
  const min = String(d.getMinutes()).padStart(2, '0');
  return `${y}/${m}/${day} ${h}:${min}`;
}

// =========================================================================
// Daily View - matches legacy work_diary.php
// =========================================================================
function DailyView({ onSwitchToCalendar, initialDate }: { onSwitchToCalendar: () => void; initialDate?: string }) {
  const toast = useToast();
  const [currentDate, setCurrentDate] = useState(initialDate || toDateStr(new Date()));
  const [diary, setDiary] = useState<WorkDiary | null>(null);
  const [prevDiary, setPrevDiary] = useState<WorkDiary | null>(null);
  const [form, setForm] = useState<DiaryFormData>(emptyForm);
  const [isLoading, setIsLoading] = useState(true);
  const [isSaving, setIsSaving] = useState(false);

  const fetchDiary = useCallback(async (date: string) => {
    setIsLoading(true);
    try {
      const prevDate = addDays(date, -1);

      const [currentRes, prevRes] = await Promise.all([
        api.get('/api/staff/work-diary', { params: { date } }),
        api.get('/api/staff/work-diary', { params: { date: prevDate } }),
      ]);

      const currentItems = currentRes.data?.data?.data ?? currentRes.data?.data;
      const currentDiary = Array.isArray(currentItems) ? currentItems[0] || null : null;

      const prevItems = prevRes.data?.data?.data ?? prevRes.data?.data;
      const prevDiaryData = Array.isArray(prevItems) ? prevItems[0] || null : null;

      setDiary(currentDiary);
      setPrevDiary(prevDiaryData);

      if (currentDiary) {
        setForm({
          previous_day_review: currentDiary.previous_day_review || '',
          daily_communication: currentDiary.daily_communication || '',
          daily_roles: currentDiary.daily_roles || '',
          prev_day_children_status: currentDiary.prev_day_children_status || '',
          children_special_notes: currentDiary.children_special_notes || '',
          other_notes: currentDiary.other_notes || '',
        });
      } else {
        setForm(emptyForm);
      }
    } catch {
      setDiary(null);
      setPrevDiary(null);
      setForm(emptyForm);
    } finally {
      setIsLoading(false);
    }
  }, []);

  useEffect(() => { fetchDiary(currentDate); }, [currentDate, fetchDiary]);

  const handleSave = async () => {
    setIsSaving(true);
    try {
      if (diary) {
        await api.put(`/api/staff/work-diary/${diary.id}`, form);
        toast.success('業務日誌を更新しました');
      } else {
        await api.post('/api/staff/work-diary', { ...form, diary_date: currentDate });
        toast.success('業務日誌を作成しました');
      }
      fetchDiary(currentDate);
    } catch {
      toast.error('保存に失敗しました');
    } finally {
      setIsSaving(false);
    }
  };

  const updateField = (key: SectionKey, value: string) => {
    setForm((prev) => ({ ...prev, [key]: value }));
  };

  const showPrevRef = (sectionKey: string) =>
    (sectionKey === 'previous_day_review' || sectionKey === 'prev_day_children_status')
    && prevDiary?.children_special_notes;

  return (
    <div className="mx-auto max-w-[900px] space-y-6">
      {/* Header with date nav and calendar button */}
      <div className="flex flex-col sm:flex-row items-center justify-between gap-4 rounded-xl bg-[var(--neutral-background-2)] p-4">
        <div className="flex items-center gap-4">
          <button
            onClick={() => setCurrentDate(addDays(currentDate, -1))}
            className="rounded-lg px-4 py-2 text-sm bg-[var(--neutral-background-3)] text-[var(--neutral-foreground-1)] hover:bg-[var(--neutral-background-4)] transition-colors"
          >
            &larr; 前日
          </button>
          <span className="text-xl font-bold text-[var(--neutral-foreground-1)]">
            {formatDateWithDow(currentDate)}
          </span>
          <button
            onClick={() => setCurrentDate(addDays(currentDate, 1))}
            className="rounded-lg px-4 py-2 text-sm bg-[var(--neutral-background-3)] text-[var(--neutral-foreground-1)] hover:bg-[var(--neutral-background-4)] transition-colors"
          >
            翌日 &rarr;
          </button>
        </div>
        <Button
          variant="secondary"
          onClick={onSwitchToCalendar}
          leftIcon={<Calendar className="h-4 w-4" />}
        >
          カレンダー表示
        </Button>
      </div>

      {isLoading ? (
        <div className="space-y-4">
          {[...Array(6)].map((_, i) => <Skeleton key={i} className="h-40 w-full rounded-xl" />)}
        </div>
      ) : (
        <>
          {SECTIONS.map(({ key, label, placeholder }) => (
            <div key={key} className="rounded-xl bg-[var(--neutral-background-1)] p-5 shadow-sm">
              <h3 className="mb-3 text-base font-semibold text-[var(--brand-80)]">{label}</h3>

              {showPrevRef(key) && (
                <div className="mb-3 rounded-lg border-l-[3px] border-orange-400 bg-[var(--neutral-background-3)] p-3 text-sm text-[var(--neutral-foreground-3)]">
                  <h4 className="mb-1 text-xs font-semibold text-orange-500">
                    {key === 'previous_day_review' ? '参考：前日の児童の状況' : '参考：前日の特記事項'}
                  </h4>
                  <p className="whitespace-pre-wrap">{prevDiary!.children_special_notes}</p>
                </div>
              )}

              <textarea
                value={form[key]}
                onChange={(e) => updateField(key, e.target.value)}
                placeholder={placeholder}
                rows={4}
                className="block w-full resize-y rounded-lg border-2 border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-1)] px-3 py-2 text-sm text-[var(--neutral-foreground-1)] transition-colors focus:border-[var(--brand-80)] focus:outline-none"
              />
            </div>
          ))}

          {/* Save button */}
          <div className="text-center pb-4">
            <Button onClick={handleSave} isLoading={isSaving} leftIcon={<Save className="h-4 w-4" />} size="lg">
              {diary ? '更新する' : '保存する'}
            </Button>
          </div>

          {/* Meta info */}
          {diary && (
            <div className="border-t border-[var(--neutral-stroke-3)] pt-3 text-xs text-[var(--neutral-foreground-4)]">
              作成者: {diary.creator?.full_name || '不明'}
              （{formatDateTime(diary.created_at)}）
              {diary.updated_by && diary.updater && (
                <> / 最終更新: {diary.updater.full_name}（{formatDateTime(diary.updated_at)}）</>
              )}
            </div>
          )}
        </>
      )}
    </div>
  );
}

// =========================================================================
// Calendar View - matches legacy work_diary_calendar.php
// =========================================================================
function CalendarView({ onSwitchToDaily }: { onSwitchToDaily: (date?: string) => void }) {
  const todayStr = toDateStr(new Date());
  const now = new Date();
  const [year, setYear] = useState(now.getFullYear());
  const [month, setMonth] = useState(now.getMonth() + 1);
  const [diaries, setDiaries] = useState<WorkDiary[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [selectedDate, setSelectedDate] = useState<string | null>(null);
  const [selectedDiary, setSelectedDiary] = useState<WorkDiary | null>(null);

  const fetchDiaries = useCallback(async () => {
    setIsLoading(true);
    try {
      const monthStr = `${year}-${String(month).padStart(2, '0')}`;
      const res = await api.get('/api/staff/work-diary', { params: { month: monthStr, per_page: 50 } });
      const payload = res.data?.data;
      const items = payload?.data ?? payload;
      setDiaries(Array.isArray(items) ? items : []);
    } catch {
      setDiaries([]);
    } finally {
      setIsLoading(false);
    }
  }, [year, month]);

  useEffect(() => { fetchDiaries(); }, [fetchDiaries]);

  // Map of date -> diary
  const diaryMap = useMemo(() => {
    const map: Record<string, WorkDiary> = {};
    for (const d of diaries) {
      const dateKey = d.diary_date?.split('T')[0];
      if (dateKey) map[dateKey] = d;
    }
    return map;
  }, [diaries]);

  // When selectedDate changes, look up diary
  useEffect(() => {
    if (selectedDate && diaryMap[selectedDate]) {
      setSelectedDiary(diaryMap[selectedDate]);
    } else {
      setSelectedDiary(null);
    }
  }, [selectedDate, diaryMap]);

  // Calendar grid data
  const calendarDays = useMemo(() => {
    const firstDay = new Date(year, month - 1, 1);
    const startDow = firstDay.getDay();
    const daysInMonth = new Date(year, month, 0).getDate();

    const cells: Array<{ day: number; dateStr: string } | null> = [];
    for (let i = 0; i < startDow; i++) cells.push(null);
    for (let d = 1; d <= daysInMonth; d++) {
      const dateStr = `${year}-${String(month).padStart(2, '0')}-${String(d).padStart(2, '0')}`;
      cells.push({ day: d, dateStr });
    }
    return cells;
  }, [year, month]);

  const goToPrevMonth = () => {
    if (month === 1) { setYear(y => y - 1); setMonth(12); }
    else setMonth(m => m - 1);
    setSelectedDate(null);
  };
  const goToNextMonth = () => {
    if (month === 12) { setYear(y => y + 1); setMonth(1); }
    else setMonth(m => m + 1);
    setSelectedDate(null);
  };
  const goToThisMonth = () => {
    const n = new Date();
    setYear(n.getFullYear());
    setMonth(n.getMonth() + 1);
    setSelectedDate(null);
  };

  return (
    <div className="space-y-4">
      {/* Quick actions */}
      <div className="flex flex-wrap gap-3">
        <Button onClick={() => onSwitchToDaily(todayStr)} leftIcon={<span>+</span>}>
          本日の業務日誌を作成
        </Button>
        <Button variant="ghost" onClick={() => onSwitchToDaily()}>
          &larr; 日付別表示に戻る
        </Button>
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-[400px_1fr] gap-5 items-start">
        {/* Calendar panel */}
        <div className="rounded-xl bg-[var(--neutral-background-1)] p-5 shadow-sm">
          <div className="flex items-center justify-between mb-4">
            <h2 className="text-lg font-bold text-[var(--neutral-foreground-1)]">{year}年 {month}月</h2>
            <div className="flex gap-2">
              <button onClick={goToPrevMonth} className="rounded px-3 py-1 text-xs bg-[var(--neutral-background-3)] text-[var(--neutral-foreground-1)] hover:bg-[var(--neutral-background-4)]">&larr; 前月</button>
              <button onClick={goToThisMonth} className="rounded px-3 py-1 text-xs bg-[var(--neutral-background-3)] text-[var(--neutral-foreground-1)] hover:bg-[var(--neutral-background-4)]">今月</button>
              <button onClick={goToNextMonth} className="rounded px-3 py-1 text-xs bg-[var(--neutral-background-3)] text-[var(--neutral-foreground-1)] hover:bg-[var(--neutral-background-4)]">次月 &rarr;</button>
            </div>
          </div>

          {isLoading ? (
            <Skeleton className="h-64 w-full rounded-lg" />
          ) : (
            <>
              <div className="grid grid-cols-7 gap-[2px]">
                {DAY_NAMES.map((dn, i) => (
                  <div key={dn} className={`text-center text-xs font-bold py-2 ${i === 0 ? 'text-red-500' : i === 6 ? 'text-blue-500' : 'text-[var(--neutral-foreground-3)]'}`}>
                    {dn}
                  </div>
                ))}

                {calendarDays.map((cell, i) => {
                  if (!cell) return <div key={`empty-${i}`} />;
                  const { day, dateStr } = cell;
                  const dow = new Date(dateStr + 'T00:00:00').getDay();
                  const isToday = dateStr === todayStr;
                  const isSelected = dateStr === selectedDate;
                  const hasDiary = !!diaryMap[dateStr];

                  return (
                    <button
                      key={dateStr}
                      onClick={() => setSelectedDate(dateStr)}
                      className={`relative flex aspect-square flex-col items-center justify-center rounded transition-colors text-sm
                        ${isSelected ? 'bg-green-600 text-white' : isToday ? 'bg-blue-600 text-white' : 'hover:bg-[var(--neutral-background-3)]'}
                      `}
                    >
                      <span className={`font-medium ${!isToday && !isSelected ? (dow === 0 ? 'text-red-500' : dow === 6 ? 'text-blue-500' : '') : ''}`}>
                        {day}
                      </span>
                      {hasDiary && (
                        <span className={`absolute bottom-1 h-1.5 w-1.5 rounded-full ${isToday || isSelected ? 'bg-white' : 'bg-orange-400'}`} />
                      )}
                    </button>
                  );
                })}
              </div>

              {/* Legend */}
              <div className="mt-4 flex gap-5 border-t border-[var(--neutral-stroke-3)] pt-3 text-xs text-[var(--neutral-foreground-3)]">
                <div className="flex items-center gap-1.5">
                  <span className="inline-block h-2 w-2 rounded-full bg-orange-400" />
                  <span>業務日誌あり</span>
                </div>
                <div className="flex items-center gap-1.5">
                  <span className="inline-block h-2 w-2 rounded-full bg-blue-600" />
                  <span>今日</span>
                </div>
              </div>
            </>
          )}
        </div>

        {/* Diary detail panel */}
        <div className="rounded-xl bg-[var(--neutral-background-1)] p-5 shadow-sm">
          {selectedDate && selectedDiary ? (
            <>
              <div className="mb-4 flex items-center justify-between border-b-2 border-[var(--brand-80)] pb-3">
                <h3 className="text-lg font-bold text-[var(--neutral-foreground-1)]">
                  {formatDateWithDow(selectedDate)}の業務日誌
                </h3>
                <Button size="sm" onClick={() => onSwitchToDaily(selectedDate)}>
                  編集
                </Button>
              </div>

              {SECTIONS.map(({ key, label }) => {
                const value = selectedDiary[key as keyof WorkDiary] as string | null;
                return (
                  <div key={key} className="mb-4">
                    <h4 className="mb-2 flex items-center gap-1.5 text-sm font-semibold text-[var(--brand-80)]">{label}</h4>
                    <div className={`whitespace-pre-wrap rounded-lg bg-[var(--neutral-background-3)] p-3 text-sm leading-relaxed ${value ? 'text-[var(--neutral-foreground-1)]' : 'italic text-[var(--neutral-foreground-4)]'}`}>
                      {value || '記入なし'}
                    </div>
                  </div>
                );
              })}

              <div className="border-t border-[var(--neutral-stroke-3)] pt-3 text-xs text-[var(--neutral-foreground-4)]">
                作成者: {selectedDiary.creator?.full_name || '不明'}
                （{formatDateTime(selectedDiary.created_at)}）
                {selectedDiary.updated_by && selectedDiary.updater && (
                  <> / 最終更新: {selectedDiary.updater.full_name}（{formatDateTime(selectedDiary.updated_at)}）</>
                )}
              </div>
            </>
          ) : selectedDate ? (
            <>
              <div className="mb-4 border-b-2 border-[var(--brand-80)] pb-3">
                <h3 className="text-lg font-bold text-[var(--neutral-foreground-1)]">
                  {formatDateWithDow(selectedDate)}
                </h3>
              </div>
              <div className="py-16 text-center text-[var(--neutral-foreground-3)]">
                <p className="mb-4">この日の業務日誌はまだ作成されていません。</p>
                <Button onClick={() => onSwitchToDaily(selectedDate)}>
                  業務日誌を作成
                </Button>
              </div>
            </>
          ) : (
            <div className="py-16 text-center text-[var(--neutral-foreground-3)]">
              <p>カレンダーから日付を選択してください。</p>
            </div>
          )}
        </div>
      </div>
    </div>
  );
}

// =========================================================================
// Main Page - toggles between daily & calendar views
// =========================================================================
export default function WorkDiaryPage() {
  const [view, setView] = useState<'daily' | 'calendar'>('daily');
  const [dailyDate, setDailyDate] = useState<string | undefined>();

  const switchToCalendar = () => setView('calendar');
  const switchToDaily = (date?: string) => {
    if (date) setDailyDate(date);
    setView('daily');
  };

  return (
    <div className="space-y-6">
      <h1 className="text-2xl font-bold text-[var(--neutral-foreground-1)]">業務日誌</h1>

      {view === 'daily' ? (
        <DailyView
          key={dailyDate}
          onSwitchToCalendar={switchToCalendar}
          initialDate={dailyDate}
        />
      ) : (
        <CalendarView onSwitchToDaily={switchToDaily} />
      )}
    </div>
  );
}
