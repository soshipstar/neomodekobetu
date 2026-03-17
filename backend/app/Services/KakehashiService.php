<?php

namespace App\Services;

use App\Models\IndividualSupportPlan;
use App\Models\KakehashiGuardian;
use App\Models\KakehashiPeriod;
use App\Models\KakehashiStaff;
use App\Models\MonitoringDetail;
use App\Models\MonitoringRecord;
use App\Models\Student;
use App\Models\SupportPlanDetail;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * かけはし自動生成サービス
 *
 * 【重要】日付計算ルール（変更禁止）
 * - 対象期間: 6ヶ月間（start_date ~ end_date）
 * - 初回の対象期間開始日 = 支援開始日
 * - 初回の提出期限 = 支援開始日の1日前
 * - 2回目以降の対象期間開始日 = 前回の終了日の翌日
 * - 2回目以降の提出期限 = 対象期間開始日の1ヶ月前
 * - 終了日 = 開始日から6ヶ月後の前日
 */
class KakehashiService
{
    /**
     * 正しい日付を計算するヘルパー関数
     * このルールは変更禁止！
     *
     * @param Carbon $supportStartDate 支援開始日
     * @param int $periodNumber 期間番号（1から開始）
     * @param Carbon|null $prevEndDate 前回の終了日（2回目以降で必要）
     * @return array ['start_date' => Carbon, 'end_date' => Carbon, 'submission_deadline' => Carbon]
     */
    public function calculateKakehashiDates(Carbon $supportStartDate, int $periodNumber, ?Carbon $prevEndDate = null): array
    {
        if ($periodNumber === 1) {
            // 初回
            $startDate = $supportStartDate->copy();
            $deadline = $supportStartDate->copy()->subDay();
        } else {
            // 2回目以降: 前回終了日の翌日から開始
            $startDate = $prevEndDate->copy()->addDay();
            // 提出期限: 開始日の1ヶ月前
            $deadline = $startDate->copy()->subMonth();
        }

        // 終了日: 開始日から6ヶ月後の前日
        $endDate = $startDate->copy()->addMonths(6)->subDay();

        return [
            'start_date' => $startDate,
            'end_date' => $endDate,
            'submission_deadline' => $deadline,
        ];
    }

    /**
     * 個別支援計画書の期限を計算するヘルパー関数
     * このルールは変更禁止！
     *
     * ルール:
     * - 初回: かけはしの提出期限と同じ（支援開始日の1日前）
     * - 2回目以降: かけはしの提出期限の1ヶ月後
     *
     * @param Carbon $kakehashiDeadline かけはしの提出期限
     * @param int $periodNumber 期間番号（1から開始）
     * @return Carbon 個別支援計画書の提出期限
     */
    public function calculateSupportPlanDeadline(Carbon $kakehashiDeadline, int $periodNumber): Carbon
    {
        $deadline = $kakehashiDeadline->copy();

        if ($periodNumber === 1) {
            // 初回: かけはしの提出期限と同じ
            return $deadline;
        }

        // 2回目以降: かけはしの提出期限の1ヶ月後
        return $deadline->addMonth();
    }

    /**
     * モニタリング表の期限を計算するヘルパー関数
     * このルールは変更禁止！
     *
     * ルール:
     * - モニタリング期限 = 紐づく個別支援計画書の期限の5ヶ月後
     *
     * @param Carbon $supportPlanDeadline 個別支援計画書の提出期限
     * @return Carbon モニタリング表の提出期限
     */
    public function calculateMonitoringDeadline(Carbon $supportPlanDeadline): Carbon
    {
        return $supportPlanDeadline->copy()->addMonths(5);
    }

    /**
     * かけはし期間から期間番号を取得
     *
     * @param int $studentId 生徒ID
     * @param int $periodId かけはし期間ID
     * @return int 期間番号（1から開始）
     */
    public function getKakehashiPeriodNumber(int $studentId, int $periodId): int
    {
        $count = KakehashiPeriod::where('student_id', $studentId)
            ->where('id', '<', $periodId)
            ->count();

        return $count + 1;
    }

    /**
     * かけはし期間から個別支援計画の開始年月を取得
     *
     * @param KakehashiPeriod|null $period かけはし期間データ
     * @return string 個別支援計画開始年月 (例: "2024年4月")
     */
    public function getIndividualSupportPlanStartMonth(?KakehashiPeriod $period): string
    {
        if (!$period || !$period->start_date) {
            return '';
        }

        return $period->start_date->format('Y年n月');
    }

    /**
     * 生徒のかけはし期間を自動生成（新規生徒用）
     *
     * ルール:
     * - 初回: 支援開始日の1日前を提出期限とする、対象期間開始日は支援開始日
     * - 2回目以降: 前回終了日の翌日から6ヶ月間、提出期限は開始日の1ヶ月前
     *
     * @param int $studentId 生徒ID
     * @param string $supportStartDate 支援開始日 (YYYY-MM-DD)
     * @return array 生成されたかけはし期間の配列
     */
    public function generateKakehashiPeriodsForStudent(int $studentId, string $supportStartDate): array
    {
        $generatedPeriods = [];

        // 生徒情報を取得
        $student = Student::find($studentId);

        if (!$student) {
            throw new \Exception("生徒が見つかりません: ID={$studentId}");
        }

        $studentName = $student->student_name;
        $withdrawalDate = $student->withdrawal_date ? Carbon::parse($student->withdrawal_date) : null;
        $supportPlanStartType = $student->support_plan_start_type ?? 'current';

        // support_plan_start_type が 'next' の場合は次回の期間から開始
        // 1回目のかけはしは作成せず、2回目以降から作成する
        // ただし、提出期限が来たら通常通り作成する（autoGenerateNextKakehashiPeriodsで処理）
        if ($supportPlanStartType === 'next') {
            Log::info("Student {$studentId} has support_plan_start_type='next'. Skipping initial kakehashi generation.");
            return $generatedPeriods;
        }

        // 既存のかけはし期間を確認
        $periodCount = KakehashiPeriod::where('student_id', $studentId)->count();

        // すでにかけはしが存在する場合はスキップ
        if ($periodCount > 0) {
            Log::info("Student {$studentId} already has kakehashi periods. Skipping auto-generation.");
            return $generatedPeriods;
        }

        // 生成上限日を計算（本日+1ヶ月）
        $today = Carbon::today();
        $generationLimit = $today->copy()->addMonth();

        $supportStartDateTime = Carbon::parse($supportStartDate);
        $prevEndDate = null;
        $currentPeriodNumber = 1;

        while (true) {
            // 正しい日付を計算
            $dates = $this->calculateKakehashiDates($supportStartDateTime, $currentPeriodNumber, $prevEndDate);

            // 提出期限が生成上限より未来の場合は終了
            if ($dates['submission_deadline']->gt($generationLimit)) {
                Log::info("Kakehashi deadline {$dates['submission_deadline']->toDateString()} is beyond generation limit. Stopping.");
                break;
            }

            // 退所日が設定されている場合、対象期間開始日が退所日以降ならスキップ
            if ($withdrawalDate && $dates['start_date']->gte($withdrawalDate)) {
                Log::info("Kakehashi start_date {$dates['start_date']->toDateString()} is after withdrawal date. Stopping.");
                break;
            }

            // 期間名を設定
            $periodName = "{$currentPeriodNumber}回目かけはし（{$studentName}）";

            // 挿入
            $period = KakehashiPeriod::create([
                'student_id' => $studentId,
                'period_name' => $periodName,
                'start_date' => $dates['start_date']->toDateString(),
                'end_date' => $dates['end_date']->toDateString(),
                'submission_deadline' => $dates['submission_deadline']->toDateString(),
                'is_active' => true,
                'is_auto_generated' => true,
            ]);

            $generatedPeriods[] = [
                'id' => $period->id,
                'period_name' => $periodName,
                'submission_deadline' => $dates['submission_deadline']->toDateString(),
                'type' => "{$currentPeriodNumber}回目",
            ];

            // 保護者・スタッフレコードを作成
            $this->createKakehashiRecordsForPeriod($period->id, $studentId);

            // モニタリングシートを作成
            $this->createMonitoringForPeriod($studentId, $dates['submission_deadline']->toDateString());

            // 次の期間のために終了日を保存
            $prevEndDate = $dates['end_date'];
            $currentPeriodNumber++;
        }

        return $generatedPeriods;
    }

    /**
     * かけはし期間に対応する保護者・スタッフレコードを作成
     *
     * @param int $periodId かけはし期間ID
     * @param int $studentId 生徒ID
     */
    public function createKakehashiRecordsForPeriod(int $periodId, int $studentId): void
    {
        // 保護者かけはしレコードを作成（is_hidden = false を明示的に設定）
        KakehashiGuardian::firstOrCreate(
            ['period_id' => $periodId, 'student_id' => $studentId],
            ['is_hidden' => false]
        );

        // スタッフかけはしレコードを作成（staff_id は NULL で作成、is_hidden = false）
        KakehashiStaff::firstOrCreate(
            ['period_id' => $periodId, 'student_id' => $studentId],
            ['staff_id' => null, 'is_hidden' => false]
        );
    }

    /**
     * 次のかけはし期間を自動生成すべきか確認
     * 次のかけはしの提出期限の1ヶ月前になったら生成する
     *
     * 次の提出期限 = 現在の対象期間終了日の翌日（次の開始日）の1ヶ月前
     * つまり、現在の対象期間終了日の2ヶ月前になったら次を生成
     *
     * @param int $studentId 生徒ID
     * @return bool 生成すべき場合true
     */
    public function shouldGenerateNextKakehashi(int $studentId): bool
    {
        // 最新のかけはし期間を取得（end_date順）
        $latestPeriod = KakehashiPeriod::where('student_id', $studentId)
            ->orderByDesc('end_date')
            ->first();

        if (!$latestPeriod) {
            return false; // かけはし期間が存在しない
        }

        // 次の提出期限 = 現在の終了日の翌日（次の開始日）の1ヶ月前 = 現在の終了日
        // その提出期限の1ヶ月前に生成 = 現在の終了日の1ヶ月前
        $latestEndDate = Carbon::parse($latestPeriod->end_date);
        $oneMonthBeforeEndDate = $latestEndDate->copy()->subMonth();

        $today = Carbon::today();

        return $today->gte($oneMonthBeforeEndDate);
    }

    /**
     * support_plan_start_type='next' の生徒で、初回かけはし生成のタイミングかチェック
     * 次回の期間（初回終了後の期間）の提出期限の1ヶ月前になったら生成
     *
     * @param string $supportStartDate 支援開始日
     * @return bool 生成すべき場合true
     */
    public function shouldGenerateFirstKakehashiForNextType(string $supportStartDate): bool
    {
        $startDate = Carbon::parse($supportStartDate);

        // 初回の仮想的な終了日を計算（支援開始日から6ヶ月後の前日）
        $firstEndDate = $startDate->copy()->addMonths(6)->subDay();

        // 次回（2回目）の提出期限 = 初回終了日の翌日（2回目開始日）の1ヶ月前 = 初回終了日
        // その1ヶ月前に生成 = 初回終了日の1ヶ月前
        $generationTriggerDate = $firstEndDate->copy()->subMonth();

        $today = Carbon::today();

        return $today->gte($generationTriggerDate);
    }

    /**
     * 定期的に次のかけはし期間を自動生成（スケジュール実行）
     * 最新の期限の1ヶ月前になったら次のかけはし期間を生成する
     *
     * @return array 生成されたかけはし期間の情報
     */
    public function autoGenerateNextKakehashiPeriods(): array
    {
        $generatedPeriods = [];

        // 1つ以上のかけはしを持つ全生徒を取得（退所していない、または退所日が未来の生徒のみ）
        $students = Student::active()
            ->whereHas('kakehashiPeriods')
            ->where(function ($query) {
                $query->whereNull('withdrawal_date')
                    ->orWhere('withdrawal_date', '>', Carbon::today()->toDateString());
            })
            ->get();

        foreach ($students as $student) {
            if ($this->shouldGenerateNextKakehashi($student->id)) {
                // 次のかけはし期間を生成
                $newPeriod = $this->generateNextKakehashiPeriod($student->id, $student->student_name);
                if ($newPeriod !== null) {
                    $generatedPeriods[] = $newPeriod;
                }
            }
        }

        // support_plan_start_type='next' でまだかけはしがない生徒も処理
        // 次回の期間（2回目相当）の提出期限が近づいたら、1回目のかけはしから生成
        $studentsWithNext = Student::active()
            ->where('support_plan_start_type', 'next')
            ->whereNotNull('support_start_date')
            ->where(function ($query) {
                $query->whereNull('withdrawal_date')
                    ->orWhere('withdrawal_date', '>', Carbon::today()->toDateString());
            })
            ->whereDoesntHave('kakehashiPeriods')
            ->get();

        foreach ($studentsWithNext as $student) {
            if ($this->shouldGenerateFirstKakehashiForNextType($student->support_start_date)) {
                // 1回目のかけはしから生成を開始
                $newPeriods = $this->generateKakehashiPeriodsForStudentForced($student->id, $student->support_start_date);
                $generatedPeriods = array_merge($generatedPeriods, $newPeriods);
            }
        }

        Log::info('Auto-generated Kakehashi periods', [
            'periods_created' => count($generatedPeriods),
        ]);

        return $generatedPeriods;
    }

    /**
     * support_plan_start_type を無視してかけはし期間を強制生成
     * support_plan_start_type='next' の生徒で、提出期限が来た場合に使用
     *
     * @param int $studentId 生徒ID
     * @param string $supportStartDate 支援開始日
     * @return array 生成されたかけはし期間の配列
     */
    public function generateKakehashiPeriodsForStudentForced(int $studentId, string $supportStartDate): array
    {
        $generatedPeriods = [];

        // 生徒情報を取得
        $student = Student::find($studentId);

        if (!$student) {
            throw new \Exception("生徒が見つかりません: ID={$studentId}");
        }

        $studentName = $student->student_name;
        $withdrawalDate = $student->withdrawal_date ? Carbon::parse($student->withdrawal_date) : null;

        // 既存のかけはし期間を確認
        $periodCount = KakehashiPeriod::where('student_id', $studentId)->count();

        // すでにかけはしが存在する場合はスキップ
        if ($periodCount > 0) {
            Log::info("Student {$studentId} already has kakehashi periods. Skipping forced generation.");
            return $generatedPeriods;
        }

        // 生成上限日を計算（本日+1ヶ月）
        $today = Carbon::today();
        $generationLimit = $today->copy()->addMonth();

        $supportStartDateTime = Carbon::parse($supportStartDate);
        $prevEndDate = null;
        $currentPeriodNumber = 1;

        while (true) {
            // 正しい日付を計算
            $dates = $this->calculateKakehashiDates($supportStartDateTime, $currentPeriodNumber, $prevEndDate);

            // 提出期限が生成上限より未来の場合は終了
            if ($dates['submission_deadline']->gt($generationLimit)) {
                Log::info("Kakehashi deadline {$dates['submission_deadline']->toDateString()} is beyond generation limit. Stopping.");
                break;
            }

            // 退所日が設定されている場合、対象期間開始日が退所日以降ならスキップ
            if ($withdrawalDate && $dates['start_date']->gte($withdrawalDate)) {
                Log::info("Kakehashi start_date {$dates['start_date']->toDateString()} is after withdrawal date. Stopping.");
                break;
            }

            // 期間名を設定
            $periodName = "{$currentPeriodNumber}回目かけはし（{$studentName}）";

            // 挿入
            $period = KakehashiPeriod::create([
                'student_id' => $studentId,
                'period_name' => $periodName,
                'start_date' => $dates['start_date']->toDateString(),
                'end_date' => $dates['end_date']->toDateString(),
                'submission_deadline' => $dates['submission_deadline']->toDateString(),
                'is_active' => true,
                'is_auto_generated' => true,
            ]);

            $generatedPeriods[] = [
                'id' => $period->id,
                'period_name' => $periodName,
                'submission_deadline' => $dates['submission_deadline']->toDateString(),
                'type' => "{$currentPeriodNumber}回目",
            ];

            // 保護者・スタッフレコードを作成
            $this->createKakehashiRecordsForPeriod($period->id, $studentId);

            // モニタリングシートを作成
            $this->createMonitoringForPeriod($studentId, $dates['submission_deadline']->toDateString());

            // 次の期間のために終了日を保存
            $prevEndDate = $dates['end_date'];
            $currentPeriodNumber++;
        }

        Log::info("Forced generation completed for student {$studentId}. Generated " . count($generatedPeriods) . " periods.");
        return $generatedPeriods;
    }

    /**
     * 次のかけはし期間を生成（6ヶ月サイクル）
     *
     * 【重要】日付計算ルール（変更禁止）
     * - 対象期間 = 前回の終了日の翌日から6ヶ月間
     * - 提出期限 = 対象期間開始日の1ヶ月前（個別支援計画の1ヶ月前に提出）
     * - 個別支援計画 = 対象期間の開始月
     *
     * @param int $studentId 生徒ID
     * @param string $studentName 生徒名
     * @return array|null 生成されたかけはし期間の情報、または既に存在する場合はnull
     */
    public function generateNextKakehashiPeriod(int $studentId, string $studentName): ?array
    {
        // 生徒の情報を確認
        $student = Student::find($studentId);
        $withdrawalDate = $student->withdrawal_date ? Carbon::parse($student->withdrawal_date) : null;
        $supportStartDate = $student->support_start_date ? Carbon::parse($student->support_start_date) : null;

        // 最新のかけはし期間を取得（end_date順）
        $latestPeriod = KakehashiPeriod::where('student_id', $studentId)
            ->orderByDesc('end_date')
            ->first();

        if (!$latestPeriod) {
            throw new \Exception("既存のかけはし期間が見つかりません");
        }

        // 期間回数を計算
        $currentCount = KakehashiPeriod::where('student_id', $studentId)->count();
        $nextPeriodNumber = $currentCount + 1;

        // 前回の終了日
        $lastEndDate = Carbon::parse($latestPeriod->end_date);

        // calculateKakehashiDates を使用して正しい日付を計算
        // 注: 2回目以降なので、$supportStartDateは使用しないが、関数の互換性のため渡す
        $dates = $this->calculateKakehashiDates(
            $supportStartDate ?? Carbon::today(),
            $nextPeriodNumber,
            $lastEndDate
        );

        $nextStartDate = $dates['start_date'];
        $nextEndDate = $dates['end_date'];
        $nextDeadline = $dates['submission_deadline'];

        // 既にこの対象期間開始日のかけはしが存在するかチェック（重複防止）
        $exists = KakehashiPeriod::where('student_id', $studentId)
            ->where('start_date', $nextStartDate->toDateString())
            ->exists();

        if ($exists) {
            Log::info("Kakehashi period for student {$studentId} with start_date {$nextStartDate->toDateString()} already exists. Skipping.");
            return null;
        }

        // 退所日が設定されている場合、対象期間開始日が退所日以降ならスキップ
        if ($withdrawalDate && $nextStartDate->gte($withdrawalDate)) {
            Log::info("Next kakehashi start_date {$nextStartDate->toDateString()} is after withdrawal date {$withdrawalDate->toDateString()} for student {$studentId}. Skipping generation.");
            return null;
        }

        $periodName = "{$nextPeriodNumber}回目かけはし（{$studentName}）";

        // 新しい期間を挿入
        $period = KakehashiPeriod::create([
            'student_id' => $studentId,
            'period_name' => $periodName,
            'start_date' => $nextStartDate->toDateString(),
            'end_date' => $nextEndDate->toDateString(),
            'submission_deadline' => $nextDeadline->toDateString(),
            'is_active' => true,
            'is_auto_generated' => true,
        ]);

        // レコードを作成
        $this->createKakehashiRecordsForPeriod($period->id, $studentId);

        return [
            'id' => $period->id,
            'student_id' => $studentId,
            'period_name' => $periodName,
            'submission_deadline' => $nextDeadline->toDateString(),
            'type' => '定期',
        ];
    }

    /**
     * かけはし期間に対応するモニタリングシートを自動作成
     * 最新の個別支援計画の内容をコピーして、評価欄のみ編集可能なモニタリングを作成
     *
     * @param int $studentId 生徒ID
     * @param string $monitoringDate モニタリング実施日（かけはし提出期限）
     */
    public function createMonitoringForPeriod(int $studentId, string $monitoringDate): void
    {
        // 生徒情報を取得
        $student = Student::find($studentId);

        if (!$student) {
            Log::warning("Student not found: {$studentId}");
            return;
        }

        // 最新の個別支援計画を取得
        $latestPlan = IndividualSupportPlan::where('student_id', $studentId)
            ->orderByDesc('created_date')
            ->orderByDesc('id')
            ->first();

        if (!$latestPlan) {
            Log::info("No support plan found for student {$studentId}. Skipping monitoring creation.");
            return;
        }

        // 同じモニタリング日のモニタリングシートが既に存在するか確認
        $exists = MonitoringRecord::where('student_id', $studentId)
            ->where('monitoring_date', $monitoringDate)
            ->exists();

        if ($exists) {
            Log::info("Monitoring already exists for student {$studentId} on {$monitoringDate}");
            return;
        }

        try {
            DB::transaction(function () use ($latestPlan, $studentId, $student, $monitoringDate) {
                // モニタリング記録を作成
                $monitoring = MonitoringRecord::create([
                    'plan_id' => $latestPlan->id,
                    'student_id' => $studentId,
                    'classroom_id' => $student->classroom_id,
                    'monitoring_date' => $monitoringDate,
                    'overall_comment' => '',
                ]);

                // 個別支援計画の明細を取得
                $planDetails = SupportPlanDetail::where('plan_id', $latestPlan->id)
                    ->orderBy('sort_order')
                    ->get();

                // 各明細に対してモニタリング明細を作成（評価欄は空白）
                // plan_detail_id を設定して元の支援計画明細とリンク
                foreach ($planDetails as $index => $detail) {
                    MonitoringDetail::create([
                        'monitoring_id' => $monitoring->id,
                        'plan_detail_id' => $detail->id,
                        'domain' => $detail->domain ?? '',
                        'achievement_level' => null,
                        'comment' => null,
                        'next_action' => null,
                        'sort_order' => $detail->sort_order ?? $index,
                    ]);
                }

                Log::info("Created monitoring sheet (ID: {$monitoring->id}) for student {$studentId} on {$monitoringDate}");
            });
        } catch (\Exception $e) {
            Log::error("Error creating monitoring: " . $e->getMessage());
        }
    }

    /**
     * Get all Kakehashi periods for a student, ordered by start date descending.
     *
     * @param int $studentId
     * @return Collection
     */
    public function getPeriodsForStudent(int $studentId): Collection
    {
        return KakehashiPeriod::where('student_id', $studentId)
            ->with(['staffEntries', 'guardianEntries'])
            ->orderByDesc('start_date')
            ->get();
    }

    /**
     * 生徒の現在有効なかけはし期間を取得
     *
     * @param int $studentId 生徒ID
     * @return Collection 現在有効な期間のリスト
     */
    public function getCurrentKakehashiPeriods(int $studentId): Collection
    {
        $today = Carbon::today()->toDateString();

        return KakehashiPeriod::where('student_id', $studentId)
            ->where('start_date', '<=', $today)
            ->where('end_date', '>=', $today)
            ->active()
            ->orderByDesc('start_date')
            ->get();
    }

    /**
     * 保護者が入力可能なかけはし期間を取得
     * （提出期限内のもの）
     *
     * @param int $studentId 生徒ID
     * @return Collection 入力可能な期間のリスト
     */
    public function getAvailableKakehashiPeriodsForGuardian(int $studentId): Collection
    {
        $today = Carbon::today()->toDateString();

        return KakehashiPeriod::where('student_id', $studentId)
            ->where('submission_deadline', '>=', $today)
            ->active()
            ->orderByDesc('start_date')
            ->get();
    }

    /**
     * 生徒のかけはし期間を再生成する
     * （初回作成日が変更された場合）
     *
     * @param int $studentId 生徒ID
     * @param string $newInitialDate 新しい支援開始日
     * @return array 生成された期間のリスト
     */
    public function regenerateKakehashiPeriods(int $studentId, string $newInitialDate): array
    {
        // 既存の自動生成期間を削除
        KakehashiPeriod::where('student_id', $studentId)
            ->where('is_auto_generated', true)
            ->delete();

        // 新しい期間を生成
        return $this->generateKakehashiPeriodsForStudent($studentId, $newInitialDate);
    }

    /**
     * 次回のかけはし期間開始日を取得
     *
     * @param int $studentId 生徒ID
     * @return string|null 次回開始日
     */
    public function getNextKakehashiPeriodDate(int $studentId): ?string
    {
        $lastEndDate = KakehashiPeriod::where('student_id', $studentId)
            ->max('end_date');

        if ($lastEndDate) {
            return Carbon::parse($lastEndDate)->addDay()->toDateString();
        }

        return null;
    }
}
