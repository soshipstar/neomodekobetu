<?php

namespace App\Services;

use App\Models\Student;
use App\Models\StudentRecord;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;

/**
 * 連絡帳 (student_records.strengths) の集計サービス。
 * 期間内の生徒記録から強み(才能)チェックスコアを取り出し、日/週/月別の平均と
 * 全期間平均・トレンド・変化量を算出する。
 *
 * 旧アプリ syuro26 の aggregatePeriodTrends に相当する処理を PHP で再実装する。
 *
 * Phase H-1: 強み項目とドメインマッピングは生徒の所属事業所のサービス種別
 * (after_school / employment_a / employment_b / transition) で動的に決定する。
 * 既定はサービス種別が取得できなかった場合の後方互換として after_school。
 */
class StrengthsAggregator
{
    /**
     * 後方互換用: 旧コードが参照する放デイ向けマッピング。
     * 新規参照は ServiceTypeRegistry::strengthDomainMapping() を経由すること。
     *
     * @deprecated H-1 以降は ServiceTypeRegistry::strengthDomainMapping() を使用
     */
    public const DOMAIN_MAPPING = [
        '集中力'                 => '認知・行動',
        '持続力'                 => '認知・行動',
        '丁寧さ'                 => '運動・感覚',
        '発想力'                 => '認知・行動',
        '観察力'                 => '認知・行動',
        '思いやり'               => '人間関係・社会性',
        '情報処理の速さ'         => '認知・行動',
        '手先の器用さ'           => '運動・感覚',
        '自分で選ぶ力'           => '健康・生活',
        'コミュニケーションの工夫' => '言語・コミュニケーション',
    ];

    /**
     * 指定期間における生徒の強み集計を返す。
     *
     * @return array{
     *   from: string,
     *   to: string,
     *   record_count: int,
     *   trends: array<int, array{
     *     label: string,
     *     domain: string|null,
     *     daily_averages: array<string, float>,
     *     weekly_averages: array<string, float>,
     *     monthly_averages: array<string, float>,
     *     overall_average: float,
     *     trend: string,
     *     change: float,
     *   }>
     * }
     */
    public function aggregateForStudent(int $studentId, ?Carbon $from, ?Carbon $to): array
    {
        $query = StudentRecord::query()
            ->where('student_id', $studentId)
            ->with(['dailyRecord:id,record_date']);

        if ($from) {
            $query->whereHas('dailyRecord', fn ($q) => $q->where('record_date', '>=', $from->toDateString()));
        }
        if ($to) {
            $query->whereHas('dailyRecord', fn ($q) => $q->where('record_date', '<=', $to->toDateString()));
        }

        $records = $query->get();

        // record_date を含めて (record, date, strengths, service_type_data) のサンプルにする。
        $strengthSamples    = []; // strengths が入っているもののみ (trends 用)
        $allSamples         = []; // 期間内のすべてのレコード (employment/transition 用)
        foreach ($records as $record) {
            $date = $record->dailyRecord?->record_date;
            if (!$date) {
                continue;
            }
            $immutable = $date instanceof Carbon ? $date->toImmutable() : CarbonImmutable::parse($date);
            $svcData = is_array($record->service_type_data) ? $record->service_type_data : [];

            $allSamples[] = [
                'date'              => $immutable,
                'service_type_data' => $svcData,
            ];

            $strengths = $record->strengths;
            if (is_array($strengths) && $strengths !== []) {
                $strengthSamples[] = [
                    'date'      => $immutable,
                    'strengths' => $strengths,
                ];
            }
        }

        // 生徒の所属事業所のサービス種別を取得 (label 順序とドメイン併記に使用)
        $serviceType = $this->resolveServiceType($studentId);

        $result = [
            'from'         => $from?->toDateString() ?? '',
            'to'           => $to?->toDateString() ?? '',
            'service_type' => $serviceType,
            'record_count' => count($strengthSamples),
            'trends'       => $this->buildTrends($strengthSamples, $serviceType),
        ];

        // サービス種別固有メトリクスを追加 (Phase J-2)
        if ($serviceType === ServiceTypeRegistry::EMPLOYMENT_A || $serviceType === ServiceTypeRegistry::EMPLOYMENT_B) {
            $result['employment_metrics'] = $this->aggregateEmploymentMetrics($allSamples, $from, $to);
        } elseif ($serviceType === ServiceTypeRegistry::TRANSITION) {
            $result['transition_metrics'] = $this->aggregateTransitionMetrics($allSamples);
        }

        return $result;
    }

    /**
     * 生徒の所属事業所からサービス種別を解決する。
     * 解決できない場合は after_school にフォールバック。
     */
    private function resolveServiceType(int $studentId): string
    {
        $serviceType = Student::query()
            ->where('students.id', $studentId)
            ->join('classrooms', 'classrooms.id', '=', 'students.classroom_id')
            ->value('classrooms.service_type');

        return ServiceTypeRegistry::isValid((string) $serviceType)
            ? (string) $serviceType
            : ServiceTypeRegistry::AFTER_SCHOOL;
    }

    /**
     * @param  array<int, array{date: CarbonImmutable, strengths: array<string, int>}>  $samples
     * @return array<int, array<string, mixed>>
     */
    private function buildTrends(array $samples, string $serviceType): array
    {
        if ($samples === []) {
            return [];
        }

        // 粒度別 bucket: granularity => key => label => values[]
        $buckets = ['day' => [], 'week' => [], 'month' => []];

        foreach ($samples as $sample) {
            foreach (['day', 'week', 'month'] as $granularity) {
                $key = $this->periodKey($sample['date'], $granularity);
                foreach ($sample['strengths'] as $label => $value) {
                    if (!is_numeric($value)) {
                        continue;
                    }
                    $buckets[$granularity][$key][$label][] = (float) $value;
                }
            }
        }

        // ラベル一覧 (サービス種別ごとの正規順を優先)
        $labelSet = [];
        foreach ($buckets['month'] as $byLabel) {
            foreach (array_keys($byLabel) as $label) {
                $labelSet[$label] = true;
            }
        }
        if ($labelSet === []) {
            return [];
        }

        $orderedLabels = [];
        foreach (ServiceTypeRegistry::strengthKeys($serviceType) as $key) {
            if (isset($labelSet[$key])) {
                $orderedLabels[] = $key;
                unset($labelSet[$key]);
            }
        }
        // 想定外ラベル (種別変更や旧データ) は末尾に
        foreach (array_keys($labelSet) as $extra) {
            $orderedLabels[] = $extra;
        }

        $domainMap = ServiceTypeRegistry::strengthDomainMapping($serviceType);
        $trends = [];
        foreach ($orderedLabels as $label) {
            $daily   = $this->extractAverages($buckets['day'], $label);
            $weekly  = $this->extractAverages($buckets['week'], $label);
            $monthly = $this->extractAverages($buckets['month'], $label);

            $allValues = [];
            foreach ($buckets['month'] as $byLabel) {
                if (isset($byLabel[$label])) {
                    foreach ($byLabel[$label] as $v) {
                        $allValues[] = $v;
                    }
                }
            }
            if ($allValues === []) {
                continue;
            }

            $overall = $this->roundedAvg($allValues);

            $monthKeys = array_keys($monthly);
            sort($monthKeys);
            $first = $monthly[$monthKeys[0]] ?? $overall;
            $last  = $monthly[$monthKeys[count($monthKeys) - 1]] ?? $overall;
            $change = round($last - $first, 1);
            $trend  = $change >= 1.0 ? 'up' : ($change <= -1.0 ? 'down' : 'stable');

            $trends[] = [
                'label'            => $label,
                'domain'           => $domainMap[$label] ?? null,
                'daily_averages'   => $daily,
                'weekly_averages'  => $weekly,
                'monthly_averages' => $monthly,
                'overall_average'  => $overall,
                'trend'            => $trend,
                'change'           => $change,
            ];
        }

        return $trends;
    }

    /**
     * @param  array<string, array<string, array<int, float>>>  $bucket
     * @return array<string, float>
     */
    private function extractAverages(array $bucket, string $label): array
    {
        $keys = array_keys($bucket);
        sort($keys);
        $result = [];
        foreach ($keys as $k) {
            if (!isset($bucket[$k][$label])) {
                continue;
            }
            $values = $bucket[$k][$label];
            if ($values === []) {
                continue;
            }
            $result[$k] = $this->roundedAvg($values);
        }
        return $result;
    }

    /**
     * @param  array<int, float>  $values
     */
    private function roundedAvg(array $values): float
    {
        if ($values === []) {
            return 0.0;
        }
        return round(array_sum($values) / count($values), 1);
    }

    private function periodKey(CarbonImmutable $date, string $granularity): string
    {
        return match ($granularity) {
            // 日: YYYY-MM-DD
            'day'   => $date->toDateString(),
            // 週: 月曜日基点の YYYY-MM-DD
            'week'  => $date->startOfWeek(CarbonImmutable::MONDAY)->toDateString(),
            // 月: YYYY-MM
            default => $date->format('Y-m'),
        };
    }

    /**
     * 就労継続支援A/B 用のメトリクス集計。
     * service_type_data から wage_eligible_hours / clock_in / clock_out /
     * work_content を取り出し、合計・平均・カテゴリ別件数・出勤率を算出する。
     *
     * 出勤率は「期間内営業日 (土日を除く概算) に対する記録件数の比」。
     * syuro26 lib/ai/diary-aggregator.ts の aggregateEmploymentMetrics と同じ。
     *
     * @param  array<int, array{date: CarbonImmutable, service_type_data: array<string, mixed>}>  $samples
     * @return array<string, mixed>
     */
    private function aggregateEmploymentMetrics(array $samples, ?Carbon $from, ?Carbon $to): array
    {
        $totalWageHours    = 0.0;
        $wageHoursCount    = 0;
        $workContentMap    = []; // category => count
        $clockInMinutes    = [];
        $clockOutMinutes   = [];

        foreach ($samples as $sample) {
            $data = $sample['service_type_data'] ?? [];

            $wage = $data['wage_eligible_hours'] ?? null;
            if (is_numeric($wage) && (float) $wage > 0) {
                $totalWageHours += (float) $wage;
                $wageHoursCount++;
            }

            $work = $data['work_content'] ?? null;
            if (is_string($work) && trim($work) !== '') {
                $key = trim($work);
                $workContentMap[$key] = ($workContentMap[$key] ?? 0) + 1;
            }

            $in = $data['clock_in'] ?? null;
            if (is_string($in) && str_contains($in, ':')) {
                [$h, $m] = array_pad(explode(':', $in, 2), 2, '0');
                $minutes = ((int) $h) * 60 + (int) $m;
                if ($minutes >= 0 && $minutes <= 24 * 60) {
                    $clockInMinutes[] = $minutes;
                }
            }

            $out = $data['clock_out'] ?? null;
            if (is_string($out) && str_contains($out, ':')) {
                [$h, $m] = array_pad(explode(':', $out, 2), 2, '0');
                $minutes = ((int) $h) * 60 + (int) $m;
                if ($minutes >= 0 && $minutes <= 24 * 60) {
                    $clockOutMinutes[] = $minutes;
                }
            }
        }

        // 営業日数の概算 (期間日数の 5/7) に対する出勤率
        $attendanceRate = 0.0;
        if ($from && $to) {
            $totalDays   = max(1, $from->diffInDays($to) + 1);
            $businessDays = max(1, (int) round($totalDays * (5 / 7)));
            $attendanceRate = round(min(count($samples) / $businessDays * 100, 100.0), 1);
        }

        return [
            'total_wage_eligible_hours'      => round($totalWageHours, 1),
            'average_wage_eligible_hours'    => $wageHoursCount > 0
                ? round($totalWageHours / $wageHoursCount, 1)
                : 0.0,
            'attendance_rate'                => $attendanceRate,
            'work_content_categories'        => $workContentMap,
            'average_clock_in'               => $clockInMinutes !== []
                ? $this->minutesToTime((int) round(array_sum($clockInMinutes) / count($clockInMinutes)))
                : null,
            'average_clock_out'              => $clockOutMinutes !== []
                ? $this->minutesToTime((int) round(array_sum($clockOutMinutes) / count($clockOutMinutes)))
                : null,
        ];
    }

    /**
     * 就労移行支援 用のメトリクス集計。
     * service_type_data から practice_content / job_search_record /
     * business_manner_score を取り出し、ユニーク化と平均を算出する。
     *
     * @param  array<int, array{date: CarbonImmutable, service_type_data: array<string, mixed>}>  $samples
     * @return array<string, mixed>
     */
    private function aggregateTransitionMetrics(array $samples): array
    {
        $practiceContents = [];
        $jobSearchRecords = [];
        $mannerScores     = [];

        foreach ($samples as $sample) {
            $data = $sample['service_type_data'] ?? [];

            $practice = $data['practice_content'] ?? null;
            if (is_string($practice) && trim($practice) !== '') {
                $practiceContents[] = trim($practice);
            }

            $job = $data['job_search_record'] ?? null;
            if (is_string($job) && trim($job) !== '') {
                $jobSearchRecords[] = trim($job);
            }

            $score = $data['business_manner_score'] ?? null;
            if (is_numeric($score)) {
                $mannerScores[] = (float) $score;
            }
        }

        return [
            'practice_contents' => array_values(array_unique($practiceContents)),
            'job_search_records' => array_values(array_unique($jobSearchRecords)),
            'average_business_manner_score' => $mannerScores !== []
                ? round(array_sum($mannerScores) / count($mannerScores), 1)
                : null,
        ];
    }

    private function minutesToTime(int $minutes): string
    {
        $h = intdiv($minutes, 60);
        $m = $minutes % 60;
        return sprintf('%02d:%02d', $h, $m);
    }

    /**
     * 集計結果を AI プロンプト/PDF 用の人間可読テキストへ整形する。
     * syuro26 の formatAfterSchoolMetrics の「強み（才能）チェック推移」ブロック相当。
     *
     * @param  array<string, mixed>  $summary  aggregateForStudent() の戻り値
     */
    public function formatAsText(array $summary): string
    {
        $lines  = [];
        $period = ($summary['from'] ?? '') . ' 〜 ' . ($summary['to'] ?? '');
        $count  = $summary['record_count'] ?? 0;
        $trends = $summary['trends'] ?? [];

        if (!empty($trends)) {
            $lines[] = "【強み（才能）チェック推移】(期間: {$period} / {$count}件)";

            foreach ($trends as $t) {
                $arrow = match ($t['trend'] ?? 'stable') {
                    'up'   => '↑',
                    'down' => '↓',
                    default => '→',
                };
                $sign = ($t['change'] ?? 0) >= 0 ? '+' : '';
                $monthly = $t['monthly_averages'] ?? [];
                ksort($monthly);
                $monthlyDetail = [];
                foreach ($monthly as $m => $v) {
                    $monthlyDetail[] = "{$m}:{$v}";
                }
                $domainTag = !empty($t['domain']) ? "【{$t['domain']}】" : '';
                $lines[] = sprintf(
                    '- %s%s: 平均%s/10 %s%s%s（%s）',
                    $t['label'] ?? '?',
                    $domainTag,
                    $t['overall_average'] ?? 0,
                    $arrow,
                    $sign,
                    $t['change'] ?? 0,
                    implode(' → ', $monthlyDetail),
                );
            }

            $growing = array_filter($trends, fn ($t) => ($t['trend'] ?? '') === 'up');
            $declining = array_filter($trends, fn ($t) => ($t['trend'] ?? '') === 'down');

            if ($growing !== []) {
                usort($growing, fn ($a, $b) => ($b['change'] ?? 0) <=> ($a['change'] ?? 0));
                $items = array_map(fn ($t) => "{$t['label']}(+{$t['change']})", $growing);
                $lines[] = '- ★成長が顕著: ' . implode('、', $items);
            }
            if ($declining !== []) {
                usort($declining, fn ($a, $b) => ($a['change'] ?? 0) <=> ($b['change'] ?? 0));
                $items = array_map(fn ($t) => "{$t['label']}({$t['change']})", $declining);
                $lines[] = '- ※低下傾向: ' . implode('、', $items);
            }
        }

        // 就労 A/B 用メトリクス
        if (!empty($summary['employment_metrics'])) {
            $em = $summary['employment_metrics'];
            $lines[] = '';
            $lines[] = '【就労メトリクス】';
            $lines[] = "- 工賃対象時間 合計: {$em['total_wage_eligible_hours']}時間 / 1日平均: {$em['average_wage_eligible_hours']}時間";
            $lines[] = "- 出勤率: {$em['attendance_rate']}%";
            if (!empty($em['work_content_categories'])) {
                arsort($em['work_content_categories']);
                $top = array_slice($em['work_content_categories'], 0, 5, true);
                $items = [];
                foreach ($top as $cat => $cnt) {
                    $items[] = "{$cat}({$cnt}回)";
                }
                $lines[] = '- 主な作業内容: ' . implode('、', $items);
            }
            if (!empty($em['average_clock_in']))  $lines[] = "- 平均出勤時刻: {$em['average_clock_in']}";
            if (!empty($em['average_clock_out'])) $lines[] = "- 平均退勤時刻: {$em['average_clock_out']}";
        }

        // 就労移行 用メトリクス
        if (!empty($summary['transition_metrics'])) {
            $tm = $summary['transition_metrics'];
            $lines[] = '';
            $lines[] = '【就労移行メトリクス】';
            if (!empty($tm['practice_contents'])) {
                $lines[] = '- 実習内容: ' . implode('、', array_slice($tm['practice_contents'], 0, 10));
            }
            if (!empty($tm['job_search_records'])) {
                $lines[] = '- 就職活動記録: ' . implode('、', array_slice($tm['job_search_records'], 0, 10));
            }
            if ($tm['average_business_manner_score'] !== null) {
                $lines[] = "- ビジネスマナー評価平均: {$tm['average_business_manner_score']}/5";
            }
        }

        return $lines === []
            ? '【強み（才能）チェック】対象期間に記録なし'
            : implode("\n", $lines);
    }
}
