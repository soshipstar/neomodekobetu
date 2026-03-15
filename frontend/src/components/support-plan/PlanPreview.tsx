'use client';

import { formatDate } from '@/lib/utils';
import { Badge } from '@/components/ui/Badge';
import { DOMAIN_LABELS, type Domain, type SupportPlan } from '@/types/support-plan';

interface PlanPreviewProps {
  plan: SupportPlan;
}

const statusLabels: Record<string, string> = {
  draft: '下書き', review: 'レビュー中', approved: '承認済', active: '有効', archived: 'アーカイブ',
};

export function PlanPreview({ plan }: PlanPreviewProps) {
  return (
    <div className="space-y-6 print:space-y-4">
      {/* Header */}
      <div className="text-center">
        <h2 className="text-xl font-bold text-gray-900">個別支援計画書</h2>
        <p className="text-sm text-gray-500">
          計画期間: {formatDate(plan.plan_period_start ?? '')} - {formatDate(plan.plan_period_end ?? '')}
        </p>
        <Badge variant={plan.status === 'active' ? 'primary' : 'default'} className="mt-2">
          {statusLabels[plan.status]}
        </Badge>
      </div>

      {/* Basic info table */}
      <div className="overflow-hidden rounded-lg border border-gray-300">
        <table className="min-w-full text-sm">
          <tbody>
            {plan.student && (
              <tr className="border-b border-gray-200">
                <th className="bg-gray-50 px-4 py-2 text-left font-medium text-gray-600 w-32">氏名</th>
                <td className="px-4 py-2 text-gray-900">{plan.student.student_name}</td>
              </tr>
            )}
            {plan.disability_type && (
              <tr className="border-b border-gray-200">
                <th className="bg-gray-50 px-4 py-2 text-left font-medium text-gray-600">障害種別</th>
                <td className="px-4 py-2 text-gray-900">{plan.disability_type}</td>
              </tr>
            )}
            {plan.disability_class && (
              <tr className="border-b border-gray-200">
                <th className="bg-gray-50 px-4 py-2 text-left font-medium text-gray-600">障害等級</th>
                <td className="px-4 py-2 text-gray-900">{plan.disability_class}</td>
              </tr>
            )}
          </tbody>
        </table>
      </div>

      {/* Wishes */}
      <div className="grid gap-4 sm:grid-cols-2">
        {plan.student_wish && (
          <div className="rounded-lg border border-gray-200 p-4">
            <h4 className="mb-2 text-sm font-semibold text-gray-700">本人の願い</h4>
            <p className="text-sm text-gray-600 whitespace-pre-wrap">{plan.student_wish}</p>
          </div>
        )}
        {plan.guardian_wish && (
          <div className="rounded-lg border border-gray-200 p-4">
            <h4 className="mb-2 text-sm font-semibold text-gray-700">保護者の願い</h4>
            <p className="text-sm text-gray-600 whitespace-pre-wrap">{plan.guardian_wish}</p>
          </div>
        )}
      </div>

      {/* Overall policy */}
      {plan.overall_policy && (
        <div className="rounded-lg border border-blue-200 bg-blue-50 p-4">
          <h4 className="mb-2 text-sm font-semibold text-blue-800">総合的な支援方針</h4>
          <p className="text-sm text-blue-900 whitespace-pre-wrap">{plan.overall_policy}</p>
        </div>
      )}

      {/* Support details */}
      {plan.details && plan.details.length > 0 && (
        <div className="space-y-4">
          <h3 className="text-lg font-semibold text-gray-900">支援内容</h3>
          {plan.details
            .sort((a, b) => a.sort_order - b.sort_order)
            .map((detail, index) => (
            <div key={detail.id} className="overflow-hidden rounded-lg border border-gray-300">
              <div className="bg-gray-50 px-4 py-2">
                <h4 className="text-sm font-semibold text-gray-800">
                  {index + 1}. {DOMAIN_LABELS[detail.domain as Domain] || detail.domain}
                </h4>
              </div>
              <table className="min-w-full text-sm">
                <tbody>
                  <tr className="border-b border-gray-200">
                    <th className="bg-gray-50/50 px-4 py-2 text-left font-medium text-gray-600 w-32">ニーズ</th>
                    <td className="px-4 py-2 text-gray-900 whitespace-pre-wrap">{detail.needs}</td>
                  </tr>
                  <tr className="border-b border-gray-200">
                    <th className="bg-gray-50/50 px-4 py-2 text-left font-medium text-gray-600">長期目標</th>
                    <td className="px-4 py-2 text-gray-900 whitespace-pre-wrap">{detail.long_term_goal}</td>
                  </tr>
                  <tr className="border-b border-gray-200">
                    <th className="bg-gray-50/50 px-4 py-2 text-left font-medium text-gray-600">短期目標</th>
                    <td className="px-4 py-2 text-gray-900 whitespace-pre-wrap">{detail.short_term_goal}</td>
                  </tr>
                  <tr className="border-b border-gray-200">
                    <th className="bg-gray-50/50 px-4 py-2 text-left font-medium text-gray-600">支援内容</th>
                    <td className="px-4 py-2 text-gray-900 whitespace-pre-wrap">{detail.support_content}</td>
                  </tr>
                  {detail.achievement_criteria && (
                    <tr>
                      <th className="bg-gray-50/50 px-4 py-2 text-left font-medium text-gray-600">達成基準</th>
                      <td className="px-4 py-2 text-gray-900 whitespace-pre-wrap">{detail.achievement_criteria}</td>
                    </tr>
                  )}
                </tbody>
              </table>
            </div>
          ))}
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
