<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\DailyRecord;
use App\Models\Student;
use App\Models\StudentRecord;
use App\Models\User;
use App\Services\ServiceTypeRegistry;
use App\Services\StrengthsAggregator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * L010 StrengthsAggregator: 連絡帳の強み(才能)チェック集計
 *
 * 差分カテゴリ: logic
 * 背景: モニタリング・個別支援計画・AI 生成・PDF が共通で参照する集計層。
 *       期間境界 (from/to)、空入力、4 サービス種別ごとのメトリクス分岐
 *       (employment_a/b の employment_metrics、transition の transition_metrics)
 *       のいずれかが壊れると下流すべてに波及するため、ここで仕様を固定する。
 */
class L010_StrengthsAggregatorTest extends TestCase
{
    use RefreshDatabase;

    private StrengthsAggregator $aggregator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->aggregator = new StrengthsAggregator();
    }

    private function makeStudent(string $serviceType): Student
    {
        $classroom = Classroom::create([
            'classroom_name' => 'Test '.$serviceType,
            'service_type'   => $serviceType,
            'is_active'      => true,
        ]);

        return Student::create([
            'classroom_id'       => $classroom->id,
            'student_name'       => 'Tester '.$serviceType,
            'support_start_date' => '2026-01-01',
            'is_active'          => true,
        ]);
    }

    private function makeStaff(Classroom $classroom): User
    {
        return User::create([
            'username'     => 'staff_'.$classroom->id.'_'.uniqid('', true),
            'password'     => bcrypt('password'),
            'full_name'    => 'Staff',
            'user_type'    => 'staff',
            'classroom_id' => $classroom->id,
            'is_active'    => true,
        ]);
    }

    private function addStudentRecord(
        Student $student,
        string $date,
        ?array $strengths = null,
        ?array $serviceTypeData = null,
    ): StudentRecord {
        $classroom = Classroom::find($student->classroom_id);
        $staff = $this->makeStaff($classroom);

        $daily = DailyRecord::create([
            'classroom_id' => $student->classroom_id,
            'record_date'  => $date,
            'staff_id'     => $staff->id,
        ]);

        return StudentRecord::create([
            'daily_record_id'   => $daily->id,
            'student_id'        => $student->id,
            'strengths'         => $strengths,
            'service_type_data' => $serviceTypeData,
        ]);
    }

    // =========================================================================
    // 空入力 / 期間境界
    // =========================================================================

    public function test_returns_zero_record_count_when_no_records(): void
    {
        $student = $this->makeStudent(ServiceTypeRegistry::AFTER_SCHOOL);

        $result = $this->aggregator->aggregateForStudent(
            $student->id,
            Carbon::parse('2026-01-01'),
            Carbon::parse('2026-03-31'),
        );

        $this->assertSame(0,  $result['record_count']);
        $this->assertSame([], $result['trends']);
        $this->assertSame(ServiceTypeRegistry::AFTER_SCHOOL, $result['service_type']);
    }

    public function test_filters_records_outside_period(): void
    {
        $student = $this->makeStudent(ServiceTypeRegistry::AFTER_SCHOOL);
        $this->addStudentRecord($student, '2025-12-31', ['集中力' => 4]); // 期間前
        $this->addStudentRecord($student, '2026-01-15', ['集中力' => 7]);
        $this->addStudentRecord($student, '2026-04-01', ['集中力' => 9]); // 期間後

        $result = $this->aggregator->aggregateForStudent(
            $student->id,
            Carbon::parse('2026-01-01'),
            Carbon::parse('2026-03-31'),
        );

        $this->assertSame(1, $result['record_count']);
        $this->assertSame('2026-01-01', $result['from']);
        $this->assertSame('2026-03-31', $result['to']);
    }

    public function test_skips_records_with_empty_strengths(): void
    {
        $student = $this->makeStudent(ServiceTypeRegistry::AFTER_SCHOOL);
        $this->addStudentRecord($student, '2026-02-01', null);
        $this->addStudentRecord($student, '2026-02-02', []);
        $this->addStudentRecord($student, '2026-02-03', ['集中力' => 5]);

        $result = $this->aggregator->aggregateForStudent(
            $student->id,
            Carbon::parse('2026-01-01'),
            Carbon::parse('2026-12-31'),
        );

        // strengths が入っているレコードのみカウント
        $this->assertSame(1, $result['record_count']);
        $this->assertCount(1, $result['trends']);
    }

    // =========================================================================
    // trends 計算 (overall_average / change / trend / domain)
    // =========================================================================

    public function test_computes_overall_average_change_and_trend_up(): void
    {
        $student = $this->makeStudent(ServiceTypeRegistry::AFTER_SCHOOL);
        // 2026-01: 平均 4 / 2026-03: 平均 8  → change = +4, trend = up
        $this->addStudentRecord($student, '2026-01-10', ['集中力' => 3]);
        $this->addStudentRecord($student, '2026-01-20', ['集中力' => 5]);
        $this->addStudentRecord($student, '2026-03-05', ['集中力' => 7]);
        $this->addStudentRecord($student, '2026-03-25', ['集中力' => 9]);

        $result = $this->aggregator->aggregateForStudent(
            $student->id,
            Carbon::parse('2026-01-01'),
            Carbon::parse('2026-12-31'),
        );

        $this->assertSame(4, $result['record_count']);
        $trend = collect($result['trends'])->firstWhere('label', '集中力');
        $this->assertNotNull($trend);
        $this->assertSame(6.0, $trend['overall_average']);
        $this->assertSame(4.0, $trend['change']);
        $this->assertSame('up', $trend['trend']);
        // 放デイは「集中力 → 認知・行動」マッピング
        $this->assertSame('認知・行動', $trend['domain']);
    }

    public function test_computes_trend_down_and_stable(): void
    {
        $student = $this->makeStudent(ServiceTypeRegistry::AFTER_SCHOOL);
        // 集中力: 9 → 9 → 4 (低下)
        $this->addStudentRecord($student, '2026-01-10', ['集中力' => 9]);
        $this->addStudentRecord($student, '2026-02-10', ['集中力' => 9]);
        $this->addStudentRecord($student, '2026-03-10', ['集中力' => 4]);
        // 持続力: 5 → 5 → 5 (安定)
        $this->addStudentRecord($student, '2026-01-15', ['持続力' => 5]);
        $this->addStudentRecord($student, '2026-02-15', ['持続力' => 5]);
        $this->addStudentRecord($student, '2026-03-15', ['持続力' => 5]);

        $result = $this->aggregator->aggregateForStudent(
            $student->id,
            Carbon::parse('2026-01-01'),
            Carbon::parse('2026-12-31'),
        );

        $focus = collect($result['trends'])->firstWhere('label', '集中力');
        $this->assertSame('down', $focus['trend']);
        $this->assertSame(-5.0,   $focus['change']);

        $persist = collect($result['trends'])->firstWhere('label', '持続力');
        $this->assertSame('stable', $persist['trend']);
        $this->assertSame(0.0,      $persist['change']);
    }

    public function test_trends_are_ordered_by_service_type_strength_keys(): void
    {
        $student = $this->makeStudent(ServiceTypeRegistry::AFTER_SCHOOL);
        // わざと逆順に投入
        $this->addStudentRecord($student, '2026-02-01', [
            'コミュニケーションの工夫' => 7,
            '集中力'                   => 3,
        ]);

        $result = $this->aggregator->aggregateForStudent(
            $student->id,
            Carbon::parse('2026-01-01'),
            Carbon::parse('2026-12-31'),
        );

        // ServiceTypeRegistry::strengthKeys(after_school) の並び順 (集中力が先頭)
        $labels = array_column($result['trends'], 'label');
        $this->assertSame('集中力', $labels[0]);
        $this->assertContains('コミュニケーションの工夫', $labels);
    }

    // =========================================================================
    // employment_a / employment_b: employment_metrics
    // =========================================================================

    public function test_employment_metrics_aggregates_wage_clock_and_work_content(): void
    {
        $student = $this->makeStudent(ServiceTypeRegistry::EMPLOYMENT_A);

        $this->addStudentRecord($student, '2026-02-02', null, [
            'wage_eligible_hours' => 4,
            'clock_in'            => '09:00',
            'clock_out'           => '15:00',
            'work_content'        => '袋詰め',
        ]);
        $this->addStudentRecord($student, '2026-02-03', null, [
            'wage_eligible_hours' => 6,
            'clock_in'            => '08:30',
            'clock_out'           => '16:30',
            'work_content'        => '袋詰め',
        ]);
        $this->addStudentRecord($student, '2026-02-04', null, [
            'wage_eligible_hours' => 5,
            'work_content'        => '清掃',
        ]);

        $result = $this->aggregator->aggregateForStudent(
            $student->id,
            Carbon::parse('2026-02-01'),
            Carbon::parse('2026-02-28'),
        );

        $this->assertSame(ServiceTypeRegistry::EMPLOYMENT_A, $result['service_type']);
        $this->assertArrayHasKey('employment_metrics', $result);
        $em = $result['employment_metrics'];

        $this->assertSame(15.0, $em['total_wage_eligible_hours']);
        $this->assertSame(5.0,  $em['average_wage_eligible_hours']);
        $this->assertSame(['袋詰め' => 2, '清掃' => 1], $em['work_content_categories']);
        // 平均出勤時刻は (09:00 + 08:30) / 2 = 08:45
        $this->assertSame('08:45', $em['average_clock_in']);
        // 平均退勤時刻は (15:00 + 16:30) / 2 = 15:45
        $this->assertSame('15:45', $em['average_clock_out']);
        // 出勤率は期間 28 日 × 5/7 = 20 営業日に対し 3 件 → 15.0%
        $this->assertSame(15.0, $em['attendance_rate']);
    }

    public function test_employment_metrics_attendance_rate_caps_at_100(): void
    {
        $student = $this->makeStudent(ServiceTypeRegistry::EMPLOYMENT_B);

        // 期間 7 日 × 5/7 = 5 営業日に対し 10 件入れる → 100% にキャップ
        for ($i = 1; $i <= 10; $i++) {
            $this->addStudentRecord($student, sprintf('2026-02-%02d', $i % 7 + 1), null, [
                'wage_eligible_hours' => 4,
                'work_content'        => 'work',
            ]);
        }

        $result = $this->aggregator->aggregateForStudent(
            $student->id,
            Carbon::parse('2026-02-01'),
            Carbon::parse('2026-02-07'),
        );

        $this->assertSame(100.0, $result['employment_metrics']['attendance_rate']);
    }

    public function test_employment_metrics_returns_null_clock_when_unset(): void
    {
        $student = $this->makeStudent(ServiceTypeRegistry::EMPLOYMENT_A);
        $this->addStudentRecord($student, '2026-02-02', null, ['work_content' => '清掃']);

        $result = $this->aggregator->aggregateForStudent(
            $student->id,
            Carbon::parse('2026-02-01'),
            Carbon::parse('2026-02-28'),
        );
        $em = $result['employment_metrics'];
        $this->assertNull($em['average_clock_in']);
        $this->assertNull($em['average_clock_out']);
        $this->assertSame(0.0, $em['total_wage_eligible_hours']);
    }

    // =========================================================================
    // transition: transition_metrics
    // =========================================================================

    public function test_transition_metrics_uniques_practice_and_averages_manner_score(): void
    {
        $student = $this->makeStudent(ServiceTypeRegistry::TRANSITION);

        $this->addStudentRecord($student, '2026-02-02', null, [
            'practice_content'      => '○○商事 接客実習',
            'job_search_record'     => 'ハローワーク訪問',
            'business_manner_score' => 3,
        ]);
        $this->addStudentRecord($student, '2026-02-09', null, [
            'practice_content'      => '○○商事 接客実習', // 重複
            'job_search_record'     => '履歴書作成',
            'business_manner_score' => 5,
        ]);
        $this->addStudentRecord($student, '2026-02-16', null, [
            'practice_content'      => '△△工業 製造実習',
            'business_manner_score' => 4,
        ]);

        $result = $this->aggregator->aggregateForStudent(
            $student->id,
            Carbon::parse('2026-02-01'),
            Carbon::parse('2026-02-28'),
        );

        $this->assertSame(ServiceTypeRegistry::TRANSITION, $result['service_type']);
        $tm = $result['transition_metrics'];

        $this->assertSame(['○○商事 接客実習', '△△工業 製造実習'], $tm['practice_contents']);
        $this->assertSame(['ハローワーク訪問', '履歴書作成'],   $tm['job_search_records']);
        $this->assertSame(4.0, $tm['average_business_manner_score']);
    }

    public function test_transition_metrics_average_score_is_null_when_no_input(): void
    {
        $student = $this->makeStudent(ServiceTypeRegistry::TRANSITION);
        $this->addStudentRecord($student, '2026-02-02', null, ['practice_content' => '実習']);

        $result = $this->aggregator->aggregateForStudent(
            $student->id,
            Carbon::parse('2026-02-01'),
            Carbon::parse('2026-02-28'),
        );

        $this->assertNull($result['transition_metrics']['average_business_manner_score']);
    }

    public function test_after_school_does_not_emit_employment_or_transition_metrics(): void
    {
        $student = $this->makeStudent(ServiceTypeRegistry::AFTER_SCHOOL);
        $this->addStudentRecord($student, '2026-02-01', ['集中力' => 5]);

        $result = $this->aggregator->aggregateForStudent(
            $student->id,
            Carbon::parse('2026-02-01'),
            Carbon::parse('2026-02-28'),
        );

        $this->assertArrayNotHasKey('employment_metrics', $result);
        $this->assertArrayNotHasKey('transition_metrics', $result);
    }

    // =========================================================================
    // formatAsText
    // =========================================================================

    public function test_format_as_text_for_empty_summary(): void
    {
        $student = $this->makeStudent(ServiceTypeRegistry::AFTER_SCHOOL);
        $result = $this->aggregator->aggregateForStudent(
            $student->id,
            Carbon::parse('2026-01-01'),
            Carbon::parse('2026-01-31'),
        );

        $text = $this->aggregator->formatAsText($result);
        $this->assertStringContainsString('対象期間に記録なし', $text);
    }

    public function test_format_as_text_includes_employment_metrics_section(): void
    {
        $student = $this->makeStudent(ServiceTypeRegistry::EMPLOYMENT_A);
        $this->addStudentRecord($student, '2026-02-02', null, [
            'wage_eligible_hours' => 4.5,
            'work_content'        => '袋詰め',
        ]);

        $result = $this->aggregator->aggregateForStudent(
            $student->id,
            Carbon::parse('2026-02-01'),
            Carbon::parse('2026-02-28'),
        );
        $text = $this->aggregator->formatAsText($result);

        $this->assertStringContainsString('【就労メトリクス】', $text);
        $this->assertStringContainsString('工賃対象時間', $text);
        $this->assertStringContainsString('袋詰め(1回)', $text);
    }
}
