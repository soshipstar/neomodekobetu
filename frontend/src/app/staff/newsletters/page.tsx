'use client';

import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import api from '@/lib/api';
import { Card, CardBody } from '@/components/ui/Card';
import { Button } from '@/components/ui/Button';
import { Badge } from '@/components/ui/Badge';
import { Modal } from '@/components/ui/Modal';
import { Skeleton } from '@/components/ui/Skeleton';
import { useToast } from '@/components/ui/Toast';
import { Plus, Pencil, Trash2, Send, Sparkles } from 'lucide-react';
import { format } from 'date-fns';

// ---------------------------------------------------------------------------
// Types
// ---------------------------------------------------------------------------

interface Newsletter {
  id: number;
  year: number;
  month: number;
  title: string;
  greeting: string | null;
  event_calendar: string | null;
  event_details: string | null;
  weekly_reports: string | null;
  weekly_intro: string | null;
  event_results: string | null;
  requests: string | null;
  others: string | null;
  elementary_report: string | null;
  junior_report: string | null;
  status: string;
  is_published: boolean;
  published_at: string | null;
  created_at: string;
}

interface NewsletterForm {
  year: number;
  month: number;
  title: string;
  greeting: string;
  event_calendar: string;
  event_details: string;
  weekly_reports: string;
  weekly_intro: string;
  event_results: string;
  requests: string;
  others: string;
  elementary_report: string;
  junior_report: string;
}

const SECTIONS = [
  { key: 'greeting', label: 'あいさつ文', ai: true },
  { key: 'event_calendar', label: '行事予定カレンダー', ai: false },
  { key: 'event_details', label: '行事の詳細', ai: true },
  { key: 'weekly_reports', label: '活動の様子', ai: true },
  { key: 'weekly_intro', label: '週間活動紹介', ai: true },
  { key: 'event_results', label: '行事の結果報告', ai: true },
  { key: 'elementary_report', label: '小学生の活動報告', ai: true },
  { key: 'junior_report', label: '中学生の活動報告', ai: true },
  { key: 'requests', label: 'お願い事項', ai: false },
  { key: 'others', label: 'その他', ai: true },
] as const;

function nl(text: string | null | undefined): string {
  if (!text) return '';
  return text.replace(/\\r\\n|\\n|\\r/g, '\n').replace(/\r\n|\r/g, '\n');
}

const emptyForm = (): NewsletterForm => {
  const now = new Date();
  return {
    year: now.getFullYear(), month: now.getMonth() + 1,
    title: `${now.getFullYear()}年${now.getMonth() + 1}月号`,
    greeting: '', event_calendar: '', event_details: '', weekly_reports: '',
    weekly_intro: '', event_results: '', requests: '', others: '',
    elementary_report: '', junior_report: '',
  };
};

// ---------------------------------------------------------------------------
// Main
// ---------------------------------------------------------------------------

export default function NewslettersPage() {
  const queryClient = useQueryClient();
  const toast = useToast();
  const [editModal, setEditModal] = useState(false);
  const [editingId, setEditingId] = useState<number | null>(null);
  const [form, setForm] = useState<NewsletterForm>(emptyForm());
  const [generatingSection, setGeneratingSection] = useState<string | null>(null);

  const { data: newsletters = [], isLoading } = useQuery({
    queryKey: ['staff', 'newsletters'],
    queryFn: async () => {
      const res = await api.get('/api/staff/newsletters');
      const p = res.data?.data;
      return Array.isArray(p) ? p as Newsletter[] : [];
    },
  });

  const saveMutation = useMutation({
    mutationFn: async (data: NewsletterForm) => {
      if (editingId) return api.put(`/api/staff/newsletters/${editingId}`, data);
      return api.post('/api/staff/newsletters', data);
    },
    onSuccess: (res) => {
      queryClient.invalidateQueries({ queryKey: ['staff', 'newsletters'] });
      const newId = res.data?.data?.id;
      if (!editingId && newId) setEditingId(newId);
      toast.success(editingId ? '保存しました' : '作成しました');
    },
    onError: (e: any) => toast.error(e.response?.data?.message || '保存に失敗しました'),
  });

  const publishMutation = useMutation({
    mutationFn: (id: number) => api.post(`/api/staff/newsletters/${id}/publish`),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['staff', 'newsletters'] });
      toast.success('配信しました');
      setEditModal(false);
    },
    onError: () => toast.error('配信に失敗しました'),
  });

  const deleteMutation = useMutation({
    mutationFn: (id: number) => api.delete(`/api/staff/newsletters/${id}`),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['staff', 'newsletters'] });
      toast.success('削除しました');
    },
    onError: () => toast.error('削除に失敗しました'),
  });

  const handleAiGenerate = async (section: string) => {
    if (!editingId) { toast.error('先に保存してからAI生成してください'); return; }
    setGeneratingSection(section);
    try {
      const res = await api.post(`/api/staff/newsletters/${editingId}/generate-ai`, {
        section,
        context: `${form.year}年${form.month}月号のお便り`,
      });
      const content = res.data?.data?.content || res.data?.data?.[section] || '';
      setForm((prev) => ({ ...prev, [section]: content }));
      toast.success(`${SECTIONS.find((s) => s.key === section)?.label}を生成しました`);
    } catch {
      toast.error('AI生成に失敗しました');
    } finally {
      setGeneratingSection(null);
    }
  };

  const openCreate = () => { setEditingId(null); setForm(emptyForm()); setEditModal(true); };
  const openEdit = (n: Newsletter) => {
    setEditingId(n.id);
    setForm({
      year: n.year, month: n.month, title: n.title,
      greeting: nl(n.greeting), event_calendar: nl(n.event_calendar),
      event_details: nl(n.event_details), weekly_reports: nl(n.weekly_reports),
      weekly_intro: nl(n.weekly_intro), event_results: nl(n.event_results),
      requests: nl(n.requests), others: nl(n.others),
      elementary_report: nl(n.elementary_report), junior_report: nl(n.junior_report),
    });
    setEditModal(true);
  };

  const updateField = (key: string, value: string | number) => setForm((prev) => ({ ...prev, [key]: value }));
  const inputCls = 'block w-full rounded-lg border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-1)] px-3 py-2 text-sm text-[var(--neutral-foreground-1)]';

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-bold text-[var(--neutral-foreground-1)]">施設通信（お便り）</h1>
        <Button leftIcon={<Plus className="h-4 w-4" />} onClick={openCreate}>新規作成</Button>
      </div>

      {/* List */}
      {isLoading ? (
        <div className="space-y-2">{[...Array(4)].map((_, i) => <Skeleton key={i} className="h-16 rounded-lg" />)}</div>
      ) : newsletters.length === 0 ? (
        <Card><CardBody><p className="py-8 text-center text-sm text-[var(--neutral-foreground-4)]">お便りがありません</p></CardBody></Card>
      ) : (
        <div className="space-y-2">
          {newsletters.map((n) => (
            <div key={n.id} className="flex items-center justify-between rounded-lg border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-1)] px-4 py-3">
              <div>
                <p className="font-medium text-[var(--neutral-foreground-1)]">{n.title}</p>
                <p className="text-xs text-[var(--neutral-foreground-3)]">
                  {n.year}年{n.month}月号 ・ {format(new Date(n.created_at), 'yyyy/MM/dd')}
                  {n.published_at && ` ・ 配信: ${format(new Date(n.published_at), 'yyyy/MM/dd')}`}
                </p>
              </div>
              <div className="flex items-center gap-2">
                <Badge variant={n.status === 'published' || n.is_published ? 'success' : 'warning'}>
                  {n.status === 'published' || n.is_published ? '配信済み' : '下書き'}
                </Badge>
                <Button variant="outline" size="sm" onClick={() => openEdit(n)}><Pencil className="h-3.5 w-3.5" /></Button>
                {!n.is_published && n.status !== 'published' && (
                  <>
                    <Button variant="outline" size="sm" onClick={() => { if (confirm('配信しますか？')) publishMutation.mutate(n.id); }}>
                      <Send className="h-3.5 w-3.5" />
                    </Button>
                    <Button variant="ghost" size="sm" onClick={() => { if (confirm('削除しますか？')) deleteMutation.mutate(n.id); }}>
                      <Trash2 className="h-3.5 w-3.5 text-[var(--status-danger-fg)]" />
                    </Button>
                  </>
                )}
              </div>
            </div>
          ))}
        </div>
      )}

      {/* Edit/Create Modal */}
      <Modal isOpen={editModal} onClose={() => setEditModal(false)} title={editingId ? 'お便り編集' : 'お便り新規作成'} size="full">
        <div className="space-y-4 max-h-[80vh] overflow-y-auto pr-2">
          {/* Meta */}
          <div className="grid grid-cols-3 gap-3">
            <div>
              <label className="mb-1 block text-xs font-medium text-[var(--neutral-foreground-3)]">年</label>
              <input type="number" value={form.year} onChange={(e) => updateField('year', Number(e.target.value))} className={inputCls} />
            </div>
            <div>
              <label className="mb-1 block text-xs font-medium text-[var(--neutral-foreground-3)]">月</label>
              <input type="number" value={form.month} min={1} max={12} onChange={(e) => updateField('month', Number(e.target.value))} className={inputCls} />
            </div>
            <div>
              <label className="mb-1 block text-xs font-medium text-[var(--neutral-foreground-3)]">タイトル</label>
              <input value={form.title} onChange={(e) => updateField('title', e.target.value)} className={inputCls} />
            </div>
          </div>

          {/* Sections */}
          {SECTIONS.map(({ key, label, ai }) => (
            <div key={key}>
              <div className="mb-1 flex items-center justify-between">
                <label className="text-sm font-medium text-[var(--neutral-foreground-2)]">{label}</label>
                {editingId && ai && (
                  <Button variant="ghost" size="sm" leftIcon={<Sparkles className="h-3.5 w-3.5" />}
                    onClick={() => handleAiGenerate(key)}
                    isLoading={generatingSection === key} disabled={!!generatingSection}>
                    AI生成
                  </Button>
                )}
              </div>
              <textarea
                value={form[key as keyof NewsletterForm] as string || ''}
                onChange={(e) => updateField(key, e.target.value)}
                className={inputCls} rows={4}
              />
            </div>
          ))}

          {/* Actions */}
          <div className="sticky bottom-0 flex items-center justify-between border-t border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-1)] pt-3">
            <div>
              {editingId && (
                <Button variant="outline" size="sm" leftIcon={<Send className="h-4 w-4" />}
                  onClick={() => { if (confirm('配信しますか？')) publishMutation.mutate(editingId); }}>
                  配信する
                </Button>
              )}
            </div>
            <div className="flex gap-2">
              <Button variant="secondary" onClick={() => setEditModal(false)}>キャンセル</Button>
              <Button onClick={() => saveMutation.mutate(form)} isLoading={saveMutation.isPending}>
                {editingId ? '下書き保存' : '作成'}
              </Button>
            </div>
          </div>
        </div>
      </Modal>
    </div>
  );
}
