<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\AiGenerationController;
use App\Models\Classroom;
use App\Services\ServiceTypeRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use ReflectionClass;
use Tests\TestCase;

/**
 * L012 AiGenerationController::resolveServiceType
 *
 * 差分カテゴリ: api
 * 背景: 個別支援計画 / モニタリング / ニュースレター生成のすべてで AI プロンプト
 *       (system message と user message の語彙) を classroom→service_type で
 *       切り替える。解決失敗時は after_school に確実にフォールバックする
 *       ことで、未マイグレーション環境や旧 classroom も画面が壊れない設計。
 *       この保証が崩れると AI 出力の語彙が事業所の実態と一致しなくなる。
 */
class L012_AiGenerationServiceTypeResolveTest extends TestCase
{
    use RefreshDatabase;

    private AiGenerationController $controller;

    protected function setUp(): void
    {
        parent::setUp();
        $this->controller = new AiGenerationController();
    }

    private function invoke(?int $classroomId): string
    {
        $method = (new ReflectionClass($this->controller))->getMethod('resolveServiceType');
        $method->setAccessible(true);
        return $method->invoke($this->controller, $classroomId);
    }

    public function test_returns_after_school_when_classroom_id_is_null(): void
    {
        $this->assertSame(ServiceTypeRegistry::AFTER_SCHOOL, $this->invoke(null));
    }

    public function test_returns_after_school_when_classroom_does_not_exist(): void
    {
        $this->assertSame(ServiceTypeRegistry::AFTER_SCHOOL, $this->invoke(99999));
    }

    public function test_returns_classroom_service_type_when_set_to_employment_a(): void
    {
        $classroom = Classroom::create([
            'classroom_name' => 'Employment A',
            'service_type'   => ServiceTypeRegistry::EMPLOYMENT_A,
            'is_active'      => true,
        ]);

        $this->assertSame(ServiceTypeRegistry::EMPLOYMENT_A, $this->invoke($classroom->id));
    }

    public function test_returns_classroom_service_type_for_each_known_value(): void
    {
        foreach (ServiceTypeRegistry::ALL as $type) {
            $classroom = Classroom::create([
                'classroom_name' => 'C-'.$type,
                'service_type'   => $type,
                'is_active'      => true,
            ]);
            $this->assertSame($type, $this->invoke($classroom->id));
        }
    }

    public function test_falls_back_to_after_school_when_service_type_is_invalid_string(): void
    {
        // CHECK 制約があるため通常の経路では入らないが、レガシー値を直接 INSERT して
        // フォールバックが効くことを検証する。CHECK 制約は一時的に外す。
        DB::statement('ALTER TABLE classrooms DROP CONSTRAINT IF EXISTS classrooms_service_type_check');

        $id = DB::table('classrooms')->insertGetId([
            'classroom_name' => 'Legacy',
            'service_type'   => 'legacy_value',
            'is_active'      => true,
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);

        $this->assertSame(ServiceTypeRegistry::AFTER_SCHOOL, $this->invoke($id));
    }
}
