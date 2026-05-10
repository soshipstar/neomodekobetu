<?php

namespace App\Services;

use App\Models\Student;
use App\Models\StudentRecord;
use App\Models\WagePeriod;
use App\Models\WageRecord;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * 月次工賃を計算する。
 *
 * 設計:
 * - student_records.service_type_data に保存されている work_eligible_hours / work_content / clock_in / clock_out を
 *   月単位で集計し、利用者の wage_calculation_type に応じて支給額を算出する。
 * - 計算結果は wage_records へ upsert する。
 * - WagePeriod が status=finalized なら再計算しない (確定済み)。
 */
class WageCalculationService
{
    /**
     * 指定 classroom × 年月の工賃を全員分計算 (or 再計算) する。
     *
     * @param  int  $classroomId
     * @param  string  $yearMonth  YYYY-MM
     * @return array{period: WagePeriod, records: array<int, WageRecord>}
     */
    public function calculate(int $classroomId, string $yearMonth, ?int $finalizedBy = null): array
    {
        $period = WagePeriod::firstOrCreate(
            ['classroom_id' => $classroomId, 'year_month' => $yearMonth],
            ['status' => WagePeriod::STATUS_DRAFT],
        );

        if ($period->status === WagePeriod::STATUS_FINALIZED || $period->status === WagePeriod::STATUS_PAID) {
            // 確定済みなら何もしない (records は既に確定値)
            return [
                'period'  => $period,
                'records' => $period->records()->with('student')->get()->all(),
            ];
        }

        [$from, $to] = $this->monthRange($yearMonth);
        $students = Student::where('classroom_id', $classroomId)
            ->where('is_active', true)
            ->get();

        $records = [];
        DB::transaction(function () use ($students, $period, $from, $to, &$records) {
            foreach ($students as $student) {
                $records[] = $this->calculateOne($student, $period, $from, $to);
            }
        });

        return ['period' => $period->fresh(), 'records' => $records];
    }

    /**
     * 指定された利用者 1 名分の工賃を計算する。
     */
    public function calculateOne(Student $student, WagePeriod $period, Carbon $from, Carbon $to): WageRecord
    {
        $records = StudentRecord::query()
            ->whereHas('dailyRecord', function ($q) use ($period, $from, $to) {
                $q->where('classroom_id', $period->classroom_id)
                    ->whereBetween('record_date', [$from->toDateString(), $to->toDateString()]);
            })
            ->where('student_id', $student->id)
            ->with('dailyRecord')
            ->get();

        $attendanceDays = $records->count();
        $totalMinutes = 0;
        $eligibleHoursSum = 0.0;

        foreach ($records as $sr) {
            $data = $sr->service_type_data ?? [];
            // 工賃対象時間を優先 (連絡帳で入力した値)
            if (isset($data['wage_eligible_hours']) && is_numeric($data['wage_eligible_hours'])) {
                $eligibleHoursSum += (float) $data['wage_eligible_hours'];
            } else {
                // clock_in / clock_out から推定 (仮: 8 時間で計算)
                $eligibleHoursSum += $this->minutesBetween($data['clock_in'] ?? null, $data['clock_out'] ?? null) / 60;
            }
            $totalMinutes += (int) round($this->minutesBetween($data['clock_in'] ?? null, $data['clock_out'] ?? null));
        }

        $eligibleHours = round($eligibleHoursSum, 2);

        $type        = $student->wage_calculation_type ?? 'hourly';
        $hourlyRate  = (float) ($student->hourly_rate ?? 0);
        $pieceUnit   = (float) ($student->piece_rate_amount ?? 0);

        $baseWage = match ($type) {
            'hourly'     => round($eligibleHours * $hourlyRate),
            'piece_rate' => round($pieceUnit * $attendanceDays), // 簡易 (作業数 = 出勤日数で代用)
            default      => 0,
        };

        $netWage = $baseWage; // 賞与・控除は別途設定

        return WageRecord::updateOrCreate(
            [
                'wage_period_id' => $period->id,
                'student_id'     => $student->id,
            ],
            [
                'attendance_days'     => $attendanceDays,
                'total_work_minutes'  => $totalMinutes,
                'wage_eligible_hours' => $eligibleHours,
                'calculation_type'    => $type,
                'hourly_rate'         => $hourlyRate,
                'piece_rate_amount'   => $pieceUnit,
                'base_wage'           => $baseWage,
                'overtime_minutes'    => 0,
                'overtime_wage'       => 0,
                'bonus'               => 0,
                'deductions'          => 0,
                'net_wage'            => $netWage,
                'calculated_at'       => now(),
            ]
        );
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function monthRange(string $yearMonth): array
    {
        $base = Carbon::createFromFormat('Y-m', $yearMonth)->startOfMonth();
        return [$base->copy(), $base->copy()->endOfMonth()];
    }

    /** "HH:MM" → 分。不正値は 0。 */
    private function minutesBetween(?string $start, ?string $end): int
    {
        if (! $start || ! $end) return 0;
        if (! preg_match('/^\d{2}:\d{2}$/', $start) || ! preg_match('/^\d{2}:\d{2}$/', $end)) return 0;
        try {
            $s = Carbon::createFromFormat('H:i', $start);
            $e = Carbon::createFromFormat('H:i', $end);
            $diff = $e->diffInMinutes($s, false);
            return $diff > 0 ? (int) $diff : 0;
        } catch (\Throwable) {
            return 0;
        }
    }

    /**
     * 月次の事業所平均工賃を返す (B型平均工賃 3,000 円ライン確認用)。
     */
    public function classroomAverageWage(int $classroomId, string $yearMonth): float
    {
        $period = WagePeriod::where('classroom_id', $classroomId)
            ->where('year_month', $yearMonth)->first();
        if (! $period) return 0.0;
        $records = $period->records()->where('attendance_days', '>', 0)->get();
        if ($records->isEmpty()) return 0.0;
        return round($records->avg('net_wage'), 2);
    }
}
