'use client';

import { useState } from 'react';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { z } from 'zod';
import api from '@/lib/api';
import { Card, CardHeader, CardTitle, CardBody } from '@/components/ui/Card';
import { Button } from '@/components/ui/Button';
import { Input } from '@/components/ui/Input';
import { Badge } from '@/components/ui/Badge';
import { SkeletonList } from '@/components/ui/Skeleton';
import { useToast } from '@/components/ui/Toast';
import { formatDate } from '@/lib/utils';
import { Send, Calendar } from 'lucide-react';

const absenceFormSchema = z.object({
  student_id: z.number({ error: 'お子様を選択してください' }),
  absence_date: z.string().min(1, '日付を入力してください'),
  reason: z.string().min(1, '理由を入力してください'),
  makeup_request: z.boolean().optional(),
  makeup_request_date: z.string().optional(),
});

type AbsenceFormValues = z.infer<typeof absenceFormSchema>;

interface AbsenceRecord {
  id: number;
  student_id: number;
  absence_date: string;
  reason: string;
  makeup_request_date: string | null;
  makeup_status: 'pending' | 'approved' | 'rejected' | null;
  created_at: string;
  student: {
    id: number;
    student_name: string;
  };
}

interface ChildOption {
  id: number;
  student_name: string;
}

const makeupStatusLabels: Record<string, { label: string; variant: 'warning' | 'success' | 'danger' }> = {
  pending: { label: '振替申請中', variant: 'warning' },
  approved: { label: '振替承認済', variant: 'success' },
  rejected: { label: '振替不可', variant: 'danger' },
};

export default function AbsenceNotificationPage() {
  const queryClient = useQueryClient();
  const toast = useToast();
  const [showMakeup, setShowMakeup] = useState(false);

  const { data: children } = useQuery({
    queryKey: ['guardian', 'children'],
    queryFn: async () => {
      const response = await api.get<{ data: ChildOption[] }>('/api/guardian/children');
      return response.data.data;
    },
  });

  const { data: absencesData, isLoading } = useQuery({
    queryKey: ['guardian', 'absences'],
    queryFn: async () => {
      const response = await api.get<{ data: { data: AbsenceRecord[] } }>('/api/guardian/absences');
      // Handle both paginated and non-paginated responses
      const d = response.data.data;
      return Array.isArray(d) ? d : (d as { data: AbsenceRecord[] }).data ?? [];
    },
  });

  const absences = absencesData ?? [];

  const {
    register,
    handleSubmit,
    reset,
    formState: { errors },
  } = useForm<AbsenceFormValues>({
    resolver: zodResolver(absenceFormSchema),
  });

  const mutation = useMutation({
    mutationFn: async (data: AbsenceFormValues) => {
      await api.post('/api/guardian/absences', {
        student_id: data.student_id,
        absence_date: data.absence_date,
        reason: data.reason,
        makeup_request: data.makeup_request ?? false,
        makeup_request_date: data.makeup_request ? data.makeup_request_date : null,
      });
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['guardian', 'absences'] });
      reset();
      setShowMakeup(false);
      toast.success('欠席連絡を送信しました');
    },
    onError: () => toast.error('送信に失敗しました'),
  });

  return (
    <div className="space-y-6">
      <h1 className="text-2xl font-bold text-[var(--neutral-foreground-1)]">欠席連絡</h1>

      {/* Form */}
      <Card>
        <CardHeader>
          <CardTitle>欠席連絡を送信</CardTitle>
        </CardHeader>
        <CardBody>
          <form onSubmit={handleSubmit((data) => mutation.mutate(data))} className="space-y-4">
            <div>
              <label className="mb-1 block text-sm font-medium text-[var(--neutral-foreground-2)]">お子様</label>
              <select
                {...register('student_id', { valueAsNumber: true })}
                className="block w-full rounded-lg border border-[var(--neutral-stroke-1)] bg-white px-3 py-2 text-sm focus:border-[var(--brand-80)] focus:outline-none focus:ring-2 focus:ring-[var(--brand-80)]/20"
              >
                <option value="">選択してください</option>
                {children?.map((child) => (
                  <option key={child.id} value={child.id}>{child.student_name}</option>
                ))}
              </select>
              {errors.student_id && <p className="mt-1 text-sm text-red-600">{errors.student_id.message}</p>}
            </div>

            <Input
              label="欠席日"
              type="date"
              error={errors.absence_date?.message}
              {...register('absence_date')}
            />

            <div>
              <label className="mb-1 block text-sm font-medium text-[var(--neutral-foreground-2)]">理由</label>
              <textarea
                {...register('reason')}
                className="block w-full rounded-lg border border-[var(--neutral-stroke-1)] px-3 py-2 text-sm focus:border-[var(--brand-80)] focus:outline-none focus:ring-2 focus:ring-[var(--brand-80)]/20"
                rows={3}
                placeholder="欠席理由を入力..."
              />
              {errors.reason && <p className="mt-1 text-sm text-red-600">{errors.reason.message}</p>}
            </div>

            {/* Makeup request toggle */}
            <div className="rounded-lg border border-[var(--neutral-stroke-2)] p-4">
              <label className="flex items-center gap-2 cursor-pointer">
                <input
                  type="checkbox"
                  {...register('makeup_request')}
                  onChange={(e) => {
                    register('makeup_request').onChange(e);
                    setShowMakeup(e.target.checked);
                  }}
                  className="rounded border-[var(--neutral-stroke-1)]"
                />
                <span className="text-sm font-medium text-[var(--neutral-foreground-2)]">振替を希望する</span>
              </label>
              {showMakeup && (
                <div className="mt-3">
                  <Input
                    label="振替希望日"
                    type="date"
                    error={errors.makeup_request_date?.message}
                    {...register('makeup_request_date')}
                  />
                </div>
              )}
            </div>

            <Button type="submit" leftIcon={<Send className="h-4 w-4" />} isLoading={mutation.isPending}>
              送信する
            </Button>
          </form>
        </CardBody>
      </Card>

      {/* History */}
      <Card>
        <CardHeader>
          <CardTitle>送信履歴</CardTitle>
        </CardHeader>
        <CardBody>
          {isLoading ? (
            <SkeletonList items={3} />
          ) : absences.length > 0 ? (
            <div className="space-y-2">
              {absences.map((absence) => (
                <div key={absence.id} className="flex items-center justify-between rounded-lg border border-[var(--neutral-stroke-3)] p-3">
                  <div>
                    <p className="text-sm font-medium text-[var(--neutral-foreground-1)]">
                      {absence.student?.student_name} - {formatDate(absence.absence_date)}
                    </p>
                    <p className="text-xs text-[var(--neutral-foreground-3)]">{absence.reason}</p>
                    {absence.makeup_request_date && (
                      <p className="mt-1 flex items-center gap-1 text-xs text-[var(--brand-80)]">
                        <Calendar className="h-3 w-3" />
                        振替希望: {formatDate(absence.makeup_request_date)}
                      </p>
                    )}
                  </div>
                  <div className="flex flex-col items-end gap-1">
                    {absence.makeup_status && makeupStatusLabels[absence.makeup_status] && (
                      <Badge variant={makeupStatusLabels[absence.makeup_status].variant} dot>
                        {makeupStatusLabels[absence.makeup_status].label}
                      </Badge>
                    )}
                  </div>
                </div>
              ))}
            </div>
          ) : (
            <p className="py-4 text-center text-sm text-[var(--neutral-foreground-3)]">送信履歴はありません</p>
          )}
        </CardBody>
      </Card>
    </div>
  );
}
