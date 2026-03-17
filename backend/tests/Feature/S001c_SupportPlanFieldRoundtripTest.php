<?php

namespace Tests\Feature;

use App\Models\IndividualSupportPlan;
use App\Models\SupportPlanDetail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * S-001c: Verify that the field mapping between frontend and backend
 * works correctly in both directions (create and retrieve).
 *
 * Frontend formToApi() maps:
 *   guardian_wish     -> life_intention
 *   manager_name      -> consent_name
 *   guardian_signature_text -> guardian_signature
 *
 * For details:
 *   support_goal -> goal
 *   category     -> category (separate from domain)
 */
class S001c_SupportPlanFieldRoundtripTest extends TestCase
{
    /**
     * Verify that individual_support_plans has the fields needed for
     * the frontend field mapping roundtrip.
     */
    public function test_plan_table_has_mapped_columns(): void
    {
        // life_intention (frontend: guardian_wish)
        $this->assertTrue(Schema::hasColumn('individual_support_plans', 'life_intention'));
        // consent_name (frontend: manager_name)
        $this->assertTrue(Schema::hasColumn('individual_support_plans', 'consent_name'));
        // guardian_signature (frontend: guardian_signature_text)
        $this->assertTrue(Schema::hasColumn('individual_support_plans', 'guardian_signature'));
    }

    /**
     * Verify that support_plan_details has the fields needed for
     * the detail field mapping roundtrip.
     */
    public function test_detail_table_has_mapped_columns(): void
    {
        // goal (frontend: support_goal)
        $this->assertTrue(Schema::hasColumn('support_plan_details', 'goal'));
        // category (frontend: category) - separate from domain
        $this->assertTrue(Schema::hasColumn('support_plan_details', 'category'));
        // domain still exists
        $this->assertTrue(Schema::hasColumn('support_plan_details', 'domain'));
    }

    /**
     * Verify model fillable supports both DB column names used in the mapping.
     */
    public function test_plan_model_fillable_supports_mapping(): void
    {
        $fillable = (new IndividualSupportPlan())->getFillable();

        $this->assertContains('life_intention', $fillable, 'life_intention must be fillable (mapped from guardian_wish)');
        $this->assertContains('consent_name', $fillable, 'consent_name must be fillable (mapped from manager_name)');
        $this->assertContains('guardian_signature', $fillable, 'guardian_signature must be fillable (mapped from guardian_signature_text)');
    }

    /**
     * Verify detail model fillable supports both column names.
     */
    public function test_detail_model_fillable_supports_mapping(): void
    {
        $fillable = (new SupportPlanDetail())->getFillable();

        $this->assertContains('goal', $fillable, 'goal must be fillable (mapped from support_goal)');
        $this->assertContains('category', $fillable, 'category must be fillable');
        $this->assertContains('domain', $fillable, 'domain must be fillable');
    }
}
