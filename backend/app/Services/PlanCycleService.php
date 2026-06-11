<?php

namespace App\Services;

use App\Models\IndividualSupportPlan;
use App\Models\MonitoringRecord;
use Illuminate\Support\Carbon;

/**
 * 個別支援計画のサイクル管理ロジック。
 *
 * - 計画作成時: cycle_number / plan_period_start/end / next_monitoring_due_date / next_plan_due_date を算出
 * - モニタリング作成時: 次回モニタリング期日を更新
 * - 期限が近い計画/モニタリングを抽出 (リマインド用)
 *
 * 設計仕様:
 * - サービス種別に関係なく、計画は 1 年有効、モニタリングは 6 ヶ月ごと
 *   (就労系は実質 6 ヶ月見直しが慣例だが法令上は最低 1 年)
 * - 既存計画があれば cycle_number は前回 + 1
 * - 期間: created_date を起点に 12 ヶ月
 * - 次回モニタリング: created_date + 6 ヶ月
 * - 次期計画: created_date + 12 ヶ月
 */
class PlanCycleService
{
    /**
     * 新規計画作成時に呼び出して cycle_number / 期間 / 期日を補完する。
     *
     * @param  array<string, mixed>  $attributes  計画属性 (created_date が必須)
     * @return array<string, mixed>  cycle 関連を埋めた属性
     */
    public function fillCycleFields(array $attributes, int $studentId): array
    {
        $createdDate = $attributes['created_date'] ?? null;
        if (! $createdDate) {
            return $attributes;
        }

        $base = Carbon::parse($createdDate);

        // cycle_number: 既存の最新値 + 1 (既存がなければ 1)
        if (empty($attributes['cycle_number'])) {
            $maxCycle = IndividualSupportPlan::where('student_id', $studentId)
                ->whereNotNull('cycle_number')
                ->max('cycle_number');
            $attributes['cycle_number'] = ($maxCycle ?? 0) + 1;
        }

        // 期間: 12 ヶ月
        if (empty($attributes['plan_period_start'])) {
            $attributes['plan_period_start'] = $base->copy()->toDateString();
        }
        if (empty($attributes['plan_period_end'])) {
            $attributes['plan_period_end'] = $base->copy()->addYear()->subDay()->toDateString();
        }

        // 次回モニタリング: 6 ヶ月後
        if (empty($attributes['next_monitoring_due_date'])) {
            $attributes['next_monitoring_due_date'] = $base->copy()->addMonths(6)->toDateString();
        }

        // 次期計画: 12 ヶ月後
        if (empty($attributes['next_plan_due_date'])) {
            $attributes['next_plan_due_date'] = $base->copy()->addYear()->toDateString();
        }

        return $attributes;
    }

    /**
     * モニタリング作成後に、対応する計画の next_monitoring_due_date を更新する。
     */
    public function bumpNextMonitoringDate(MonitoringRecord $monitoring): void
    {
        if (! $monitoring->plan_id) return;
        $plan = IndividualSupportPlan::find($monitoring->plan_id);
        if (! $plan) return;
        $base = Carbon::parse($monitoring->monitoring_date);
        $plan->update([
            'next_monitoring_due_date' => $base->copy()->addMonths(6)->toDateString(),
        ]);
    }

    /**
     * 期日が近い (or 過ぎた) 計画・モニタリングを抽出する。
     *
     * @return array{plans_due: \Illuminate\Support\Collection, monitorings_due: \Illuminate\Support\Collection}
     */
    public function dueSoon(int $classroomId, int $daysAhead = 30): array
    {
        $today = Carbon::today();
        $cutoff = $today->copy()->addDays($daysAhead);

        // LOGIC-06 修正: 「次期計画が既に作成済みの古い計画」を除外する。
        // 旧実装は is_official=true かつ next_plan_due_date<=cutoff を全件返すため、
        // 次期計画を作成済みでも古い計画の期日が拾われ、スタッフに「対応済みなのに
        // 期限切れ通知が消えない」状態を生んでいた (通知疲れで本当の期限切れを見落とす)。
        // 同一児童について、より新しい created_date の official 計画が存在する計画は
        // 「既に次期へ移行済み」とみなして除外する。
        $supersededExpr = function ($query) {
            // 同じ student_id でより新しい official 計画が存在しないこと
            $query->whereNotExists(function ($sub) {
                $sub->selectRaw(1)
                    ->from('individual_support_plans as newer')
                    ->whereColumn('newer.student_id', 'individual_support_plans.student_id')
                    ->where('newer.is_official', true)
                    ->whereColumn('newer.created_date', '>', 'individual_support_plans.created_date');
            });
        };

        $plansDue = IndividualSupportPlan::query()
            ->where('classroom_id', $classroomId)
            ->where('is_official', true)
            ->whereNotNull('next_plan_due_date')
            ->whereDate('next_plan_due_date', '<=', $cutoff)
            ->where($supersededExpr)
            ->orderBy('next_plan_due_date')
            ->with('student:id,student_name')
            ->get();

        $monitoringsDue = IndividualSupportPlan::query()
            ->where('classroom_id', $classroomId)
            ->where('is_official', true)
            ->whereNotNull('next_monitoring_due_date')
            ->whereDate('next_monitoring_due_date', '<=', $cutoff)
            ->where($supersededExpr)
            ->orderBy('next_monitoring_due_date')
            ->with('student:id,student_name')
            ->get();

        return [
            'plans_due'       => $plansDue,
            'monitorings_due' => $monitoringsDue,
        ];
    }
}
