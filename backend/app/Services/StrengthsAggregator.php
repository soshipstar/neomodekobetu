<?php

namespace App\Services;

use App\Models\StudentRecord;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;

/**
 * 連絡帳 (student_records.strengths) の集計サービス。
 * 期間内の生徒記録から強み(才能)チェックスコアを取り出し、日/週/月別の平均と
 * 全期間平均・トレンド・変化量を算出する。
 *
 * 旧アプリ syuro26 の aggregatePeriodTrends に相当する処理を PHP で再実装する。
 */
class StrengthsAggregator
{
    /**
     * 強み項目 → 5 領域 のマッピング。モニタリング表示や PDF 出力でドメイン名を併記する用途。
     * syuro26 の domainMapping と同じ。
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
            ->whereNotNull('strengths')
            ->with(['dailyRecord:id,record_date']);

        if ($from) {
            $query->whereHas('dailyRecord', fn ($q) => $q->where('record_date', '>=', $from->toDateString()));
        }
        if ($to) {
            $query->whereHas('dailyRecord', fn ($q) => $q->where('record_date', '<=', $to->toDateString()));
        }

        $records = $query->get();

        // record_date を含めて (record, date) のペアにする。dailyRecord が無いケースは除外。
        $samples = [];
        foreach ($records as $record) {
            $date = $record->dailyRecord?->record_date;
            if (!$date) {
                continue;
            }
            $strengths = $record->strengths;
            if (!is_array($strengths) || $strengths === []) {
                continue;
            }
            $samples[] = [
                'date'      => $date instanceof Carbon ? $date->toImmutable() : CarbonImmutable::parse($date),
                'strengths' => $strengths,
            ];
        }

        return [
            'from'         => $from?->toDateString() ?? '',
            'to'           => $to?->toDateString() ?? '',
            'record_count' => count($samples),
            'trends'       => $this->buildTrends($samples),
        ];
    }

    /**
     * @param  array<int, array{date: CarbonImmutable, strengths: array<string, int>}>  $samples
     * @return array<int, array<string, mixed>>
     */
    private function buildTrends(array $samples): array
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

        // ラベル一覧 (STRENGTH_KEYS の正規順を優先)
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
        foreach (StudentRecord::STRENGTH_KEYS as $key) {
            if (isset($labelSet[$key])) {
                $orderedLabels[] = $key;
                unset($labelSet[$key]);
            }
        }
        // 想定外ラベルは末尾に
        foreach (array_keys($labelSet) as $extra) {
            $orderedLabels[] = $extra;
        }

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
                'domain'           => self::DOMAIN_MAPPING[$label] ?? null,
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
     * 集計結果を AI プロンプト/PDF 用の人間可読テキストへ整形する。
     * syuro26 の formatAfterSchoolMetrics の「強み（才能）チェック推移」ブロック相当。
     *
     * @param  array<string, mixed>  $summary  aggregateForStudent() の戻り値
     */
    public function formatAsText(array $summary): string
    {
        $trends = $summary['trends'] ?? [];
        if (empty($trends)) {
            return '【強み（才能）チェック】対象期間に記録なし';
        }

        $lines = [];
        $period = ($summary['from'] ?? '') . ' 〜 ' . ($summary['to'] ?? '');
        $count  = $summary['record_count'] ?? 0;
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

        return implode("\n", $lines);
    }
}
