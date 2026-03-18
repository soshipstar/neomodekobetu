'use client';

import { useState, useEffect, useCallback } from 'react';
import { useSearchParams, useRouter } from 'next/navigation';
import api from '@/lib/api';
import { Card, CardHeader, CardTitle, CardBody } from '@/components/ui/Card';
import { Button } from '@/components/ui/Button';
import { Input } from '@/components/ui/Input';
import { Badge } from '@/components/ui/Badge';
import { Skeleton } from '@/components/ui/Skeleton';
import { useToast } from '@/components/ui/Toast';
import {
  ArrowLeft,
  Plus,
  Trash2,
  Search,
  X,
  UserPlus,
} from 'lucide-react';
import Link from 'next/link';

// ---------------------------------------------------------------------------
// Types
// ---------------------------------------------------------------------------

interface Student {
  id: number;
  student_name: string;
  grade_level: string;
  is_scheduled_today: boolean;
}

interface StudentFormData {
  id: number;
  student_name: string;
  daily_note: string;
  domain1: string;
  domain1_content: string;
  domain2: string;
  domain2_content: string;
}

const DOMAINS = [
  { value: 'health_life', label: '健康・生活' },
  { value: 'motor_sensory', label: '運動・感覚' },
  { value: 'cognitive_behavior', label: '認知・行動' },
  { value: 'language_communication', label: '言語・コミュニケーション' },
  { value: 'social_relations', label: '人間関係・社会性' },
];

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

// ---------------------------------------------------------------------------
// Page
// ---------------------------------------------------------------------------

export default function NewActivityPage() {
  const searchParams = useSearchParams();
  const router = useRouter();
  const toast = useToast();

  const dateParam = searchParams.get('date') || new Date().toISOString().split('T')[0];

  // Form state
  const [activityName, setActivityName] = useState('');
  const [commonActivity, setCommonActivity] = useState('');
  const [students, setStudents] = useState<StudentFormData[]>([]);
  const [saving, setSaving] = useState(false);
  const [selectedPlanId, setSelectedPlanId] = useState<number | null>(null);

  // Support plans
  const [supportPlans, setSupportPlans] = useState<any[]>([]);
  const [loadingPlans, setLoadingPlans] = useState(false);

  // Student picker modal
  const [showStudentPicker, setShowStudentPicker] = useState(false);
  const [availableStudents, setAvailableStudents] = useState<Student[]>([]);
  const [loadingStudents, setLoadingStudents] = useState(false);
  const [studentSearch, setStudentSearch] = useState('');

  // Fetch available students
  const fetchStudents = useCallback(async () => {
    setLoadingStudents(true);
    try {
      const res = await api.get('/api/staff/dashboard/attendance', { params: { date: dateParam } });
      const payload = res.data?.data ?? res.data;
      const list = Array.isArray(payload) ? payload : (Array.isArray(payload?.students) ? payload.students : []);
      setAvailableStudents(list.map((s: { id: number; name: string; grade_group: string; type: string }) => ({
        id: s.id,
        student_name: s.name,
        grade_level: s.grade_group,
        is_scheduled_today: s.type === 'regular',
      })));
    } catch {
      // Fallback: try to get all classroom students
      try {
        const res = await api.get('/api/admin/students', { params: { per_page: 200 } });
        const payload = res.data?.data ?? res.data;
        const list = Array.isArray(payload) ? payload : (payload?.data ?? []);
        setAvailableStudents(list.map((s: { id: number; student_name: string; grade_level: string }) => ({
          id: s.id,
          student_name: s.student_name,
          grade_level: s.grade_level,
          is_scheduled_today: false,
        })));
      } catch {
        setAvailableStudents([]);
      }
    }
    setLoadingStudents(false);
  }, [dateParam]);

  useEffect(() => {
    fetchStudents();
  }, [fetchStudents]);

  // Fetch support plans
  useEffect(() => {
    setLoadingPlans(true);
    api.get('/api/staff/activity-support-plans', { params: { per_page: 100 } })
      .then((res) => {
        const p = res.data?.data;
        setSupportPlans(Array.isArray(p) ? p : []);
      })
      .catch(() => setSupportPlans([]))
      .finally(() => setLoadingPlans(false));
  }, []);

  // Handle support plan selection
  const handleSelectPlan = (planId: string) => {
    if (!planId) {
      setSelectedPlanId(null);
      return;
    }
    const plan = supportPlans.find((p: any) => p.id === Number(planId));
    if (plan) {
      setSelectedPlanId(plan.id);
      setActivityName(plan.activity_name || '');
      setCommonActivity(
        [plan.activity_purpose, plan.activity_content].filter(Boolean).join('\n\n') || ''
      );
    }
  };

  // Add students to form
  const addStudent = (student: Student) => {
    if (students.find((s) => s.id === student.id)) return;
    setStudents((prev) => [
      ...prev,
      {
        id: student.id,
        student_name: student.student_name,
        daily_note: '',
        domain1: '',
        domain1_content: '',
        domain2: '',
        domain2_content: '',
      },
    ]);
  };

  const removeStudent = (studentId: number) => {
    setStudents((prev) => prev.filter((s) => s.id !== studentId));
  };

  const updateStudentField = (studentId: number, field: keyof StudentFormData, value: string) => {
    setStudents((prev) =>
      prev.map((s) => (s.id === studentId ? { ...s, [field]: value } : s))
    );
  };

  // Add all scheduled students
  const addAllScheduled = () => {
    const existing = new Set(students.map((s) => s.id));
    const newStudents = availableStudents
      .filter((s) => s.is_scheduled_today && !existing.has(s.id))
      .map((s) => ({
        id: s.id,
        student_name: s.student_name,
        daily_note: '',
        domain1: '',
        domain1_content: '',
        domain2: '',
        domain2_content: '',
      }));
    setStudents((prev) => [...prev, ...newStudents]);
  };

  // Save
  const handleSave = async () => {
    if (!activityName.trim()) {
      toast.warning('活動名を入力してください');
      return;
    }
    if (!commonActivity.trim()) {
      toast.warning('共通の活動内容を入力してください');
      return;
    }
    if (students.length === 0) {
      toast.warning('生徒を1名以上追加してください');
      return;
    }

    // Validate student domains
    for (const s of students) {
      if (!s.domain1) {
        toast.warning(`${s.student_name}: 領域1を選択してください`);
        return;
      }
      if (!s.domain1_content.trim()) {
        toast.warning(`${s.student_name}: 領域1の内容を入力してください`);
        return;
      }
      if (s.domain2 && s.domain1 === s.domain2) {
        toast.warning(`${s.student_name}: 領域1と領域2に同じ項目を選択できません`);
        return;
      }
    }

    setSaving(true);
    try {
      await api.post('/api/staff/renrakucho', {
        record_date: dateParam,
        activity_name: activityName,
        common_activity: commonActivity,
        support_plan_id: selectedPlanId || undefined,
        students: students.map((s) => ({
          id: s.id,
          daily_note: s.daily_note,
          domain1: s.domain1,
          domain1_content: s.domain1_content,
          domain2: s.domain2 || null,
          domain2_content: s.domain2_content || null,
        })),
      });
      toast.success('活動を保存しました');
      router.push('/staff/dashboard');
    } catch (err: unknown) {
      const message = (err as { response?: { data?: { message?: string } } })?.response?.data?.message || '保存に失敗しました';
      toast.error(message);
    }
    setSaving(false);
  };

  // Filtered available students for picker
  const filteredAvailable = availableStudents.filter((s) => {
    if (students.find((added) => added.id === s.id)) return false;
    if (studentSearch) {
      return s.student_name.toLowerCase().includes(studentSearch.toLowerCase());
    }
    return true;
  });

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex items-center gap-3">
        <Link href="/staff/dashboard">
          <Button variant="ghost" size="sm">
            <ArrowLeft className="h-4 w-4" />
          </Button>
        </Link>
        <div>
          <h1 className="text-xl font-semibold text-[var(--neutral-foreground-1)]">
            新規活動記録
          </h1>
          <p className="text-sm text-[var(--neutral-foreground-3)]">
            {dateParam.replace(/-/g, '/')}
          </p>
        </div>
      </div>

      {/* Activity info */}
      <Card>
        <CardHeader>
          <CardTitle>活動情報</CardTitle>
        </CardHeader>
        <CardBody className="space-y-4">
          {/* Support Plan Selector */}
          <div>
            <label className="mb-1 block text-sm font-medium text-[var(--neutral-foreground-1)]">
              支援案から作成（任意）
            </label>
            <select
              value={selectedPlanId ?? ''}
              onChange={(e) => handleSelectPlan(e.target.value)}
              className="block w-full rounded-lg border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-1)] px-3 py-2 text-sm text-[var(--neutral-foreground-1)]"
            >
              <option value="">支援案を選択しない（手動入力）</option>
              {supportPlans
                .filter((plan: any) => plan.activity_date && plan.activity_date.startsWith(dateParam))
                .map((plan: any) => (
                <option key={plan.id} value={plan.id}>
                  {plan.activity_name} {plan.tags ? `[${plan.tags}]` : ''}
                </option>
              ))}
            </select>
            <p className="mt-1 text-xs text-[var(--neutral-foreground-4)]">
              支援案を選択すると、活動名と内容が自動入力されます
            </p>
          </div>

          <div>
            <label className="mb-1 block text-sm font-medium text-[var(--neutral-foreground-1)]">
              活動名 <span className="text-[var(--status-danger-fg)]">*</span>
            </label>
            <Input
              value={activityName}
              onChange={(e) => setActivityName(e.target.value)}
              placeholder="例: 公園で散歩、音楽活動、制作活動"
            />
          </div>
          <div>
            <label className="mb-1 block text-sm font-medium text-[var(--neutral-foreground-1)]">
              本日の活動（共通） <span className="text-[var(--status-danger-fg)]">*</span>
            </label>
            <textarea
              className="w-full rounded-md border border-[var(--neutral-stroke-1)] bg-[var(--neutral-background-1)] px-3 py-2 text-sm text-[var(--neutral-foreground-1)] placeholder:text-[var(--neutral-foreground-4)] focus:border-[var(--brand-80)] focus:outline-none focus:ring-1 focus:ring-[var(--brand-80)]"
              rows={3}
              value={commonActivity}
              onChange={(e) => setCommonActivity(e.target.value)}
              placeholder="例: 公園で散歩、音楽活動、制作活動など"
            />
          </div>
        </CardBody>
      </Card>

      {/* Student list */}
      <Card>
        <CardHeader>
          <CardTitle>参加生徒 ({students.length}名)</CardTitle>
          <div className="flex gap-2">
            <Button
              variant="outline"
              size="sm"
              onClick={addAllScheduled}
              leftIcon={<UserPlus className="h-4 w-4" />}
            >
              本日予定の生徒を追加
            </Button>
            <Button
              size="sm"
              onClick={() => setShowStudentPicker(true)}
              leftIcon={<Plus className="h-4 w-4" />}
            >
              生徒を追加
            </Button>
          </div>
        </CardHeader>
        <CardBody>
          {students.length === 0 ? (
            <div className="py-8 text-center text-sm text-[var(--neutral-foreground-4)]">
              <UserPlus className="mx-auto mb-2 h-10 w-10" />
              <p>参加生徒を追加してください</p>
              <Button
                variant="outline"
                size="sm"
                className="mt-3"
                onClick={addAllScheduled}
              >
                本日予定の生徒を一括追加
              </Button>
            </div>
          ) : (
            <div className="space-y-4">
              {students.map((student) => (
                <StudentRecordCard
                  key={student.id}
                  student={student}
                  onUpdate={(field, value) => updateStudentField(student.id, field, value)}
                  onRemove={() => removeStudent(student.id)}
                />
              ))}
            </div>
          )}
        </CardBody>
      </Card>

      {/* Save button */}
      <div className="flex justify-end gap-3">
        <Link href="/staff/dashboard">
          <Button variant="outline">キャンセル</Button>
        </Link>
        <Button onClick={handleSave} disabled={saving}>
          {saving ? '保存中...' : '保存'}
        </Button>
      </div>

      {/* Student picker modal */}
      {showStudentPicker && (
        <StudentPickerModal
          students={filteredAvailable}
          loading={loadingStudents}
          search={studentSearch}
          onSearchChange={setStudentSearch}
          onAdd={addStudent}
          onClose={() => { setShowStudentPicker(false); setStudentSearch(''); }}
        />
      )}
    </div>
  );
}

// ---------------------------------------------------------------------------
// Student Record Card
// ---------------------------------------------------------------------------

function StudentRecordCard({
  student,
  onUpdate,
  onRemove,
}: {
  student: StudentFormData;
  onUpdate: (field: keyof StudentFormData, value: string) => void;
  onRemove: () => void;
}) {
  return (
    <div className="rounded-lg border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-1)] p-4">
      {/* Header */}
      <div className="mb-3 flex items-center justify-between">
        <h4 className="text-sm font-semibold text-[var(--neutral-foreground-1)]">
          {student.student_name}
        </h4>
        <button
          onClick={onRemove}
          className="rounded p-1 text-[var(--neutral-foreground-4)] hover:bg-[var(--neutral-background-3)] hover:text-[var(--status-danger-fg)]"
        >
          <Trash2 className="h-4 w-4" />
        </button>
      </div>

      {/* Daily note */}
      <div className="mb-3">
        <label className="mb-1 block text-xs font-medium text-[var(--neutral-foreground-2)]">
          本日の様子
        </label>
        <textarea
          className="w-full rounded-md border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-1)] px-3 py-2 text-sm focus:border-[var(--brand-80)] focus:outline-none focus:ring-1 focus:ring-[var(--brand-80)]"
          rows={2}
          value={student.daily_note}
          onChange={(e) => onUpdate('daily_note', e.target.value)}
          placeholder="本日の様子を記入..."
        />
      </div>

      {/* Domain 1 */}
      <div className="mb-3 rounded-md bg-[var(--status-info-bg)] p-3">
        <label className="mb-1 block text-xs font-semibold text-[var(--neutral-foreground-1)]">
          気になったこと 1つ目 <span className="text-[var(--status-danger-fg)]">*</span>
        </label>
        <select
          className="mb-2 w-full rounded-md border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-1)] px-3 py-1.5 text-sm focus:border-[var(--brand-80)] focus:outline-none"
          value={student.domain1}
          onChange={(e) => onUpdate('domain1', e.target.value)}
        >
          <option value="">領域を選択...</option>
          {DOMAINS.map((d) => (
            <option key={d.value} value={d.value}>{d.label}</option>
          ))}
        </select>
        <textarea
          className="w-full rounded-md border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-1)] px-3 py-2 text-sm focus:border-[var(--brand-80)] focus:outline-none"
          rows={2}
          value={student.domain1_content}
          onChange={(e) => onUpdate('domain1_content', e.target.value)}
          placeholder="具体的な内容を記入..."
        />
      </div>

      {/* Domain 2 */}
      <div className="rounded-md bg-[var(--neutral-background-3)] p-3">
        <label className="mb-1 block text-xs font-semibold text-[var(--neutral-foreground-2)]">
          気になったこと 2つ目（任意）
        </label>
        <select
          className="mb-2 w-full rounded-md border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-1)] px-3 py-1.5 text-sm focus:border-[var(--brand-80)] focus:outline-none"
          value={student.domain2}
          onChange={(e) => onUpdate('domain2', e.target.value)}
        >
          <option value="">領域を選択...</option>
          {DOMAINS.filter((d) => d.value !== student.domain1).map((d) => (
            <option key={d.value} value={d.value}>{d.label}</option>
          ))}
        </select>
        {student.domain2 && (
          <textarea
            className="w-full rounded-md border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-1)] px-3 py-2 text-sm focus:border-[var(--brand-80)] focus:outline-none"
            rows={2}
            value={student.domain2_content}
            onChange={(e) => onUpdate('domain2_content', e.target.value)}
            placeholder="具体的な内容を記入..."
          />
        )}
      </div>
    </div>
  );
}

// ---------------------------------------------------------------------------
// Student Picker Modal
// ---------------------------------------------------------------------------

function StudentPickerModal({
  students,
  loading,
  search,
  onSearchChange,
  onAdd,
  onClose,
}: {
  students: Student[];
  loading: boolean;
  search: string;
  onSearchChange: (v: string) => void;
  onAdd: (s: Student) => void;
  onClose: () => void;
}) {
  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40">
      <div className="w-full max-w-md rounded-lg border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-1)] shadow-[var(--shadow-28)]">
        {/* Header */}
        <div className="flex items-center justify-between border-b border-[var(--neutral-stroke-2)] px-4 py-3">
          <h3 className="text-sm font-semibold text-[var(--neutral-foreground-1)]">
            生徒を追加
          </h3>
          <button onClick={onClose} className="rounded p-1 hover:bg-[var(--neutral-background-3)]">
            <X className="h-4 w-4" />
          </button>
        </div>

        {/* Search */}
        <div className="border-b border-[var(--neutral-stroke-3)] px-4 py-2">
          <div className="relative">
            <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-[var(--neutral-foreground-4)]" />
            <Input
              placeholder="名前で検索..."
              value={search}
              onChange={(e) => onSearchChange(e.target.value)}
              className="pl-10"
            />
          </div>
        </div>

        {/* List */}
        <div className="max-h-80 overflow-y-auto p-2">
          {loading ? (
            <div className="space-y-2 p-2">
              {[...Array(5)].map((_, i) => <Skeleton key={i} className="h-10 w-full rounded" />)}
            </div>
          ) : students.length === 0 ? (
            <p className="py-8 text-center text-sm text-[var(--neutral-foreground-4)]">
              追加可能な生徒がいません
            </p>
          ) : (
            students.map((student) => (
              <button
                key={student.id}
                onClick={() => onAdd(student)}
                className="flex w-full items-center justify-between rounded-md px-3 py-2 text-left transition-colors hover:bg-[var(--neutral-background-3)]"
              >
                <div>
                  <span className="text-sm font-medium text-[var(--neutral-foreground-1)]">
                    {student.student_name}
                  </span>
                  <span className="ml-2 text-xs text-[var(--neutral-foreground-3)]">
                    {GRADE_LABELS[student.grade_level] || student.grade_level}
                  </span>
                </div>
                {student.is_scheduled_today && (
                  <Badge variant="info">本日予定</Badge>
                )}
              </button>
            ))
          )}
        </div>

        {/* Footer */}
        <div className="border-t border-[var(--neutral-stroke-2)] px-4 py-3">
          <Button variant="outline" size="sm" onClick={onClose} className="w-full">
            閉じる
          </Button>
        </div>
      </div>
    </div>
  );
}
