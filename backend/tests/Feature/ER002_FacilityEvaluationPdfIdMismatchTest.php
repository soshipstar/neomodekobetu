<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * ER002: 施設評価 responseStatus → responsePdf の ID 不整合バグの再発防止
 *
 * 差分カテゴリ: api
 * 背景: /staff/facility-evaluation 画面の「閲覧」ボタンが 404 を返していた。
 *       responseStatus が u.id (user.id) を返していたのに、
 *       responsePdf は evaluation.id を期待していたため。
 *       修正: responseStatus に evaluation_id を含める。
 */
class ER002_FacilityEvaluationPdfIdMismatchTest extends TestCase
{
    use RefreshDatabase;

    private function setupFixture(): array
    {
        $company = Company::create(['name' => '企業A']);
        $classroom = Classroom::create([
            'classroom_name' => '本校',
            'company_id' => $company->id,
            'is_active' => true,
        ]);

        $staff = User::create([
            'username' => 'staff_er002',
            'password' => bcrypt('pass'),
            'full_name' => 'スタッフ',
            'user_type' => 'staff',
            'classroom_id' => $classroom->id,
            'is_active' => true,
        ]);

        $guardian = User::create([
            'username' => 'guardian_er002',
            'password' => bcrypt('pass'),
            'full_name' => '保護者',
            'user_type' => 'guardian',
            'classroom_id' => $classroom->id,
            'is_active' => true,
        ]);

        $periodId = DB::table('facility_evaluation_periods')->insertGetId([
            'fiscal_year' => 2025,
            'title' => '2025年度施設評価',
            'status' => 'collecting',
            'classroom_id' => $classroom->id,
            'guardian_deadline' => '2026-03-31',
            'staff_deadline' => '2026-03-31',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $evaluationId = DB::table('facility_guardian_evaluations')->insertGetId([
            'period_id' => $periodId,
            'guardian_id' => $guardian->id,
            'is_submitted' => true,
            'submitted_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return compact('classroom', 'staff', 'guardian', 'periodId', 'evaluationId');
    }

    public function test_response_status_returns_evaluation_id_distinct_from_user_id(): void
    {
        $f = $this->setupFixture();

        $response = $this->actingAs($f['staff'], 'sanctum')
            ->getJson("/api/staff/facility-evaluation/periods/{$f['periodId']}/status");

        $response->assertStatus(200);

        $guardians = $response->json('data.guardian_responses');
        $this->assertIsArray($guardians);
        $this->assertNotEmpty($guardians);

        // 該当の保護者行を探す
        $row = collect($guardians)->firstWhere('id', $f['guardian']->id);
        $this->assertNotNull($row, '保護者の回答行が見つからない');

        // id は user.id、evaluation_id は評価 PK であり、値が違うはず
        $this->assertEquals($f['guardian']->id, $row['id']);
        $this->assertEquals($f['evaluationId'], $row['evaluation_id']);
        $this->assertTrue($row['is_submitted']);
    }

    public function test_response_pdf_accepts_evaluation_id_from_status_response(): void
    {
        $f = $this->setupFixture();

        // 1. 画面ロード時の API: status エンドポイントから evaluation_id を取得
        $statusRes = $this->actingAs($f['staff'], 'sanctum')
            ->getJson("/api/staff/facility-evaluation/periods/{$f['periodId']}/status");
        $row = collect($statusRes->json('data.guardian_responses'))
            ->firstWhere('id', $f['guardian']->id);
        $evaluationId = $row['evaluation_id'];

        // 2. 閲覧ボタン押下時の API: pdf エンドポイントに渡して 200
        $pdfRes = $this->actingAs($f['staff'], 'sanctum')
            ->getJson("/api/staff/facility-evaluation/responses/{$evaluationId}/pdf");

        $pdfRes->assertStatus(200);
        $this->assertEquals($evaluationId, $pdfRes->json('data.evaluation.id'));
    }

    public function test_unsubmitted_rows_have_null_evaluation_id(): void
    {
        $f = $this->setupFixture();
        // 2 人目の保護者 (未回答)
        $guardian2 = User::create([
            'username' => 'guardian2_er002',
            'password' => bcrypt('pass'),
            'full_name' => '未回答保護者',
            'user_type' => 'guardian',
            'classroom_id' => $f['classroom']->id,
            'is_active' => true,
        ]);

        $response = $this->actingAs($f['staff'], 'sanctum')
            ->getJson("/api/staff/facility-evaluation/periods/{$f['periodId']}/status");

        $row = collect($response->json('data.guardian_responses'))
            ->firstWhere('id', $guardian2->id);
        $this->assertNotNull($row);
        $this->assertNull($row['evaluation_id']);
        $this->assertFalse($row['is_submitted']);
    }
}
