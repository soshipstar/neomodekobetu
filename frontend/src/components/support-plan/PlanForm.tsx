'use client';

import { useForm, useFieldArray } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { useMutation } from '@tanstack/react-query';
import api from '@/lib/api';
import { supportPlanSchema, type SupportPlanFormData } from '@/lib/validators';
import { Input } from '@/components/ui/Input';
import { Button } from '@/components/ui/Button';
import { Card, CardBody } from '@/components/ui/Card';
import { useToast } from '@/components/ui/Toast';
import { Plus, Trash2, GripVertical } from 'lucide-react';
import { DOMAIN_LABELS, type Domain, type SupportPlan } from '@/types/support-plan';

interface PlanFormProps {
  studentId: number;
  existingPlan?: SupportPlan | null;
  onSuccess: () => void;
  onCancel: () => void;
}

const domainOptions = Object.entries(DOMAIN_LABELS) as [Domain, string][];

export function PlanForm({ studentId, existingPlan, onSuccess, onCancel }: PlanFormProps) {
  const toast = useToast();
  const isEditing = !!existingPlan;

  const {
    register,
    control,
    handleSubmit,
    formState: { errors },
  } = useForm<SupportPlanFormData>({
    resolver: zodResolver(supportPlanSchema),
    defaultValues: existingPlan
      ? {
          student_id: studentId,
          plan_period_start: existingPlan.plan_period_start ?? '',
          plan_period_end: existingPlan.plan_period_end ?? '',
          status: (['draft', 'review', 'approved', 'active', 'archived'].includes(existingPlan.status) ? existingPlan.status : 'draft') as 'draft' | 'review' | 'approved' | 'active' | 'archived',
          disability_type: existingPlan.disability_type ?? '',
          disability_class: existingPlan.disability_class ?? '',
          student_wish: existingPlan.student_wish ?? '',
          guardian_wish: existingPlan.guardian_wish ?? '',
          overall_policy: existingPlan.overall_policy ?? '',
          details: existingPlan.details?.map((d) => ({
            domain: d.domain,
            needs: d.needs ?? '',
            long_term_goal: d.long_term_goal ?? '',
            short_term_goal: d.short_term_goal ?? '',
            support_content: d.support_content ?? '',
            achievement_criteria: d.achievement_criteria ?? '',
            priority: d.priority ?? 3,
          })) ?? [],
        }
      : {
          student_id: studentId,
          plan_period_start: '',
          plan_period_end: '',
          status: 'draft',
          details: [
            {
              domain: 'health_life',
              needs: '',
              long_term_goal: '',
              short_term_goal: '',
              support_content: '',
              achievement_criteria: '',
              priority: 3,
            },
          ],
        },
  });

  const { fields, append, remove } = useFieldArray({
    control,
    name: 'details',
  });

  const mutation = useMutation({
    mutationFn: async (data: SupportPlanFormData) => {
      if (isEditing) {
        await api.put(`/api/staff/students/${studentId}/support-plans/${existingPlan!.id}`, data);
      } else {
        await api.post(`/api/staff/students/${studentId}/support-plans`, data);
      }
    },
    onSuccess: () => {
      toast.success(isEditing ? '個別支援計画を更新しました' : '個別支援計画を作成しました');
      onSuccess();
    },
    onError: () => toast.error('保存に失敗しました'),
  });

  return (
    <form onSubmit={handleSubmit((data) => mutation.mutate(data))} className="space-y-6">
      {/* Basic Info */}
      <div className="grid gap-4 sm:grid-cols-2">
        <Input
          label="計画期間（開始）"
          type="date"
          error={errors.plan_period_start?.message}
          {...register('plan_period_start')}
        />
        <Input
          label="計画期間（終了）"
          type="date"
          error={errors.plan_period_end?.message}
          {...register('plan_period_end')}
        />
      </div>

      <div className="grid gap-4 sm:grid-cols-2">
        <Input label="障害種別" {...register('disability_type')} />
        <Input label="障害等級" {...register('disability_class')} />
      </div>

      <div>
        <label className="mb-1 block text-sm font-medium text-gray-700">本人の願い</label>
        <textarea
          {...register('student_wish')}
          className="block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20"
          rows={3}
          placeholder="本人の願いを入力..."
        />
      </div>

      <div>
        <label className="mb-1 block text-sm font-medium text-gray-700">保護者の願い</label>
        <textarea
          {...register('guardian_wish')}
          className="block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20"
          rows={3}
          placeholder="保護者の願いを入力..."
        />
      </div>

      <div>
        <label className="mb-1 block text-sm font-medium text-gray-700">総合的な支援方針</label>
        <textarea
          {...register('overall_policy')}
          className="block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20"
          rows={4}
          placeholder="総合的な支援方針を入力..."
        />
      </div>

      {/* Support Details by Domain */}
      <div className="space-y-4">
        <div className="flex items-center justify-between">
          <h3 className="text-lg font-semibold text-gray-900">支援領域</h3>
          <Button
            type="button"
            variant="outline"
            size="sm"
            leftIcon={<Plus className="h-4 w-4" />}
            onClick={() =>
              append({
                domain: 'health_life',
                needs: '',
                long_term_goal: '',
                short_term_goal: '',
                support_content: '',
                achievement_criteria: '',
                priority: 3,
              })
            }
          >
            領域を追加
          </Button>
        </div>

        {errors.details?.root && (
          <p className="text-sm text-red-600">{errors.details.root.message}</p>
        )}

        {fields.map((field, index) => (
          <Card key={field.id} className="relative">
            <CardBody>
              <div className="absolute right-4 top-4 flex items-center gap-2">
                <GripVertical className="h-4 w-4 text-gray-300" />
                {fields.length > 1 && (
                  <button
                    type="button"
                    onClick={() => remove(index)}
                    className="rounded p-1 text-gray-400 hover:text-red-500"
                  >
                    <Trash2 className="h-4 w-4" />
                  </button>
                )}
              </div>

              <div className="space-y-4">
                <div className="grid gap-4 sm:grid-cols-2">
                  <div>
                    <label className="mb-1 block text-sm font-medium text-gray-700">領域</label>
                    <select
                      {...register(`details.${index}.domain`)}
                      className="block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm"
                    >
                      {domainOptions.map(([value, label]) => (
                        <option key={value} value={value}>{label}</option>
                      ))}
                    </select>
                    {errors.details?.[index]?.domain && (
                      <p className="mt-1 text-sm text-red-600">{errors.details[index].domain?.message}</p>
                    )}
                  </div>
                  <div>
                    <label className="mb-1 block text-sm font-medium text-gray-700">優先度 (1-5)</label>
                    <select
                      {...register(`details.${index}.priority`, { valueAsNumber: true })}
                      className="block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm"
                    >
                      {[1, 2, 3, 4, 5].map((v) => (
                        <option key={v} value={v}>{v}</option>
                      ))}
                    </select>
                  </div>
                </div>

                <div>
                  <label className="mb-1 block text-sm font-medium text-gray-700">ニーズ</label>
                  <textarea
                    {...register(`details.${index}.needs`)}
                    className="block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20"
                    rows={2}
                    placeholder="ニーズを入力..."
                  />
                  {errors.details?.[index]?.needs && (
                    <p className="mt-1 text-sm text-red-600">{errors.details[index].needs?.message}</p>
                  )}
                </div>

                <div>
                  <label className="mb-1 block text-sm font-medium text-gray-700">長期目標</label>
                  <textarea
                    {...register(`details.${index}.long_term_goal`)}
                    className="block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20"
                    rows={2}
                    placeholder="長期目標を入力..."
                  />
                  {errors.details?.[index]?.long_term_goal && (
                    <p className="mt-1 text-sm text-red-600">{errors.details[index].long_term_goal?.message}</p>
                  )}
                </div>

                <div>
                  <label className="mb-1 block text-sm font-medium text-gray-700">短期目標</label>
                  <textarea
                    {...register(`details.${index}.short_term_goal`)}
                    className="block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20"
                    rows={2}
                    placeholder="短期目標を入力..."
                  />
                  {errors.details?.[index]?.short_term_goal && (
                    <p className="mt-1 text-sm text-red-600">{errors.details[index].short_term_goal?.message}</p>
                  )}
                </div>

                <div>
                  <label className="mb-1 block text-sm font-medium text-gray-700">支援内容・方法</label>
                  <textarea
                    {...register(`details.${index}.support_content`)}
                    className="block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20"
                    rows={3}
                    placeholder="支援内容・方法を入力..."
                  />
                  {errors.details?.[index]?.support_content && (
                    <p className="mt-1 text-sm text-red-600">{errors.details[index].support_content?.message}</p>
                  )}
                </div>

                <div>
                  <label className="mb-1 block text-sm font-medium text-gray-700">達成基準（任意）</label>
                  <textarea
                    {...register(`details.${index}.achievement_criteria`)}
                    className="block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20"
                    rows={2}
                    placeholder="達成基準を入力..."
                  />
                </div>
              </div>
            </CardBody>
          </Card>
        ))}
      </div>

      {/* Actions */}
      <div className="flex items-center justify-end gap-3 border-t border-gray-200 pt-4">
        <Button type="button" variant="ghost" onClick={onCancel}>
          キャンセル
        </Button>
        <Button type="submit" isLoading={mutation.isPending}>
          {isEditing ? '更新する' : '作成する'}
        </Button>
      </div>
    </form>
  );
}

export default PlanForm;
