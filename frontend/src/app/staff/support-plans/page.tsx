'use client';

import { useState, useEffect, useCallback } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import api from '@/lib/api';
import { Card, CardHeader, CardTitle, CardBody } from '@/components/ui/Card';
import { Button } from '@/components/ui/Button';
import { Input } from '@/components/ui/Input';
import { Badge } from '@/components/ui/Badge';
import { Modal } from '@/components/ui/Modal';
import { SkeletonList } from '@/components/ui/Skeleton';
import { useToast } from '@/components/ui/Toast';
import Link from 'next/link';
import {
  Plus,
  Pencil,
  Trash2,
  Search,
  ArrowLeft,
  Calendar,
  Tag,
  Clock,
  User,
  Copy,
  Sparkles,
  ChevronUp,
  ChevronDown,
  X,
  Loader2,
  Download,
} from 'lucide-react';

// ---------------------------------------------------------------------------
// Types
// ---------------------------------------------------------------------------

interface SupportPlan {
  id: number;
  activity_name: string;
  activity_date: string;
  plan_type: 'normal' | 'event' | 'other';
  target_grade: string | null;
  activity_purpose: string | null;
  activity_content: string | null;
  tags: string | null;
  day_of_week: string | null;
  five_domains_consideration: string | null;
  other_notes: string | null;
  total_duration: number;
  activity_schedule: ScheduleItem[] | null;
  staff_name?: string;
  usage_count?: number;
  created_at: string;
}

interface ScheduleItem {
  type: 'routine' | 'main-activity';
  routineId?: number;
  name: string;
  content: string;
  duration: number;
}

interface DailyRoutine {
  id: number;
  routine_name: string;
  routine_content: string | null;
  scheduled_time: number | null;
}

// ---------------------------------------------------------------------------
// Constants
// ---------------------------------------------------------------------------

const PLAN_TYPES: Record<string, string> = {
  normal: '通常活動',
  event: 'イベント',
  other: 'その他',
};

const PLAN_TYPE_COLORS: Record<string, string> = {
  normal: 'var(--brand-80)',
  event: '#F97316',
  other: '#6B7280',
};

const TARGET_GRADES: Record<string, string> = {
  preschool: '小学生未満',
  elementary: '小学生',
  junior_high: '中学生',
  high_school: '高校生',
};

const TARGET_GRADE_COLORS: Record<string, string> = {
  preschool: '#8B5CF6',
  elementary: '#10B981',
  junior_high: '#3B82F6',
  high_school: '#F97316',
};

const DAYS_OF_WEEK: Record<string, string> = {
  monday: '月曜日',
  tuesday: '火曜日',
  wednesday: '水曜日',
  thursday: '木曜日',
  friday: '金曜日',
  saturday: '土曜日',
  sunday: '日曜日',
};

const DEFAULT_TAGS = ['動画', '食', '学習', 'イベント', 'その他'];

// ---------------------------------------------------------------------------
// Main Page
// ---------------------------------------------------------------------------

export default function SupportPlansPage() {
  const queryClient = useQueryClient();
  const toast = useToast();

  // Search filters
  const [searchTag, setSearchTag] = useState('');
  const [searchDayOfWeek, setSearchDayOfWeek] = useState('');
  const [searchKeyword, setSearchKeyword] = useState('');

  // Form modal
  const [formOpen, setFormOpen] = useState(false);
  const [editingPlan, setEditingPlan] = useState<SupportPlan | null>(null);

  // Tags from backend
  const { data: availableTags = DEFAULT_TAGS } = useQuery({
    queryKey: ['staff', 'tags'],
    queryFn: async () => {
      try {
        const res = await api.get<{ data: { name: string }[] }>('/api/staff/tag-settings');
        const tags = res.data.data.map((t) => t.name);
        return tags.length > 0 ? tags : DEFAULT_TAGS;
      } catch {
        return DEFAULT_TAGS;
      }
    },
  });

  // Fetch plans
  const { data: plans = [], isLoading } = useQuery({
    queryKey: ['staff', 'activity-support-plans', searchTag, searchDayOfWeek, searchKeyword],
    queryFn: async () => {
      const params: Record<string, string> = {};
      if (searchTag) params.tag = searchTag;
      if (searchDayOfWeek) params.day_of_week = searchDayOfWeek;
      if (searchKeyword) params.keyword = searchKeyword;
      const res = await api.get<{ data: SupportPlan[] }>('/api/staff/activity-support-plans', { params });
      return res.data.data;
    },
  });

  // Delete mutation
  const deleteMutation = useMutation({
    mutationFn: (id: number) => api.delete(`/api/staff/activity-support-plans/${id}`),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['staff', 'activity-support-plans'] });
      toast.success('支援案を削除しました');
    },
    onError: (err: any) => {
      toast.error(err.response?.data?.message || '削除に失敗しました');
    },
  });

  const handleDelete = (plan: SupportPlan) => {
    const msg = `この支援案を削除しますか？${plan.usage_count && plan.usage_count > 0 ? `\n\n注意: この支援案は${plan.usage_count}回使用されています。` : ''}`;
    if (confirm(msg)) {
      deleteMutation.mutate(plan.id);
    }
  };

  const handleEdit = (plan: SupportPlan) => {
    setEditingPlan(plan);
    setFormOpen(true);
  };

  const handleCreate = () => {
    setEditingPlan(null);
    setFormOpen(true);
  };

  // PDF download
  const handlePdfDownload = async (plan: SupportPlan) => {
    try {
      const res = await api.get(`/api/staff/activity-support-plans/${plan.id}/pdf`, { responseType: 'blob' });
      const url = window.URL.createObjectURL(new Blob([res.data]));
      const link = document.createElement('a');
      link.href = url;
      link.download = `activity_support_plan_${plan.id}.pdf`;
      link.click();
      window.URL.revokeObjectURL(url);
    } catch {
      toast.error('PDF生成に失敗しました');
    }
  };

  const clearSearch = () => {
    setSearchTag('');
    setSearchDayOfWeek('');
    setSearchKeyword('');
  };

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex flex-wrap items-center justify-between gap-3">
        <h1 className="text-2xl font-bold text-[var(--neutral-foreground-1)]">支援案一覧</h1>
        <div className="flex flex-wrap gap-2">
          <Button onClick={handleCreate} leftIcon={<Plus className="h-4 w-4" />}>
            新しい支援案を作成
          </Button>
          <Link href="/staff/daily-routines">
            <Button variant="outline" leftIcon={<Clock className="h-4 w-4" />}>
              毎日の支援を設定
            </Button>
          </Link>
          <Link href="/staff/tag-settings">
            <Button variant="outline" leftIcon={<Tag className="h-4 w-4" />}>
              タグを設定
            </Button>
          </Link>
        </div>
      </div>

      <Link href="/staff/renrakucho" className="inline-flex items-center gap-1 text-sm text-[var(--neutral-foreground-3)] hover:text-[var(--neutral-foreground-1)]">
        <ArrowLeft className="h-3.5 w-3.5" /> 活動管理へ
      </Link>

      {/* Search */}
      <Card>
        <CardBody>
          <div className="flex items-center gap-2 mb-4">
            <Search className="h-4 w-4 text-[var(--neutral-foreground-3)]" />
            <span className="text-sm font-semibold text-[var(--neutral-foreground-2)]">支援案を検索</span>
          </div>
          <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
            <div>
              <label className="block text-xs font-medium text-[var(--neutral-foreground-3)] mb-1">タグ</label>
              <select
                value={searchTag}
                onChange={(e) => setSearchTag(e.target.value)}
                className="w-full rounded-md border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-1)] px-3 py-2 text-sm"
              >
                <option value="">すべて</option>
                {availableTags.map((tag) => (
                  <option key={tag} value={tag}>{tag}</option>
                ))}
              </select>
            </div>
            <div>
              <label className="block text-xs font-medium text-[var(--neutral-foreground-3)] mb-1">曜日</label>
              <select
                value={searchDayOfWeek}
                onChange={(e) => setSearchDayOfWeek(e.target.value)}
                className="w-full rounded-md border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-1)] px-3 py-2 text-sm"
              >
                <option value="">すべて</option>
                {Object.entries(DAYS_OF_WEEK).map(([value, label]) => (
                  <option key={value} value={value}>{label}</option>
                ))}
              </select>
            </div>
            <div>
              <label className="block text-xs font-medium text-[var(--neutral-foreground-3)] mb-1">キーワード</label>
              <Input
                value={searchKeyword}
                onChange={(e) => setSearchKeyword(e.target.value)}
                placeholder="活動名、内容、目的で検索"
              />
            </div>
          </div>
          <div className="mt-3 flex gap-2">
            <Button variant="outline" size="sm" onClick={clearSearch}>クリア</Button>
          </div>
        </CardBody>
      </Card>

      {/* Plan List */}
      {isLoading ? (
        <SkeletonList items={3} />
      ) : plans.length === 0 ? (
        <Card>
          <CardBody>
            <div className="py-12 text-center text-[var(--neutral-foreground-4)]">
              <h2 className="text-lg font-semibold mb-2">支援案が登録されていません</h2>
              <p className="text-sm">「新しい支援案を作成」ボタンから支援案を作成してください。</p>
            </div>
          </CardBody>
        </Card>
      ) : (
        <div className="space-y-4">
          {plans.map((plan) => (
            <PlanCard key={plan.id} plan={plan} onEdit={handleEdit} onDelete={handleDelete} onPdfDownload={handlePdfDownload} />
          ))}
        </div>
      )}

      {/* Form Modal */}
      {formOpen && (
        <SupportPlanFormModal
          plan={editingPlan}
          availableTags={availableTags}
          onClose={() => {
            setFormOpen(false);
            setEditingPlan(null);
          }}
          onSaved={() => {
            queryClient.invalidateQueries({ queryKey: ['staff', 'activity-support-plans'] });
            setFormOpen(false);
            setEditingPlan(null);
          }}
        />
      )}
    </div>
  );
}

// ---------------------------------------------------------------------------
// Plan Card
// ---------------------------------------------------------------------------

function PlanCard({
  plan,
  onEdit,
  onDelete,
  onPdfDownload,
}: {
  plan: SupportPlan;
  onEdit: (p: SupportPlan) => void;
  onDelete: (p: SupportPlan) => void;
  onPdfDownload: (p: SupportPlan) => void;
}) {
  const dateStr = formatDateJP(plan.activity_date);
  const tags = plan.tags ? plan.tags.split(',') : [];

  return (
    <Card>
      <CardBody>
        <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
          <div className="flex-1 min-w-0">
            <div className="flex flex-wrap items-center gap-2 mb-1">
              <h3 className="text-lg font-semibold text-[var(--neutral-foreground-1)]">
                {plan.activity_name}
              </h3>
              <Badge
                variant="default"
                style={{ backgroundColor: PLAN_TYPE_COLORS[plan.plan_type], color: 'white' }}
              >
                {PLAN_TYPES[plan.plan_type]}
              </Badge>
              <span className="flex items-center gap-1 text-sm text-[var(--brand-80)]">
                <Calendar className="h-3.5 w-3.5" /> {dateStr}
              </span>
            </div>
            <div className="flex flex-wrap gap-3 text-xs text-[var(--neutral-foreground-3)]">
              <span className="flex items-center gap-1">
                <User className="h-3 w-3" /> 作成者: {plan.staff_name}
              </span>
              <span>作成日: {formatDateJP(plan.created_at)}</span>
              {plan.usage_count !== undefined && plan.usage_count > 0 && (
                <Badge variant="info">使用回数: {plan.usage_count}回</Badge>
              )}
            </div>
            {tags.length > 0 && (
              <div className="mt-2 flex flex-wrap gap-1">
                {tags.map((tag) => (
                  <Badge key={tag} variant="default" className="text-[10px]">{tag}</Badge>
                ))}
              </div>
            )}
          </div>
        </div>

        {plan.activity_purpose && (
          <Section title="活動の目的" content={plan.activity_purpose} />
        )}
        {plan.activity_content && (
          <Section title="活動の内容" content={plan.activity_content} />
        )}
        {plan.five_domains_consideration && (
          <Section title="五領域への配慮" content={plan.five_domains_consideration} />
        )}
        {plan.other_notes && (
          <Section title="その他" content={plan.other_notes} />
        )}

        <div className="mt-4 flex gap-2 border-t border-[var(--neutral-stroke-3)] pt-3">
          <Button size="sm" onClick={() => onEdit(plan)} leftIcon={<Pencil className="h-3.5 w-3.5" />}>
            編集
          </Button>
          <Button size="sm" variant="outline" onClick={() => onPdfDownload(plan)} leftIcon={<Download className="h-3.5 w-3.5" />}>
            PDF
          </Button>
          <Button size="sm" variant="danger" onClick={() => onDelete(plan)} leftIcon={<Trash2 className="h-3.5 w-3.5" />}>
            削除
          </Button>
        </div>
      </CardBody>
    </Card>
  );
}

function Section({ title, content }: { title: string; content: string }) {
  return (
    <div className="mt-3">
      <div className="text-xs font-semibold text-[var(--brand-80)] mb-0.5">{title}</div>
      <div className="text-sm text-[var(--neutral-foreground-2)] whitespace-pre-wrap line-clamp-4">
        {content}
      </div>
    </div>
  );
}

// ---------------------------------------------------------------------------
// Form Modal
// ---------------------------------------------------------------------------

interface FormData {
  activity_name: string;
  activity_date: string;
  plan_type: 'normal' | 'event' | 'other';
  target_grade: string[];
  activity_purpose: string;
  activity_content: string;
  tags: string[];
  day_of_week: string[];
  five_domains_consideration: string;
  other_notes: string;
  total_duration: number;
}

function SupportPlanFormModal({
  plan,
  availableTags,
  onClose,
  onSaved,
}: {
  plan: SupportPlan | null;
  availableTags: string[];
  onClose: () => void;
  onSaved: () => void;
}) {
  const toast = useToast();
  const isEdit = !!plan;

  const [form, setForm] = useState<FormData>(() => ({
    activity_name: plan?.activity_name ?? '',
    activity_date: plan?.activity_date?.split('T')[0] ?? '',
    plan_type: plan?.plan_type ?? 'normal',
    target_grade: plan?.target_grade ? plan.target_grade.split(',') : [],
    activity_purpose: plan?.activity_purpose ?? '',
    activity_content: plan?.activity_content ?? '',
    tags: plan?.tags ? plan.tags.split(',') : [],
    day_of_week: plan?.day_of_week ? plan.day_of_week.split(',') : [],
    five_domains_consideration: plan?.five_domains_consideration ?? '',
    other_notes: plan?.other_notes ?? '',
    total_duration: plan?.total_duration ?? 180,
  }));

  // Schedule items
  const [scheduleItems, setScheduleItems] = useState<ScheduleItem[]>(
    plan?.activity_schedule ?? []
  );
  const [newMainName, setNewMainName] = useState('');
  const [newMainDuration, setNewMainDuration] = useState('');
  const [newMainContent, setNewMainContent] = useState('');

  // AI loading states
  const [aiGeneratingFiveDomains, setAiGeneratingFiveDomains] = useState(false);
  const [aiGeneratingSchedule, setAiGeneratingSchedule] = useState(false);

  // Copy from past modal
  const [copyModalOpen, setCopyModalOpen] = useState(false);

  // Daily routines
  const { data: dailyRoutines = [] } = useQuery({
    queryKey: ['staff', 'daily-routines'],
    queryFn: async () => {
      try {
        const res = await api.get<{ data: DailyRoutine[] }>('/api/staff/daily-routines');
        return res.data.data;
      } catch {
        return [];
      }
    },
  });

  // Save
  const [saving, setSaving] = useState(false);

  const handleSave = async () => {
    if (!form.activity_name.trim()) {
      toast.error('活動名を入力してください');
      return;
    }
    if (!form.activity_date) {
      toast.error('活動予定日を選択してください');
      return;
    }

    setSaving(true);
    try {
      const payload = {
        activity_name: form.activity_name,
        activity_date: form.activity_date,
        plan_type: form.plan_type,
        target_grade: form.target_grade.length > 0 ? form.target_grade.join(',') : null,
        activity_purpose: form.activity_purpose || null,
        activity_content: form.activity_content || null,
        tags: form.tags.length > 0 ? form.tags.join(',') : null,
        day_of_week: form.day_of_week.length > 0 ? form.day_of_week.join(',') : null,
        five_domains_consideration: form.five_domains_consideration || null,
        other_notes: form.other_notes || null,
        total_duration: form.total_duration,
        activity_schedule: scheduleItems.length > 0 ? scheduleItems : null,
      };

      if (isEdit) {
        await api.put(`/api/staff/activity-support-plans/${plan!.id}`, payload);
        toast.success('支援案を更新しました');
      } else {
        await api.post('/api/staff/activity-support-plans', payload);
        toast.success('支援案を作成しました');
      }
      onSaved();
    } catch (err: any) {
      toast.error(err.response?.data?.message || '保存に失敗しました');
    } finally {
      setSaving(false);
    }
  };

  // Schedule helpers
  const usedTime = scheduleItems.reduce((s, item) => s + (Number(item.duration) || 0), 0);
  const remainingTime = form.total_duration - usedTime;

  const addRoutine = (routine: DailyRoutine) => {
    setScheduleItems((prev) => [
      ...prev,
      {
        type: 'routine',
        routineId: routine.id,
        name: routine.routine_name,
        content: routine.routine_content || '',
        duration: Number(routine.scheduled_time) || 15,
      },
    ]);
  };

  const addMainActivity = () => {
    if (!newMainName.trim()) {
      toast.error('主活動名を入力してください');
      return;
    }
    setScheduleItems((prev) => [
      ...prev,
      {
        type: 'main-activity',
        name: newMainName,
        content: newMainContent,
        duration: parseInt(newMainDuration) || 30,
      },
    ]);
    setNewMainName('');
    setNewMainDuration('');
    setNewMainContent('');
  };

  const removeScheduleItem = (index: number) => {
    setScheduleItems((prev) => prev.filter((_, i) => i !== index));
  };

  const moveScheduleItem = (index: number, direction: -1 | 1) => {
    const newIndex = index + direction;
    if (newIndex < 0 || newIndex >= scheduleItems.length) return;
    setScheduleItems((prev) => {
      const copy = [...prev];
      [copy[index], copy[newIndex]] = [copy[newIndex], copy[index]];
      return copy;
    });
  };

  const updateScheduleDuration = (index: number, value: number) => {
    setScheduleItems((prev) =>
      prev.map((item, i) => (i === index ? { ...item, duration: value } : item))
    );
  };

  const isRoutineAdded = (routineId: number) =>
    scheduleItems.some((item) => item.type === 'routine' && item.routineId === routineId);

  // AI: generate five domains
  const handleGenerateAiFiveDomains = async () => {
    if (!form.activity_name.trim()) {
      toast.error('活動名を入力してください');
      return;
    }
    setAiGeneratingFiveDomains(true);
    try {
      const res = await api.post<{ success: boolean; data: { five_domains_consideration?: string; other_notes?: string } }>(
        '/api/staff/activity-support-plans/generate-ai/five-domains',
        {
          activity_name: form.activity_name,
          activity_purpose: form.activity_purpose,
          activity_content: form.activity_content,
        }
      );
      if (res.data.success && res.data.data) {
        const fd = res.data.data.five_domains_consideration;
        const on = res.data.data.other_notes;
        if (fd) {
          const formatted = typeof fd === 'object' ? formatDomainObject(fd) : String(fd);
          if (!form.five_domains_consideration.trim() || confirm('既存の「五領域への配慮」を上書きしますか？')) {
            setForm((prev) => ({ ...prev, five_domains_consideration: formatted }));
          }
        }
        if (on) {
          const formatted = typeof on === 'object' ? extractStringFromObject(on) : String(on);
          if (!form.other_notes.trim() || confirm('既存の「その他」を上書きしますか？')) {
            setForm((prev) => ({ ...prev, other_notes: formatted }));
          }
        }
        toast.success('AIによる生成が完了しました');
      }
    } catch {
      toast.error('AI生成に失敗しました');
    } finally {
      setAiGeneratingFiveDomains(false);
    }
  };

  // AI: generate schedule content
  const handleGenerateAiScheduleContent = async () => {
    if (!form.activity_name.trim()) {
      toast.error('活動名を入力してください');
      return;
    }
    if (scheduleItems.length === 0) {
      toast.error('活動スケジュールに少なくとも1つの活動を追加してください');
      return;
    }
    setAiGeneratingSchedule(true);
    try {
      const res = await api.post<{ success: boolean; data: { activity_content?: string; other_notes?: string } }>(
        '/api/staff/activity-support-plans/generate-ai/schedule-content',
        {
          activity_name: form.activity_name,
          activity_purpose: form.activity_purpose,
          total_duration: form.total_duration,
          schedule: scheduleItems,
          target_grade: form.target_grade.join(','),
        }
      );
      if (res.data.success && res.data.data) {
        const ac = res.data.data.activity_content;
        const on = res.data.data.other_notes;
        if (ac) {
          const formatted = typeof ac === 'object' ? extractStringFromObject(ac) : String(ac);
          if (!form.activity_content.trim() || confirm('既存の「活動の内容」を上書きしますか？')) {
            setForm((prev) => ({ ...prev, activity_content: formatted }));
          }
        }
        if (on) {
          const formatted = typeof on === 'object' ? extractStringFromObject(on) : String(on);
          if (!form.other_notes.trim() || confirm('既存の「その他」を上書きしますか？')) {
            setForm((prev) => ({ ...prev, other_notes: formatted }));
          }
        }
        toast.success('AIによる活動内容の生成が完了しました');
      }
    } catch {
      toast.error('AI生成に失敗しました');
    } finally {
      setAiGeneratingSchedule(false);
    }
  };

  // Copy from past plan
  const handleCopyPlan = (pastPlan: SupportPlan) => {
    setForm((prev) => ({
      ...prev,
      activity_name: pastPlan.activity_name,
      activity_purpose: pastPlan.activity_purpose ?? '',
      activity_content: pastPlan.activity_content ?? '',
      five_domains_consideration: pastPlan.five_domains_consideration ?? '',
      other_notes: pastPlan.other_notes ?? '',
    }));
    if (pastPlan.activity_schedule) {
      setScheduleItems(pastPlan.activity_schedule);
    }
    setCopyModalOpen(false);
    toast.success('支援案の内容を引用しました。活動予定日を設定して保存してください。');
  };

  // Toggle helpers
  const toggleArrayItem = (field: 'target_grade' | 'tags' | 'day_of_week', value: string) => {
    setForm((prev) => {
      const arr = prev[field];
      return {
        ...prev,
        [field]: arr.includes(value) ? arr.filter((v) => v !== value) : [...arr, value],
      };
    });
  };

  return (
    <div className="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-black/50 p-4">
      <div className="w-full max-w-3xl bg-[var(--neutral-background-1)] rounded-xl shadow-xl my-8">
        {/* Header */}
        <div className="flex items-center justify-between border-b border-[var(--neutral-stroke-2)] px-6 py-4">
          <h2 className="text-lg font-bold text-[var(--neutral-foreground-1)]">
            {isEdit ? '支援案編集' : '支援案作成'}
          </h2>
          <button onClick={onClose} className="rounded-md p-1 hover:bg-[var(--neutral-background-3)]">
            <X className="h-5 w-5 text-[var(--neutral-foreground-3)]" />
          </button>
        </div>

        <div className="px-6 py-5 space-y-6 max-h-[calc(100vh-200px)] overflow-y-auto">
          {/* Info box */}
          <div className="rounded-lg border-l-4 border-[var(--brand-80)] bg-[var(--brand-160)] px-4 py-3 text-sm text-[var(--neutral-foreground-2)]">
            支援案は活動日専用の事前計画です。連絡帳作成時に、その日の支援案が自動的に利用可能になります。
          </div>

          {/* Copy from past button */}
          {!isEdit && (
            <div className="text-center">
              <Button variant="outline" onClick={() => setCopyModalOpen(true)} leftIcon={<Copy className="h-4 w-4" />}>
                過去の支援案を引用する
              </Button>
            </div>
          )}

          {/* Activity date */}
          <FieldGroup label="活動予定日" required>
            <Input
              type="date"
              value={form.activity_date}
              onChange={(e) => setForm((p) => ({ ...p, activity_date: e.target.value }))}
            />
            <HelpText>この支援案を使用する活動の予定日を選択してください</HelpText>
          </FieldGroup>

          {/* Activity name */}
          <FieldGroup label="活動名" required>
            <Input
              value={form.activity_name}
              onChange={(e) => setForm((p) => ({ ...p, activity_name: e.target.value }))}
              placeholder="例: 公園での自然観察、クッキング活動、グループワーク"
            />
          </FieldGroup>

          {/* Plan type */}
          <FieldGroup label="種別" required>
            <div className="flex flex-wrap gap-2">
              {Object.entries(PLAN_TYPES).map(([value, label]) => {
                const selected = form.plan_type === value;
                const color = PLAN_TYPE_COLORS[value];
                return (
                  <button
                    key={value}
                    type="button"
                    onClick={() => setForm((p) => ({ ...p, plan_type: value as FormData['plan_type'] }))}
                    className="rounded-lg border-2 px-4 py-2 text-sm font-semibold transition-colors"
                    style={{
                      borderColor: color,
                      backgroundColor: selected ? color : 'transparent',
                      color: selected ? 'white' : color,
                    }}
                  >
                    {label}
                  </button>
                );
              })}
            </div>
            <HelpText>通常活動: 日常の活動、イベント: 特別なイベント、その他: 上記以外</HelpText>
          </FieldGroup>

          {/* Target grade */}
          <FieldGroup label="対象年齢層">
            <div className="flex flex-wrap gap-2">
              {Object.entries(TARGET_GRADES).map(([value, label]) => {
                const selected = form.target_grade.includes(value);
                const color = TARGET_GRADE_COLORS[value];
                return (
                  <button
                    key={value}
                    type="button"
                    onClick={() => toggleArrayItem('target_grade', value)}
                    className="rounded-lg border-2 px-4 py-2 text-sm font-semibold transition-colors"
                    style={{
                      borderColor: color,
                      backgroundColor: selected ? color : 'transparent',
                      color: selected ? 'white' : color,
                    }}
                  >
                    {label}
                  </button>
                );
              })}
            </div>
            <HelpText>この活動の対象となる年齢層を選択してください（複数選択可、未選択の場合は全年齢対象）</HelpText>
          </FieldGroup>

          {/* Total duration */}
          <FieldGroup label="総活動時間" required>
            <div className="flex items-center gap-2">
              <Input
                type="number"
                value={form.total_duration}
                onChange={(e) => setForm((p) => ({ ...p, total_duration: parseInt(e.target.value) || 180 }))}
                min={30}
                max={480}
                className="w-24"
              />
              <span className="text-sm font-semibold">分</span>
              <span className={`ml-4 text-sm font-semibold ${remainingTime < 0 ? 'text-[var(--status-danger-fg)]' : 'text-[var(--status-success-fg)]'}`}>
                （残り時間: {remainingTime}分）
              </span>
            </div>
            <HelpText>活動全体の所要時間を入力してください（30〜480分）</HelpText>
          </FieldGroup>

          {/* Activity purpose */}
          <FieldGroup label="活動の目的">
            <textarea
              value={form.activity_purpose}
              onChange={(e) => setForm((p) => ({ ...p, activity_purpose: e.target.value }))}
              className="w-full min-h-[100px] rounded-md border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-1)] px-3 py-2 text-sm resize-y"
              placeholder="この活動を通して達成したい目標や狙いを記載してください"
            />
          </FieldGroup>

          {/* Activity schedule */}
          <div className="rounded-lg border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-3)] p-4 space-y-4">
            <div>
              <span className="text-sm font-semibold text-[var(--neutral-foreground-1)]">活動スケジュール</span>
              <p className="mt-1 text-xs text-[var(--neutral-foreground-3)]">
                毎日の支援と主活動を追加し、順番と所要時間を設定してください。
                <Link href="/staff/daily-routines" className="text-[var(--brand-80)] ml-1">毎日の支援を設定する</Link>
              </p>
            </div>

            {/* Routine selector */}
            <div>
              <label className="text-xs font-medium text-[var(--neutral-foreground-3)]">毎日の支援を追加</label>
              <div className="mt-1 flex flex-wrap gap-2">
                {dailyRoutines.length === 0 ? (
                  <span className="text-xs italic text-[var(--neutral-foreground-4)]">
                    毎日の支援が登録されていません。<Link href="/staff/daily-routines" className="text-[var(--brand-80)]">設定する</Link>
                  </span>
                ) : (
                  dailyRoutines.map((routine) => {
                    const added = isRoutineAdded(routine.id);
                    return (
                      <button
                        key={routine.id}
                        type="button"
                        onClick={() => !added && addRoutine(routine)}
                        disabled={added}
                        className={`rounded-md border-2 border-[#F97316] px-3 py-1.5 text-xs font-semibold transition-colors ${
                          added
                            ? 'bg-[#F97316] text-white opacity-50 cursor-not-allowed'
                            : 'bg-transparent text-[#F97316] hover:bg-[#F97316] hover:text-white'
                        }`}
                      >
                        {routine.routine_name}
                        {routine.scheduled_time ? ` (${routine.scheduled_time}分)` : ''}
                      </button>
                    );
                  })
                )}
              </div>
            </div>

            {/* Add main activity */}
            <div className="rounded-lg border border-[var(--brand-80)]/30 bg-[var(--brand-160)] p-3 space-y-2">
              <label className="text-xs font-semibold text-[var(--brand-80)]">主活動を追加</label>
              <div className="flex flex-wrap gap-2">
                <Input
                  value={newMainName}
                  onChange={(e) => setNewMainName(e.target.value)}
                  placeholder="主活動名を入力"
                  className="flex-1 min-w-[150px]"
                />
                <Input
                  type="number"
                  value={newMainDuration}
                  onChange={(e) => setNewMainDuration(e.target.value)}
                  placeholder="時間"
                  min={5}
                  max={240}
                  className="w-20"
                />
                <span className="self-center text-sm">分</span>
              </div>
              <textarea
                value={newMainContent}
                onChange={(e) => setNewMainContent(e.target.value)}
                placeholder="主活動の内容を入力（この内容がAI生成時に参照されます）"
                className="w-full min-h-[60px] rounded-md border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-1)] px-3 py-2 text-sm resize-y"
              />
              <Button size="sm" variant="outline" onClick={addMainActivity}>主活動を追加</Button>
            </div>

            {/* Schedule list */}
            <div className="rounded-md border border-dashed border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-1)] p-3 min-h-[60px]">
              {scheduleItems.length === 0 ? (
                <p className="text-center text-xs text-[var(--neutral-foreground-4)] py-4">活動を追加してください</p>
              ) : (
                <div className="space-y-2">
                  {scheduleItems.map((item, index) => (
                    <div
                      key={index}
                      className={`flex items-center gap-2 rounded-md border p-2 text-sm ${
                        item.type === 'routine'
                          ? 'border-l-4 border-l-[#F97316] border-[var(--neutral-stroke-2)]'
                          : 'border-l-4 border-l-[var(--brand-80)] border-[var(--neutral-stroke-2)]'
                      }`}
                    >
                      <span
                        className="flex h-6 w-6 shrink-0 items-center justify-center rounded-full text-xs font-bold text-white"
                        style={{
                          backgroundColor: item.type === 'routine' ? '#F97316' : 'var(--brand-80)',
                        }}
                      >
                        {index + 1}
                      </span>
                      <div className="flex-1 min-w-0">
                        <div className="font-medium truncate">{item.name}</div>
                        {item.content && (
                          <div className="text-xs text-[var(--neutral-foreground-3)] truncate">{item.content}</div>
                        )}
                      </div>
                      <span className="shrink-0 rounded bg-[var(--neutral-background-3)] px-2 py-0.5 text-[10px]">
                        {item.type === 'routine' ? '毎日の支援' : '主活動'}
                      </span>
                      <div className="flex items-center gap-1 shrink-0">
                        <Input
                          type="number"
                          value={item.duration}
                          onChange={(e) => updateScheduleDuration(index, parseInt(e.target.value) || 15)}
                          min={5}
                          max={240}
                          className="w-16 text-center text-xs"
                        />
                        <span className="text-xs">分</span>
                      </div>
                      <div className="flex gap-0.5 shrink-0">
                        <button
                          type="button"
                          onClick={() => moveScheduleItem(index, -1)}
                          disabled={index === 0}
                          className="rounded p-1 hover:bg-[var(--neutral-background-3)] disabled:opacity-30"
                        >
                          <ChevronUp className="h-3.5 w-3.5" />
                        </button>
                        <button
                          type="button"
                          onClick={() => moveScheduleItem(index, 1)}
                          disabled={index === scheduleItems.length - 1}
                          className="rounded p-1 hover:bg-[var(--neutral-background-3)] disabled:opacity-30"
                        >
                          <ChevronDown className="h-3.5 w-3.5" />
                        </button>
                        <button
                          type="button"
                          onClick={() => removeScheduleItem(index)}
                          className="rounded p-1 text-[var(--status-danger-fg)] hover:bg-[var(--status-danger-bg)]"
                        >
                          <X className="h-3.5 w-3.5" />
                        </button>
                      </div>
                    </div>
                  ))}
                </div>
              )}
            </div>
          </div>

          {/* AI: generate schedule content */}
          <div className="rounded-lg border-l-4 border-[var(--status-success-fg)] bg-[var(--status-success-bg)] p-4">
            <span className="text-sm font-semibold text-[var(--status-success-fg)]">AIで活動内容を生成</span>
            <p className="mt-1 text-xs text-[var(--neutral-foreground-3)]">
              活動名、目的、スケジュールを設定後、AIが時間配分を含めた詳細な活動内容を自動生成します。
            </p>
            <Button
              size="sm"
              className="mt-2"
              onClick={handleGenerateAiScheduleContent}
              disabled={aiGeneratingSchedule}
              leftIcon={aiGeneratingSchedule ? <Loader2 className="h-4 w-4 animate-spin" /> : <Sparkles className="h-4 w-4" />}
            >
              {aiGeneratingSchedule ? '生成中...' : 'スケジュールをもとに活動内容を生成'}
            </Button>
          </div>

          {/* Activity content */}
          <FieldGroup label="活動の内容">
            <textarea
              value={form.activity_content}
              onChange={(e) => setForm((p) => ({ ...p, activity_content: e.target.value }))}
              className="w-full min-h-[200px] rounded-md border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-1)] px-3 py-2 text-sm resize-y"
              placeholder="具体的な活動の流れや内容を記載してください（AIで自動生成可能）"
            />
          </FieldGroup>

          {/* AI: generate five domains */}
          <div className="rounded-lg border-l-4 border-[var(--brand-80)] bg-[var(--brand-160)] p-4">
            <span className="text-sm font-semibold text-[var(--brand-80)]">AIで詳細を生成</span>
            <p className="mt-1 text-xs text-[var(--neutral-foreground-3)]">
              活動名、目的、内容を入力後、AIが「五領域への配慮」と「その他」を自動生成します。
            </p>
            <Button
              size="sm"
              className="mt-2"
              onClick={handleGenerateAiFiveDomains}
              disabled={aiGeneratingFiveDomains}
              leftIcon={aiGeneratingFiveDomains ? <Loader2 className="h-4 w-4 animate-spin" /> : <Sparkles className="h-4 w-4" />}
            >
              {aiGeneratingFiveDomains ? '生成中...' : 'AIで五領域への配慮を生成'}
            </Button>
          </div>

          {/* Tags */}
          <FieldGroup label="タグ">
            <div className="grid grid-cols-2 gap-2 sm:grid-cols-3">
              {availableTags.map((tag) => (
                <label key={tag} className="flex items-center gap-2 cursor-pointer text-sm">
                  <input
                    type="checkbox"
                    checked={form.tags.includes(tag)}
                    onChange={() => toggleArrayItem('tags', tag)}
                    className="rounded border-[var(--neutral-stroke-2)]"
                  />
                  {tag}
                </label>
              ))}
            </div>
            <HelpText>活動に関連するタグを選択してください（複数選択可）</HelpText>
          </FieldGroup>

          {/* Day of week */}
          <FieldGroup label="実施曜日">
            <div className="flex flex-wrap gap-2">
              {Object.entries(DAYS_OF_WEEK).map(([value, label]) => (
                <label key={value} className="flex items-center gap-2 cursor-pointer text-sm">
                  <input
                    type="checkbox"
                    checked={form.day_of_week.includes(value)}
                    onChange={() => toggleArrayItem('day_of_week', value)}
                    className="rounded border-[var(--neutral-stroke-2)]"
                  />
                  {label}
                </label>
              ))}
            </div>
            <HelpText>この支援案を実施する曜日を選択してください（複数選択可）</HelpText>
          </FieldGroup>

          {/* Five domains consideration */}
          <FieldGroup label="五領域への配慮">
            <textarea
              value={form.five_domains_consideration}
              onChange={(e) => setForm((p) => ({ ...p, five_domains_consideration: e.target.value }))}
              className="w-full min-h-[200px] rounded-md border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-1)] px-3 py-2 text-sm resize-y"
              placeholder="健康・生活、運動・感覚、認知・行動、言語・コミュニケーション、人間関係・社会性の五領域への配慮を記載してください"
            />
          </FieldGroup>

          {/* Other notes */}
          <FieldGroup label="その他">
            <textarea
              value={form.other_notes}
              onChange={(e) => setForm((p) => ({ ...p, other_notes: e.target.value }))}
              className="w-full min-h-[100px] rounded-md border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-1)] px-3 py-2 text-sm resize-y"
              placeholder="特記事項や注意点などがあれば記載してください"
            />
          </FieldGroup>
        </div>

        {/* Footer buttons */}
        <div className="flex gap-3 border-t border-[var(--neutral-stroke-2)] px-6 py-4">
          <Button variant="outline" onClick={onClose} className="flex-1">キャンセル</Button>
          <Button onClick={handleSave} disabled={saving} className="flex-1" leftIcon={saving ? <Loader2 className="h-4 w-4 animate-spin" /> : undefined}>
            {saving ? '保存中...' : isEdit ? '更新する' : '作成する'}
          </Button>
        </div>
      </div>

      {/* Copy from past modal */}
      {copyModalOpen && (
        <CopyFromPastModal
          onClose={() => setCopyModalOpen(false)}
          onCopy={handleCopyPlan}
        />
      )}
    </div>
  );
}

// ---------------------------------------------------------------------------
// Copy From Past Modal
// ---------------------------------------------------------------------------

function CopyFromPastModal({
  onClose,
  onCopy,
}: {
  onClose: () => void;
  onCopy: (plan: SupportPlan) => void;
}) {
  const [period, setPeriod] = useState('30');
  const [startDate, setStartDate] = useState('');
  const [endDate, setEndDate] = useState('');
  const [searchTerm, setSearchTerm] = useState('');
  const [viewMode, setViewMode] = useState<'date' | 'list'>('date');

  const params: Record<string, string> = {};
  if (startDate && endDate) {
    params.start_date = startDate;
    params.end_date = endDate;
  } else {
    params.period = period;
  }

  const { data: pastPlans = [], isLoading } = useQuery({
    queryKey: ['staff', 'activity-support-plans', 'past', params],
    queryFn: async () => {
      const res = await api.get<{ data: SupportPlan[] }>('/api/staff/activity-support-plans/past', { params });
      return res.data.data;
    },
  });

  // Filter by search
  const filtered = searchTerm
    ? pastPlans.filter(
        (p) =>
          p.activity_name.toLowerCase().includes(searchTerm.toLowerCase()) ||
          (p.activity_purpose ?? '').toLowerCase().includes(searchTerm.toLowerCase()) ||
          (p.activity_content ?? '').toLowerCase().includes(searchTerm.toLowerCase())
      )
    : pastPlans;

  // Group by date
  const byDate = filtered.reduce<Record<string, SupportPlan[]>>((acc, plan) => {
    const date = plan.activity_date.split('T')[0];
    (acc[date] ??= []).push(plan);
    return acc;
  }, {});
  const sortedDates = Object.keys(byDate).sort((a, b) => b.localeCompare(a));

  const handleApplyDateRange = () => {
    if (!startDate || !endDate) {
      alert('開始日と終了日を両方入力してください');
      return;
    }
    if (startDate > endDate) {
      alert('開始日は終了日より前の日付を指定してください');
      return;
    }
    // triggers re-fetch via params change
  };

  const clearDateRange = () => {
    setStartDate('');
    setEndDate('');
    setPeriod('30');
  };

  return (
    <div className="fixed inset-0 z-[60] flex items-start justify-center overflow-y-auto bg-black/50 p-4" onClick={(e) => e.target === e.currentTarget && onClose()}>
      <div className="w-full max-w-3xl bg-[var(--neutral-background-1)] rounded-xl shadow-xl my-8 max-h-[90vh] flex flex-col">
        <div className="flex items-center justify-between border-b border-[var(--neutral-stroke-2)] px-6 py-4 shrink-0">
          <h2 className="text-lg font-bold">過去の支援案を選択</h2>
          <button onClick={onClose} className="rounded-md p-1 hover:bg-[var(--neutral-background-3)]">
            <X className="h-5 w-5" />
          </button>
        </div>

        <div className="px-6 py-4 space-y-4 overflow-y-auto flex-1">
          {/* Search */}
          <Input
            value={searchTerm}
            onChange={(e) => setSearchTerm(e.target.value)}
            placeholder="活動名で検索..."
          />

          {/* Period buttons */}
          <div>
            <span className="text-xs font-medium text-[var(--neutral-foreground-3)]">表示期間</span>
            <div className="mt-1 flex flex-wrap gap-2">
              {[
                { label: '1週間', value: '7' },
                { label: '1ヶ月', value: '30' },
                { label: '3ヶ月', value: '90' },
                { label: 'すべて', value: 'all' },
              ].map((opt) => (
                <button
                  key={opt.value}
                  onClick={() => {
                    setPeriod(opt.value);
                    setStartDate('');
                    setEndDate('');
                  }}
                  className={`rounded-md border-2 border-[var(--brand-80)] px-3 py-1 text-xs font-semibold transition-colors ${
                    period === opt.value && !startDate
                      ? 'bg-[var(--brand-80)] text-white'
                      : 'bg-transparent text-[var(--brand-80)]'
                  }`}
                >
                  {opt.label}
                </button>
              ))}
            </div>
          </div>

          {/* Date range */}
          <div className="rounded-md border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-3)] p-3">
            <span className="text-xs font-medium text-[var(--neutral-foreground-3)]">期間を指定</span>
            <div className="mt-1 flex flex-wrap items-center gap-2">
              <Input type="date" value={startDate} onChange={(e) => setStartDate(e.target.value)} className="w-40" />
              <span className="text-sm">〜</span>
              <Input type="date" value={endDate} onChange={(e) => setEndDate(e.target.value)} className="w-40" />
              <Button size="sm" onClick={handleApplyDateRange}>適用</Button>
              <Button size="sm" variant="outline" onClick={clearDateRange}>クリア</Button>
            </div>
          </div>

          {/* View mode tabs */}
          <div className="flex border-b border-[var(--neutral-stroke-2)]">
            <button
              onClick={() => setViewMode('date')}
              className={`px-4 py-2 text-sm font-semibold ${viewMode === 'date' ? 'border-b-2 border-[var(--brand-80)] text-[var(--brand-80)]' : 'text-[var(--neutral-foreground-3)]'}`}
            >
              日付順
            </button>
            <button
              onClick={() => setViewMode('list')}
              className={`px-4 py-2 text-sm font-semibold ${viewMode === 'list' ? 'border-b-2 border-[var(--brand-80)] text-[var(--brand-80)]' : 'text-[var(--neutral-foreground-3)]'}`}
            >
              一覧
            </button>
          </div>

          {/* Plans */}
          {isLoading ? (
            <SkeletonList items={3} />
          ) : filtered.length === 0 ? (
            <p className="text-center text-sm text-[var(--neutral-foreground-4)] py-6">該当する支援案がありません</p>
          ) : viewMode === 'date' ? (
            sortedDates.map((date) => (
              <div key={date} className="mb-4">
                <h3 className="text-sm font-bold text-[var(--brand-80)] border-b-2 border-[var(--brand-80)] pb-1 mb-3">
                  {formatDateJP(date)}
                </h3>
                {byDate[date].map((p) => (
                  <PastPlanCard key={p.id} plan={p} onCopy={onCopy} />
                ))}
              </div>
            ))
          ) : (
            <>
              <div className="text-sm text-[var(--neutral-foreground-3)] mb-2">全 {filtered.length} 件の支援案</div>
              {filtered.map((p) => (
                <PastPlanCard key={p.id} plan={p} onCopy={onCopy} showDate />
              ))}
            </>
          )}
        </div>
      </div>
    </div>
  );
}

function PastPlanCard({
  plan,
  onCopy,
  showDate = false,
}: {
  plan: SupportPlan;
  onCopy: (p: SupportPlan) => void;
  showDate?: boolean;
}) {
  return (
    <div className="rounded-lg border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-3)] p-3 mb-3">
      <div className="flex items-start justify-between gap-2">
        <div className="min-w-0">
          <div className="flex items-center gap-2">
            <span className="font-semibold text-sm">{plan.activity_name}</span>
            {showDate && (
              <span className="text-xs text-[var(--brand-80)]">{formatDateJP(plan.activity_date)}</span>
            )}
          </div>
          {plan.activity_purpose && (
            <p className="mt-1 text-xs text-[var(--neutral-foreground-3)]">
              <strong>目的:</strong> {plan.activity_purpose.substring(0, 100)}
              {plan.activity_purpose.length > 100 ? '...' : ''}
            </p>
          )}
          {plan.activity_content && (
            <p className="mt-0.5 text-xs text-[var(--neutral-foreground-3)]">
              <strong>内容:</strong> {plan.activity_content.substring(0, 100)}
              {plan.activity_content.length > 100 ? '...' : ''}
            </p>
          )}
        </div>
        <Button size="sm" onClick={() => onCopy(plan)}>この支援案を引用</Button>
      </div>
    </div>
  );
}

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function FieldGroup({ label, required, children }: { label: string; required?: boolean; children: React.ReactNode }) {
  return (
    <div>
      <label className="block text-sm font-semibold text-[var(--neutral-foreground-1)] mb-1.5">
        {label}
        {required && <span className="text-[var(--status-danger-fg)] ml-1">*</span>}
      </label>
      {children}
    </div>
  );
}

function HelpText({ children }: { children: React.ReactNode }) {
  return <p className="mt-1 text-xs text-[var(--neutral-foreground-4)]">{children}</p>;
}

function formatDateJP(dateStr: string): string {
  if (!dateStr) return '';
  const d = dateStr.split('T')[0];
  const parts = d.split('-');
  if (parts.length !== 3) return dateStr;
  return `${parseInt(parts[0])}年${parseInt(parts[1])}月${parseInt(parts[2])}日`;
}

function formatDomainObject(obj: unknown): string {
  if (typeof obj === 'string') return obj;
  if (typeof obj !== 'object' || obj === null) return String(obj ?? '');
  const domainKeys = ['健康・生活', '運動・感覚', '認知・行動', '言語・コミュニケーション', '人間関係・社会性'];
  const entries = Object.entries(obj as Record<string, unknown>);
  const hasDomainKeys = entries.some(([k]) => domainKeys.includes(k));
  if (hasDomainKeys || entries.length > 0) {
    return entries
      .filter(([, v]) => typeof v === 'string' && v.trim())
      .map(([k, v]) => `【${k}】\n${v}`)
      .join('\n\n');
  }
  return '';
}

function extractStringFromObject(obj: unknown): string {
  if (typeof obj === 'string') return obj;
  if (typeof obj !== 'object' || obj === null) return String(obj ?? '');
  for (const val of Object.values(obj as Record<string, unknown>)) {
    if (typeof val === 'string') return val;
  }
  return '';
}
