<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * A-004: SupportPlanController の store/update で
 * 6つの追加フィールド (category, sub_category, achievement_date,
 * staff_organization, notes, priority) がバリデーション・保存されること
 *
 * NOTE: 実際のDB操作テストは S-001b マイグレーション適用後に可能。
 * ここではコード上のバリデーションルールと create() 呼び出しを静的検証する。
 */
class A004_SupportPlanDetailsFieldsTest extends TestCase
{
    private string $controllerPath;
    private string $contents;

    protected function setUp(): void
    {
        parent::setUp();
        $this->controllerPath = app_path('Http/Controllers/Staff/SupportPlanController.php');
        $this->contents = file_get_contents($this->controllerPath);
    }

    /**
     * バリデーションルールに 6 フィールドが含まれること
     */
    public function test_validation_rules_include_new_fields(): void
    {
        $requiredFields = [
            'details.*.category',
            'details.*.sub_category',
            'details.*.achievement_date',
            'details.*.staff_organization',
            'details.*.notes',
            'details.*.priority',
        ];

        foreach ($requiredFields as $field) {
            $this->assertStringContainsString(
                "'{$field}'",
                $this->contents,
                "SupportPlanController にバリデーションルール {$field} がありません"
            );
        }
    }

    /**
     * SupportPlanDetail::create() 呼び出しに 6 フィールドが含まれること
     */
    public function test_create_call_includes_new_fields(): void
    {
        $requiredInCreate = [
            "'category'",
            "'sub_category'",
            "'achievement_date'",
            "'staff_organization'",
            "'notes'",
            "'priority'",
        ];

        foreach ($requiredInCreate as $field) {
            $this->assertStringContainsString(
                $field,
                $this->contents,
                "SupportPlanDetail::create() に {$field} が含まれていません"
            );
        }
    }
}
