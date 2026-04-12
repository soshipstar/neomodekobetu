'use client';

import { useState, useEffect } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import api from '@/lib/api';
import { Card, CardBody } from '@/components/ui/Card';
import { Button } from '@/components/ui/Button';
import { Badge } from '@/components/ui/Badge';
import { Modal } from '@/components/ui/Modal';
import { Skeleton } from '@/components/ui/Skeleton';
import { useToast } from '@/components/ui/Toast';
import { format } from 'date-fns';
import { MaterialIcon } from '@/components/ui/MaterialIcon';
import { PhotoPickerModal, type PhotoOption } from '@/components/photos/PhotoPickerModal';

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
  report_start_date: string | null;
  report_end_date: string | null;
  schedule_start_date: string | null;
  schedule_end_date: string | null;
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
  report_start_date: string;
  report_end_date: string;
  schedule_start_date: string;
  schedule_end_date: string;
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

/** 年月からデフォルト日付範囲を算出 */
function defaultDates(year: number, month: number) {
  const fmt = (d: Date) => `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
  // 報告期間: 当月1日〜末日
  const reportStart = new Date(year, month - 1, 1);
  const reportEnd = new Date(year, month, 0);
  // 予定期間: 報告期間の翌日から1ヶ月
  const scheduleStart = new Date(year, month, 1); // 翌月1日
  const scheduleEnd = new Date(year, month + 1, 0); // 翌月末日
  return {
    report_start_date: fmt(reportStart),
    report_end_date: fmt(reportEnd),
    schedule_start_date: fmt(scheduleStart),
    schedule_end_date: fmt(scheduleEnd),
  };
}

const emptyForm = (): NewsletterForm => {
  const now = new Date();
  const dates = defaultDates(now.getFullYear(), now.getMonth() + 1);
  return {
    year: now.getFullYear(), month: now.getMonth() + 1,
    title: `${now.getFullYear()}年${now.getMonth() + 1}月号`,
    greeting: '', event_calendar: '', event_details: '', weekly_reports: '',
    weekly_intro: '', event_results: '', requests: '', others: '',
    elementary_report: '', junior_report: '',
    ...dates,
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
  const [generatingAll, setGeneratingAll] = useState(false);
  const [photoPickerFor, setPhotoPickerFor] = useState<string | null>(null);

  // 設定からデフォルトテキストを読み込む
  const { data: settings } = useQuery({
    queryKey: ['staff', 'newsletter-settings'],
    queryFn: async () => {
      const res = await api.get('/api/staff/newsletter-settings');
      return res.data?.data ?? null;
    },
  });

  const { data: newsletters = [], isLoading } = useQuery({
    queryKey: ['staff', 'newsletters'],
    queryFn: async () => {
      const res = await api.get('/api/staff/newsletters');
      const p = res.data?.data;
      // paginated response: { data: [...], current_page, ... }
      const items = Array.isArray(p) ? p : (Array.isArray(p?.data) ? p.data : []);
      return items as Newsletter[];
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

  // 個別セクションAI生成
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

  // 全セクション一括AI生成（旧アプリの「AIで通信を生成」相当）
  const handleAiGenerateAll = async () => {
    if (!editingId) { toast.error('先に保存してからAI生成してください'); return; }
    if (!confirm('全セクションをAIで生成します。既存の内容は上書きされます。\n（1〜2分かかる場合があります）')) return;
    setGeneratingAll(true);
    try {
      const res = await api.post(`/api/staff/newsletters/${editingId}/generate-all`, {
        context: `${form.year}年${form.month}月号のお便り`,
      });
      const data = res.data?.data || {};
      setForm((prev) => ({
        ...prev,
        greeting: data.greeting ?? prev.greeting,
        event_calendar: data.event_calendar ?? prev.event_calendar,
        event_details: data.event_details ?? prev.event_details,
        weekly_reports: data.weekly_reports ?? prev.weekly_reports,
        weekly_intro: data.weekly_intro ?? prev.weekly_intro,
        event_results: data.event_results ?? prev.event_results,
        elementary_report: data.elementary_report ?? prev.elementary_report,
        junior_report: data.junior_report ?? prev.junior_report,
        requests: data.requests ?? prev.requests,
        others: data.others ?? prev.others,
      }));
      toast.success('全セクションのAI生成が完了しました');
    } catch {
      toast.error('一括AI生成に失敗しました');
    } finally {
      setGeneratingAll(false);
    }
  };

  const openCreate = () => {
    setEditingId(null);
    const f = emptyForm();
    // 設定からデフォルトテキストを適用
    if (settings) {
      if (settings.default_requests) f.requests = settings.default_requests;
      if (settings.default_others) f.others = settings.default_others;
    }
    setForm(f);
    setEditModal(true);
  };

  const openEdit = (n: Newsletter) => {
    setEditingId(n.id);
    const dates = defaultDates(n.year, n.month);
    setForm({
      year: n.year, month: n.month, title: n.title,
      greeting: nl(n.greeting), event_calendar: nl(n.event_calendar),
      event_details: nl(n.event_details), weekly_reports: nl(n.weekly_reports),
      weekly_intro: nl(n.weekly_intro), event_results: nl(n.event_results),
      requests: nl(n.requests), others: nl(n.others),
      elementary_report: nl(n.elementary_report), junior_report: nl(n.junior_report),
      report_start_date: n.report_start_date ?? dates.report_start_date,
      report_end_date: n.report_end_date ?? dates.report_end_date,
      schedule_start_date: n.schedule_start_date ?? dates.schedule_start_date,
      schedule_end_date: n.schedule_end_date ?? dates.schedule_end_date,
    });
    setEditModal(true);
  };

  // 年月が変わったら日付範囲も更新
  const updateYearMonth = (key: 'year' | 'month', value: number) => {
    setForm((prev) => {
      const next = { ...prev, [key]: value };
      const y = key === 'year' ? value : prev.year;
      const m = key === 'month' ? value : prev.month;
      const dates = defaultDates(y, m);
      return {
        ...next,
        title: `${y}年${m}月号`,
        ...dates,
      };
    });
  };

  const updateField = (key: string, value: string | number) => setForm((prev) => ({ ...prev, [key]: value }));
  const inputCls = 'block w-full rounded-lg border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-1)] px-3 py-2 text-sm text-[var(--neutral-foreground-1)]';

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-bold text-[var(--neutral-foreground-1)]">施設通信（お便り）</h1>
        <Button leftIcon={<MaterialIcon name="add" size={16} />} onClick={openCreate}>新規作成</Button>
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
                <Button variant="outline" size="sm" onClick={() => openEdit(n)}><MaterialIcon name="edit" size={14} /></Button>
                {!n.is_published && n.status !== 'published' && (
                  <>
                    <Button variant="outline" size="sm" onClick={() => { if (confirm('配信しますか？')) publishMutation.mutate(n.id); }}>
                      <MaterialIcon name="send" size={14} />
                    </Button>
                    <Button variant="ghost" size="sm" onClick={() => { if (confirm('削除しますか？')) deleteMutation.mutate(n.id); }}>
                      <MaterialIcon name="delete" size={14} className="text-[var(--status-danger-fg)]" />
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
          {/* Meta: 年・月・タイトル */}
          <div className="grid grid-cols-3 gap-3">
            <div>
              <label className="mb-1 block text-xs font-medium text-[var(--neutral-foreground-3)]">年</label>
              <input type="number" value={form.year} onChange={(e) => updateYearMonth('year', Number(e.target.value))} className={inputCls} />
            </div>
            <div>
              <label className="mb-1 block text-xs font-medium text-[var(--neutral-foreground-3)]">月</label>
              <input type="number" value={form.month} min={1} max={12} onChange={(e) => updateYearMonth('month', Number(e.target.value))} className={inputCls} />
            </div>
            <div>
              <label className="mb-1 block text-xs font-medium text-[var(--neutral-foreground-3)]">タイトル</label>
              <input value={form.title} onChange={(e) => updateField('title', e.target.value)} className={inputCls} />
            </div>
          </div>

          {/* 日付範囲 */}
          <div className="grid grid-cols-2 gap-3 rounded-lg border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-3)] p-3">
            <div>
              <label className="mb-1 block text-xs font-medium text-[var(--neutral-foreground-3)]">報告期間（活動記録の対象）</label>
              <div className="flex items-center gap-2">
                <input type="date" value={form.report_start_date} onChange={(e) => updateField('report_start_date', e.target.value)} className={inputCls + ' flex-1'} />
                <span className="text-xs text-[var(--neutral-foreground-4)]">〜</span>
                <input type="date" value={form.report_end_date} onChange={(e) => updateField('report_end_date', e.target.value)} className={inputCls + ' flex-1'} />
              </div>
            </div>
            <div>
              <label className="mb-1 block text-xs font-medium text-[var(--neutral-foreground-3)]">予定期間（イベント予定の対象）</label>
              <div className="flex items-center gap-2">
                <input type="date" value={form.schedule_start_date} onChange={(e) => updateField('schedule_start_date', e.target.value)} className={inputCls + ' flex-1'} />
                <span className="text-xs text-[var(--neutral-foreground-4)]">〜</span>
                <input type="date" value={form.schedule_end_date} onChange={(e) => updateField('schedule_end_date', e.target.value)} className={inputCls + ' flex-1'} />
              </div>
            </div>
          </div>

          {/* 一括AI生成ボタン */}
          {editingId && (
            <div className="rounded-lg border border-[var(--brand-80)]/30 bg-[var(--brand-10)] p-3">
              <div className="flex items-center justify-between">
                <div>
                  <p className="text-sm font-medium text-[var(--brand-100)]">
                    <MaterialIcon name="auto_awesome" size={16} className="inline mr-1" />
                    支援案ベースの一括AI生成
                  </p>
                  <p className="text-xs text-[var(--neutral-foreground-3)] mt-0.5">
                    支援案・活動記録・イベント情報をもとに全セクションを自動生成します（1〜2分）
                  </p>
                </div>
                <Button
                  onClick={handleAiGenerateAll}
                  isLoading={generatingAll}
                  disabled={generatingAll || !!generatingSection}
                  leftIcon={<MaterialIcon name="auto_awesome" size={16} />}
                >
                  AIで通信を生成
                </Button>
              </div>
            </div>
          )}

          {/* Sections */}
          {SECTIONS.map(({ key, label, ai }) => (
            <div key={key}>
              <div className="mb-1 flex items-center justify-between">
                <label className="text-sm font-medium text-[var(--neutral-foreground-2)]">{label}</label>
                <div className="flex gap-1">
                  <Button variant="ghost" size="sm"
                    leftIcon={<MaterialIcon name="photo_library" size={14} />}
                    onClick={() => setPhotoPickerFor(key)}>
                    写真を引用
                  </Button>
                  {editingId && ai && (
                    <Button variant="ghost" size="sm" leftIcon={<MaterialIcon name="auto_awesome" size={14} />}
                      onClick={() => handleAiGenerate(key)}
                      isLoading={generatingSection === key} disabled={!!generatingSection || generatingAll}>
                      AI生成
                    </Button>
                  )}
                </div>
              </div>
              <textarea
                value={form[key as keyof NewsletterForm] as string || ''}
                onChange={(e) => updateField(key, e.target.value)}
                className={inputCls} rows={4}
              />
            </div>
          ))}

          {/* 写真引用ピッカー (セクション指定) */}
          <PhotoPickerModal
            isOpen={photoPickerFor !== null}
            multiple={true}
            onClose={() => setPhotoPickerFor(null)}
            onConfirm={(photos: PhotoOption[]) => {
              if (!photoPickerFor) return;
              const current = (form[photoPickerFor as keyof NewsletterForm] as string) || '';
              const imageMarkdown = photos.map((p) => `![${p.activity_description ?? '写真'}](${p.url})`).join('\n');
              updateField(photoPickerFor, current + (current ? '\n\n' : '') + imageMarkdown);
              setPhotoPickerFor(null);
            }}
          />

          {/* Actions */}
          <div className="sticky bottom-0 flex items-center justify-between border-t border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-1)] pt-3">
            <div className="flex items-center gap-2">
              {editingId && (
                <>
                  <Button variant="outline" size="sm" leftIcon={<MaterialIcon name="send" size={16} />}
                    onClick={() => { if (confirm('配信しますか？')) publishMutation.mutate(editingId); }}>
                    配信する
                  </Button>
                  <Button variant="outline" size="sm" leftIcon={<MaterialIcon name="picture_as_pdf" size={16} />}
                    onClick={async () => {
                      try {
                        const res = await api.get(`/api/staff/newsletters/${editingId}/pdf`, { responseType: 'blob' });
                        const url = URL.createObjectURL(res.data);
                        const a = document.createElement('a'); a.href = url; a.download = `${form.title}.pdf`; a.click(); URL.revokeObjectURL(url);
                      } catch { toast.error('PDF生成に失敗しました'); }
                    }}>
                    PDF
                  </Button>
                  <Button variant="outline" size="sm" leftIcon={<MaterialIcon name="description" size={16} />}
                    onClick={async () => {
                      try {
                        const res = await api.get(`/api/staff/newsletters/${editingId}/word`, { responseType: 'blob' });
                        const url = URL.createObjectURL(res.data);
                        const a = document.createElement('a'); a.href = url; a.download = `${form.title}.doc`; a.click(); URL.revokeObjectURL(url);
                      } catch { toast.error('Word出力に失敗しました'); }
                    }}>
                    Word
                  </Button>
                </>
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
