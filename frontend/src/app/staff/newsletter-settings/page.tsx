'use client';

import { useState, useEffect } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import api from '@/lib/api';
import { Card, CardHeader, CardTitle, CardBody } from '@/components/ui/Card';
import { Button } from '@/components/ui/Button';
import { Input } from '@/components/ui/Input';
import { SkeletonList } from '@/components/ui/Skeleton';
import { useToast } from '@/components/ui/Toast';
import { MaterialIcon } from '@/components/ui/MaterialIcon';

// ---------------------------------------------------------------------------
// Types
// ---------------------------------------------------------------------------

interface NewsletterSettings {
  id: number;
  classroom_id: number;
  show_facility_name: boolean;
  show_logo: boolean;
  show_greeting: boolean;
  show_event_calendar: boolean;
  calendar_format: 'list' | 'table';
  show_event_details: boolean;
  show_weekly_reports: boolean;
  show_weekly_intro: boolean;
  show_event_results: boolean;
  show_requests: boolean;
  show_others: boolean;
  show_elementary_report: boolean;
  show_junior_report: boolean;
  show_custom_section: boolean;
  default_requests: string;
  default_others: string;
  greeting_instructions: string;
  event_details_instructions: string;
  weekly_reports_instructions: string;
  weekly_intro_instructions: string;
  event_results_instructions: string;
  elementary_report_instructions: string;
  junior_report_instructions: string;
  custom_section_title: string;
  custom_section_content: string;
}

type SettingsForm = Omit<NewsletterSettings, 'id' | 'classroom_id'>;

const TOGGLE_SECTIONS: { key: keyof SettingsForm; label: string; description: string }[] = [
  { key: 'show_facility_name', label: '施設名を表示', description: 'おたよりに施設名を表示します' },
  { key: 'show_logo', label: 'ロゴを表示', description: '施設のロゴ画像を表示します' },
  { key: 'show_greeting', label: 'あいさつ文', description: '月初のあいさつ文を表示します' },
  { key: 'show_event_calendar', label: 'イベントカレンダー', description: '月間イベントカレンダーを表示します' },
  { key: 'show_event_details', label: 'イベント詳細', description: 'イベントの詳細説明を表示します' },
  { key: 'show_weekly_reports', label: '週間レポート', description: '週ごとの活動レポートを表示します' },
  { key: 'show_weekly_intro', label: '週間紹介文', description: '週間レポートの紹介文を表示します' },
  { key: 'show_event_results', label: 'イベント実施報告', description: '実施したイベントの報告を表示します' },
  { key: 'show_requests', label: 'お願い事項', description: '保護者へのお願い事項を表示します' },
  { key: 'show_others', label: 'その他', description: 'その他の情報を表示します' },
  { key: 'show_elementary_report', label: '小学生レポート', description: '小学生向けの週間レポートを表示します' },
  { key: 'show_junior_report', label: '中学生レポート', description: '中学生向けの週間レポートを表示します' },
  { key: 'show_custom_section', label: 'カスタムセクション', description: '独自のセクションを追加します' },
];

const AI_INSTRUCTION_FIELDS: { key: keyof SettingsForm; label: string }[] = [
  { key: 'greeting_instructions', label: 'あいさつ文のAI指示' },
  { key: 'event_details_instructions', label: 'イベント詳細のAI指示' },
  { key: 'weekly_reports_instructions', label: '週間レポートのAI指示' },
  { key: 'weekly_intro_instructions', label: '週間紹介文のAI指示' },
  { key: 'event_results_instructions', label: 'イベント実施報告のAI指示' },
  { key: 'elementary_report_instructions', label: '小学生レポートのAI指示' },
  { key: 'junior_report_instructions', label: '中学生レポートのAI指示' },
];

const defaultSettings: SettingsForm = {
  show_facility_name: true,
  show_logo: true,
  show_greeting: true,
  show_event_calendar: true,
  calendar_format: 'list',
  show_event_details: true,
  show_weekly_reports: true,
  show_weekly_intro: false,
  show_event_results: true,
  show_requests: true,
  show_others: true,
  show_elementary_report: false,
  show_junior_report: false,
  show_custom_section: false,
  default_requests: '',
  default_others: '',
  greeting_instructions: '',
  event_details_instructions: '',
  weekly_reports_instructions: '',
  weekly_intro_instructions: '',
  event_results_instructions: '',
  elementary_report_instructions: '',
  junior_report_instructions: '',
  custom_section_title: '',
  custom_section_content: '',
};

export default function NewsletterSettingsPage() {
  const queryClient = useQueryClient();
  const toast = useToast();
  const [form, setForm] = useState<SettingsForm>(defaultSettings);

  // Fetch settings
  const { data: settings, isLoading } = useQuery({
    queryKey: ['staff', 'newsletter-settings'],
    queryFn: async () => {
      const res = await api.get<{ data: NewsletterSettings }>('/api/staff/newsletter-settings');
      return res.data.data;
    },
  });

  // Populate form when settings load
  useEffect(() => {
    if (settings) {
      const { id, classroom_id, ...rest } = settings;
      setForm(rest);
    }
  }, [settings]);

  // Save mutation
  const saveMutation = useMutation({
    mutationFn: (data: SettingsForm) => api.put('/api/staff/newsletter-settings', data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['staff', 'newsletter-settings'] });
      toast.success('設定を保存しました');
    },
    onError: () => toast.error('保存に失敗しました'),
  });

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    saveMutation.mutate(form);
  };

  const toggleField = (key: keyof SettingsForm) => {
    setForm((prev) => ({ ...prev, [key]: !prev[key] }));
  };

  const updateField = (key: keyof SettingsForm, value: string) => {
    setForm((prev) => ({ ...prev, [key]: value }));
  };

  if (isLoading) {
    return (
      <div className="space-y-6">
        <h1 className="text-2xl font-bold text-[var(--neutral-foreground-1)]">施設通信設定</h1>
        <SkeletonList items={5} />
      </div>
    );
  }

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-bold text-[var(--neutral-foreground-1)]">施設通信設定</h1>
        <Button leftIcon={<MaterialIcon name="save" size={16} />} onClick={handleSubmit} isLoading={saveMutation.isPending}>
          保存
        </Button>
      </div>

      <form onSubmit={handleSubmit} className="space-y-6">
        {/* Display toggles */}
        <Card>
          <CardHeader>
            <div className="flex items-center gap-2">
              <MaterialIcon name="settings" size={20} className="text-[var(--brand-80)]" />
              <CardTitle>表示セクション設定</CardTitle>
            </div>
          </CardHeader>
          <CardBody>
            <div className="space-y-3">
              {TOGGLE_SECTIONS.map(({ key, label, description }) => (
                <label
                  key={key}
                  className="flex items-center justify-between rounded-lg border border-[var(--neutral-stroke-2)] p-3 cursor-pointer hover:bg-[var(--neutral-background-2)] transition-colors"
                >
                  <div>
                    <p className="text-sm font-medium text-[var(--neutral-foreground-1)]">{label}</p>
                    <p className="text-xs text-[var(--neutral-foreground-3)]">{description}</p>
                  </div>
                  <input
                    type="checkbox"
                    checked={!!form[key]}
                    onChange={() => toggleField(key)}
                    className="h-5 w-5 rounded border-[var(--neutral-stroke-2)]"
                  />
                </label>
              ))}
            </div>
          </CardBody>
        </Card>

        {/* Calendar format */}
        <Card>
          <CardHeader>
            <CardTitle>カレンダー形式</CardTitle>
          </CardHeader>
          <CardBody>
            <div className="flex gap-4">
              <label className="flex items-center gap-2">
                <input
                  type="radio"
                  name="calendar_format"
                  value="list"
                  checked={form.calendar_format === 'list'}
                  onChange={() => setForm({ ...form, calendar_format: 'list' })}
                />
                <span className="text-sm text-[var(--neutral-foreground-1)]">リスト形式</span>
              </label>
              <label className="flex items-center gap-2">
                <input
                  type="radio"
                  name="calendar_format"
                  value="table"
                  checked={form.calendar_format === 'table'}
                  onChange={() => setForm({ ...form, calendar_format: 'table' })}
                />
                <span className="text-sm text-[var(--neutral-foreground-1)]">テーブル形式</span>
              </label>
            </div>
          </CardBody>
        </Card>

        {/* Default texts */}
        <Card>
          <CardHeader>
            <div className="flex items-center gap-2">
              <MaterialIcon name="description" size={20} className="text-[var(--brand-80)]" />
              <CardTitle>デフォルトテキスト</CardTitle>
            </div>
          </CardHeader>
          <CardBody>
            <div className="space-y-4">
              <div>
                <label className="mb-1 block text-sm font-medium text-[var(--neutral-foreground-2)]">お願い事項のデフォルト文</label>
                <textarea
                  value={form.default_requests}
                  onChange={(e) => updateField('default_requests', e.target.value)}
                  className="block w-full rounded-lg border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-1)] px-3 py-2 text-sm text-[var(--neutral-foreground-1)]"
                  rows={3}
                  placeholder="毎月のおたよりに自動挿入されるお願い事項"
                />
              </div>
              <div>
                <label className="mb-1 block text-sm font-medium text-[var(--neutral-foreground-2)]">その他のデフォルト文</label>
                <textarea
                  value={form.default_others}
                  onChange={(e) => updateField('default_others', e.target.value)}
                  className="block w-full rounded-lg border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-1)] px-3 py-2 text-sm text-[var(--neutral-foreground-1)]"
                  rows={3}
                  placeholder="毎月のおたよりに自動挿入されるその他の情報"
                />
              </div>
            </div>
          </CardBody>
        </Card>

        {/* Custom section */}
        {form.show_custom_section && (
          <Card>
            <CardHeader>
              <CardTitle>カスタムセクション</CardTitle>
            </CardHeader>
            <CardBody>
              <div className="space-y-4">
                <Input
                  label="セクションタイトル"
                  value={form.custom_section_title}
                  onChange={(e) => updateField('custom_section_title', e.target.value)}
                  placeholder="独自セクションのタイトル"
                />
                <div>
                  <label className="mb-1 block text-sm font-medium text-[var(--neutral-foreground-2)]">セクション内容</label>
                  <textarea
                    value={form.custom_section_content}
                    onChange={(e) => updateField('custom_section_content', e.target.value)}
                    className="block w-full rounded-lg border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-1)] px-3 py-2 text-sm text-[var(--neutral-foreground-1)]"
                    rows={4}
                    placeholder="カスタムセクションの内容"
                  />
                </div>
              </div>
            </CardBody>
          </Card>
        )}

        {/* AI instructions */}
        <Card>
          <CardHeader>
            <div className="flex items-center gap-2">
              <MaterialIcon name="auto_awesome" size={20} className="text-[var(--brand-80)]" />
              <CardTitle>AI生成指示</CardTitle>
            </div>
          </CardHeader>
          <CardBody>
            <p className="mb-4 text-sm text-[var(--neutral-foreground-3)]">
              各セクションのAI自動生成時に使用する指示テキストを設定します。空欄の場合はデフォルトの指示が使用されます。
            </p>
            <div className="space-y-4">
              {AI_INSTRUCTION_FIELDS.map(({ key, label }) => (
                <div key={key}>
                  <label className="mb-1 block text-sm font-medium text-[var(--neutral-foreground-2)]">{label}</label>
                  <textarea
                    value={form[key] as string}
                    onChange={(e) => updateField(key, e.target.value)}
                    className="block w-full rounded-lg border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-1)] px-3 py-2 text-sm text-[var(--neutral-foreground-1)]"
                    rows={2}
                    placeholder={`${label}を入力（任意）`}
                  />
                </div>
              ))}
            </div>
          </CardBody>
        </Card>

        {/* Save button (bottom) */}
        <div className="flex justify-end">
          <Button type="submit" leftIcon={<MaterialIcon name="save" size={16} />} isLoading={saveMutation.isPending}>
            設定を保存
          </Button>
        </div>
      </form>
    </div>
  );
}
