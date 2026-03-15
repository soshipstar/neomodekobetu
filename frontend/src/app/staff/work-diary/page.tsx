'use client';

import { useState, useEffect, useCallback } from 'react';
import api from '@/lib/api';
import { Card, CardHeader, CardTitle, CardBody } from '@/components/ui/Card';
import { Button } from '@/components/ui/Button';
import { Modal } from '@/components/ui/Modal';
import { Input } from '@/components/ui/Input';
import { Skeleton } from '@/components/ui/Skeleton';
import { useToast } from '@/components/ui/Toast';
import { formatDate } from '@/lib/utils';
import { Plus, PenSquare, ChevronLeft, ChevronRight, Calendar } from 'lucide-react';

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
  creator?: { id: number; full_name: string };
  created_at: string;
}

const SECTIONS = [
  { key: 'previous_day_review', label: '前日の振り返り' },
  { key: 'daily_communication', label: '連絡事項' },
  { key: 'daily_roles', label: '本日の役割分担' },
  { key: 'prev_day_children_status', label: '前日の子どもの様子' },
  { key: 'children_special_notes', label: '子どもの特記事項' },
  { key: 'other_notes', label: 'その他' },
] as const;

type DiaryFormData = {
  diary_date: string;
  previous_day_review: string;
  daily_communication: string;
  daily_roles: string;
  prev_day_children_status: string;
  children_special_notes: string;
  other_notes: string;
};

const emptyForm: DiaryFormData = {
  diary_date: '',
  previous_day_review: '',
  daily_communication: '',
  daily_roles: '',
  prev_day_children_status: '',
  children_special_notes: '',
  other_notes: '',
};

export default function WorkDiaryPage() {
  const toast = useToast();
  const today = new Date();
  const [year, setYear] = useState(today.getFullYear());
  const [month, setMonth] = useState(today.getMonth() + 1);
  const [diaries, setDiaries] = useState<WorkDiary[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [showCreate, setShowCreate] = useState(false);
  const [editingDiary, setEditingDiary] = useState<WorkDiary | null>(null);
  const [form, setForm] = useState<DiaryFormData>(emptyForm);
  const [isSaving, setIsSaving] = useState(false);

  const fetchDiaries = useCallback(async () => {
    setIsLoading(true);
    try {
      const monthStr = `${year}-${String(month).padStart(2, '0')}`;
      const res = await api.get('/api/staff/work-diary', { params: { month: monthStr } });
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

  const goToPrevMonth = () => {
    if (month === 1) { setYear((y) => y - 1); setMonth(12); }
    else setMonth((m) => m - 1);
  };
  const goToNextMonth = () => {
    if (month === 12) { setYear((y) => y + 1); setMonth(1); }
    else setMonth((m) => m + 1);
  };
  const goToToday = () => {
    const now = new Date();
    setYear(now.getFullYear());
    setMonth(now.getMonth() + 1);
  };

  const openCreate = () => {
    const todayStr = `${today.getFullYear()}-${String(today.getMonth() + 1).padStart(2, '0')}-${String(today.getDate()).padStart(2, '0')}`;
    setForm({ ...emptyForm, diary_date: todayStr });
    setEditingDiary(null);
    setShowCreate(true);
  };

  const openEdit = (diary: WorkDiary) => {
    setForm({
      diary_date: diary.diary_date?.split('T')[0] || '',
      previous_day_review: diary.previous_day_review || '',
      daily_communication: diary.daily_communication || '',
      daily_roles: diary.daily_roles || '',
      prev_day_children_status: diary.prev_day_children_status || '',
      children_special_notes: diary.children_special_notes || '',
      other_notes: diary.other_notes || '',
    });
    setEditingDiary(diary);
    setShowCreate(true);
  };

  const handleSave = async () => {
    setIsSaving(true);
    try {
      if (editingDiary) {
        await api.put(`/api/staff/work-diary/${editingDiary.id}`, form);
        toast.success('業務日誌を更新しました');
      } else {
        await api.post('/api/staff/work-diary', form);
        toast.success('業務日誌を作成しました');
      }
      setShowCreate(false);
      setForm(emptyForm);
      setEditingDiary(null);
      fetchDiaries();
    } catch {
      toast.error('保存に失敗しました');
    } finally {
      setIsSaving(false);
    }
  };

  const updateField = (key: keyof DiaryFormData, value: string) => {
    setForm((prev) => ({ ...prev, [key]: value }));
  };

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-bold text-[var(--neutral-foreground-1)]">業務日誌</h1>
        <div className="flex items-center gap-2">
          <Button variant="ghost" size="sm" onClick={goToToday} leftIcon={<Calendar className="h-4 w-4" />}>今日</Button>
          <Button leftIcon={<Plus className="h-4 w-4" />} onClick={openCreate}>新規作成</Button>
        </div>
      </div>

      {/* Month navigation */}
      <div className="flex items-center gap-3">
        <button onClick={goToPrevMonth} className="rounded-lg p-1.5 hover:bg-[var(--neutral-background-3)] transition-colors">
          <ChevronLeft className="h-5 w-5 text-[var(--neutral-foreground-3)]" />
        </button>
        <span className="min-w-[120px] text-center text-lg font-semibold text-[var(--neutral-foreground-1)]">
          {year}年{month}月
        </span>
        <button onClick={goToNextMonth} className="rounded-lg p-1.5 hover:bg-[var(--neutral-background-3)] transition-colors">
          <ChevronRight className="h-5 w-5 text-[var(--neutral-foreground-3)]" />
        </button>
      </div>

      {/* Diary list */}
      {isLoading ? (
        <div className="space-y-3">
          {[...Array(4)].map((_, i) => <Skeleton key={i} className="h-32 w-full rounded-lg" />)}
        </div>
      ) : diaries.length > 0 ? (
        <div className="space-y-4">
          {diaries.map((diary) => (
            <Card key={diary.id}>
              <CardHeader>
                <div className="flex items-center gap-2">
                  <PenSquare className="h-4 w-4 text-[var(--neutral-foreground-4)]" />
                  <CardTitle>{formatDate(diary.diary_date)}</CardTitle>
                </div>
                <div className="flex items-center gap-2">
                  <span className="text-xs text-[var(--neutral-foreground-4)]">
                    {diary.creator?.full_name || '不明'}
                  </span>
                  <Button variant="ghost" size="sm" onClick={() => openEdit(diary)}>編集</Button>
                </div>
              </CardHeader>
              <CardBody>
                <div className="space-y-3">
                  {SECTIONS.map(({ key, label }) => {
                    const value = diary[key as keyof WorkDiary] as string | null;
                    if (!value) return null;
                    return (
                      <div key={key}>
                        <h4 className="text-xs font-semibold text-[var(--neutral-foreground-3)] uppercase tracking-wider mb-1">{label}</h4>
                        <p className="whitespace-pre-wrap text-sm text-[var(--neutral-foreground-2)]">{value}</p>
                      </div>
                    );
                  })}
                  {SECTIONS.every(({ key }) => !diary[key as keyof WorkDiary]) && (
                    <p className="text-sm text-[var(--neutral-foreground-4)]">内容がありません</p>
                  )}
                </div>
              </CardBody>
            </Card>
          ))}
        </div>
      ) : (
        <Card>
          <CardBody>
            <div className="py-8 text-center">
              <PenSquare className="mx-auto h-10 w-10 mb-2 text-[var(--neutral-foreground-4)]" />
              <p className="text-sm text-[var(--neutral-foreground-3)]">{year}年{month}月の業務日誌はありません</p>
              <Button className="mt-3" onClick={openCreate} leftIcon={<Plus className="h-4 w-4" />}>
                新規作成
              </Button>
            </div>
          </CardBody>
        </Card>
      )}

      {/* Create / Edit Modal */}
      <Modal isOpen={showCreate} onClose={() => setShowCreate(false)} title={editingDiary ? '業務日誌を編集' : '業務日誌を作成'} size="lg">
        <div className="space-y-4">
          <Input
            label="日付"
            type="date"
            value={form.diary_date}
            onChange={(e) => updateField('diary_date', e.target.value)}
            disabled={!!editingDiary}
          />
          {SECTIONS.map(({ key, label }) => (
            <div key={key}>
              <label className="mb-1 block text-sm font-medium text-[var(--neutral-foreground-2)]">{label}</label>
              <textarea
                value={form[key]}
                onChange={(e) => updateField(key, e.target.value)}
                className="block w-full rounded-lg border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-1)] px-3 py-2 text-sm text-[var(--neutral-foreground-1)] focus:border-[var(--brand-80)] focus:outline-none focus:ring-2 focus:ring-[var(--brand-80)]/20"
                rows={3}
                placeholder={`${label}を入力...`}
              />
            </div>
          ))}
          <div className="flex justify-end gap-2 pt-2">
            <Button variant="ghost" onClick={() => setShowCreate(false)}>キャンセル</Button>
            <Button onClick={handleSave} isLoading={isSaving} disabled={!form.diary_date}>
              {editingDiary ? '更新' : '作成'}
            </Button>
          </div>
        </div>
      </Modal>
    </div>
  );
}
