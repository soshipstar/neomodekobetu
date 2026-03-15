'use client';

import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import api from '@/lib/api';
import { Card, CardHeader, CardTitle, CardBody } from '@/components/ui/Card';
import { Button } from '@/components/ui/Button';
import { Badge } from '@/components/ui/Badge';
import { SkeletonList } from '@/components/ui/Skeleton';
import { useToast } from '@/components/ui/Toast';
import { Tabs } from '@/components/ui/Tabs';
import {
  CheckCircle2,
  Eye,
  User,
  Calendar,
  Download,
} from 'lucide-react';
import { format } from 'date-fns';

// ---------------------------------------------------------------------------
// Types
// ---------------------------------------------------------------------------

interface Student {
  id: number;
  student_name: string;
}

interface GuardianKakehashi {
  id: number;
  student_id: number;
  student_name: string;
  period_id: number;
  period_name: string;
  submission_deadline: string;
  guardian_name: string;
  student_wish: string;
  short_term_goal: string;
  long_term_goal: string;
  health_life: string;
  motor_sensory: string;
  cognitive_behavior: string;
  language_communication: string;
  social_relations: string;
  is_confirmed: boolean;
  confirmed_at: string | null;
  submitted_at: string;
}

const DOMAIN_FIELDS = [
  { key: 'health_life', label: '健康・生活' },
  { key: 'motor_sensory', label: '運動・感覚' },
  { key: 'cognitive_behavior', label: '認知・行動' },
  { key: 'language_communication', label: '言語・コミュニケーション' },
  { key: 'social_relations', label: '人間関係・社会性' },
] as const;

export default function KakehashiGuardianPage() {
  const queryClient = useQueryClient();
  const toast = useToast();
  const [selectedStudentId, setSelectedStudentId] = useState<number | null>(null);
  const [expandedId, setExpandedId] = useState<number | null>(null);

  // Fetch students
  const { data: students = [], isLoading: loadingStudents } = useQuery({
    queryKey: ['staff', 'kakehashi', 'students'],
    queryFn: async () => {
      const res = await api.get<{ data: Student[] }>('/api/staff/students');
      return res.data.data;
    },
  });

  // Fetch guardian kakehashi entries
  const { data: entries = [], isLoading: loadingEntries } = useQuery({
    queryKey: ['staff', 'kakehashi', 'guardian-entries', selectedStudentId],
    queryFn: async () => {
      const url = selectedStudentId
        ? `/api/staff/kakehashi/guardian-entries?student_id=${selectedStudentId}`
        : '/api/staff/kakehashi/guardian-entries';
      const res = await api.get<{ data: GuardianKakehashi[] }>(url);
      return res.data.data;
    },
  });

  // Confirm mutation
  const confirmMutation = useMutation({
    mutationFn: (id: number) => api.post(`/api/staff/kakehashi/guardian-entries/${id}/confirm`),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['staff', 'kakehashi', 'guardian-entries'] });
      toast.success('確認しました');
    },
    onError: () => toast.error('確認に失敗しました'),
  });

  // PDF download
  const handlePdfDownload = async (entryId: number) => {
    try {
      const res = await api.get(`/api/staff/kakehashi/guardian-entries/${entryId}/pdf`, { responseType: 'blob' });
      const url = window.URL.createObjectURL(new Blob([res.data]));
      const link = document.createElement('a');
      link.href = url;
      link.download = `kakehashi_guardian_${entryId}.pdf`;
      link.click();
      window.URL.revokeObjectURL(url);
    } catch {
      toast.error('PDF生成に失敗しました');
    }
  };

  const unconfirmed = entries.filter((e) => !e.is_confirmed);
  const confirmed = entries.filter((e) => e.is_confirmed);

  const renderEntry = (entry: GuardianKakehashi) => {
    const isExpanded = expandedId === entry.id;

    return (
      <Card key={entry.id}>
        <CardHeader>
          <div className="flex items-center justify-between">
            <div className="flex items-center gap-3">
              <CardTitle className="text-base">
                {entry.student_name} - {entry.period_name}
              </CardTitle>
              <Badge variant={entry.is_confirmed ? 'success' : 'warning'}>
                {entry.is_confirmed ? '確認済み' : '未確認'}
              </Badge>
            </div>
            <div className="flex items-center gap-2">
              <span className="text-xs text-[var(--neutral-foreground-3)]">
                保護者: {entry.guardian_name}
              </span>
              <span className="text-xs text-[var(--neutral-foreground-3)]">
                提出: {format(new Date(entry.submitted_at), 'yyyy/MM/dd')}
              </span>
            </div>
          </div>
        </CardHeader>
        <CardBody>
          <button
            className="mb-3 flex items-center gap-1 text-sm font-medium text-[var(--brand-80)] hover:text-[var(--brand-70)]"
            onClick={() => setExpandedId(isExpanded ? null : entry.id)}
          >
            <Eye className="h-4 w-4" />
            {isExpanded ? '閉じる' : '詳細を表示'}
          </button>

          {isExpanded && (
            <div className="space-y-4 rounded-lg bg-[var(--neutral-background-2)] p-4">
              <div className="grid gap-3 md:grid-cols-2">
                <div>
                  <p className="text-xs font-medium text-[var(--neutral-foreground-3)]">本人の願い</p>
                  <p className="text-sm text-[var(--neutral-foreground-1)] whitespace-pre-wrap">{entry.student_wish || '-'}</p>
                </div>
                <div>
                  <p className="text-xs font-medium text-[var(--neutral-foreground-3)]">短期目標</p>
                  <p className="text-sm text-[var(--neutral-foreground-1)] whitespace-pre-wrap">{entry.short_term_goal || '-'}</p>
                </div>
                <div className="md:col-span-2">
                  <p className="text-xs font-medium text-[var(--neutral-foreground-3)]">長期目標</p>
                  <p className="text-sm text-[var(--neutral-foreground-1)] whitespace-pre-wrap">{entry.long_term_goal || '-'}</p>
                </div>
              </div>

              <div className="space-y-2">
                <h4 className="text-sm font-semibold text-[var(--neutral-foreground-1)]">5領域</h4>
                {DOMAIN_FIELDS.map(({ key, label }) => (
                  <div key={key}>
                    <p className="text-xs font-medium text-[var(--neutral-foreground-3)]">{label}</p>
                    <p className="text-sm text-[var(--neutral-foreground-1)] whitespace-pre-wrap">
                      {(entry as unknown as Record<string, string>)[key] || '-'}
                    </p>
                  </div>
                ))}
              </div>
            </div>
          )}

          <div className="mt-3 flex gap-2">
            <Button variant="outline" size="sm" onClick={() => handlePdfDownload(entry.id)}>
              <Download className="mr-1 h-4 w-4" />
              PDF
            </Button>
            {!entry.is_confirmed && (
              <Button
                size="sm"
                leftIcon={<CheckCircle2 className="h-4 w-4" />}
                onClick={() => confirmMutation.mutate(entry.id)}
                isLoading={confirmMutation.isPending}
              >
                確認する
              </Button>
            )}
            {entry.is_confirmed && entry.confirmed_at && (
              <span className="flex items-center gap-1 text-xs text-[var(--status-success-fg)]">
                <CheckCircle2 className="h-3 w-3" />
                {format(new Date(entry.confirmed_at), 'yyyy/MM/dd HH:mm')} に確認済み
              </span>
            )}
          </div>
        </CardBody>
      </Card>
    );
  };

  return (
    <div className="space-y-6">
      <h1 className="text-2xl font-bold text-[var(--neutral-foreground-1)]">かけはし（保護者閲覧）</h1>

      {/* Student filter */}
      <Card>
        <CardBody>
          <label className="mb-2 block text-sm font-medium text-[var(--neutral-foreground-2)]">生徒で絞り込み</label>
          <select
            className="block w-full rounded-lg border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-1)] px-3 py-2 text-sm text-[var(--neutral-foreground-1)]"
            value={selectedStudentId ?? ''}
            onChange={(e) => setSelectedStudentId(e.target.value ? Number(e.target.value) : null)}
          >
            <option value="">すべての生徒</option>
            {students.map((s) => (
              <option key={s.id} value={s.id}>{s.student_name}</option>
            ))}
          </select>
        </CardBody>
      </Card>

      {loadingEntries ? (
        <SkeletonList items={3} />
      ) : (
        <Tabs
          items={[
            {
              key: 'unconfirmed',
              label: '未確認',
              badge: unconfirmed.length,
              content: unconfirmed.length === 0 ? (
                <p className="py-8 text-center text-sm text-[var(--neutral-foreground-3)]">未確認のかけはしはありません</p>
              ) : (
                <div className="space-y-4">{unconfirmed.map(renderEntry)}</div>
              ),
            },
            {
              key: 'confirmed',
              label: '確認済み',
              badge: confirmed.length,
              content: confirmed.length === 0 ? (
                <p className="py-8 text-center text-sm text-[var(--neutral-foreground-3)]">確認済みのかけはしはありません</p>
              ) : (
                <div className="space-y-4">{confirmed.map(renderEntry)}</div>
              ),
            },
          ]}
        />
      )}
    </div>
  );
}
