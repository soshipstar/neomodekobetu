'use client';

import { useState, useEffect, useCallback } from 'react';
import { useSearchParams } from 'next/navigation';
import api from '@/lib/api';
import { Card, CardHeader, CardTitle, CardBody } from '@/components/ui/Card';
import { Button } from '@/components/ui/Button';
import { Badge } from '@/components/ui/Badge';
import { Modal } from '@/components/ui/Modal';
import { Input } from '@/components/ui/Input';
import { Tabs } from '@/components/ui/Tabs';
import { SkeletonList } from '@/components/ui/Skeleton';
import { useToast } from '@/components/ui/Toast';
import {
  Plus,
  ChevronLeft,
  ChevronRight,
  Calendar,
  Users,
  Send,
  Save,
  Sparkles,
  CheckCircle,
  AlertCircle,
  Clock,
  Trash2,
  Edit3,
  Eye,
  FileText,
} from 'lucide-react';
import { format, addDays, subDays } from 'date-fns';
import { ja } from 'date-fns/locale';

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
  const [students, setStudents] = useState<Student[]>([]);
  const [unconfirmedNotes, setUnconfirmedNotes] = useState<UnconfirmedNote[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [isLoadingUnconfirmed, setIsLoadingUnconfirmed] = useState(false);

  // --- Create activity modal ---
  const [showCreateModal, setShowCreateModal] = useState(false);
  const [createForm, setCreateForm] = useState({ activity_name: '', common_activity: '' });
  const [selectedStudentIds, setSelectedStudentIds] = useState<number[]>([]);
  const [isCreating, setIsCreating] = useState(false);

  // --- Student record form modal ---
  const [editingActivity, setEditingActivity] = useState<DailyRecord | null>(null);
  const [studentRecords, setStudentRecords] = useState<StudentRecord[]>([]);
  const [isLoadingRecords, setIsLoadingRecords] = useState(false);
  const [editingStudentId, setEditingStudentId] = useState<number | null>(null);
  const [studentFormData, setStudentFormData] = useState<Record<string, string>>({});
  const [isSavingStudent, setIsSavingStudent] = useState(false);

  // --- Send to guardians modal ---
  const [showSendModal, setShowSendModal] = useState(false);
  const [sendActivityId, setSendActivityId] = useState<number | null>(null);
  const [sendNotes, setSendNotes] = useState<Record<number, string>>({});
  const [isSending, setIsSending] = useState(false);
  const [isGenerating, setIsGenerating] = useState<number | null>(null);

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

  const fetchStudents = useCallback(async () => {
    try {
      const res = await api.get('/api/staff/students');
      const payload = res.data?.data;
      const items = payload?.data ?? payload;
      setStudents(Array.isArray(items) ? items : []);
    } catch {
      setStudents([]);
    }
  }, []);

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
    fetchStudents();
  }, [fetchStudents]);

  useEffect(() => {
    if (activeTab === 'unconfirmed') {
      fetchUnconfirmedNotes();
    }
  }, [activeTab, fetchUnconfirmedNotes]);

  // =========================================================================
  // Create activity
  // =========================================================================

  const handleOpenCreate = () => {
    setCreateForm({ activity_name: '', common_activity: '' });
    setSelectedStudentIds([]);
    setShowCreateModal(true);
  };

  const toggleStudentSelection = (id: number) => {
    setSelectedStudentIds((prev) =>
      prev.includes(id) ? prev.filter((sid) => sid !== id) : [...prev, id]
    );
  };

  const selectAllStudents = () => {
    setSelectedStudentIds(students.map((s) => s.id));
  };

  const deselectAllStudents = () => {
    setSelectedStudentIds([]);
  };

  const handleCreateActivity = async () => {
    if (!createForm.activity_name.trim()) {
      toast.warning('活動名を入力してください');
      return;
    }
    if (!createForm.common_activity.trim()) {
      toast.warning('本日の活動（共通）を入力してください');
      return;
    }
    if (selectedStudentIds.length === 0) {
      toast.warning('参加者を選択してください');
      return;
    }

    setIsCreating(true);
    try {
      await api.post('/api/staff/renrakucho', {
        record_date: dateStr,
        activity_name: createForm.activity_name,
        common_activity: createForm.common_activity,
        students: selectedStudentIds.map((id) => ({ id })),
      });
      toast.success('活動を作成しました');
      setShowCreateModal(false);
      fetchActivities();
    } catch (err: unknown) {
      const msg = (err as { response?: { data?: { message?: string } } })?.response?.data?.message;
      toast.error(msg || '作成に失敗しました');
    } finally {
      setIsCreating(false);
    }
  };

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
    setIsLoadingRecords(true);
    try {
      const res = await api.get(`/api/staff/renrakucho/${activity.id}/student-records`);
      const items = res.data?.data;
      setStudentRecords(Array.isArray(items) ? items : []);
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

  const handleSaveStudentRecord = async () => {
    if (!editingActivity || !editingStudentId) return;
    setIsSavingStudent(true);
    try {
      await api.post(`/api/staff/renrakucho/${editingActivity.id}/student-records`, {
        student_id: editingStudentId,
        ...studentFormData,
      });
      toast.success('保存しました');
      // Refresh records
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
  // Send to guardians
  // =========================================================================

  const handleOpenSendModal = (activityId: number) => {
    setSendActivityId(activityId);
    setSendNotes({});
    setShowSendModal(true);

    // Pre-populate send notes from student records
    const activity = activities.find((a) => a.id === activityId);
    if (activity) {
      api
        .get(`/api/staff/renrakucho/${activityId}/student-records`)
        .then((res) => {
          const recs: StudentRecord[] = res.data?.data ?? [];
          const notes: Record<number, string> = {};
          recs.forEach((r) => {
            // Build default content from domains
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
          // Also store student records for reference in send modal
          setStudentRecords(recs);
        })
        .catch(() => {
          /* ignore */
        });
    }
  };

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

  const handleSendToGuardians = async () => {
    if (!sendActivityId) return;
    const notesArray = Object.entries(sendNotes)
      .filter(([, content]) => content.trim())
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
              <ChevronLeft className="h-4 w-4" />
            </Button>
            <Button variant="outline" size="sm" onClick={goToToday}>
              今日
            </Button>
            <Button variant="ghost" size="sm" onClick={goToNextDay}>
              <ChevronRight className="h-4 w-4" />
            </Button>
          </div>
          <div className="flex items-center gap-2">
            <Calendar className="h-4 w-4 text-[var(--neutral-foreground-3)]" />
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
                      <Users className="h-3 w-3" />
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
                    <Edit3 className="h-4 w-4" />
                  </Button>
                  <Button
                    variant="subtle"
                    size="sm"
                    title="保護者に送信"
                    onClick={() => handleOpenSendModal(activity.id)}
                  >
                    <Send className="h-4 w-4" />
                  </Button>
                  <Button
                    variant="ghost"
                    size="sm"
                    title="削除"
                    onClick={() => handleDeleteActivity(activity.id)}
                  >
                    <Trash2 className="h-4 w-4 text-[var(--status-danger-fg)]" />
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
              <FileText className="mx-auto mb-3 h-10 w-10 text-[var(--neutral-foreground-4)]" />
              <p className="text-sm text-[var(--neutral-foreground-3)]">
                この日の活動はまだ作成されていません
              </p>
              <Button className="mt-4" size="sm" leftIcon={<Plus className="h-4 w-4" />} onClick={handleOpenCreate}>
                活動を作成する
              </Button>
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
                    {note.integrated_content}
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
              <CheckCircle className="mx-auto mb-3 h-10 w-10 text-[var(--status-success-fg)]" />
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
        <Button leftIcon={<Plus className="h-4 w-4" />} onClick={handleOpenCreate}>
          活動を作成
        </Button>
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
            icon: <FileText className="h-4 w-4" />,
            badge: activities.length,
            content: renderActivityList(),
          },
          {
            key: 'unconfirmed',
            label: '未確認連絡帳',
            icon: <AlertCircle className="h-4 w-4" />,
            badge: unconfirmedNotes.length,
            content: renderUnconfirmedNotes(),
          },
        ]}
      />

      {/* ================================================================= */}
      {/* Create Activity Modal                                             */}
      {/* ================================================================= */}
      <Modal
        isOpen={showCreateModal}
        onClose={() => setShowCreateModal(false)}
        title="新しい活動を作成"
        size="xl"
      >
        <div className="space-y-4">
          <Input
            label="活動名"
            placeholder="例: 午前の活動、外出活動、制作活動など"
            value={createForm.activity_name}
            onChange={(e) => setCreateForm((f) => ({ ...f, activity_name: e.target.value }))}
          />

          <div>
            <label className="mb-1 block text-sm font-medium text-[var(--neutral-foreground-1)]">
              本日の活動（共通）
            </label>
            <textarea
              rows={3}
              placeholder="本日の活動内容を記入してください"
              value={createForm.common_activity}
              onChange={(e) => setCreateForm((f) => ({ ...f, common_activity: e.target.value }))}
              className="block w-full rounded-md border border-[var(--neutral-stroke-1)] bg-[var(--neutral-background-1)] px-3 py-1.5 text-sm text-[var(--neutral-foreground-1)] placeholder-[var(--neutral-foreground-4)] focus:border-[var(--brand-80)] focus:outline-none focus:ring-1 focus:ring-[var(--brand-80)]"
            />
          </div>

          {/* Student selection */}
          <div>
            <div className="flex items-center justify-between mb-2">
              <label className="text-sm font-medium text-[var(--neutral-foreground-1)]">
                参加者選択 <span className="text-[var(--status-danger-fg)]">*</span>
              </label>
              <div className="flex gap-2">
                <Button variant="ghost" size="sm" onClick={selectAllStudents}>
                  全選択
                </Button>
                <Button variant="ghost" size="sm" onClick={deselectAllStudents}>
                  全解除
                </Button>
              </div>
            </div>
            <p className="mb-2 text-xs text-[var(--neutral-foreground-3)]">
              {selectedStudentIds.length}名選択中
            </p>
            <div className="flex flex-wrap gap-2 max-h-60 overflow-y-auto rounded-md border border-[var(--neutral-stroke-2)] p-3">
              {students.map((student) => {
                const isChecked = selectedStudentIds.includes(student.id);
                return (
                  <label
                    key={student.id}
                    className={`flex cursor-pointer items-center gap-2 rounded-md px-3 py-2 text-sm transition-colors ${
                      isChecked
                        ? 'bg-[var(--brand-160)] text-[var(--brand-60)]'
                        : 'bg-[var(--neutral-background-3)] text-[var(--neutral-foreground-2)] hover:bg-[var(--neutral-background-4)]'
                    }`}
                  >
                    <input
                      type="checkbox"
                      checked={isChecked}
                      onChange={() => toggleStudentSelection(student.id)}
                      className="h-4 w-4 rounded border-[var(--neutral-stroke-1)] accent-[var(--brand-80)]"
                    />
                    {student.student_name}
                    {student.grade_level && (
                      <Badge
                        variant={GRADE_BADGE_VARIANT[student.grade_level] || 'default'}
                        className="text-[10px] px-1.5"
                      >
                        {GRADE_LABELS[student.grade_level] || student.grade_level}
                      </Badge>
                    )}
                  </label>
                );
              })}
              {students.length === 0 && (
                <p className="text-xs text-[var(--neutral-foreground-4)]">生徒が登録されていません</p>
              )}
            </div>
          </div>

          <div className="flex justify-end gap-2 pt-2 border-t border-[var(--neutral-stroke-3)]">
            <Button variant="secondary" onClick={() => setShowCreateModal(false)}>
              キャンセル
            </Button>
            <Button
              isLoading={isCreating}
              leftIcon={<Plus className="h-4 w-4" />}
              onClick={handleCreateActivity}
            >
              作成
            </Button>
          </div>
        </div>
      </Modal>

      {/* ================================================================= */}
      {/* Student Record Edit Panel (shown below activity list)             */}
      {/* ================================================================= */}
      {editingActivity && (
        <Card>
          <CardHeader>
            <CardTitle>
              <div className="flex items-center gap-2">
                <Edit3 className="h-4 w-4" />
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
                          <CheckCircle className="h-4 w-4 text-[var(--status-success-fg)]" />
                        ) : (
                          <Clock className="h-4 w-4 text-[var(--neutral-foreground-4)]" />
                        )}
                      </button>
                    );
                  })}
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
                          rows={2}
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
                          leftIcon={<Save className="h-4 w-4" />}
                          onClick={handleSaveStudentRecord}
                        >
                          保存
                        </Button>
                      </div>
                    </div>
                  ) : (
                    <div className="flex h-full items-center justify-center py-12 text-sm text-[var(--neutral-foreground-4)]">
                      <div className="text-center">
                        <Eye className="mx-auto mb-2 h-8 w-8" />
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
      {/* Send to Guardians Modal                                           */}
      {/* ================================================================= */}
      <Modal
        isOpen={showSendModal}
        onClose={() => setShowSendModal(false)}
        title="保護者へ送信"
        size="full"
      >
        <div className="space-y-4">
          <p className="text-xs text-[var(--neutral-foreground-3)]">
            各生徒の連絡帳文を確認・編集して送信してください。AIボタンで5領域の観察記録から統合文を自動生成できます。
          </p>

          {studentRecords
            .filter((r) => r.student)
            .map((rec) => {
              const studentId = rec.student_id;
              const studentName = rec.student?.student_name || '';
              return (
                <Card key={studentId}>
                  <CardBody>
                    <div className="flex items-center justify-between mb-2">
                      <span className="text-sm font-semibold text-[var(--neutral-foreground-1)]">
                        {studentName}
                      </span>
                      <Button
                        variant="subtle"
                        size="sm"
                        isLoading={isGenerating === studentId}
                        leftIcon={<Sparkles className="h-4 w-4" />}
                        onClick={() => handleGenerateIntegrated(studentId)}
                      >
                        AI生成
                      </Button>
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
                      placeholder="送信する連絡帳文を入力..."
                      className="block w-full rounded-md border border-[var(--neutral-stroke-1)] bg-[var(--neutral-background-1)] px-3 py-1.5 text-sm text-[var(--neutral-foreground-1)] placeholder-[var(--neutral-foreground-4)] focus:border-[var(--brand-80)] focus:outline-none focus:ring-1 focus:ring-[var(--brand-80)]"
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

          <div className="flex justify-end gap-2 pt-2 border-t border-[var(--neutral-stroke-3)]">
            <Button variant="secondary" onClick={() => setShowSendModal(false)}>
              キャンセル
            </Button>
            <Button
              isLoading={isSending}
              leftIcon={<Send className="h-4 w-4" />}
              onClick={handleSendToGuardians}
              disabled={Object.values(sendNotes).every((v) => !v.trim())}
            >
              保護者に送信
            </Button>
          </div>
        </div>
      </Modal>
    </div>
  );
}
