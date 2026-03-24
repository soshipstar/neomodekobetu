'use client';

import { formatDate, nl } from '@/lib/utils';
import { Badge } from '@/components/ui/Badge';
import type { SupportPlan } from '@/types/support-plan';

interface PlanPreviewProps {
  plan: SupportPlan;
}

const statusLabels: Record<string, string> = {
  draft: '下書き', submitted: '提出済', official: '正式',
};

export function PlanPreview({ plan }: PlanPreviewProps) {
  const details = plan.details?.sort((a, b) => a.sort_order - b.sort_order) ?? [];

  return (
    <div className="space-y-6 print:space-y-4">
      {/* Header */}
      <div className="text-center">
        <h2 className="text-xl font-bold text-[var(--neutral-foreground-1)]">個別支援計画書</h2>
        <p className="text-sm text-[var(--neutral-foreground-3)]">
          作成日: {formatDate(plan.created_date)}
        </p>
        <Badge variant={plan.status === 'official' ? 'success' : plan.status === 'submitted' ? 'warning' : 'default'} className="mt-2">
          {statusLabels[plan.status] || plan.status}
        </Badge>
      </div>

      {/* Basic info */}
      <div className="overflow-hidden rounded-lg border border-[var(--neutral-stroke-1)]">
        <table className="min-w-full text-sm">
          <tbody>
            <tr className="border-b border-[var(--neutral-stroke-2)]">
              <th className="bg-[var(--neutral-background-3)] px-4 py-2 text-left font-medium text-[var(--neutral-foreground-3)] w-32">氏名</th>
              <td className="px-4 py-2 text-[var(--neutral-foreground-1)]">{plan.student_name || plan.student?.student_name || ''}</td>
            </tr>
            {plan.consent_date && (
              <tr className="border-b border-[var(--neutral-stroke-2)]">
                <th className="bg-[var(--neutral-background-3)] px-4 py-2 text-left font-medium text-[var(--neutral-foreground-3)]">同意日</th>
                <td className="px-4 py-2 text-[var(--neutral-foreground-1)]">{formatDate(plan.consent_date)}</td>
              </tr>
            )}
          </tbody>
        </table>
      </div>

      {/* Life Intention & Overall Policy */}
      <div className="grid gap-4 sm:grid-cols-2">
        {plan.life_intention && (
          <div className="rounded-lg border border-[var(--neutral-stroke-2)] p-4">
            <h4 className="mb-2 text-sm font-semibold text-[var(--neutral-foreground-2)]">利用児及び家族の生活に対する意向</h4>
            <p className="text-sm text-[var(--neutral-foreground-3)] whitespace-pre-wrap">{nl(plan.life_intention)}</p>
          </div>
        )}
        {plan.overall_policy && (
          <div className="rounded-lg border border-[var(--brand-130)] bg-[var(--brand-160)] p-4">
            <h4 className="mb-2 text-sm font-semibold text-blue-800">総合的な支援の方針</h4>
            <p className="text-sm text-blue-900 whitespace-pre-wrap">{nl(plan.overall_policy)}</p>
          </div>
        )}
      </div>

      {/* Long-term & Short-term Goals (plan-level) */}
      <div className="grid gap-4 sm:grid-cols-2">
        {plan.long_term_goal && (
          <div className="rounded-lg border border-green-200 bg-green-50 p-4">
            <h4 className="mb-2 text-sm font-semibold text-green-800">長期目標</h4>
            {plan.long_term_goal_date && (
              <p className="mb-1 text-xs text-green-600">達成時期: {formatDate(plan.long_term_goal_date)}</p>
            )}
            <p className="text-sm text-green-900 whitespace-pre-wrap">{nl(plan.long_term_goal)}</p>
          </div>
        )}
        {plan.short_term_goal && (
          <div className="rounded-lg border border-amber-200 bg-amber-50 p-4">
            <h4 className="mb-2 text-sm font-semibold text-amber-800">短期目標</h4>
            {plan.short_term_goal_date && (
              <p className="mb-1 text-xs text-amber-600">達成時期: {formatDate(plan.short_term_goal_date)}</p>
            )}
            <p className="text-sm text-amber-900 whitespace-pre-wrap">{nl(plan.short_term_goal)}</p>
          </div>
        )}
      </div>

      {/* Support details table */}
      {details.length > 0 && (
        <div className="space-y-3">
          <h3 className="text-lg font-semibold text-[var(--neutral-foreground-1)]">支援目標及び具体的な支援内容等</h3>
          <div className="overflow-x-auto">
            <table className="min-w-full border-collapse text-sm">
              <thead>
                <tr className="bg-gray-600 text-white">
                  <th className="border border-gray-600 px-3 py-2 text-left">項目</th>
                  <th className="border border-gray-600 px-3 py-2 text-left">支援目標</th>
                  <th className="border border-gray-600 px-3 py-2 text-left">支援内容</th>
                  <th className="border border-gray-600 px-3 py-2 text-left">達成時期</th>
                  <th className="border border-gray-600 px-3 py-2 text-left">担当者</th>
                  <th className="border border-gray-600 px-3 py-2 text-left">留意事項</th>
                  <th className="border border-gray-600 px-3 py-2 text-center">優先</th>
                </tr>
              </thead>
              <tbody>
                {details.map((detail, index) => {
                  const cat = detail.category || '';
                  const bgClass = cat.includes('家族') ? 'bg-[var(--brand-160)]' : cat.includes('地域') ? 'bg-green-50' : 'bg-[var(--neutral-background-3)]';
                  return (
                    <tr key={detail.id || index} className={bgClass}>
                      <td className="border border-[var(--neutral-stroke-1)] px-3 py-2 whitespace-pre-wrap">{nl(detail.sub_category || detail.domain || '')}</td>
                      <td className="border border-[var(--neutral-stroke-1)] px-3 py-2 whitespace-pre-wrap">{nl(detail.support_goal || detail.goal || '')}</td>
                      <td className="border border-[var(--neutral-stroke-1)] px-3 py-2 whitespace-pre-wrap">{nl(detail.support_content || '')}</td>
                      <td className="border border-[var(--neutral-stroke-1)] px-3 py-2">{detail.achievement_date ? formatDate(detail.achievement_date) : ''}</td>
                      <td className="border border-[var(--neutral-stroke-1)] px-3 py-2 whitespace-pre-wrap">{nl(detail.staff_organization || '')}</td>
                      <td className="border border-[var(--neutral-stroke-1)] px-3 py-2 whitespace-pre-wrap">{nl(detail.notes || '')}</td>
                      <td className="border border-[var(--neutral-stroke-1)] px-3 py-2 text-center">{detail.priority ?? ''}</td>
                    </tr>
                  );
                })}
              </tbody>
            </table>
          </div>
        </div>
      )}

      {/* Print button */}
      <div className="flex justify-center print:hidden">
        <button
          onClick={() => window.print()}
          className="rounded-lg bg-[var(--neutral-background-4)] px-6 py-2 text-sm font-medium text-[var(--neutral-foreground-2)] hover:bg-[var(--neutral-background-5)]"
        >
          印刷する
        </button>
      </div>
    </div>
  );
}

export default PlanPreview;
