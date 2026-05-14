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
import Link from 'next/link';
import { MaterialIcon } from '@/components/ui/MaterialIcon';

// ---------------------------------------------------------------------------
// Types
// ---------------------------------------------------------------------------

interface Student {
  id: number;
  student_name: string;
  grade_level: string;
  is_scheduled_today: boolean;
}

interface ConcernEntry {
  domain: string;        // domain key (health_life, etc.) or '' if not selected
  content: string;       // 具体的な内容
  goal_snapshot: string; // 領域選択時に自動で挿入される目標 (read-only 表示)
  quote_goal: boolean;   // 「引用する」チェック
}

interface StudentFormData {
  id: number;
  student_name: string;
  daily_note: string;
  concerns: ConcernEntry[];
  /** 領域別目標を即時取得した結果のキャッシュ (FE 表示用) */
  domain_goals?: Record<string, string | null>;
}

const DOMAINS = [
  { value: 'health_life', label: '健康・生活' },
  { value: 'motor_sensory', label: '運動・感覚' },
  { value: 'cognitive_behavior', label: '認知・行動' },
  { value: 'language_communication', label: '言語・コミュニケーション' },
  { value: 'social_relations', label: '人間関係・社会性' },
];
const DOMAIN_LABEL_MAP: Record<string, string> = Object.fromEntries(DOMAINS.map((d) => [d.value, d.label]));

/** 必須の concern 数。1つ目と2つ目 (index 0, 1) は必須。 */
const REQUIRED_CONCERNS = 2;

function emptyConcern(): ConcernEntry {
  return { domain: '', content: '', goal_snapshot: '', quote_goal: false };
}

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

  /** 生徒の領域別目標 (support_plan_details) を fetch してフォームに格納 */
  const fetchStudentGoals = useCallback(async (studentId: number) => {
    try {
      const res = await api.get<{ data: { domains: Record<string, string | null> } | null }>(
        `/api/staff/renrakucho/student-goals/${studentId}`
      );
      const domains = res.data?.data?.domains ?? null;
      setStudents((prev) => prev.map((s) => (s.id === studentId ? { ...s, domain_goals: domains ?? {} } : s)));
    } catch {
      setStudents((prev) => prev.map((s) => (s.id === studentId ? { ...s, domain_goals: {} } : s)));
    }
  }, []);

  // Add students to form
  const addStudent = (student: Student) => {
    if (students.find((s) => s.id === student.id)) return;
    setStudents((prev) => [
      ...prev,
      {
        id: student.id,
        student_name: student.student_name,
        daily_note: '',
        concerns: [emptyConcern(), emptyConcern()],  // 1つ目・2つ目 必須
      },
    ]);
    fetchStudentGoals(student.id);
  };

  const removeStudent = (studentId: number) => {
    setStudents((prev) => prev.filter((s) => s.id !== studentId));
  };

  const updateStudentDailyNote = (studentId: number, value: string) => {
    setStudents((prev) => prev.map((s) => (s.id === studentId ? { ...s, daily_note: value } : s)));
  };

  const updateConcern = (studentId: number, idx: number, patch: Partial<ConcernEntry>) => {
    setStudents((prev) =>
      prev.map((s) => {
        if (s.id !== studentId) return s;
        const next = [...s.concerns];
        next[idx] = { ...next[idx], ...patch };
        // 領域変更時は goal_snapshot を再取得 (domain_goals キャッシュから)
        if (patch.domain !== undefined) {
          const goal = (s.domain_goals?.[patch.domain] ?? '') as string;
          next[idx].goal_snapshot = goal;
          // 領域が空になったら quote_goal は false に
          if (!patch.domain) next[idx].quote_goal = false;
        }
        return { ...s, concerns: next };
      })
    );
  };

  const addConcernRow = (studentId: number) => {
    setStudents((prev) =>
      prev.map((s) => {
        if (s.id !== studentId) return s;
        if (s.concerns.length >= DOMAINS.length) return s; // 上限: 5領域
        return { ...s, concerns: [...s.concerns, emptyConcern()] };
      })
    );
  };

  const removeConcernRow = (studentId: number, idx: number) => {
    setStudents((prev) =>
      prev.map((s) => {
        if (s.id !== studentId) return s;
        // 必須 (1つ目・2つ目) は削除不可
        if (idx < REQUIRED_CONCERNS) return s;
        return { ...s, concerns: s.concerns.filter((_, i) => i !== idx) };
      })
    );
  };

  // Add all scheduled students
  const addAllScheduled = () => {
    const existing = new Set(students.map((s) => s.id));
    const newOnes = availableStudents
      .filter((s) => s.is_scheduled_today && !existing.has(s.id))
      .map((s) => ({
        id: s.id,
        student_name: s.student_name,
        daily_note: '',
        concerns: [emptyConcern(), emptyConcern()],
      }));
    setStudents((prev) => [...prev, ...newOnes]);
    newOnes.forEach((s) => fetchStudentGoals(s.id));
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

    // Validate concerns: 1つ目・2つ目は必須、3つ目以降は任意だが domain 入力時は content 必須
    for (const s of students) {
      for (let i = 0; i < s.concerns.length; i++) {
        const c = s.concerns[i];
        if (i < REQUIRED_CONCERNS) {
          if (!c.domain) {
            toast.warning(`${s.student_name}: 気になったこと${i + 1}つ目の領域を選択してください`);
            return;
          }
          if (!c.content.trim()) {
            toast.warning(`${s.student_name}: 気になったこと${i + 1}つ目の内容を入力してください`);
            return;
          }
        } else if (c.domain && !c.content.trim()) {
          toast.warning(`${s.student_name}: ${i + 1}つ目の気になったことの内容を入力してください`);
          return;
        }
      }
      // 領域重複チェック
      const usedDomains = s.concerns.map((c) => c.domain).filter(Boolean);
      const uniqueDomains = new Set(usedDomains);
      if (uniqueDomains.size !== usedDomains.length) {
        toast.warning(`${s.student_name}: 同じ領域を複数回選択できません`);
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
        students: students.map((s) => {
          // concerns を 5 領域カラム + domain_goal_quotes に展開
          const domainFields: Record<string, string | null> = {
            health_life: null,
            motor_sensory: null,
            cognitive_behavior: null,
            language_communication: null,
            social_relations: null,
          };
          const goalQuotes: Record<string, { quoted: boolean; goal_snapshot: string | null }> = {};
          for (const c of s.concerns) {
            if (!c.domain) continue;
            if (c.content.trim()) {
              domainFields[c.domain] = c.content.trim();
            }
            if (c.quote_goal && c.goal_snapshot) {
              goalQuotes[c.domain] = { quoted: true, goal_snapshot: c.goal_snapshot };
            }
          }
          return {
            id: s.id,
            notes: s.daily_note,
            ...domainFields,
            domain_goal_quotes: Object.keys(goalQuotes).length > 0 ? goalQuotes : null,
          };
        }),
      });
      toast.success('活動を保存しました');
      router.push(`/staff/renrakucho?date=${dateParam}`);
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
        <Link href={`/staff/renrakucho?date=${dateParam}`}>
          <Button variant="ghost" size="sm">
            <MaterialIcon name="arrow_back" size={16} />
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
              leftIcon={<MaterialIcon name="person_add" size={16} />}
            >
              本日予定の生徒を追加
            </Button>
            <Button
              size="sm"
              onClick={() => setShowStudentPicker(true)}
              leftIcon={<MaterialIcon name="add" size={16} />}
            >
              生徒を追加
            </Button>
          </div>
        </CardHeader>
        <CardBody>
          {students.length === 0 ? (
            <div className="py-8 text-center text-sm text-[var(--neutral-foreground-4)]">
              <MaterialIcon name="person_add" size={40} className="mx-auto mb-2" />
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
                  onUpdateDailyNote={(v) => updateStudentDailyNote(student.id, v)}
                  onUpdateConcern={(idx, patch) => updateConcern(student.id, idx, patch)}
                  onAddConcern={() => addConcernRow(student.id)}
                  onRemoveConcern={(idx) => removeConcernRow(student.id, idx)}
                  onRemove={() => removeStudent(student.id)}
                />
              ))}
            </div>
          )}
        </CardBody>
      </Card>

      {/* Save button */}
      <div className="flex justify-end gap-3">
        <Link href={`/staff/renrakucho?date=${dateParam}`}>
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
  onUpdateDailyNote,
  onUpdateConcern,
  onAddConcern,
  onRemoveConcern,
  onRemove,
}: {
  student: StudentFormData;
  onUpdateDailyNote: (value: string) => void;
  onUpdateConcern: (idx: number, patch: Partial<ConcernEntry>) => void;
  onAddConcern: () => void;
  onRemoveConcern: (idx: number) => void;
  onRemove: () => void;
}) {
  const usedDomainsExcept = (idx: number): Set<string> =>
    new Set(student.concerns.map((c, i) => (i === idx ? '' : c.domain)).filter(Boolean));

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
          title="生徒をフォームから外す"
        >
          <MaterialIcon name="delete" size={16} />
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
          onChange={(e) => onUpdateDailyNote(e.target.value)}
          placeholder="本日の様子を記入..."
        />
      </div>

      {/* Concerns (1つ目..N つ目) */}
      {student.concerns.map((c, idx) => {
        const isRequired = idx < REQUIRED_CONCERNS;
        const bg = isRequired ? 'bg-[var(--status-info-bg)]' : 'bg-[var(--neutral-background-3)]';
        const used = usedDomainsExcept(idx);
        const availableDomains = DOMAINS.filter((d) => !used.has(d.value));
        return (
          <div key={idx} className={`mb-3 rounded-md ${bg} p-3`}>
            <div className="mb-1 flex items-center justify-between">
              <label className="block text-xs font-semibold text-[var(--neutral-foreground-1)]">
                気になったこと {idx + 1}つ目
                {isRequired && <span className="ml-1 text-[var(--status-danger-fg)]">*</span>}
                {!isRequired && <span className="ml-1 text-[var(--neutral-foreground-4)]">（任意）</span>}
              </label>
              {!isRequired && (
                <button
                  type="button"
                  onClick={() => onRemoveConcern(idx)}
                  className="text-[10px] text-[var(--neutral-foreground-4)] hover:text-[var(--status-danger-fg)]"
                >
                  <MaterialIcon name="close" size={14} className="inline" /> 削除
                </button>
              )}
            </div>
            <select
              className="mb-2 w-full rounded-md border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-1)] px-3 py-1.5 text-sm focus:border-[var(--brand-80)] focus:outline-none"
              value={c.domain}
              onChange={(e) => onUpdateConcern(idx, { domain: e.target.value })}
            >
              <option value="">領域を選択...</option>
              {availableDomains.map((d) => (
                <option key={d.value} value={d.value}>{d.label}</option>
              ))}
            </select>

            {/* 個別支援計画の目標表示 + 引用チェックボックス */}
            {c.domain && (
              <div className="mb-2 rounded border border-[var(--brand-80)]/30 bg-[var(--brand-160)]/40 p-2">
                <div className="mb-1 flex items-center gap-1 text-[11px] font-semibold text-[var(--brand-80)]">
                  <MaterialIcon name="flag" size={12} />
                  個別支援計画の目標
                  {DOMAIN_LABEL_MAP[c.domain] && (
                    <span className="ml-1 text-[var(--neutral-foreground-3)]">
                      （{DOMAIN_LABEL_MAP[c.domain]}）
                    </span>
                  )}
                </div>
                {c.goal_snapshot ? (
                  <>
                    <p className="mb-1.5 whitespace-pre-wrap text-xs text-[var(--neutral-foreground-1)]">
                      {c.goal_snapshot}
                    </p>
                    <label className="flex cursor-pointer items-center gap-1.5 text-[11px]">
                      <input
                        type="checkbox"
                        checked={c.quote_goal}
                        onChange={(e) => onUpdateConcern(idx, { quote_goal: e.target.checked })}
                        className="rounded border-[var(--neutral-stroke-2)]"
                      />
                      <span className="text-[var(--neutral-foreground-1)]">
                        引用する（統合時にこの目標を AI 文章に含める）
                      </span>
                    </label>
                  </>
                ) : (
                  <p className="text-[11px] text-[var(--neutral-foreground-4)]">
                    この領域の目標は個別支援計画に登録されていません。
                  </p>
                )}
              </div>
            )}

            <textarea
              className="w-full rounded-md border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-1)] px-3 py-2 text-sm focus:border-[var(--brand-80)] focus:outline-none"
              rows={2}
              value={c.content}
              onChange={(e) => onUpdateConcern(idx, { content: e.target.value })}
              placeholder="具体的な内容を記入..."
            />
          </div>
        );
      })}

      {/* 追加ボタン (上限 = 5 領域分) */}
      {student.concerns.length < DOMAINS.length && (
        <button
          type="button"
          onClick={onAddConcern}
          className="inline-flex items-center gap-1 rounded border border-dashed border-[var(--brand-80)]/50 px-3 py-1.5 text-xs font-medium text-[var(--brand-80)] hover:bg-[var(--brand-160)]/40"
        >
          <MaterialIcon name="add" size={14} />
          気になったことを追加
        </button>
      )}
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
            <MaterialIcon name="close" size={16} />
          </button>
        </div>

        {/* Search */}
        <div className="border-b border-[var(--neutral-stroke-3)] px-4 py-2">
          <div className="relative">
            <MaterialIcon name="search" size={16} className="absolute left-3 top-1/2 -translate-y-1/2 text-[var(--neutral-foreground-4)]" />
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
