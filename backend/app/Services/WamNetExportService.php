<?php

namespace App\Services;

use App\Models\Classroom;
use App\Models\DailyRecord;
use App\Models\Student;
use App\Models\StudentRecord;
use Illuminate\Support\Carbon;
use ZipArchive;

/**
 * 国保連 (WAM-NET) 障害福祉サービス費等請求情報 CSV のエクスポート。
 *
 * 出力ファイル (Shift-JIS、CRLF):
 *   - 請求書情報.csv       (レコード種別 21)
 *   - 明細書情報.csv       (レコード種別 41 / 42 — 基本 / 上限管理結果)
 *   - 実績記録票情報.csv   (レコード種別 71 / 72 — 基本 / 日次提供記録)
 *
 * これら 3 ファイルを 1 つの ZIP にまとめてダウンロード可能にする。
 *
 * 注: WAM-NET の最新仕様 (障害福祉サービス費等請求情報インターフェース仕様) は
 *     毎年度報酬改定で一部更新される。本実装は 2024 年度報酬改定後の標準的な
 *     レコード構造に準拠している。提出前に必ず国保連の最新仕様書および
 *     都道府県の独自要件を確認すること。
 */
class WamNetExportService
{
    /** サービス種類コード (2桁) */
    public const SERVICE_KIND_CODE = [
        'after_school' => '63',
        'employment_a' => '15',
        'employment_b' => '16',
        'transition'   => '14',
    ];

    /** 既定のサービスコード (6桁) — 加算なしの基本単位
     *  classroom.wam_service_code_default が未設定時に使用される。
     *  (実際の事業所では加算込みのコードが多数あるため、要 classroom 設定で上書き) */
    public const DEFAULT_SERVICE_CODE = [
        'after_school' => '636111',  // 児童発達支援/放課後等デイサービスⅠ
        'employment_a' => '151111',  // 就労継続支援A型サービス費(I)(1)
        'employment_b' => '161111',  // 就労継続支援B型サービス費(I)
        'transition'   => '141111',  // 就労移行支援サービス費(I)
    ];

    /**
     * 3 ファイルを ZIP にまとめて返す。
     *
     * @return string  生成された ZIP ファイルのフルパス
     */
    public function generateBundle(int $classroomId, string $yearMonth): string
    {
        $classroom = Classroom::findOrFail($classroomId);
        [$from, $to] = $this->monthRange($yearMonth);

        $usages = $this->collectUsages($classroom, $from, $to);

        // 一時ディレクトリに 3 ファイル書き出し
        $tmpDir = storage_path('app/tmp/wamnet-' . uniqid('', true));
        if (! is_dir($tmpDir)) mkdir($tmpDir, 0700, true);

        $billFile  = $tmpDir . '/請求書情報.csv';
        $detailFile = $tmpDir . '/明細書情報.csv';
        $recordFile = $tmpDir . '/実績記録票情報.csv';

        file_put_contents($billFile,   $this->toSjis($this->buildInvoiceCsv($classroom, $usages, $yearMonth)));
        file_put_contents($detailFile, $this->toSjis($this->buildDetailCsv($classroom, $usages, $yearMonth)));
        file_put_contents($recordFile, $this->toSjis($this->buildProvisionRecordCsv($classroom, $usages, $yearMonth)));

        $zipPath = storage_path("app/tmp/wamnet-{$classroomId}-{$yearMonth}.zip");
        if (file_exists($zipPath)) unlink($zipPath);

        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE) === true) {
            // Shift-JIS のファイル名は ZIP 内ではバイナリでそのまま保持
            $zip->addFile($billFile,   '請求書情報.csv');
            $zip->addFile($detailFile, '明細書情報.csv');
            $zip->addFile($recordFile, '実績記録票情報.csv');
            $zip->close();
        }

        // クリーンアップ用に一時ファイル削除はコントローラ側で
        return $zipPath;
    }

    /**
     * 必須項目が揃っているかの事前バリデーション。UI から呼ぶ。
     *
     * @return string[]  エラーメッセージの配列 (空なら問題なし)
     */
    public function validate(int $classroomId, string $yearMonth): array
    {
        $errors = [];
        $classroom = Classroom::find($classroomId);
        if (! $classroom) {
            return ['事業所が見つかりません。'];
        }
        if (! $classroom->wam_office_code || strlen($classroom->wam_office_code) !== 10) {
            $errors[] = '事業所番号 (10桁) が設定されていません。事業所設定画面で登録してください。';
        }
        if (! $classroom->prefecture_code || strlen($classroom->prefecture_code) !== 2) {
            $errors[] = '都道府県コード (2桁) が設定されていません。';
        }
        if (! $classroom->wam_service_code_default || strlen($classroom->wam_service_code_default) !== 6) {
            $errors[] = '主使用サービスコード (6桁) が設定されていません。';
        }

        // 受給者証番号が未設定の利用者がいるかチェック
        [$from, $to] = $this->monthRange($yearMonth);
        $missing = StudentRecord::query()
            ->whereHas('dailyRecord', function ($q) use ($classroomId, $from, $to) {
                $q->where('classroom_id', $classroomId)
                  ->whereBetween('record_date', [$from->toDateString(), $to->toDateString()]);
            })
            ->whereHas('student', function ($q) {
                $q->whereNull('beneficiary_number')->orWhere('beneficiary_number', '');
            })
            ->with('student:id,student_name,beneficiary_number')
            ->get()
            ->pluck('student.student_name')
            ->unique()
            ->values()
            ->all();

        if ($missing) {
            $errors[] = '受給者証番号が未登録の利用者がいます: ' . implode(', ', $missing);
        }

        return $errors;
    }

    // =========================================================================
    // 内部: 1 ヶ月分のデータ集計
    // =========================================================================

    /**
     * 利用者ごとに 1 ヶ月のサービス提供データを集計する。
     *
     * @return array<int, array{student: Student, usage_dates: array<int, string>, total_units: int, total_amount: int, public_share: int, user_copay: int}>
     */
    private function collectUsages(Classroom $classroom, Carbon $from, Carbon $to): array
    {
        $serviceType = $classroom->service_type ?? 'after_school';
        $serviceCode = $classroom->wam_service_code_default ?: self::DEFAULT_SERVICE_CODE[$serviceType];
        $unitPrice   = $classroom->wam_unit_price_yen ?? 10;

        $unitsPerDay = $this->dailyUnits($serviceType);
        $students = Student::where('classroom_id', $classroom->id)->where('is_active', true)->get();

        $result = [];
        foreach ($students as $student) {
            $dailyRecords = DailyRecord::where('classroom_id', $classroom->id)
                ->whereBetween('record_date', [$from->toDateString(), $to->toDateString()])
                ->whereHas('studentRecords', fn ($q) => $q->where('student_id', $student->id))
                ->orderBy('record_date')
                ->get();
            if ($dailyRecords->isEmpty()) continue;

            $usageDates = $dailyRecords->pluck('record_date')->map(fn ($d) => $d->format('Ymd'))->all();
            $usageDays = count($usageDates);
            $totalUnits = $usageDays * $unitsPerDay;
            $totalAmount = (int) round($totalUnits * $unitPrice);
            $userCopayBefore = (int) round($totalAmount * 0.1);
            $cap = $student->monthly_copay_cap ?? 0;
            $userCopay = $cap > 0 ? min($userCopayBefore, $cap) : $userCopayBefore;
            $publicShare = $totalAmount - $userCopay;

            $result[] = [
                'student'        => $student,
                'service_code'   => $serviceCode,
                'usage_dates'    => $usageDates,
                'usage_days'     => $usageDays,
                'units_per_day'  => $unitsPerDay,
                'total_units'    => $totalUnits,
                'unit_price'     => $unitPrice,
                'total_amount'   => $totalAmount,
                'public_share'   => $publicShare,
                'user_copay'     => $userCopay,
            ];
        }
        return $result;
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function monthRange(string $yearMonth): array
    {
        $base = Carbon::createFromFormat('Y-m', $yearMonth)->startOfMonth();
        return [$base->copy(), $base->copy()->endOfMonth()];
    }

    private function dailyUnits(string $serviceType): int
    {
        return match ($serviceType) {
            'after_school' => 660,
            'employment_a' => 580,
            'employment_b' => 580,
            'transition'   => 800,
            default        => 600,
        };
    }

    // =========================================================================
    // 内部: 各 CSV の生成
    // =========================================================================

    /**
     * 請求書情報 (レコード種別 21)。1 ヶ月 1 事業所につき 1 レコード。
     *
     * @param  array<int, mixed>  $usages
     */
    private function buildInvoiceCsv(Classroom $classroom, array $usages, string $yearMonth): string
    {
        $serviceKind = self::SERVICE_KIND_CODE[$classroom->service_type ?? 'after_school'] ?? '63';
        $ymCode = $this->wareki($yearMonth); // 令和 7 年 5 月 → 5070500
        $totalAmount   = array_sum(array_column($usages, 'total_amount'));
        $totalPublic   = array_sum(array_column($usages, 'public_share'));
        $totalCopay    = array_sum(array_column($usages, 'user_copay'));
        $caseCount     = count($usages);

        // レコード種別 21 = 請求書情報
        $row = [
            '21',                                    // レコード種別コード
            $ymCode,                                 // 処理対象年月 (和暦)
            (string) $classroom->prefecture_code,    // 都道府県コード
            (string) $classroom->wam_office_code,    // 事業所番号
            $this->halfWidthKana($classroom->classroom_name), // 事業所名 (半角ｶﾅ)
            $serviceKind,                            // サービス種類コード
            (string) $caseCount,                     // 件数
            (string) $totalAmount,                   // 総費用額 (円)
            (string) $totalPublic,                   // 給付費請求額
            (string) $totalCopay,                    // 利用者負担額
            '',                                      // 予備
        ];
        return $this->csvLine($row);
    }

    /**
     * 明細書情報 (レコード種別 41/42)。利用者ごとに 1 レコード。
     *
     * @param  array<int, mixed>  $usages
     */
    private function buildDetailCsv(Classroom $classroom, array $usages, string $yearMonth): string
    {
        $out = '';
        $serviceKind = self::SERVICE_KIND_CODE[$classroom->service_type ?? 'after_school'] ?? '63';
        $ymCode = $this->wareki($yearMonth);

        foreach ($usages as $u) {
            /** @var Student $student */
            $student = $u['student'];
            // レコード種別 41 = 明細書情報 (基本)
            $out .= $this->csvLine([
                '41',                                                  // レコード種別
                $ymCode,                                                // 処理対象年月
                (string) $classroom->prefecture_code,                   // 都道府県コード
                (string) $classroom->wam_office_code,                   // 事業所番号
                $student->beneficiary_number ?? '',                     // 受給者証番号
                $student->municipality_code ?? '',                      // 支給市町村番号
                $this->halfWidthKana($student->student_name),           // 利用者氏名(半角ｶﾅ)
                $serviceKind,                                           // サービス種類コード
                $u['service_code'],                                     // サービスコード
                (string) $u['usage_days'],                              // サービス提供日数
                (string) $u['total_units'],                             // 単位数合計
                (string) $u['unit_price'],                              // 単位単価
                (string) $u['total_amount'],                            // 総費用額
                (string) ($student->monthly_copay_cap ?? 0),            // 月額負担上限額
                (string) $u['user_copay'],                              // 利用者負担額
                (string) $u['public_share'],                            // 給付費請求額
            ]);
            // レコード種別 42 = 明細書・上限額管理結果 (該当者のみ)
            if (($student->copay_management_provider ?? '') !== '') {
                $out .= $this->csvLine([
                    '42',
                    $ymCode,
                    (string) $classroom->wam_office_code,
                    $student->beneficiary_number ?? '',
                    $student->copay_management_provider === '自事業所' ? '1' : '2', // 1: 自事業所 2: 別事業所
                    (string) ($student->monthly_copay_cap ?? 0),
                    (string) $u['user_copay'],
                ]);
            }
        }
        return $out;
    }

    /**
     * 実績記録票情報 (レコード種別 71/72)。基本 1 + 日次 N レコード。
     *
     * @param  array<int, mixed>  $usages
     */
    private function buildProvisionRecordCsv(Classroom $classroom, array $usages, string $yearMonth): string
    {
        $out = '';
        $serviceKind = self::SERVICE_KIND_CODE[$classroom->service_type ?? 'after_school'] ?? '63';
        $ymCode = $this->wareki($yearMonth);

        foreach ($usages as $u) {
            /** @var Student $student */
            $student = $u['student'];

            // レコード種別 71 = 実績記録票 (基本)
            $out .= $this->csvLine([
                '71',
                $ymCode,
                (string) $classroom->wam_office_code,
                $student->beneficiary_number ?? '',
                $this->halfWidthKana($student->student_name),
                $serviceKind,
                (string) $u['usage_days'],
                (string) $u['total_units'],
            ]);

            // レコード種別 72 = 実績記録票・日別 (提供日ごと 1 レコード)
            foreach ($u['usage_dates'] as $date) {
                $out .= $this->csvLine([
                    '72',
                    $ymCode,
                    (string) $classroom->wam_office_code,
                    $student->beneficiary_number ?? '',
                    $date,                       // 提供年月日 (YYYYMMDD)
                    $u['service_code'],          // サービスコード
                    (string) $u['units_per_day'], // 当日単位数
                    '1',                         // サービス提供フラグ (1: 提供, 0: 欠席)
                ]);
            }
        }
        return $out;
    }

    // =========================================================================
    // 内部: 文字コード / 整形ヘルパ
    // =========================================================================

    /**
     * @param  string[]  $cells
     */
    private function csvLine(array $cells): string
    {
        $escaped = array_map(function ($v) {
            $s = (string) ($v ?? '');
            if (preg_match('/[",\r\n]/', $s)) {
                $s = '"' . str_replace('"', '""', $s) . '"';
            }
            return $s;
        }, $cells);
        return implode(',', $escaped) . "\r\n";
    }

    /**
     * UTF-8 → Shift-JIS 変換 (WAM-NET 要件)。
     */
    private function toSjis(string $utf8): string
    {
        $sjis = @mb_convert_encoding($utf8, 'SJIS-win', 'UTF-8');
        return $sjis === false ? $utf8 : $sjis;
    }

    /**
     * 全角カナ → 半角カナ (WAM-NET 仕様で氏名は半角)。
     * 漢字はそのまま (Shift-JIS で表現可能)。
     */
    private function halfWidthKana(string $text): string
    {
        return mb_convert_kana($text, 'kh', 'UTF-8');
    }

    /**
     * "2026-05" → "5080500" (令和8年5月) のような和暦表現。
     * 元号は 5=令和 として 7桁: 元号(1) + 年(2) + 月(2) + 日(2)。
     * 月単位処理は日 "00" を使う。
     */
    private function wareki(string $yearMonth): string
    {
        $parts = explode('-', $yearMonth);
        $year  = (int) $parts[0];
        $month = (int) ($parts[1] ?? 1);
        $waYear = $year - 2018;   // 令和元年 = 2019
        return sprintf('5%02d%02d00', $waYear, $month);
    }
}
