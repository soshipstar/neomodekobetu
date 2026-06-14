<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\Company;
use App\Models\Student;
use App\Models\VectorEmbedding;
use App\Services\VectorSearchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * テナント分離(rank5): vector_embeddings の法人スコープ検索の回帰ガード。
 * findSimilarPlans は起点の法人のみを返し、他法人の類似プランを漏らさない。
 *
 * 差分カテゴリ: logic
 */
class VectorEmbeddingTenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        if (class_exists(\Laravel\Telescope\Telescope::class)) {
            \Laravel\Telescope\Telescope::stopRecording();
        }
    }

    /** 1536次元のダミーベクトル文字列。検索のWHERE分離検証用(順位は問わない)。 */
    private function vec(float $v = 0.1): string
    {
        return '['.implode(',', array_fill(0, 1536, $v)).']';
    }

    public function test_find_similar_plans_is_company_scoped(): void
    {
        $companyA = Company::create(['name' => 'A法人']);
        $roomA = Classroom::create(['classroom_name' => 'A', 'company_id' => $companyA->id, 'is_active' => true]);
        $studentA = Student::create(['student_name' => '児A', 'classroom_id' => $roomA->id, 'status' => 'active', 'is_active' => true]);

        $companyB = Company::create(['name' => 'B法人']);
        $roomB = Classroom::create(['classroom_name' => 'B', 'company_id' => $companyB->id, 'is_active' => true]);

        // 起点: A法人・児Aのプラン埋め込み
        VectorEmbedding::create([
            'source_type' => 'support_plan', 'source_id' => 1001, 'company_id' => $companyA->id,
            'embedding' => $this->vec(), 'metadata' => ['student_id' => $studentA->id, 'classroom_id' => $roomA->id],
        ]);
        // A法人の別プラン(返るべき)
        VectorEmbedding::create([
            'source_type' => 'support_plan', 'source_id' => 1002, 'company_id' => $companyA->id,
            'embedding' => $this->vec(), 'metadata' => ['student_id' => 9999, 'classroom_id' => $roomA->id],
        ]);
        // B法人のプラン(漏れてはならない)
        VectorEmbedding::create([
            'source_type' => 'support_plan', 'source_id' => 2001, 'company_id' => $companyB->id,
            'embedding' => $this->vec(), 'metadata' => ['student_id' => 8888, 'classroom_id' => $roomB->id],
        ]);

        $results = app(VectorSearchService::class)->findSimilarPlans($studentA->id);
        $ids = collect($results)->pluck('source_id')->map(fn ($v) => (int) $v);

        $this->assertTrue($ids->contains(1002), 'A法人の別プランは返る');
        $this->assertFalse($ids->contains(2001), 'B法人(他法人)のプランは返らない');
        $this->assertFalse($ids->contains(1001), '起点自身は除外される');
    }

    public function test_find_similar_plans_returns_empty_when_anchor_has_no_company(): void
    {
        $company = Company::create(['name' => 'A法人']);
        $room = Classroom::create(['classroom_name' => 'A', 'company_id' => $company->id, 'is_active' => true]);
        $student = Student::create(['student_name' => '児A', 'classroom_id' => $room->id, 'status' => 'active', 'is_active' => true]);

        // 起点の company_id が null(legacy) → fail-closed で横断検索しない
        VectorEmbedding::create([
            'source_type' => 'support_plan', 'source_id' => 3001, 'company_id' => null,
            'embedding' => $this->vec(), 'metadata' => ['student_id' => $student->id],
        ]);
        VectorEmbedding::create([
            'source_type' => 'support_plan', 'source_id' => 3002, 'company_id' => $company->id,
            'embedding' => $this->vec(), 'metadata' => ['student_id' => 7777, 'classroom_id' => $room->id],
        ]);

        $results = app(VectorSearchService::class)->findSimilarPlans($student->id);
        $this->assertCount(0, $results, '法人不明の起点は横断検索しない(fail-closed)');
    }
}
