'use client';

import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import api from '@/lib/api';
import { Card, CardHeader, CardTitle, CardBody, CardFooter } from '@/components/ui/Card';
import { Button } from '@/components/ui/Button';
import { Badge } from '@/components/ui/Badge';
import { Modal } from '@/components/ui/Modal';
import { Input } from '@/components/ui/Input';
import { SkeletonList } from '@/components/ui/Skeleton';
import { useToast } from '@/components/ui/Toast';
import { Plus, Send } from 'lucide-react';

interface Newsletter {
  id: number;
  year: number;
  month: number;
  title: string;
  greeting: string | null;
  event_calendar: string | null;
  event_details: string | null;
  weekly_reports: string | null;
  event_results: string | null;
  requests: string | null;
  others: string | null;
  is_published: boolean;
  created_at: string;
}

export default function NewslettersPage() {
  const queryClient = useQueryClient();
  const toast = useToast();
  const [showCreate, setShowCreate] = useState(false);
  const now = new Date();
  const [form, setForm] = useState({
    year: now.getFullYear(),
    month: now.getMonth() + 1,
    title: '',
    greeting: '',
    event_calendar: '',
    event_details: '',
    weekly_reports: '',
    event_results: '',
    requests: '',
    others: '',
  });

  const { data: newsletters, isLoading } = useQuery({
    queryKey: ['staff', 'newsletters'],
    queryFn: async () => {
      const response = await api.get<{ data: Newsletter[] }>('/api/staff/newsletters');
      return response.data.data;
    },
  });

  const createMutation = useMutation({
    mutationFn: async () => {
      await api.post('/api/staff/newsletters', form);
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['staff', 'newsletters'] });
      setShowCreate(false);
      const n = new Date();
      setForm({ year: n.getFullYear(), month: n.getMonth() + 1, title: '', greeting: '', event_calendar: '', event_details: '', weekly_reports: '', event_results: '', requests: '', others: '' });
      toast.success('おたよりを作成しました');
    },
    onError: () => toast.error('作成に失敗しました'),
  });

  const publishMutation = useMutation({
    mutationFn: async (id: number) => {
      await api.post(`/api/staff/newsletters/${id}/publish`);
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['staff', 'newsletters'] });
      toast.success('おたよりを配信しました');
    },
    onError: () => toast.error('配信に失敗しました'),
  });

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-bold text-[var(--neutral-foreground-1)]">おたより</h1>
        <Button leftIcon={<Plus className="h-4 w-4" />} onClick={() => setShowCreate(true)}>
          新規作成
        </Button>
      </div>

      {isLoading ? (
        <SkeletonList items={4} />
      ) : newsletters && newsletters.length > 0 ? (
        <div className="space-y-3">
          {newsletters.map((nl) => (
            <Card key={nl.id}>
              <CardHeader>
                <div className="flex items-center gap-2">
                  <CardTitle>{nl.title || `${nl.year}年${nl.month}月号`}</CardTitle>
                  <Badge variant={nl.is_published ? 'success' : 'default'}>
                    {nl.is_published ? '配信済' : '下書き'}
                  </Badge>
                </div>
                <span className="text-xs text-[var(--neutral-foreground-4)]">{nl.year}年{nl.month}月</span>
              </CardHeader>
              <CardBody>
                <p className="line-clamp-2 text-sm text-[var(--neutral-foreground-2)]">{nl.greeting || nl.event_details || '-'}</p>
              </CardBody>
              {!nl.is_published && (
                <CardFooter>
                  <Button
                    variant="primary"
                    size="sm"
                    leftIcon={<Send className="h-4 w-4" />}
                    onClick={() => publishMutation.mutate(nl.id)}
                    isLoading={publishMutation.isPending}
                  >
                    配信する
                  </Button>
                </CardFooter>
              )}
            </Card>
          ))}
        </div>
      ) : (
        <Card>
          <CardBody>
            <p className="py-8 text-center text-sm text-[var(--neutral-foreground-3)]">おたよりがありません</p>
          </CardBody>
        </Card>
      )}

      {/* Create Modal */}
      <Modal isOpen={showCreate} onClose={() => setShowCreate(false)} title="おたよりを作成" size="full">
        <div className="space-y-4">
          <div className="grid gap-4 sm:grid-cols-3">
            <Input label="年" type="number" value={String(form.year)} onChange={(e) => setForm({ ...form, year: Number(e.target.value) })} />
            <Input label="月" type="number" value={String(form.month)} onChange={(e) => setForm({ ...form, month: Number(e.target.value) })} />
            <Input label="タイトル" value={form.title} onChange={(e) => setForm({ ...form, title: e.target.value })} placeholder="おたよりのタイトル" />
          </div>
          {(['greeting', 'event_calendar', 'event_details', 'weekly_reports', 'event_results', 'requests', 'others'] as const).map((field) => {
            const labels: Record<string, string> = { greeting: 'ごあいさつ', event_calendar: '行事カレンダー', event_details: '行事詳細', weekly_reports: '週報', event_results: '行事結果', requests: 'お願い', others: 'その他' };
            return (
              <div key={field}>
                <label className="mb-1 block text-sm font-medium text-[var(--neutral-foreground-2)]">{labels[field]}</label>
                <textarea
                  value={form[field]}
                  onChange={(e) => setForm({ ...form, [field]: e.target.value })}
                  className="block w-full rounded-lg border border-[var(--neutral-stroke-2)] px-3 py-2 text-sm focus:border-[var(--brand-80)] focus:outline-none focus:ring-2 focus:ring-[var(--brand-80)]/20"
                  rows={3}
                />
              </div>
            );
          })}
          <div className="flex justify-end gap-2">
            <Button variant="ghost" onClick={() => setShowCreate(false)}>キャンセル</Button>
            <Button onClick={() => createMutation.mutate()} isLoading={createMutation.isPending} disabled={!form.title}>
              作成
            </Button>
          </div>
        </div>
      </Modal>
    </div>
  );
}
