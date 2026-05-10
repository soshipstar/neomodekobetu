<?php

namespace App\Services;

use App\Models\Classroom;
use App\Models\DailyRecord;
use App\Models\Student;
use App\Models\StudentRecord;
use Illuminate\Support\Carbon;

/**
 * 国保連請求 (障害福祉サービス) 用 CSV / 集計を生成する。
 *
 * 注: 本実装は WAM-NET 標準インターフェース (障害福祉サービス費等) の
 *     完全準拠ではなく、実地指導で求められる主要項目を簡易 CSV として
 *     出力するものである。本番運用では各自治体の最新仕様書 (国保連合会)
 *     を確認し、必要に応じてカラム数・コードを更新すること。
 */
class BillingService
{
    /** サービス種別コード (障害福祉サービス) */
    public const SERVICE_CODES = [
        'after_school'  => '63', // 放課後等デイサービス
        'employment_a'  => '15', // 就労継続支援A型
        'employment_b'  => '16', // 就労継続支援B型
        'transition'    => '14', // 就労移行支援
    ];

    /**
     * 月次の請求集計データを利用者単位で生成する。
     *
     * @return array<int, array<string, mixed>>
     */
    public function buildMonthlyBilling(int $classroomId, string $yearMonth): array
    {
        $classroom = Classroom::findOrFail($classroomId);
        [$from, $to] = $this->monthRange($yearMonth);

        $students = Student::where('classroom_id', $classroomId)
            ->where('is_active', true)
            ->orderBy('id')
            ->get();

        $rows = [];
        foreach ($students as $student) {
            // 月内の出勤日 (DailyRecord ベース)
            $dailyRecords = DailyRecord::where('classroom_id', $classroomId)
                ->whereBetween('record_date', [$from->toDateString(), $to->toDateString()])
                ->whereHas('studentRecords', fn ($q) => $q->where('student_id', $student->id))
                ->orderBy('record_date')
                ->get();

            $usageDays = $dailyRecords->count();
            if ($usageDays === 0) continue;

            $unitsPerDay = $this->dailyUnits($classroom->service_type ?? 'after_school');
            $totalUnits = $usageDays * $unitsPerDay;

            // 単位単価 (1単位 = 10円換算で簡略化、実際は地域区分で変動)
            $unitPrice = 10.0;
            $totalAmount = (int) round($totalUnits * $unitPrice);

            // 公費負担 (90%) と 利用者負担 (10%, 上限あり)
            $publicShareBeforeCap = (int) round($totalAmount * 0.9);
            $userCopayBeforeCap = $totalAmount - $publicShareBeforeCap;
            $cap = $student->monthly_copay_cap ?? 0;
            $userCopay = $cap > 0 ? min($userCopayBeforeCap, $cap) : $userCopayBeforeCap;
            $publicShare = $totalAmount - $userCopay;

            $rows[] = [
                'student_id'                => $student->id,
                'student_name'               => $student->student_name,
                'beneficiary_number'         => $student->beneficiary_number ?? '未登録',
                'municipality_code'          => $student->municipality_code ?? '',
                'disability_category'        => $student->disability_category ?? '',
                'disability_grade'           => $student->disability_grade ?? '',
                'service_code'               => self::SERVICE_CODES[$classroom->service_type ?? 'after_school'] ?? '63',
                'service_label'              => $this->serviceLabel($classroom->service_type),
                'usage_days'                 => $usageDays,
                'monthly_usage_days_cap'     => $student->monthly_usage_days_cap ?? null,
                'total_units'                => $totalUnits,
                'unit_price'                 => $unitPrice,
                'total_amount'               => $totalAmount,
                'public_share'               => $publicShare,
                'user_copay'                 => $userCopay,
                'monthly_copay_cap'          => $cap,
                'copay_management_provider'  => $student->copay_management_provider ?? null,
                'usage_dates'                => $dailyRecords->pluck('record_date')->map(fn ($d) => $d->toDateString())->all(),
            ];
        }

        return $rows;
    }

    /**
     * 月次集計の合計を返す。
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<string, mixed>
     */
    public function summarize(array $rows): array
    {
        return [
            'student_count'       => count($rows),
            'total_usage_days'    => array_sum(array_column($rows, 'usage_days')),
            'total_units'         => array_sum(array_column($rows, 'total_units')),
            'total_amount'        => array_sum(array_column($rows, 'total_amount')),
            'total_public_share'  => array_sum(array_column($rows, 'public_share')),
            'total_user_copay'    => array_sum(array_column($rows, 'user_copay')),
        ];
    }

    /**
     * 国保連請求用 CSV を生成 (UTF-8 BOM 付き、Excel で開ける形式)。
     */
    public function generateCsv(int $classroomId, string $yearMonth): string
    {
        $classroom = Classroom::findOrFail($classroomId);
        $rows = $this->buildMonthlyBilling($classroomId, $yearMonth);

        $headers = [
            '事業所名', 'サービスコード', 'サービス種別',
            '利用者番号', '利用者氏名', '受給者証番号', '支給市町村コード',
            '障害種別', '障害支援区分',
            '提供月', '利用日数', '月利用日数上限',
            '合計単位数', '単位単価', '総費用',
            '公費負担額', '利用者負担額', '月額上限額', '上限管理事業所',
        ];

        $output = "\xEF\xBB\xBF"; // UTF-8 BOM
        $output .= $this->csvLine($headers);

        foreach ($rows as $r) {
            $output .= $this->csvLine([
                $classroom->classroom_name,
                $r['service_code'],
                $r['service_label'],
                (string) $r['student_id'],
                $r['student_name'],
                $r['beneficiary_number'],
                $r['municipality_code'],
                $this->labelDisabilityCategory($r['disability_category']),
                $r['disability_grade'],
                $yearMonth,
                (string) $r['usage_days'],
                (string) ($r['monthly_usage_days_cap'] ?? ''),
                (string) $r['total_units'],
                (string) $r['unit_price'],
                (string) $r['total_amount'],
                (string) $r['public_share'],
                (string) $r['user_copay'],
                (string) ($r['monthly_copay_cap'] ?? ''),
                (string) ($r['copay_management_provider'] ?? ''),
            ]);
        }

        return $output;
    }

    /**
     * @param  string[]  $cells
     */
    private function csvLine(array $cells): string
    {
        $escaped = array_map(function ($v) {
            $s = (string) ($v ?? '');
            // 特殊文字を含む場合のみダブルクオート
            if (preg_match('/[",\n\r]/', $s)) {
                $s = '"' . str_replace('"', '""', $s) . '"';
            }
            return $s;
        }, $cells);
        return implode(',', $escaped) . "\r\n";
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function monthRange(string $yearMonth): array
    {
        $base = Carbon::createFromFormat('Y-m', $yearMonth)->startOfMonth();
        return [$base->copy(), $base->copy()->endOfMonth()];
    }

    /**
     * サービス種別ごとの 1 日あたり単位数 (簡略).
     * 実際は加算 (送迎/食事/福祉専門職員配置等) で変動する。
     */
    private function dailyUnits(string $serviceType): int
    {
        return match ($serviceType) {
            'after_school' => 660,  // 放デイ Ⅰ (3時間以上)
            'employment_a' => 580,  // 就労 A 7.5時間以上
            'employment_b' => 580,  // 就労 B 7.5時間以上
            'transition'   => 800,  // 就労移行 (定員 20 人以下)
            default        => 600,
        };
    }

    private function serviceLabel(?string $serviceType): string
    {
        return match ($serviceType) {
            'after_school' => '放課後等デイサービス',
            'employment_a' => '就労継続支援A型',
            'employment_b' => '就労継続支援B型',
            'transition'   => '就労移行支援',
            default        => 'その他',
        };
    }

    private function labelDisabilityCategory(?string $code): string
    {
        return match ($code) {
            'intellectual'   => '知的',
            'physical'       => '身体',
            'mental'         => '精神',
            'developmental'  => '発達',
            'dual'           => '重複',
            default          => $code ?? '',
        };
    }
}
