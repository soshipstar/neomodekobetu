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
import type { SupportPlan } from '@/types/support-plan';
import { MaterialIcon } from '@/components/ui/MaterialIcon';

interface PlanFormProps {
  studentId: number;
  existingPlan?: SupportPlan | null;
  onSuccess: () => void;
  onCancel: () => void;
}

const DEFAULT_DETAILS = [
  { category: '本人支援', sub_category: '生活習慣（健康・生活）', staff_organization: '保育士\n児童指導員' },
  { category: '本人支援', sub_category: 'コミュニケーション（言語・コミュニケーション）', staff_organization: '保育士\n児童指導員' },
  { category: '本人支援', sub_category: '社会性（人間関係・社会性）', staff_organization: '保育士\n児童指導員' },
  { category: '本人支援', sub_category: '運動・感覚（運動・感覚）', staff_organization: '保育士\n児童指導員' },
  { category: '本人支援', sub_category: '学習（認知・行動）', staff_organization: '保育士\n児童指導員' },
  { category: '家族支援', sub_category: '保護者支援', staff_organization: '児童発達支援管理責任者\n保育士' },
  { category: '地域支援', sub_category: '関係機関連携', staff_organization: '児童発達支援管理責任者' },
];

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
          created_date: existingPlan.created_date ?? new Date().toISOString().split('T')[0],
          life_intention: existingPlan.life_intention ?? '',
          overall_policy: existingPlan.overall_policy ?? '',
          long_term_goal: existingPlan.long_term_goal ?? '',
          long_term_goal_date: existingPlan.long_term_goal_date ?? '',
          short_term_goal: existingPlan.short_term_goal ?? '',
          short_term_goal_date: existingPlan.short_term_goal_date ?? '',
          consent_date: existingPlan.consent_date ?? '',
          manager_name: existingPlan.manager_name ?? '',
          status: (['draft', 'submitted', 'official'].includes(existingPlan.status) ? existingPlan.status : 'draft') as 'draft' | 'submitted' | 'official',
          details: existingPlan.details?.map((d) => ({
            category: d.category ?? '',
            sub_category: d.sub_category ?? '',
            domain: d.domain ?? '',
            support_goal: d.support_goal ?? d.goal ?? '',
            support_content: d.support_content ?? '',
            achievement_date: d.achievement_date ?? '',
            staff_organization: d.staff_organization ?? '',
            notes: d.notes ?? '',
            priority: d.priority ?? undefined,
          })) ?? [],
        }
      : {
          student_id: studentId,
          created_date: new Date().toISOString().split('T')[0],
          life_intention: '',
          overall_policy: '',
          long_term_goal: '',
          long_term_goal_date: '',
          short_term_goal: '',
          short_term_goal_date: '',
          consent_date: '',
          manager_name: '',
          status: 'draft',
          details: DEFAULT_DETAILS.map((d) => ({
            category: d.category,
            sub_category: d.sub_category,
            domain: '',
            support_goal: '',
            support_content: '',
            achievement_date: '',
            staff_organization: d.staff_organization,
            notes: '',
            priority: undefined,
          })),
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

  const textareaClass = 'block w-full rounded-lg border border-[var(--neutral-stroke-1)] px-3 py-2 text-sm focus:border-[var(--brand-80)] focus:outline-none focus:ring-2 focus:ring-[var(--brand-80)]/20';
  const inputClass = 'block w-full rounded-lg border border-[var(--neutral-stroke-1)] px-3 py-2 text-sm focus:border-[var(--brand-80)] focus:outline-none focus:ring-2 focus:ring-[var(--brand-80)]/20';

  return (
    <form onSubmit={handleSubmit((data) => mutation.mutate(data))} className="space-y-6">
      {/* Basic Info */}
      <div className="grid gap-4 sm:grid-cols-2">
        <Input
          label="作成年月日"
          type="date"
          {...register('created_date')}
        />
        <div />
      </div>

      {/* Life Intention */}
      <div>
        <label className="mb-1 block text-sm font-medium text-[var(--neutral-foreground-2)]">利用児及び家族の生活に対する意向</label>
        <textarea
          {...register('life_intention')}
          className={textareaClass}
          rows={4}
          placeholder="利用児及び家族の生活に対する意向を入力..."
        />
      </div>

      {/* Overall Policy */}
      <div>
        <label className="mb-1 block text-sm font-medium text-[var(--neutral-foreground-2)]">総合的な支援の方針</label>
        <textarea
          {...register('overall_policy')}
          className={textareaClass}
          rows={4}
          placeholder="総合的な支援の方針を入力..."
        />
      </div>

      {/* Long-term Goal (plan-level) */}
      <Card>
        <CardBody>
          <h3 className="mb-3 text-lg font-semibold text-[var(--brand-70)]">長期目標</h3>
          <div className="mb-3 flex items-center gap-3">
            <span className="text-sm font-medium text-[var(--neutral-foreground-2)]">達成時期</span>
            <input
              type="date"
              {...register('long_term_goal_date')}
              className={inputClass + ' max-w-[200px]'}
            />
          </div>
          <textarea
            {...register('long_term_goal')}
            className={textareaClass}
            rows={4}
            placeholder="長期目標を入力..."
          />
        </CardBody>
      </Card>

      {/* Short-term Goal (plan-level) */}
      <Card>
        <CardBody>
          <h3 className="mb-3 text-lg font-semibold text-[var(--brand-70)]">短期目標</h3>
          <div className="mb-3 flex items-center gap-3">
            <span className="text-sm font-medium text-[var(--neutral-foreground-2)]">達成時期</span>
            <input
              type="date"
              {...register('short_term_goal_date')}
              className={inputClass + ' max-w-[200px]'}
            />
          </div>
          <textarea
            {...register('short_term_goal')}
            className={textareaClass}
            rows={4}
            placeholder="短期目標を入力..."
          />
        </CardBody>
      </Card>

      {/* Support Details Table */}
      <div className="space-y-4">
        <div className="flex items-center justify-between">
          <h3 className="text-lg font-semibold text-[var(--neutral-foreground-1)]">支援目標及び具体的な支援内容等</h3>
          <Button
            type="button"
            variant="outline"
            size="sm"
            leftIcon={<MaterialIcon name="add" size={16} />}
            onClick={() =>
              append({
                category: '',
                sub_category: '',
                domain: '',
                support_goal: '',
                support_content: '',
                achievement_date: '',
                staff_organization: '',
                notes: '',
                priority: undefined,
              })
            }
          >
            行を追加
          </Button>
        </div>

        {errors.details?.root && (
          <p className="text-sm text-red-600">{errors.details.root.message}</p>
        )}

        <div className="overflow-x-auto">
          <table className="w-full border-collapse text-sm">
            <thead>
              <tr className="bg-[var(--brand-80)] text-white">
                <th className="border border-blue-600 px-2 py-2 text-left" style={{ width: '80px' }}>項目</th>
                <th className="border border-blue-600 px-2 py-2 text-left" style={{ width: '200px' }}>支援目標</th>
                <th className="border border-blue-600 px-2 py-2 text-left" style={{ width: '300px' }}>支援内容</th>
                <th className="border border-blue-600 px-2 py-2 text-left" style={{ width: '90px' }}>達成時期</th>
                <th className="border border-blue-600 px-2 py-2 text-left" style={{ width: '110px' }}>担当者/提供機関</th>
                <th className="border border-blue-600 px-2 py-2 text-left" style={{ width: '140px' }}>留意事項</th>
                <th className="border border-blue-600 px-2 py-2 text-center" style={{ width: '50px' }}>優先</th>
                <th className="border border-blue-600 px-2 py-2 text-center" style={{ width: '30px' }} />
              </tr>
            </thead>
            <tbody>
              {fields.map((field, index) => (
                <tr key={field.id} className="border border-[var(--neutral-stroke-1)]">
                  <td className="border border-[var(--neutral-stroke-1)] p-1 align-top">
                    <input
                      type="text"
                      {...register(`details.${index}.category`)}
                      placeholder="項目"
                      className="mb-1 block w-full rounded border border-[var(--neutral-stroke-1)] px-1 py-1 text-xs"
                    />
                    <textarea
                      {...register(`details.${index}.sub_category`)}
                      rows={2}
                      placeholder="サブカテゴリ"
                      className="block w-full rounded border border-[var(--neutral-stroke-1)] px-1 py-1 text-xs"
                    />
                  </td>
                  <td className="border border-[var(--neutral-stroke-1)] p-1 align-top">
                    <textarea
                      {...register(`details.${index}.support_goal`)}
                      rows={3}
                      className="block w-full rounded border border-[var(--neutral-stroke-1)] px-1 py-1 text-xs"
                    />
                  </td>
                  <td className="border border-[var(--neutral-stroke-1)] p-1 align-top">
                    <textarea
                      {...register(`details.${index}.support_content`)}
                      rows={3}
                      className="block w-full rounded border border-[var(--neutral-stroke-1)] px-1 py-1 text-xs"
                    />
                  </td>
                  <td className="border border-[var(--neutral-stroke-1)] p-1 align-top">
                    <input
                      type="date"
                      {...register(`details.${index}.achievement_date`)}
                      className="block w-full rounded border border-[var(--neutral-stroke-1)] px-1 py-1 text-xs"
                    />
                  </td>
                  <td className="border border-[var(--neutral-stroke-1)] p-1 align-top">
                    <textarea
                      {...register(`details.${index}.staff_organization`)}
                      rows={3}
                      className="block w-full rounded border border-[var(--neutral-stroke-1)] px-1 py-1 text-xs"
                    />
                  </td>
                  <td className="border border-[var(--neutral-stroke-1)] p-1 align-top">
                    <textarea
                      {...register(`details.${index}.notes`)}
                      rows={3}
                      className="block w-full rounded border border-[var(--neutral-stroke-1)] px-1 py-1 text-xs"
                    />
                  </td>
                  <td className="border border-[var(--neutral-stroke-1)] p-1 align-top text-center">
                    <input
                      type="number"
                      {...register(`details.${index}.priority`, { valueAsNumber: true })}
                      min={1}
                      max={10}
                      className="block w-full rounded border border-[var(--neutral-stroke-1)] px-1 py-1 text-center text-xs"
                    />
                  </td>
                  <td className="border border-[var(--neutral-stroke-1)] p-1 align-top text-center">
                    {fields.length > 1 && (
                      <button
                        type="button"
                        onClick={() => remove(index)}
                        className="rounded p-1 text-[var(--neutral-foreground-4)] hover:text-red-500"
                      >
                        <MaterialIcon name="delete" size={12} />
                      </button>
                    )}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>

        <div className="rounded-md border-l-4 border-orange-400 bg-orange-50 p-3 text-sm text-[var(--neutral-foreground-2)]">
          <strong>※ 5領域の視点：</strong>「健康・生活」「運動・感覚」「認知・行動」「言語・コミュニケーション」「人間関係・社会性」
        </div>
      </div>

      {/* Consent Section */}
      <Card>
        <CardBody>
          <h3 className="mb-3 text-lg font-semibold text-[var(--brand-70)]">同意</h3>
          <div className="grid gap-4 sm:grid-cols-2">
            <Input
              label="管理責任者氏名"
              {...register('manager_name')}
            />
            <Input
              label="同意日"
              type="date"
              {...register('consent_date')}
            />
          </div>
        </CardBody>
      </Card>

      {/* Actions */}
      <div className="flex items-center justify-end gap-3 border-t border-[var(--neutral-stroke-2)] pt-4">
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
