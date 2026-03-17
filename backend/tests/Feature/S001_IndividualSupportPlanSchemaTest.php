<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class S001_IndividualSupportPlanSchemaTest extends TestCase
{
    /**
     * S-001: Verify individual_support_plans has all required columns
     * after running the 2026_03_18_000001 migration.
     */
    public function test_individual_support_plans_has_consent_name_column(): void
    {
        $this->assertTrue(
            Schema::hasColumn('individual_support_plans', 'consent_name'),
            'Column consent_name should exist on individual_support_plans'
        );
    }

    public function test_individual_support_plans_has_is_draft_column(): void
    {
        $this->assertTrue(
            Schema::hasColumn('individual_support_plans', 'is_draft'),
            'Column is_draft should exist on individual_support_plans'
        );
    }

    public function test_individual_support_plans_has_guardian_review_comment_at_column(): void
    {
        $this->assertTrue(
            Schema::hasColumn('individual_support_plans', 'guardian_review_comment_at'),
            'Column guardian_review_comment_at should exist on individual_support_plans'
        );
    }

    public function test_individual_support_plans_has_staff_signature_image_column(): void
    {
        $this->assertTrue(
            Schema::hasColumn('individual_support_plans', 'staff_signature_image'),
            'Column staff_signature_image should exist on individual_support_plans'
        );
    }

    public function test_individual_support_plans_has_guardian_signature_image_column(): void
    {
        $this->assertTrue(
            Schema::hasColumn('individual_support_plans', 'guardian_signature_image'),
            'Column guardian_signature_image should exist on individual_support_plans'
        );
    }

    /**
     * Verify columns from the legacy migration (2026_03_11) also exist.
     */
    public function test_individual_support_plans_has_legacy_columns(): void
    {
        $expectedColumns = [
            'manager_name',
            'long_term_goal_date',
            'short_term_goal_date',
            'is_hidden',
            'guardian_confirmed',
            'guardian_confirmed_at',
            'source_monitoring_id',
            'basis_generated_at',
            'staff_signer_name',
        ];

        foreach ($expectedColumns as $column) {
            $this->assertTrue(
                Schema::hasColumn('individual_support_plans', $column),
                "Column {$column} should exist on individual_support_plans"
            );
        }
    }

    /**
     * Verify columns from the base create migration also exist.
     */
    public function test_individual_support_plans_has_base_columns(): void
    {
        $expectedColumns = [
            'id', 'student_id', 'classroom_id', 'student_name', 'created_date',
            'life_intention', 'overall_policy', 'long_term_goal', 'short_term_goal',
            'consent_date', 'basis_content', 'plan_source_period', 'start_type',
            'status', 'is_official', 'staff_signature', 'staff_signature_date',
            'guardian_signature', 'guardian_signature_date', 'guardian_review_comment',
            'guardian_reviewed_at', 'created_by', 'created_at', 'updated_at',
        ];

        foreach ($expectedColumns as $column) {
            $this->assertTrue(
                Schema::hasColumn('individual_support_plans', $column),
                "Column {$column} should exist on individual_support_plans"
            );
        }
    }

    /**
     * S-001b: Verify support_plan_details has category column separate from domain.
     */
    public function test_support_plan_details_has_category_column(): void
    {
        $this->assertTrue(
            Schema::hasColumn('support_plan_details', 'category'),
            'Column category should exist on support_plan_details (separate from domain)'
        );
    }

    public function test_support_plan_details_has_domain_column(): void
    {
        $this->assertTrue(
            Schema::hasColumn('support_plan_details', 'domain'),
            'Column domain should still exist on support_plan_details'
        );
    }

    public function test_support_plan_details_has_all_columns(): void
    {
        $expectedColumns = [
            'id', 'plan_id', 'domain', 'category', 'current_status', 'goal',
            'support_content', 'achievement_status', 'sort_order',
            'sub_category', 'achievement_date', 'staff_organization', 'notes', 'priority',
            'created_at', 'updated_at',
        ];

        foreach ($expectedColumns as $column) {
            $this->assertTrue(
                Schema::hasColumn('support_plan_details', $column),
                "Column {$column} should exist on support_plan_details"
            );
        }
    }

    /**
     * Verify the model fillable includes all new columns.
     */
    public function test_model_fillable_includes_new_columns(): void
    {
        $model = new \App\Models\IndividualSupportPlan();
        $fillable = $model->getFillable();

        $this->assertContains('consent_name', $fillable);
        $this->assertContains('is_draft', $fillable);
        $this->assertContains('guardian_signature_image', $fillable);
        $this->assertContains('staff_signature_image', $fillable);
    }

    public function test_support_plan_detail_model_fillable_includes_category(): void
    {
        $model = new \App\Models\SupportPlanDetail();
        $fillable = $model->getFillable();

        $this->assertContains('category', $fillable);
        $this->assertContains('domain', $fillable);
        $this->assertContains('goal', $fillable);
    }
}
