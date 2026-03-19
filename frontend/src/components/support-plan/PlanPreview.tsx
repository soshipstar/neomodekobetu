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
        <h2 className="text-xl font-bold text-gray-900">個別支援計画書</h2>
        <p className="text-sm text-gray-500">
          作成日: {formatDate(plan.created_date)}
        </p>
        <Badge variant={plan.status === 'official' ? 'success' : plan.status === 'submitted' ? 'warning' : 'default'} className="mt-2">
          {statusLabels[plan.status] || plan.status}
        </Badge>
      </div>

      {/* Basic info */}
      <div className="overflow-hidden rounded-lg border border-gray-300">
        <table className="min-w-full text-sm">
          <tbody>
            <tr className="border-b border-gray-200">
              <th className="bg-gray-50 px-4 py-2 text-left font-medium text-gray-600 w-32">氏名</th>
              <td className="px-4 py-2 text-gray-900">{plan.student_name || plan.student?.student_name || ''}</td>
            </tr>
            {plan.consent_date && (
              <tr className="border-b border-gray-200">
                <th className="bg-gray-50 px-4 py-2 text-left font-medium text-gray-600">同意日</th>
                <td className="px-4 py-2 text-gray-900">{formatDate(plan.consent_date)}</td>
              </tr>
            )}
          </tbody>
        </table>
      </div>

      {/* Life Intention & Overall Policy */}
      <div className="grid gap-4 sm:grid-cols-2">
        {plan.life_intention && (
          <div className="rounded-lg border border-gray-200 p-4">
            <h4 className="mb-2 text-sm font-semibold text-gray-700">利用児及び家族の生活に対する意向</h4>
            <p className="text-sm text-gray-600 whitespace-pre-wrap">{nl(plan.life_intention)}</p>
          </div>
        )}
        {plan.overall_policy && (
          <div className="rounded-lg border border-blue-200 bg-blue-50 p-4">
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
          <h3 className="text-lg font-semibold text-gray-900">支援目標及び具体的な支援内容等</h3>
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
                  const bgClass = cat.includes('家族') ? 'bg-blue-50' : cat.includes('地域') ? 'bg-green-50' : 'bg-gray-50';
                  return (
                    <tr key={detail.id || index} className={bgClass}>
                      <td className="border border-gray-300 px-3 py-2 whitespace-pre-wrap">{nl(detail.sub_category || detail.domain || '')}</td>
                      <td className="border border-gray-300 px-3 py-2 whitespace-pre-wrap">{nl(detail.support_goal || detail.goal || '')}</td>
                      <td className="border border-gray-300 px-3 py-2 whitespace-pre-wrap">{nl(detail.support_content || '')}</td>
                      <td className="border border-gray-300 px-3 py-2">{detail.achievement_date ? formatDate(detail.achievement_date) : ''}</td>
                      <td className="border border-gray-300 px-3 py-2 whitespace-pre-wrap">{nl(detail.staff_organization || '')}</td>
                      <td className="border border-gray-300 px-3 py-2 whitespace-pre-wrap">{nl(detail.notes || '')}</td>
                      <td className="border border-gray-300 px-3 py-2 text-center">{detail.priority ?? ''}</td>
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
          className="rounded-lg bg-gray-100 px-6 py-2 text-sm font-medium text-gray-700 hover:bg-gray-200"
        >
          印刷する
        </button>
      </div>
    </div>
  );
}

export default PlanPreview;
