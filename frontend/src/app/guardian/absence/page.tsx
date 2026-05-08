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
import { MaterialIcon } from '@/components/ui/MaterialIcon';

const absenceFormSchema = z.object({
  student_id: z.number({ error: 'お子様を選択してください' }),
  absence_date: z.string().min(1, '日付を入力してください'),
  reason: z.string().min(1, '理由を入力してください'),
  makeup_request: z.boolean().optional(),
  makeup_request_date: z.string().optional(),
  // 体調情報 (任意)
  body_temperature: z.union([z.string(), z.number()]).optional(),
  hospital_visit: z.boolean().optional(),
  symptom_abdominal_pain: z.boolean().optional(),
  symptom_headache: z.boolean().optional(),
  symptom_sore_throat: z.boolean().optional(),
  symptom_cough: z.boolean().optional(),
  symptom_sneeze: z.boolean().optional(),
  symptom_runny_nose: z.boolean().optional(),
  other_concerns: z.string().optional(),
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
  body_temperature: string | number | null;
  hospital_visit: boolean;
  symptom_abdominal_pain: boolean;
  symptom_headache: boolean;
  symptom_sore_throat: boolean;
  symptom_cough: boolean;
  symptom_sneeze: boolean;
  symptom_runny_nose: boolean;
  other_concerns: string | null;
  advice: string | null;
  advice_at: string | null;
  advice_author?: { id: number; full_name: string } | null;
  student: {
    id: number;
    student_name: string;
  };
}

const SYMPTOM_FIELDS: Array<{ key: 'symptom_abdominal_pain' | 'symptom_headache' | 'symptom_sore_throat' | 'symptom_cough' | 'symptom_sneeze' | 'symptom_runny_nose'; label: string }> = [
  { key: 'symptom_abdominal_pain', label: '腹痛' },
  { key: 'symptom_headache',       label: '頭痛' },
  { key: 'symptom_sore_throat',    label: '咽頭痛' },
  { key: 'symptom_cough',          label: '咳' },
  { key: 'symptom_sneeze',         label: 'くしゃみ' },
  { key: 'symptom_runny_nose',     label: '鼻水' },
];

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
      const tempStr = typeof data.body_temperature === 'string' ? data.body_temperature.trim() : data.body_temperature;
      const tempNum = tempStr === '' || tempStr == null ? null : Number(tempStr);
      await api.post('/api/guardian/absences', {
        student_id: data.student_id,
        absence_date: data.absence_date,
        reason: data.reason,
        makeup_request: data.makeup_request ?? false,
        makeup_request_date: data.makeup_request ? data.makeup_request_date : null,
        body_temperature: Number.isFinite(tempNum as number) ? tempNum : null,
        hospital_visit: !!data.hospital_visit,
        symptom_abdominal_pain: !!data.symptom_abdominal_pain,
        symptom_headache: !!data.symptom_headache,
        symptom_sore_throat: !!data.symptom_sore_throat,
        symptom_cough: !!data.symptom_cough,
        symptom_sneeze: !!data.symptom_sneeze,
        symptom_runny_nose: !!data.symptom_runny_nose,
        other_concerns: data.other_concerns?.trim() || null,
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

            {/* 体調情報 (任意) */}
            <div className="rounded-lg border border-[var(--neutral-stroke-2)] bg-[var(--neutral-background-2)] p-4 space-y-3">
              <div className="flex items-center gap-2">
                <MaterialIcon name="thermostat" size={18} className="text-[var(--brand-80)]" />
                <span className="text-sm font-semibold text-[var(--neutral-foreground-2)]">体調 (任意)</span>
              </div>

              <div className="grid grid-cols-2 gap-3">
                <Input
                  label="体温 (℃)"
                  type="number"
                  step="0.1"
                  min={30}
                  max={45}
                  placeholder="例: 36.5"
                  {...register('body_temperature')}
                />
                <label className="flex items-center gap-2 self-end pb-2">
                  <input
                    type="checkbox"
                    {...register('hospital_visit')}
                    className="rounded border-[var(--neutral-stroke-1)]"
                  />
                  <span className="text-sm text-[var(--neutral-foreground-2)]">通院した / する予定</span>
                </label>
              </div>

              <div>
                <p className="mb-1 text-xs font-medium text-[var(--neutral-foreground-3)]">症状 (該当するもの)</p>
                <div className="grid grid-cols-2 gap-2 sm:grid-cols-3">
                  {SYMPTOM_FIELDS.map((s) => (
                    <label key={s.key} className="flex items-center gap-2">
                      <input
                        type="checkbox"
                        {...register(s.key)}
                        className="rounded border-[var(--neutral-stroke-1)]"
                      />
                      <span className="text-sm text-[var(--neutral-foreground-2)]">{s.label}</span>
                    </label>
                  ))}
                </div>
              </div>

              <div>
                <label className="mb-1 block text-xs font-medium text-[var(--neutral-foreground-3)]">その他困っていること</label>
                <textarea
                  {...register('other_concerns')}
                  className="block w-full rounded-lg border border-[var(--neutral-stroke-1)] px-3 py-2 text-sm focus:border-[var(--brand-80)] focus:outline-none focus:ring-2 focus:ring-[var(--brand-80)]/20"
                  rows={2}
                  placeholder="夜中ぐずっていた、食欲がない など"
                />
              </div>
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

            <Button type="submit" leftIcon={<MaterialIcon name="send" size={16} />} isLoading={mutation.isPending}>
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
              {absences.map((absence) => {
                const symptoms = SYMPTOM_FIELDS.filter((s) => absence[s.key]).map((s) => s.label);
                const hasHealthInfo = absence.body_temperature != null || absence.hospital_visit
                  || symptoms.length > 0 || absence.other_concerns;
                return (
                  <div key={absence.id} className="rounded-lg border border-[var(--neutral-stroke-3)] p-3 space-y-2">
                    <div className="flex items-start justify-between">
                      <div>
                        <p className="text-sm font-medium text-[var(--neutral-foreground-1)]">
                          {absence.student?.student_name} - {formatDate(absence.absence_date)}
                        </p>
                        <p className="text-xs text-[var(--neutral-foreground-3)]">{absence.reason}</p>
                        {absence.makeup_request_date && (
                          <p className="mt-1 flex items-center gap-1 text-xs text-[var(--brand-80)]">
                            <MaterialIcon name="calendar_month" size={12} />
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

                    {hasHealthInfo && (
                      <div className="rounded-md bg-[var(--neutral-background-2)] p-2 text-xs text-[var(--neutral-foreground-2)] space-y-1">
                        <div className="flex flex-wrap items-center gap-x-3 gap-y-1">
                          {absence.body_temperature != null && (
                            <span><MaterialIcon name="thermostat" size={12} className="inline mr-0.5" />{Number(absence.body_temperature).toFixed(1)}℃</span>
                          )}
                          {absence.hospital_visit && (
                            <span className="text-[var(--status-warning-fg)]"><MaterialIcon name="local_hospital" size={12} className="inline mr-0.5" />通院</span>
                          )}
                          {symptoms.length > 0 && (
                            <span>症状: {symptoms.join('・')}</span>
                          )}
                        </div>
                        {absence.other_concerns && (
                          <p className="text-[var(--neutral-foreground-3)]">困っていること: {absence.other_concerns}</p>
                        )}
                      </div>
                    )}

                    {absence.advice && (
                      <div className="rounded-md bg-[var(--brand-160)] border border-[var(--brand-130)] p-2 text-xs space-y-1">
                        <div className="flex items-center gap-1 text-[var(--brand-60)] font-semibold">
                          <MaterialIcon name="support_agent" size={12} />
                          スタッフからのアドバイス
                          {absence.advice_author?.full_name && (
                            <span className="ml-1 font-normal text-[var(--neutral-foreground-3)]">
                              ({absence.advice_author.full_name})
                            </span>
                          )}
                        </div>
                        <p className="whitespace-pre-wrap text-[var(--neutral-foreground-1)]">{absence.advice}</p>
                      </div>
                    )}
                  </div>
                );
              })}
            </div>
          ) : (
            <p className="py-4 text-center text-sm text-[var(--neutral-foreground-3)]">送信履歴はありません</p>
          )}
        </CardBody>
      </Card>
    </div>
  );
}
