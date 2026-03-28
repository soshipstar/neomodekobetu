'use client';

import { useState, useEffect, useCallback, useRef } from 'react';
import { useSearchParams } from 'next/navigation';
import api from '@/lib/api';
import { Card, CardHeader, CardTitle, CardBody } from '@/components/ui/Card';
import { Button } from '@/components/ui/Button';
import { Badge } from '@/components/ui/Badge';
import { Modal } from '@/components/ui/Modal';
import { Tabs } from '@/components/ui/Tabs';
import { SkeletonList } from '@/components/ui/Skeleton';
import { useToast } from '@/components/ui/Toast';
import { format, addDays, subDays } from 'date-fns';
import { ja } from 'date-fns/locale';
import Link from 'next/link';
import { MaterialIcon } from '@/components/ui/MaterialIcon';

// ---------------------------------------------------------------------------
// Types
// ---------------------------------------------------------------------------

interface Student {
  id: number;
  student_name: string;
  grade_level?: string;
}

interface StaffUser {
  id: number;
  full_name: string;
}

interface DailyRecord {
  id: number;
  record_date: string;
  activity_name: string;
  common_activity: string | null;
  staff_id: number;
  staff?: StaffUser;
  student_records_count: number;
  sent_count: number;
  unsent_count: number;
  created_at: string;
}

interface StudentRecord {
  id: number;
  daily_record_id: number;
  student_id: number;
  student?: Student;
  health_life: string | null;
  motor_sensory: string | null;
  cognitive_behavior: string | null;
  language_communication: string | null;
  social_relations: string | null;
  notes: string | null;
}

interface UnconfirmedNote {
  id: number;
  integrated_content: string;
  sent_at: string;
  guardian_confirmed: boolean;
  student?: Student;
  daily_record?: { id: number; record_date: string; activity_name: string };
}

interface IntegratedNoteView {
  id: number;
  student_id: number;
  integrated_content: string;
  is_sent: boolean;
  sent_at: string | null;
  guardian_confirmed: boolean;
  guardian_confirmed_at: string | null;
  created_at: string;
  student?: Student & { grade_level?: string };
}

// Domain labels (5 areas)
const DOMAIN_LABELS: Record<string, string> = {
  health_life: '健康・生活',
  motor_sensory: '運動・感覚',
  cognitive_behavior: '認知・行動',
  language_communication: '言語・コミュニケーション',
  social_relations: '人間関係・社会性',
};

const DOMAIN_KEYS = Object.keys(DOMAIN_LABELS) as Array<keyof typeof DOMAIN_LABELS>;

/** Normalize escaped newlines from API */
function nl(text: string | null | undefined): string {
  if (!text) return '';
  return text.replace(/\\r\\n|\\n|\\r/g, '\n').replace(/\r\n|\r/g, '\n');
}

const GRADE_LABELS: Record<string, string> = {
  preschool: '未就学',
  elementary: '小学生',
  junior_high: '中学生',
  high_school: '高校生',
};

const GRADE_BADGE_VARIANT: Record<string, 'primary' | 'success' | 'warning' | 'danger' | 'info'> = {
  preschool: 'warning',
  elementary: 'danger',
  junior_high: 'info',
  high_school: 'primary',
};

// ---------------------------------------------------------------------------
// Component
// ---------------------------------------------------------------------------

export default function RenrakuchoPage() {
  const toast = useToast();

  // --- Date navigation ---
  const searchParams = useSearchParams();
  const initialDate = searchParams.get('date') ? new Date(searchParams.get('date')!) : new Date();
  const [selectedDate, setSelectedDate] = useState(initialDate);
  const dateStr = format(selectedDate, 'yyyy-MM-dd');
  const dateLabelFull = format(selectedDate, 'yyyy年M月d日(E)', { locale: ja });

  // --- Data ---
  const [activities, setActivities] = useState<DailyRecord[]>([]);
  const [unconfirmedNotes, setUnconfirmedNotes] = useState<UnconfirmedNote[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [isLoadingUnconfirmed, setIsLoadingUnconfirmed] = useState(false);

  // --- Student record form panel ---
  const [editingActivity, setEditingActivity] = useState<DailyRecord | null>(null);
  const [studentRecords, setStudentRecords] = useState<StudentRecord[]>([]);
  const [isLoadingRecords, setIsLoadingRecords] = useState(false);
  const [editingStudentId, setEditingStudentId] = useState<number | null>(null);
  const [studentFormData, setStudentFormData] = useState<Record<string, string>>({});
  const [isSavingStudent, setIsSavingStudent] = useState(false);

  // --- Add student to activity ---
  const [allStudents, setAllStudents] = useState<Student[]>([]);
  const [showAddStudent, setShowAddStudent] = useState(false);

  // --- Send/Integrate modal ---
  const [showSendModal, setShowSendModal] = useState(false);
  const [sendActivityId, setSendActivityId] = useState<number | null>(null);
  const [sendNotes, setSendNotes] = useState<Record<number, string>>({});
  const [sentStudentIds, setSentStudentIds] = useState<Set<number>>(new Set());
  const [isSending, setIsSending] = useState(false);
  const [isSavingDraft, setIsSavingDraft] = useState(false);
  const [isGenerating, setIsGenerating] = useState<number | null>(null);
  const [lastSavedTime, setLastSavedTime] = useState<string | null>(null);
  const autoSaveRef = useRef<ReturnType<typeof setInterval> | null>(null);

  // --- View integrated modal ---
  const [showViewModal, setShowViewModal] = useState(false);
  const [viewActivityId, setViewActivityId] = useState<number | null>(null);
  const [viewData, setViewData] = useState<{
    activity: { id: number; activity_name: string; record_date: string; staff_name: string | null; staff_id: number };
    notes: IntegratedNoteView[];
    summary: { total: number; sent: number; confirmed: number; unconfirmed: number };
  } | null>(null);
  const [isLoadingView, setIsLoadingView] = useState(false);

  // --- Active tab ---
  const [activeTab, setActiveTab] = useState('activities');

  // =========================================================================
  // Data fetching
  // =========================================================================

  const fetchActivities = useCallback(async () => {
    setIsLoading(true);
    try {
      const res = await api.get('/api/staff/renrakucho', { params: { date: dateStr } });
      const payload = res.data?.data;
      const items = payload?.data ?? payload;
      setActivities(Array.isArray(items) ? items : []);
    } catch {
      setActivities([]);
    } finally {
      setIsLoading(false);
    }
  }, [dateStr]);

  const fetchUnconfirmedNotes = useCallback(async () => {
    setIsLoadingUnconfirmed(true);
    try {
      const res = await api.get('/api/staff/unconfirmed-notes', {
        params: { filter: 'unconfirmed' },
      });
      const items = res.data?.data;
      setUnconfirmedNotes(Array.isArray(items) ? items : []);
    } catch {
      setUnconfirmedNotes([]);
    } finally {
      setIsLoadingUnconfirmed(false);
    }
  }, []);

  useEffect(() => {
    fetchActivities();
  }, [fetchActivities]);

  useEffect(() => {
    if (activeTab === 'unconfirmed') {
      fetchUnconfirmedNotes();
    }
  }, [activeTab, fetchUnconfirmedNotes]);

  // =========================================================================
  // Delete activity
  // =========================================================================

  const handleDeleteActivity = async (activityId: number) => {
    if (!window.confirm('この活動を削除してもよろしいですか？')) return;
    try {
      await api.delete(`/api/staff/renrakucho/${activityId}`);
      toast.success('活動を削除しました');
      fetchActivities();
    } catch {
      toast.error('削除に失敗しました');
    }
  };

  // =========================================================================
  // Student records (edit per activity)
  // =========================================================================

  const handleOpenActivity = async (activity: DailyRecord) => {
    setEditingActivity(activity);
    setEditingStudentId(null);
    setStudentFormData({});
    setShowAddStudent(false);
    setIsLoadingRecords(true);
    try {
      const [recRes, stuRes] = await Promise.all([
        api.get(`/api/staff/renrakucho/${activity.id}/student-records`),
        api.get('/api/staff/students'),
      ]);
      const items = recRes.data?.data;
      setStudentRecords(Array.isArray(items) ? items : []);
      const stuItems = stuRes.data?.data;
      setAllStudents(Array.isArray(stuItems) ? stuItems : []);
    } catch {
      setStudentRecords([]);
      toast.error('記録の読み込みに失敗しました');
    } finally {
      setIsLoadingRecords(false);
    }
  };

  const handleSelectStudent = (rec: StudentRecord) => {
    setEditingStudentId(rec.student_id);
    setStudentFormData({
      health_life: nl(rec.health_life),
      motor_sensory: nl(rec.motor_sensory),
      cognitive_behavior: nl(rec.cognitive_behavior),
      language_communication: nl(rec.language_communication),
      social_relations: nl(rec.social_relations),
      notes: nl(rec.notes),
    });
  };

  const handleAddStudent = (student: Student) => {
    // Add a blank student record to the list
    const newRec: StudentRecord = {
      id: 0,
      daily_record_id: editingActivity!.id,
      student_id: student.id,
      student: student,
      health_life: null,
      motor_sensory: null,
      cognitive_behavior: null,
      language_communication: null,
      social_relations: null,
      notes: null,
    };
    setStudentRecords((prev) => [...prev, newRec]);
    setShowAddStudent(false);
    // Auto-select the new student for editing
    setEditingStudentId(student.id);
    setStudentFormData({
      health_life: '',
      motor_sensory: '',
      cognitive_behavior: '',
      language_communication: '',
      social_relations: '',
      notes: '',
    });
  };

  const handleSaveStudentRecord = async () => {
    if (!editingActivity || !editingStudentId) return;
    setIsSavingStudent(true);
    try {
      await api.post(`/api/staff/renrakucho/${editingActivity.id}/student-records`, {
        student_id: editingStudentId,
        ...studentFormData,
      });
      toast.success('保存しました');
      const res = await api.get(`/api/staff/renrakucho/${editingActivity.id}/student-records`);
      setStudentRecords(res.data?.data ?? []);
      setEditingStudentId(null);
      fetchActivities();
    } catch {
      toast.error('保存に失敗しました');
    } finally {
      setIsSavingStudent(false);
    }
  };

  // =========================================================================
  // Send to guardians (with draft save, auto-save, Ctrl+S)
  // =========================================================================

  const handleOpenSendModal = async (activityId: number) => {
    setSendActivityId(activityId);
    setSendNotes({});
    setSentStudentIds(new Set());
    setLastSavedTime(null);
    setShowSendModal(true);

    try {
      // First load existing integrated notes (drafts or sent)
      const viewRes = await api.get(`/api/staff/renrakucho/${activityId}/view-integrated`);
      const existingNotes: IntegratedNoteView[] = viewRes.data?.data?.notes ?? [];

      const notes: Record<number, string> = {};
      const sentIds = new Set<number>();
      existingNotes.forEach((n) => {
        notes[n.student_id] = n.integrated_content;
        if (n.is_sent) {
          sentIds.add(n.student_id);
        }
      });

      // Then load student records for students without existing notes
      const recRes = await api.get(`/api/staff/renrakucho/${activityId}/student-records`);
      const recs: StudentRecord[] = recRes.data?.data ?? [];
      setStudentRecords(recs);

      // For students without existing integrated notes, build default content
      recs.forEach((r) => {
        if (notes[r.student_id] === undefined) {
          const parts: string[] = [];
          if (r.health_life) parts.push(`【健康・生活】${r.health_life}`);
          if (r.motor_sensory) parts.push(`【運動・感覚】${r.motor_sensory}`);
          if (r.cognitive_behavior) parts.push(`【認知・行動】${r.cognitive_behavior}`);
          if (r.language_communication) parts.push(`【言語・コミュニケーション】${r.language_communication}`);
          if (r.social_relations) parts.push(`【人間関係・社会性】${r.social_relations}`);
          if (r.notes) parts.push(`【メモ】${r.notes}`);
          notes[r.student_id] = parts.join('\n');
        }
      });

      setSendNotes(notes);
      setSentStudentIds(sentIds);
    } catch {
      // Fallback: just load student records
      try {
        const recRes = await api.get(`/api/staff/renrakucho/${activityId}/student-records`);
        const recs: StudentRecord[] = recRes.data?.data ?? [];
        setStudentRecords(recs);
        const notes: Record<number, string> = {};
        recs.forEach((r) => {
          const parts: string[] = [];
          if (r.health_life) parts.push(`【健康・生活】${r.health_life}`);
          if (r.motor_sensory) parts.push(`【運動・感覚】${r.motor_sensory}`);
          if (r.cognitive_behavior) parts.push(`【認知・行動】${r.cognitive_behavior}`);
          if (r.language_communication) parts.push(`【言語・コミュニケーション】${r.language_communication}`);
          if (r.social_relations) parts.push(`【人間関係・社会性】${r.social_relations}`);
          if (r.notes) parts.push(`【メモ】${r.notes}`);
          notes[r.student_id] = parts.join('\n');
        });
        setSendNotes(notes);
      } catch {
        /* ignore */
      }
    }
  };

  // Draft save function
  const handleSaveDraft = useCallback(async (silent = false) => {
    if (!sendActivityId) return;

    const notesArray = Object.entries(sendNotes)
      .filter(([studentId, content]) => content.trim() && !sentStudentIds.has(Number(studentId)))
      .map(([studentId, content]) => ({
        student_id: Number(studentId),
        content,
      }));

    if (notesArray.length === 0) {
      if (!silent) toast.info('保存する内容がありません');
      return;
    }

    if (!silent) setIsSavingDraft(true);
    try {
      const res = await api.post(`/api/staff/renrakucho/${sendActivityId}/save-draft`, {
        notes: notesArray,
      });
      const now = new Date();
      setLastSavedTime(now.toLocaleTimeString('ja-JP', { hour: '2-digit', minute: '2-digit', second: '2-digit' }));
      if (!silent) {
        toast.success(res.data?.message || '途中保存しました');
      }
    } catch {
      if (!silent) toast.error('保存に失敗しました');
    } finally {
      if (!silent) setIsSavingDraft(false);
    }
  }, [sendActivityId, sendNotes, sentStudentIds, toast]);

  // Auto-save every 5 minutes when send modal is open
  useEffect(() => {
    if (showSendModal && sendActivityId) {
      autoSaveRef.current = setInterval(() => {
        handleSaveDraft(true);
      }, 5 * 60 * 1000);

      return () => {
        if (autoSaveRef.current) {
          clearInterval(autoSaveRef.current);
          autoSaveRef.current = null;
        }
      };
    }
    return () => {
      if (autoSaveRef.current) {
        clearInterval(autoSaveRef.current);
        autoSaveRef.current = null;
      }
    };
  }, [showSendModal, sendActivityId, handleSaveDraft]);

  // Ctrl+S / Cmd+S shortcut for draft save
  useEffect(() => {
    if (!showSendModal) return;

    const handleKeyDown = (e: KeyboardEvent) => {
      if ((e.ctrlKey || e.metaKey) && e.key === 's') {
        e.preventDefault();
        handleSaveDraft(false);
      }
    };

    document.addEventListener('keydown', handleKeyDown);
    return () => document.removeEventListener('keydown', handleKeyDown);
  }, [showSendModal, handleSaveDraft]);

  const handleGenerateIntegrated = async (studentId: number) => {
    if (!sendActivityId) return;
    setIsGenerating(studentId);
    try {
      const res = await api.post(`/api/staff/renrakucho/${sendActivityId}/generate-integrated`, {
        student_id: studentId,
      });
      const content = res.data?.data?.content;
      if (content) {
        setSendNotes((prev) => ({ ...prev, [studentId]: content }));
        toast.success(res.data?.message || 'AI統合文を生成しました');
      }
    } catch {
      toast.error('生成に失敗しました');
    } finally {
      setIsGenerating(null);
    }
  };

  const handleRegenerateAll = async () => {
    if (!sendActivityId) return;
    if (!window.confirm('未送信の統合内容を全て削除して、新しく生成しますか？')) return;

    try {
      await api.post(`/api/staff/renrakucho/${sendActivityId}/regenerate-integrated`);
      toast.success('統合内容をリセットしました');
      // Clear unsent notes
      setSendNotes((prev) => {
        const newNotes = { ...prev };
        Object.keys(newNotes).forEach((key) => {
          if (!sentStudentIds.has(Number(key))) {
            delete newNotes[Number(key)];
          }
        });
        return newNotes;
      });
    } catch {
      toast.error('リセットに失敗しました');
    }
  };

  const handleSendToGuardians = async () => {
    if (!sendActivityId) return;
    const notesArray = Object.entries(sendNotes)
      .filter(([studentId, content]) => content.trim() && !sentStudentIds.has(Number(studentId)))
      .map(([studentId, content]) => ({
        student_id: Number(studentId),
        content,
      }));

    if (notesArray.length === 0) {
      toast.warning('送信する内容がありません');
      return;
    }

    setIsSending(true);
    try {
      const res = await api.post(`/api/staff/renrakucho/${sendActivityId}/send-to-guardians`, {
        notes: notesArray,
      });
      toast.success(res.data?.message || '送信しました');
      setShowSendModal(false);
      fetchActivities();
    } catch (err: unknown) {
      const msg = (err as { response?: { data?: { message?: string } } })?.response?.data?.message;
      toast.error(msg || '送信に失敗しました');
    } finally {
      setIsSending(false);
    }
  };

  // =========================================================================
  // View integrated notes
  // =========================================================================

  const handleOpenViewModal = async (activityId: number) => {
    setViewActivityId(activityId);
    setShowViewModal(true);
    setIsLoadingView(true);
    try {
      const res = await api.get(`/api/staff/renrakucho/${activityId}/view-integrated`);
      setViewData(res.data?.data ?? null);
    } catch {
      setViewData(null);
      toast.error('読み込みに失敗しました');
    } finally {
      setIsLoadingView(false);
    }
  };

  // =========================================================================
  // Date navigation helpers
  // =========================================================================

  const goToPrevDay = () => setSelectedDate((d) => subDays(d, 1));
  const goToNextDay = () => setSelectedDate((d) => addDays(d, 1));
  const goToToday = () => setSelectedDate(new Date());

  // Week display for quick nav
  const weekDays: Date[] = [];
  const dayOfWeek = selectedDate.getDay(); // 0=Sun
  const mondayOffset = dayOfWeek === 0 ? -6 : 1 - dayOfWeek;
  const monday = addDays(selectedDate, mondayOffset);
  for (let i = 0; i < 7; i++) {
    weekDays.push(addDays(monday, i));
  }

  // =========================================================================
  // Render helpers
  // =========================================================================

  const renderDateNav = () => (
    <Card>
      <CardBody>
        {/* Prev/Next day + today */}
        <div className="flex items-center justify-between mb-3">
          <div className="flex items-center gap-2">
            <Button variant="ghost" size="sm" onClick={goToPrevDay}>
              <MaterialIcon name="chevron_left" size={16} />
            </Button>
            <Button variant="outline" size="sm" onClick={goToToday}>
              今日
            </Button>
            <Button variant="ghost" size="sm" onClick={goToNextDay}>
              <MaterialIcon name="chevron_right" size={16} />
            </Button>
          </div>
          <div className="flex items-center gap-2">
            <MaterialIcon name="calendar_month" size={16} className="text-[var(--neutral-foreground-3)]" />
            <input
              type="date"
              value={dateStr}
              onChange={(e) => {
                if (e.target.value) setSelectedDate(new Date(e.target.value + 'T00:00:00'));
              }}
              className="rounded-md border border-[var(--neutral-stroke-1)] bg-[var(--neutral-background-1)] px-2 py-1 text-sm"
            />
          </div>
        </div>

        {/* Week strip */}
        <div className="flex gap-1 justify-center">
          {weekDays.map((day) => {
            const dayStr = format(day, 'yyyy-MM-dd');
            const isSelected = dayStr === dateStr;
            const isToday = dayStr === format(new Date(), 'yyyy-MM-dd');
            return (
              <button
                key={dayStr}
                onClick={() => setSelectedDate(day)}
                className={`flex flex-col items-center rounded-lg px-3 py-2 text-sm transition-colors ${
                  isSelected
                    ? 'bg-[var(--brand-80)] text-white'
                    : isToday
                    ? 'bg-[var(--brand-160)] text-[var(--brand-80)]'
                    : 'text-[var(--neutral-foreground-2)] hover:bg-[var(--neutral-background-3)]'
                }`}
              >
                <span className="text-xs">{format(day, 'E', { locale: ja })}</span>
                <span className="font-medium">{format(day, 'd')}</span>
              </button>
            );
          })}
        </div>
        <p className="mt-2 text-center text-sm font-semibold text-[var(--neutral-foreground-1)]">
          {dateLabelFull}
        </p>
      </CardBody>
    </Card>
  );

  const renderActivityList = () => (
    <div className="space-y-3">
      {isLoading ? (
        <SkeletonList items={3} />
      ) : activities.length > 0 ? (
        activities.map((activity) => (
          <Card key={activity.id} className="transition-shadow hover:shadow-[var(--shadow-8)]">
            <CardBody>
              <div className="flex items-start justify-between gap-3">
                <div className="flex-1 min-w-0">
                  <div className="flex items-center gap-2 flex-wrap mb-1">
                    <h3 className="text-sm font-semibold text-[var(--neutral-foreground-1)]">
                      {activity.activity_name}
                    </h3>
                    {activity.staff?.full_name && (
                      <Badge variant="default">{activity.staff.full_name}</Badge>
                    )}
                  </div>
                  {activity.common_activity && (
                    <p className="text-xs text-[var(--neutral-foreground-3)] mb-2 line-clamp-2">
                      {activity.common_activity}
                    </p>
                  )}
                  <div className="flex items-center gap-3 text-xs text-[var(--neutral-foreground-3)]">
                    <span className="flex items-center gap-1">
                      <MaterialIcon name="group" size={12} />
                      {activity.student_records_count}名
                    </span>
                    {activity.sent_count > 0 && (
                      <Badge variant="success" dot>
                        送信済 {activity.sent_count}
                      </Badge>
                    )}
                    {activity.unsent_count > 0 && (
                      <Badge variant="warning" dot>
                        未送信 {activity.unsent_count}
                      </Badge>
                    )}
                  </div>
                </div>

                {/* Action buttons */}
                <div className="flex items-center gap-1 shrink-0">
                  <Button
                    variant="subtle"
                    size="sm"
                    title="記録を編集"
                    onClick={() => handleOpenActivity(activity)}
                  >
                    <MaterialIcon name="edit" size={16} />
                  </Button>
                  <Button
                    variant="outline"
                    size="sm"
                    title="統合・送信"
                    onClick={() => handleOpenSendModal(activity.id)}
                  >
                    <MaterialIcon name="send" size={16} className="mr-1" />
                    <span className="text-xs">統合</span>
                  </Button>
                  {activity.sent_count > 0 && (
                    <Button
                      variant="subtle"
                      size="sm"
                      title="送信済み内容を閲覧"
                      onClick={() => handleOpenViewModal(activity.id)}
                    >
                      <MaterialIcon name="visibility" size={16} />
                    </Button>
                  )}
                  <Button
                    variant="ghost"
                    size="sm"
                    title="削除"
                    onClick={() => handleDeleteActivity(activity.id)}
                  >
                    <MaterialIcon name="delete" size={16} className="text-[var(--status-danger-fg)]" />
                  </Button>
                </div>
              </div>
            </CardBody>
          </Card>
        ))
      ) : (
        <Card>
          <CardBody>
            <div className="py-10 text-center">
              <MaterialIcon name="description" size={40} className="mx-auto mb-3 text-[var(--neutral-foreground-4)]" />
              <p className="text-sm text-[var(--neutral-foreground-3)]">
                この日の活動はまだ作成されていません
              </p>
              <Link href={`/staff/activities/new?date=${dateStr}`}>
                <Button className="mt-4" size="sm" leftIcon={<MaterialIcon name="add" size={16} />}>
                  活動を作成する
                </Button>
              </Link>
            </div>
          </CardBody>
        </Card>
      )}
    </div>
  );

  const renderUnconfirmedNotes = () => (
    <div className="space-y-3">
      {isLoadingUnconfirmed ? (
        <SkeletonList items={3} />
      ) : unconfirmedNotes.length > 0 ? (
        <>
          {/* Stats */}
          <div className="grid grid-cols-2 gap-3 sm:grid-cols-3">
            <Card>
              <CardBody>
                <div className="text-center">
                  <p className="text-2xl font-bold text-[var(--brand-80)]">{unconfirmedNotes.length}</p>
                  <p className="text-xs text-[var(--neutral-foreground-3)]">未確認</p>
                </div>
              </CardBody>
            </Card>
            <Card>
              <CardBody>
                <div className="text-center">
                  <p className="text-2xl font-bold text-[var(--status-danger-fg)]">
                    {unconfirmedNotes.filter((n) => {
                      const sentDate = new Date(n.sent_at);
                      const diffDays = Math.floor((Date.now() - sentDate.getTime()) / 86400000);
                      return diffDays >= 3;
                    }).length}
                  </p>
                  <p className="text-xs text-[var(--neutral-foreground-3)]">3日以上経過</p>
                </div>
              </CardBody>
            </Card>
            <Card>
              <CardBody>
                <div className="text-center">
                  <p className="text-2xl font-bold text-[var(--status-warning-fg)]">
                    {unconfirmedNotes.filter((n) => {
                      const sentDate = new Date(n.sent_at);
                      const diffDays = Math.floor((Date.now() - sentDate.getTime()) / 86400000);
                      return diffDays >= 1 && diffDays < 3;
                    }).length}
                  </p>
                  <p className="text-xs text-[var(--neutral-foreground-3)]">1-2日経過</p>
                </div>
              </CardBody>
            </Card>
          </div>

          {unconfirmedNotes.map((note) => {
            const sentDate = new Date(note.sent_at);
            const diffDays = Math.floor((Date.now() - sentDate.getTime()) / 86400000);
            const urgency = diffDays >= 3 ? 'danger' : diffDays >= 1 ? 'warning' : 'info';
            return (
              <Card key={note.id}>
                <CardBody>
                  <div className="flex items-start justify-between gap-2 mb-2">
                    <div className="flex items-center gap-2">
                      <span className="text-sm font-semibold text-[var(--neutral-foreground-1)]">
                        {note.student?.student_name || '不明'}
                      </span>
                      <Badge variant={urgency as 'danger' | 'warning' | 'info'}>
                        {diffDays === 0 ? '今日送信' : `${diffDays}日経過`}
                      </Badge>
                    </div>
                  </div>
                  <p className="text-xs text-[var(--neutral-foreground-3)] mb-1">
                    活動: {note.daily_record?.activity_name} | 記録日:{' '}
                    {note.daily_record?.record_date
                      ? format(new Date(note.daily_record.record_date), 'M月d日')
                      : '-'}
                  </p>
                  <div className="rounded-md bg-[var(--neutral-background-3)] p-3 text-xs text-[var(--neutral-foreground-2)] whitespace-pre-wrap max-h-32 overflow-y-auto">
                    {nl(note.integrated_content)}
                  </div>
                </CardBody>
              </Card>
            );
          })}
        </>
      ) : (
        <Card>
          <CardBody>
            <div className="py-10 text-center">
              <MaterialIcon name="check_circle" size={40} className="mx-auto mb-3 text-[var(--status-success-fg)]" />
              <p className="text-sm font-medium text-[var(--status-success-fg)]">
                未確認の連絡帳はありません
              </p>
              <p className="mt-1 text-xs text-[var(--neutral-foreground-3)]">
                全ての送信済み連絡帳が保護者に確認されています
              </p>
            </div>
          </CardBody>
        </Card>
      )}
    </div>
  );

  // =========================================================================
  // Main render
  // =========================================================================

  return (
    <div className="space-y-6">
      {/* Page header */}
      <div className="flex items-center justify-between flex-wrap gap-3">
        <div>
          <h1 className="text-2xl font-bold text-[var(--neutral-foreground-1)]">連絡帳入力</h1>
          <p className="text-sm text-[var(--neutral-foreground-3)]">日常活動の記録と保護者への連絡</p>
        </div>
        <Link href={`/staff/activities/new?date=${dateStr}`}>
          <Button leftIcon={<MaterialIcon name="add" size={16} />}>
            活動を作成
          </Button>
        </Link>
      </div>

      {/* Date navigation */}
      {renderDateNav()}

      {/* Tabs: Activities / Unconfirmed */}
      <Tabs
        activeTab={activeTab}
        onChange={setActiveTab}
        items={[
          {
            key: 'activities',
            label: '活動一覧',
            icon: <MaterialIcon name="description" size={16} />,
            badge: activities.length,
            content: renderActivityList(),
          },
          {
            key: 'unconfirmed',
            label: '未確認連絡帳',
            icon: <MaterialIcon name="error" size={16} />,
            badge: unconfirmedNotes.length,
            content: renderUnconfirmedNotes(),
          },
        ]}
      />

      {/* ================================================================= */}
      {/* Student Record Edit Panel (shown below activity list)             */}
      {/* ================================================================= */}
      {editingActivity && (
        <Card>
          <CardHeader>
            <CardTitle>
              <div className="flex items-center gap-2">
                <MaterialIcon name="edit" size={16} />
                {editingActivity.activity_name} - 生徒記録
              </div>
            </CardTitle>
            <Button variant="ghost" size="sm" onClick={() => setEditingActivity(null)}>
              閉じる
            </Button>
          </CardHeader>
          <CardBody>
            {isLoadingRecords ? (
              <SkeletonList items={3} />
            ) : (
              <div className="grid gap-4 lg:grid-cols-[280px_1fr]">
                {/* Student list */}
                <div className="space-y-1 max-h-[500px] overflow-y-auto rounded-md border border-[var(--neutral-stroke-2)] p-2">
                  <p className="mb-2 text-xs font-medium text-[var(--neutral-foreground-3)] px-2">
                    生徒一覧 ({studentRecords.length}名)
                  </p>
                  {studentRecords.map((rec) => {
                    const hasContent = DOMAIN_KEYS.some(
                      (k) => rec[k as keyof StudentRecord] && String(rec[k as keyof StudentRecord]).trim()
                    );
                    const isActive = editingStudentId === rec.student_id;
                    return (
                      <button
                        key={rec.student_id}
                        onClick={() => handleSelectStudent(rec)}
                        className={`flex w-full items-center justify-between rounded-md px-3 py-2 text-left text-sm transition-colors ${
                          isActive
                            ? 'bg-[var(--brand-160)] text-[var(--brand-60)]'
                            : 'hover:bg-[var(--neutral-background-3)] text-[var(--neutral-foreground-1)]'
                        }`}
                      >
                        <span>{rec.student?.student_name || `生徒ID: ${rec.student_id}`}</span>
                        {hasContent ? (
                          <MaterialIcon name="check_circle" size={16} className="text-[var(--status-success-fg)]" />
                        ) : (
                          <MaterialIcon name="schedule" size={16} className="text-[var(--neutral-foreground-4)]" />
                        )}
                      </button>
                    );
                  })}

                  {/* Add student button & dropdown */}
                  <div className="mt-2 border-t border-[var(--neutral-stroke-3)] pt-2">
                    {showAddStudent ? (
                      <div className="space-y-1">
                        <p className="px-2 text-xs font-medium text-[var(--neutral-foreground-3)]">追加する生徒を選択</p>
                        <div className="max-h-[200px] overflow-y-auto">
                          {allStudents
                            .filter((s) => !studentRecords.some((r) => r.student_id === s.id))
                            .map((s) => (
                              <button
                                key={s.id}
                                onClick={() => handleAddStudent(s)}
                                className="flex w-full items-center gap-2 rounded-md px-3 py-2 text-left text-sm text-[var(--neutral-foreground-1)] hover:bg-[var(--neutral-background-3)] transition-colors"
                              >
                                <MaterialIcon name="person_add" size={14} className="text-[var(--brand-80)]" />
                                {s.student_name}
                                {s.grade_level && (
                                  <Badge variant={GRADE_BADGE_VARIANT[s.grade_level] || 'default'} className="text-[10px] ml-auto">
                                    {GRADE_LABELS[s.grade_level] || s.grade_level}
                                  </Badge>
                                )}
                              </button>
                            ))}
                          {allStudents.filter((s) => !studentRecords.some((r) => r.student_id === s.id)).length === 0 && (
                            <p className="px-3 py-2 text-xs text-[var(--neutral-foreground-4)]">追加できる生徒がいません</p>
                          )}
                        </div>
                        <Button variant="ghost" size="sm" onClick={() => setShowAddStudent(false)} className="w-full">
                          キャンセル
                        </Button>
                      </div>
                    ) : (
                      <Button
                        variant="outline"
                        size="sm"
                        onClick={() => setShowAddStudent(true)}
                        className="w-full"
                        leftIcon={<MaterialIcon name="person_add" size={14} />}
                      >
                        生徒を追加
                      </Button>
                    )}
                  </div>
                </div>

                {/* Form area */}
                <div>
                  {editingStudentId ? (
                    <div className="space-y-3">
                      <p className="text-sm font-semibold text-[var(--neutral-foreground-1)]">
                        {studentRecords.find((r) => r.student_id === editingStudentId)?.student
                          ?.student_name || ''}
                        の記録
                      </p>

                      {/* 5 domain textareas */}
                      {DOMAIN_KEYS.map((key) => (
                        <div key={key}>
                          <label className="mb-1 block text-xs font-medium text-[var(--neutral-foreground-2)]">
                            {DOMAIN_LABELS[key]}
                          </label>
                          <textarea
                            rows={2}
                            value={studentFormData[key] || ''}
                            onChange={(e) =>
                              setStudentFormData((prev) => ({ ...prev, [key]: e.target.value }))
                            }
                            placeholder={`${DOMAIN_LABELS[key]}の観察記録...`}
                            className="block w-full rounded-md border border-[var(--neutral-stroke-1)] bg-[var(--neutral-background-1)] px-3 py-1.5 text-sm text-[var(--neutral-foreground-1)] placeholder-[var(--neutral-foreground-4)] focus:border-[var(--brand-80)] focus:outline-none focus:ring-1 focus:ring-[var(--brand-80)]"
                          />
                        </div>
                      ))}

                      {/* Notes */}
                      <div>
                        <label className="mb-1 block text-xs font-medium text-[var(--neutral-foreground-2)]">
                          個別メモ
                        </label>
                        <textarea
                          rows={4}
                          value={studentFormData.notes || ''}
                          onChange={(e) =>
                            setStudentFormData((prev) => ({ ...prev, notes: e.target.value }))
                          }
                          placeholder="その他のメモ..."
                          className="block w-full rounded-md border border-[var(--neutral-stroke-1)] bg-[var(--neutral-background-1)] px-3 py-1.5 text-sm text-[var(--neutral-foreground-1)] placeholder-[var(--neutral-foreground-4)] focus:border-[var(--brand-80)] focus:outline-none focus:ring-1 focus:ring-[var(--brand-80)]"
                        />
                      </div>

                      <div className="flex justify-end gap-2 pt-2">
                        <Button
                          variant="secondary"
                          size="sm"
                          onClick={() => setEditingStudentId(null)}
                        >
                          キャンセル
                        </Button>
                        <Button
                          size="sm"
                          isLoading={isSavingStudent}
                          leftIcon={<MaterialIcon name="save" size={16} />}
                          onClick={handleSaveStudentRecord}
                        >
                          保存
                        </Button>
                      </div>
                    </div>
                  ) : (
                    <div className="flex h-full items-center justify-center py-12 text-sm text-[var(--neutral-foreground-4)]">
                      <div className="text-center">
                        <MaterialIcon name="visibility" size={32} className="mx-auto mb-2" />
                        <p>左の一覧から生徒を選択してください</p>
                      </div>
                    </div>
                  )}
                </div>
              </div>
            )}
          </CardBody>
        </Card>
      )}

      {/* ================================================================= */}
      {/* Send to Guardians Modal (with draft save, auto-save, Ctrl+S)     */}
      {/* ================================================================= */}
      <Modal
        isOpen={showSendModal}
        onClose={() => setShowSendModal(false)}
        title="統合内容の編集"
        size="full"
      >
        <div className="space-y-4">
          <div className="rounded-md bg-[var(--neutral-background-3)] p-3 text-xs text-[var(--neutral-foreground-3)] border-l-4 border-[var(--status-warning-fg)]">
            <p>AIが生成した統合内容を確認・編集できます。</p>
            <p>途中保存した内容は、次回アクセス時に続きから編集できます。</p>
            <p>「途中保存」ボタンで下書き保存（自動保存: 5分ごと / ショートカット: Ctrl+S）</p>
            <p>「活動内容を送信」ボタンで保護者に配信されます。</p>
          </div>

          {lastSavedTime && (
            <p className="text-center text-xs text-[var(--neutral-foreground-3)]">
              最終保存: {lastSavedTime}
            </p>
          )}

          {studentRecords
            .filter((r) => r.student)
            .map((rec) => {
              const studentId = rec.student_id;
              const studentName = rec.student?.student_name || '';
              const isSent = sentStudentIds.has(studentId);
              return (
                <Card key={studentId}>
                  <CardBody>
                    <div className="flex items-center justify-between mb-2">
                      <div className="flex items-center gap-2">
                        <span className="text-sm font-semibold text-[var(--neutral-foreground-1)]">
                          {studentName}
                        </span>
                        {isSent && (
                          <Badge variant="success">送信済み</Badge>
                        )}
                      </div>
                      {!isSent && (
                        <Button
                          variant="subtle"
                          size="sm"
                          isLoading={isGenerating === studentId}
                          leftIcon={<MaterialIcon name="auto_awesome" size={16} />}
                          onClick={() => handleGenerateIntegrated(studentId)}
                        >
                          AI生成
                        </Button>
                      )}
                    </div>

                    {/* Domain summary (read only) */}
                    <div className="mb-2 flex flex-wrap gap-1">
                      {DOMAIN_KEYS.map((key) => {
                        const val = rec[key as keyof StudentRecord];
                        if (!val || !String(val).trim()) return null;
                        return (
                          <Badge key={key} variant="default" className="text-[10px]">
                            {DOMAIN_LABELS[key]}
                          </Badge>
                        );
                      })}
                    </div>

                    <textarea
                      rows={5}
                      value={sendNotes[studentId] || ''}
                      onChange={(e) =>
                        setSendNotes((prev) => ({ ...prev, [studentId]: e.target.value }))
                      }
                      readOnly={isSent}
                      placeholder="統合文を入力またはAIで生成..."
                      className={`block w-full rounded-md border border-[var(--neutral-stroke-1)] px-3 py-1.5 text-sm text-[var(--neutral-foreground-1)] placeholder-[var(--neutral-foreground-4)] focus:border-[var(--brand-80)] focus:outline-none focus:ring-1 focus:ring-[var(--brand-80)] ${
                        isSent ? 'bg-[var(--neutral-background-3)]' : 'bg-[var(--neutral-background-1)]'
                      }`}
                    />
                  </CardBody>
                </Card>
              );
            })}

          {studentRecords.length === 0 && (
            <p className="py-8 text-center text-sm text-[var(--neutral-foreground-3)]">
              この活動にはまだ生徒記録がありません
            </p>
          )}

          <div className="flex justify-between gap-2 pt-2 border-t border-[var(--neutral-stroke-3)]">
            <div className="flex gap-2">
              <Button
                variant="outline"
                size="sm"
                leftIcon={<MaterialIcon name="refresh" size={16} className="h-4 w-4" />}
                onClick={handleRegenerateAll}
              >
                統合をリセット
              </Button>
            </div>
            <div className="flex gap-2">
              <Button variant="secondary" onClick={() => setShowSendModal(false)}>
                キャンセル
              </Button>
              <Button
                variant="outline"
                isLoading={isSavingDraft}
                leftIcon={<MaterialIcon name="save" size={16} />}
                onClick={() => handleSaveDraft(false)}
              >
                途中保存
              </Button>
              <Button
                isLoading={isSending}
                leftIcon={<MaterialIcon name="send" size={16} />}
                onClick={handleSendToGuardians}
                disabled={Object.entries(sendNotes).every(([sid, v]) => !v.trim() || sentStudentIds.has(Number(sid)))}
              >
                保護者に送信
              </Button>
            </div>
          </div>
        </div>
      </Modal>

      {/* ================================================================= */}
      {/* View Integrated Notes Modal                                       */}
      {/* ================================================================= */}
      <Modal
        isOpen={showViewModal}
        onClose={() => setShowViewModal(false)}
        title="送信済み内容の閲覧"
        size="full"
      >
        {isLoadingView ? (
          <SkeletonList items={3} />
        ) : viewData ? (
          <div className="space-y-4">
            {/* Activity info */}
            <Card>
              <CardBody>
                <p className="text-sm"><strong>活動名:</strong> {viewData.activity.activity_name}</p>
                <p className="text-sm"><strong>記録日:</strong> {viewData.activity.record_date}</p>
                {viewData.activity.staff_name && (
                  <p className="text-sm"><strong>作成者:</strong> {viewData.activity.staff_name}</p>
                )}
              </CardBody>
            </Card>

            {/* Confirmation summary */}
            {viewData.summary.sent > 0 && (
              <div className="grid grid-cols-3 gap-3">
                <Card>
                  <CardBody>
                    <div className="text-center">
                      <p className="text-2xl font-bold text-[var(--neutral-foreground-1)]">{viewData.summary.sent}</p>
                      <p className="text-xs text-[var(--neutral-foreground-3)]">件送信済み</p>
                    </div>
                  </CardBody>
                </Card>
                <Card>
                  <CardBody>
                    <div className="text-center">
                      <p className="text-2xl font-bold text-[var(--status-success-fg)]">{viewData.summary.confirmed}</p>
                      <p className="text-xs text-[var(--neutral-foreground-3)]">件確認済み</p>
                    </div>
                  </CardBody>
                </Card>
                {viewData.summary.unconfirmed > 0 && (
                  <Card>
                    <CardBody>
                      <div className="text-center">
                        <p className="text-2xl font-bold text-[var(--status-danger-fg)]">{viewData.summary.unconfirmed}</p>
                        <p className="text-xs text-[var(--neutral-foreground-3)]">件未確認</p>
                      </div>
                    </CardBody>
                  </Card>
                )}
              </div>
            )}

            {/* Notes */}
            {viewData.notes
              .filter((n) => n.is_sent)
              .map((note) => (
                <Card
                  key={note.id}
                  className={`border-l-4 ${note.guardian_confirmed ? 'border-l-[var(--status-success-fg)]' : 'border-l-[var(--status-danger-fg)]'}`}
                >
                  <CardBody>
                    <div className="flex items-center justify-between mb-2">
                      <div className="flex items-center gap-2">
                        <span className="text-sm font-semibold text-[var(--neutral-foreground-1)]">
                          {note.student?.student_name || '不明'}
                        </span>
                        {note.student?.grade_level && (
                          <Badge variant={GRADE_BADGE_VARIANT[note.student.grade_level] || 'default'} className="text-[10px]">
                            {GRADE_LABELS[note.student.grade_level] || note.student.grade_level}
                          </Badge>
                        )}
                      </div>
                      <div className="flex gap-1">
                        <Badge variant="success">送信済み</Badge>
                        {note.guardian_confirmed ? (
                          <Badge variant="success">確認済み</Badge>
                        ) : (
                          <Badge variant="danger">未確認</Badge>
                        )}
                      </div>
                    </div>

                    <div className="rounded-md bg-[var(--neutral-background-3)] p-3 text-sm text-[var(--neutral-foreground-1)] whitespace-pre-wrap leading-relaxed mb-2">
                      {nl(note.integrated_content)}
                    </div>

                    <div className="text-xs text-[var(--neutral-foreground-3)] border-t border-[var(--neutral-stroke-3)] pt-2">
                      {note.sent_at && (
                        <span>送信日時: {format(new Date(note.sent_at), 'yyyy年M月d日 HH:mm')}</span>
                      )}
                    </div>

                    {note.guardian_confirmed && note.guardian_confirmed_at ? (
                      <div className="mt-2 rounded-md bg-[rgba(var(--status-success-fg-rgb,52,199,89),0.1)] p-2 text-xs text-[var(--status-success-fg)]">
                        <MaterialIcon name="check_circle" size={12} className="inline mr-1" />
                        保護者確認日時: {format(new Date(note.guardian_confirmed_at), 'yyyy年M月d日 HH:mm')}
                      </div>
                    ) : (
                      <div className="mt-2 rounded-md bg-[rgba(var(--status-danger-fg-rgb,255,59,48),0.1)] p-2 text-xs text-[var(--status-danger-fg)]">
                        <MaterialIcon name="error" size={12} className="inline mr-1" />
                        まだ保護者が確認していません
                      </div>
                    )}
                  </CardBody>
                </Card>
              ))}

            {viewData.notes.filter((n) => n.is_sent).length === 0 && (
              <Card>
                <CardBody>
                  <div className="py-8 text-center">
                    <p className="text-sm text-[var(--neutral-foreground-3)]">
                      送信済みの内容がありません
                    </p>
                    <p className="text-xs text-[var(--neutral-foreground-4)] mt-1">
                      「統合内容を編集」から統合内容を編集し、保護者に送信してください。
                    </p>
                  </div>
                </CardBody>
              </Card>
            )}
          </div>
        ) : (
          <p className="py-8 text-center text-sm text-[var(--neutral-foreground-3)]">
            データの読み込みに失敗しました
          </p>
        )}
      </Modal>
    </div>
  );
}
