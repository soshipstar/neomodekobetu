<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\DailyRecord;
use App\Models\IndividualSupportPlan;
use App\Models\Student;
use App\Services\OperationMetricsService;
use App\Services\PlanCycleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * L013: 計画サイクル / 運営指標の修正テスト (LOGIC-06, LOGIC-09)
 *
 * 差分カテゴリ: logic
 *
 * 放デイ業務リスク監査で検出:
 *  LOGIC-06 PlanCycleService::dueSoon() が次期計画作成済みの古い計画も
 *           リマインドし続ける (通知疲れ)。
 *  LOGIC-09 OperationMetricsService::monthly() の在籍数が集計対象月でなく
 *           「今日時点」のスナップショットで算出され、過去月帳票がズレる。
 */
class L013_PlanCycleAndMetricsFixTest extends TestCase
{
    use RefreshDatabase;

    // =========================================================================
    // LOGIC-06: dueSoon が次期計画作成済みの古い計画を除外
    // =========================================================================

    public function test_logic06_due_soon_excludes_superseded_plans(): void
    {
        $classroom = Classroom::create(['classroom_name' => 'L013教室', 'is_active' => true, 'capacity' => 10]);
        $student = Student::create([
            'classroom_id' => $classroom->id,
            'student_name' => '生徒L013',
            'is_active'    => true,
        ]);

        // 第1期計画 (期限切れ間近) — next_plan_due_date が近い
        IndividualSupportPlan::create([
            'student_id'         => $student->id,
            'classroom_id'       => $classroom->id,
            'student_name'       => '生徒L013',
            'created_date'       => '2025-04-01',
            'status'             => 'official',
            'is_official'        => true,
            'next_plan_due_date' => '2026-04-01',
        ]);

        // 第2期計画 (より新しい official) — 第1期を supersede する
        IndividualSupportPlan::create([
            'student_id'         => $student->id,
            'classroom_id'       => $classroom->id,
            'student_name'       => '生徒L013',
            'created_date'       => '2026-03-15',
            'status'             => 'official',
            'is_official'        => true,
            'next_plan_due_date' => '2027-03-15',
        ]);

        $result = app(PlanCycleService::class)->dueSoon($classroom->id, 36500);

        // 第1期 (期限切れ) は除外され、第2期のみ残ること
        $dueCreatedDates = $result['plans_due']->pluck('created_date')
            ->map(fn ($d) => $d instanceof \Carbon\Carbon ? $d->toDateString() : (string) $d)
            ->all();
        $this->assertNotContains('2025-04-01', $dueCreatedDates, '次期計画作成済みの古い計画がリマインドに残っています (LOGIC-06)。');
    }

    // =========================================================================
    // LOGIC-09: 在籍数が集計対象月末時点で算出される
    // =========================================================================

    public function test_logic09_active_students_uses_target_month(): void
    {
        $classroom = Classroom::create(['classroom_name' => 'L013指標教室', 'is_active' => true, 'capacity' => 10]);

        // 2026-05 在籍 (5月以前に開始、未退所)
        Student::create([
            'classroom_id'       => $classroom->id,
            'student_name'       => '継続児',
            'support_start_date' => '2026-01-01',
            'is_active'          => true,
        ]);

        // 2026-06 に退所した児童 → 2026-05 の集計には含まれるべき
        Student::create([
            'classroom_id'       => $classroom->id,
            'student_name'       => '6月退所児',
            'support_start_date' => '2026-01-01',
            'withdrawal_date'    => '2026-06-15',
            'status'             => 'withdrawn',
            'is_active'          => false,
        ]);

        // 2026-07 に入所した児童 → 2026-05 の集計には含まれないべき
        Student::create([
            'classroom_id'       => $classroom->id,
            'student_name'       => '7月入所児',
            'support_start_date' => '2026-07-01',
            'is_active'          => true,
        ]);

        $metrics = app(OperationMetricsService::class)->monthly($classroom->id, '2026-05');

        // 2026-05 時点では継続児 + 6月退所児 = 2 名 (7月入所児は含まない)
        $this->assertSame(2, $metrics['active_students'], '在籍数が集計対象月末時点で算出されていません (LOGIC-09)。');
    }
}
