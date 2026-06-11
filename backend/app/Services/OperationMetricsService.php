<?php

namespace App\Services;

use App\Models\Classroom;
use App\Models\DailyRecord;
use App\Models\Student;
use App\Models\StudentRecord;
use Illuminate\Support\Carbon;

/**
 * 事業所運営指標 (稼働率/平均利用率/開所日数 etc) を集計する。
 *
 * 国保連請求の補助情報、実地指導、経営判断のための指標を提供。
 */
class OperationMetricsService
{
    /**
     * 月次運営指標を返す。
     *
     * @return array<string, mixed>
     */
    public function monthly(int $classroomId, string $yearMonth): array
    {
        $classroom = Classroom::findOrFail($classroomId);
        $base = Carbon::createFromFormat('Y-m', $yearMonth)->startOfMonth();
        $from = $base->copy();
        $to = $base->copy()->endOfMonth();

        // 開所日数 (実際に DailyRecord が作成された日数)
        $openingDays = DailyRecord::where('classroom_id', $classroomId)
            ->whereBetween('record_date', [$from->toDateString(), $to->toDateString()])
            ->distinct('record_date')
            ->count('record_date');

        // 在籍利用者数 (LOGIC-09 修正: 集計対象月末時点での在籍数を算出)
        // 旧実装は「今日時点」の is_active/status のスナップショットを返していたため、
        // 過去月の帳票を後日確認すると、その後に退所/入所した分だけ在籍数がズレ、
        // 延べ利用日数との整合が取れず実地指導で指摘されるリスクがあった。
        // 「対象月末までに利用を開始し、対象月初時点でまだ退所していない」を在籍とする。
        $activeStudents = Student::where('classroom_id', $classroomId)
            ->where(function ($q) use ($to) {
                // 支援開始日が対象月末以前 (未設定は対象に含める=旧データ互換)
                $q->whereNull('support_start_date')
                  ->orWhereDate('support_start_date', '<=', $to->toDateString());
            })
            ->where(function ($q) use ($from) {
                // 退所日が対象月初より後、または未退所
                $q->whereNull('withdrawal_date')
                  ->orWhereDate('withdrawal_date', '>=', $from->toDateString());
            })
            ->count();

        // 延べ利用日数 (StudentRecord の件数)
        $totalUsageDays = StudentRecord::query()
            ->whereHas('dailyRecord', function ($q) use ($classroomId, $from, $to) {
                $q->where('classroom_id', $classroomId)
                    ->whereBetween('record_date', [$from->toDateString(), $to->toDateString()]);
            })
            ->count();

        // 1 日平均利用者数
        $avgDailyUsers = $openingDays > 0 ? round($totalUsageDays / $openingDays, 1) : 0;

        // 稼働率 (1 日平均利用者数 / 定員)
        $capacity = $classroom->capacity ?? 0;
        $utilization = $capacity > 0 ? round(($avgDailyUsers / $capacity) * 100, 1) : 0;

        // 月利用日数上限超過の利用者 (国保連請求の月利用日数上限を撤去したため空配列のまま)
        $overCapStudents = [];

        // 過去 6 ヶ月の推移
        $trend = [];
        for ($i = 5; $i >= 0; $i--) {
            $m = $base->copy()->subMonths($i);
            $mFrom = $m->copy()->startOfMonth();
            $mTo = $m->copy()->endOfMonth();
            $monthlyOpening = DailyRecord::where('classroom_id', $classroomId)
                ->whereBetween('record_date', [$mFrom->toDateString(), $mTo->toDateString()])
                ->distinct('record_date')
                ->count('record_date');
            $monthlyTotal = StudentRecord::query()
                ->whereHas('dailyRecord', function ($q) use ($classroomId, $mFrom, $mTo) {
                    $q->where('classroom_id', $classroomId)
                        ->whereBetween('record_date', [$mFrom->toDateString(), $mTo->toDateString()]);
                })
                ->count();
            $monthlyAvg = $monthlyOpening > 0 ? round($monthlyTotal / $monthlyOpening, 1) : 0;
            $monthlyUtil = $capacity > 0 ? round(($monthlyAvg / $capacity) * 100, 1) : 0;
            $trend[] = [
                'year_month'      => $m->format('Y-m'),
                'opening_days'    => $monthlyOpening,
                'total_usage'     => $monthlyTotal,
                'avg_daily_users' => $monthlyAvg,
                'utilization'     => $monthlyUtil,
            ];
        }

        return [
            'classroom_id'          => $classroom->id,
            'classroom_name'        => $classroom->classroom_name,
            'service_type'          => $classroom->service_type,
            'capacity'              => $capacity,
            'year_month'            => $yearMonth,
            'opening_days'          => $openingDays,
            'active_students'       => $activeStudents,
            'total_usage_days'      => $totalUsageDays,
            'avg_daily_users'       => $avgDailyUsers,
            'utilization'           => $utilization,
            'over_cap_students'     => $overCapStudents,
            'trend_6_months'        => $trend,
            'minimum_capacity_recommended' => $this->minimumCapacity($classroom->service_type),
        ];
    }

    private function minimumCapacity(?string $serviceType): int
    {
        return match ($serviceType) {
            'after_school' => 10,
            'employment_a' => 10,
            'employment_b' => 20,
            'transition'   => 6,
            default        => 10,
        };
    }
}
