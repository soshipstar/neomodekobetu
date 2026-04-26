<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\MasterAdminAuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * B-100: Billing API 権限スコープと表示制御フィルタの確認
 *
 * 検証:
 *  - 通常管理者は自社の請求情報を閲覧できる
 *  - 企業管理者は自社のみ閲覧、他社へのアクセス不可
 *  - マスター管理者は ?company_id= で任意企業を閲覧
 *  - 通常管理者・企業管理者は /master/billing/* にアクセス不可
 *  - guardian/staff は /billing/* にアクセス不可
 *  - display_settings の allow_self_cancel=false で /billing/cancel は 403
 *  - display_settings の show_invoice_history=hidden で invoices は空配列
 *  - マスター管理者の updateDisplaySettings は monitoring_audit_logs を残す
 */
class B100_BillingApiPermissionTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{
     *   companyA: Company,
     *   companyB: Company,
     *   classA: Classroom,
     *   classB: Classroom,
     *   master: User,
     *   normalAdminA: User,
     *   companyAdminA: User,
     *   companyAdminB: User,
     *   staffA: User,
     *   guardianA: User,
     * }
     */
    private function fixture(): array
    {
        $companyA = Company::create(['name' => 'A社', 'subscription_status' => 'active', 'custom_amount' => 25000]);
        $companyB = Company::create(['name' => 'B社', 'subscription_status' => 'active']);

        $classA = Classroom::create(['classroom_name' => 'A教室', 'company_id' => $companyA->id, 'is_active' => true]);
        $classB = Classroom::create(['classroom_name' => 'B教室', 'company_id' => $companyB->id, 'is_active' => true]);

        $mkUser = fn(string $uname, string $type, array $extra = []) => User::create(array_merge([
            'username' => $uname,
            'password' => bcrypt('pass'),
            'full_name' => $uname,
            'user_type' => $type,
            'is_master' => false,
            'is_company_admin' => false,
            'is_active' => true,
        ], $extra));

        return [
            'companyA' => $companyA,
            'companyB' => $companyB,
            'classA' => $classA,
            'classB' => $classB,
            'master' => $mkUser('master_b100', 'admin', ['is_master' => true]),
            'normalAdminA' => $mkUser('admin_a_b100', 'admin', ['classroom_id' => $classA->id]),
            'companyAdminA' => $mkUser('cadmin_a_b100', 'admin', ['classroom_id' => $classA->id, 'is_company_admin' => true]),
            'companyAdminB' => $mkUser('cadmin_b_b100', 'admin', ['classroom_id' => $classB->id, 'is_company_admin' => true]),
            'staffA' => $mkUser('staff_a_b100', 'staff', ['classroom_id' => $classA->id]),
            'guardianA' => $mkUser('guardian_a_b100', 'guardian', ['classroom_id' => $classA->id]),
        ];
    }

    public function test_unauthenticated_billing_returns_401(): void
    {
        $this->getJson('/api/admin/billing/subscription')->assertStatus(401);
    }

    public function test_staff_cannot_access_billing(): void
    {
        $f = $this->fixture();
        $this->actingAs($f['staffA'])
            ->getJson('/api/admin/billing/subscription')
            ->assertStatus(403);
    }

    public function test_guardian_cannot_access_billing(): void
    {
        $f = $this->fixture();
        $this->actingAs($f['guardianA'])
            ->getJson('/api/admin/billing/subscription')
            ->assertStatus(403);
    }

    public function test_normal_admin_cannot_view_billing_subscription(): void
    {
        // 仕様: 通常管理者（is_master=false, is_company_admin=false）は
        // 自社の請求情報も閲覧不可。閲覧できるのは企業管理者とマスター管理者のみ。
        $f = $this->fixture();
        $this->actingAs($f['normalAdminA'])
            ->getJson('/api/admin/billing/subscription')
            ->assertStatus(403);
    }

    public function test_company_admin_can_view_own_company_subscription(): void
    {
        $f = $this->fixture();
        $res = $this->actingAs($f['companyAdminA'])
            ->getJson('/api/admin/billing/subscription')
            ->assertStatus(200);
        $res->assertJsonPath('data.company_id', $f['companyA']->id);
    }

    public function test_master_can_view_any_company_subscription_via_query_param(): void
    {
        $f = $this->fixture();
        $res = $this->actingAs($f['master'])
            ->getJson('/api/admin/billing/subscription?company_id='.$f['companyB']->id)
            ->assertStatus(200);
        $res->assertJsonPath('data.company_id', $f['companyB']->id);
    }

    public function test_company_admin_b_cannot_view_company_a_data(): void
    {
        $f = $this->fixture();
        // 企業管理者Bは自社（B社）のみ。?company_id= で他社指定しても自社が返るのが正
        $res = $this->actingAs($f['companyAdminB'])
            ->getJson('/api/admin/billing/subscription?company_id='.$f['companyA']->id)
            ->assertStatus(200);
        // company_id=A を指定したが、企業管理者の権限ではマスターでないので
        // resolveOwnCompany は自社（B社）を返す
        $res->assertJsonPath('data.company_id', $f['companyB']->id);
    }

    public function test_normal_admin_cannot_access_master_billing(): void
    {
        $f = $this->fixture();
        $this->actingAs($f['normalAdminA'])
            ->getJson('/api/admin/master/billing/overview')
            ->assertStatus(403);
    }

    public function test_company_admin_cannot_access_master_billing(): void
    {
        $f = $this->fixture();
        $this->actingAs($f['companyAdminA'])
            ->getJson('/api/admin/master/billing/overview')
            ->assertStatus(403);
    }

    public function test_master_can_access_master_billing_overview(): void
    {
        $f = $this->fixture();
        $res = $this->actingAs($f['master'])
            ->getJson('/api/admin/master/billing/overview')
            ->assertStatus(200);
        $res->assertJsonStructure(['data' => ['companies', 'status_counts']]);
    }

    public function test_self_cancel_is_blocked_when_display_settings_disallow(): void
    {
        $f = $this->fixture();
        $f['companyA']->update(['display_settings' => ['allow_self_cancel' => false]]);

        $this->actingAs($f['companyAdminA'])
            ->postJson('/api/admin/billing/cancel')
            ->assertStatus(403);
    }

    public function test_invoice_history_hidden_returns_empty_array(): void
    {
        $f = $this->fixture();
        Invoice::create([
            'company_id' => $f['companyA']->id,
            'stripe_invoice_id' => 'in_test_'.uniqid(),
            'status' => 'paid',
            'total' => 25000,
            'currency' => 'jpy',
            'period_start' => now()->subMonths(2),
        ]);

        $f['companyA']->update(['display_settings' => ['show_invoice_history' => 'hidden']]);

        $res = $this->actingAs($f['companyAdminA'])
            ->getJson('/api/admin/billing/invoices')
            ->assertStatus(200);
        $res->assertJsonPath('meta.hidden_by_master', true);
        $this->assertEmpty($res->json('data'));
    }

    public function test_invoice_pdf_blocked_when_download_disabled(): void
    {
        $f = $this->fixture();
        $invoice = Invoice::create([
            'company_id' => $f['companyA']->id,
            'stripe_invoice_id' => 'in_test_'.uniqid(),
            'status' => 'paid',
            'total' => 25000,
            'currency' => 'jpy',
            'invoice_pdf' => 'https://stripe.example/test.pdf',
        ]);

        $f['companyA']->update(['display_settings' => ['allow_invoice_download' => false]]);

        $this->actingAs($f['companyAdminA'])
            ->getJson('/api/admin/billing/invoices/'.$invoice->id.'/pdf')
            ->assertStatus(403);
    }

    public function test_master_update_display_settings_creates_audit_log(): void
    {
        $f = $this->fixture();

        $this->actingAs($f['master'])
            ->putJson('/api/admin/master/billing/companies/'.$f['companyA']->id.'/display-settings', [
                'plan_label' => 'A社特別プラン',
                'show_amount' => false,
                'allow_self_cancel' => true,
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.display_settings.plan_label', 'A社特別プラン')
            ->assertJsonPath('data.display_settings.show_amount', false);

        $this->assertDatabaseHas('master_admin_audit_logs', [
            'master_user_id' => $f['master']->id,
            'company_id' => $f['companyA']->id,
            'action' => 'update_display_settings',
        ]);

        $log = MasterAdminAuditLog::where('company_id', $f['companyA']->id)->latest('id')->first();
        $this->assertSame('A社特別プラン', $log->after['display_settings']['plan_label']);
    }

    public function test_master_update_feature_flags_creates_audit_log(): void
    {
        $f = $this->fixture();

        $this->actingAs($f['master'])
            ->putJson('/api/admin/master/billing/companies/'.$f['companyA']->id.'/feature-flags', [
                'feature_flags' => ['ai_analysis' => true, 'excel_export' => false],
            ])
            ->assertStatus(200);

        $f['companyA']->refresh();
        $this->assertTrue($f['companyA']->feature_flags['ai_analysis']);
        $this->assertFalse($f['companyA']->feature_flags['excel_export']);

        $this->assertDatabaseHas('master_admin_audit_logs', [
            'master_user_id' => $f['master']->id,
            'company_id' => $f['companyA']->id,
            'action' => 'update_feature_flags',
        ]);
    }

    public function test_subscription_payload_masks_amount_when_show_amount_false(): void
    {
        $f = $this->fixture();
        $f['companyA']->update([
            'display_settings' => ['show_amount' => false],
            'is_custom_pricing' => true,
        ]);

        $res = $this->actingAs($f['companyAdminA'])
            ->getJson('/api/admin/billing/subscription')
            ->assertStatus(200);

        $this->assertArrayNotHasKey('amount', $res->json('data'));
        $this->assertArrayNotHasKey('custom_amount', $res->json('data'));
    }

    public function test_subscription_payload_includes_announcement_when_set(): void
    {
        $f = $this->fixture();
        $f['companyA']->update([
            'display_settings' => [
                'announcement' => [
                    'level' => 'warning',
                    'title' => '重要',
                    'body' => '来月から価格改定',
                    'shown_until' => now()->addMonths(2)->toDateString(),
                ],
            ],
        ]);

        $res = $this->actingAs($f['companyAdminA'])
            ->getJson('/api/admin/billing/subscription')
            ->assertStatus(200);

        $this->assertSame('重要', $res->json('data.announcement.title'));
    }

    public function test_announcement_expired_is_filtered_out(): void
    {
        $f = $this->fixture();
        $f['companyA']->update([
            'display_settings' => [
                'announcement' => [
                    'level' => 'info',
                    'title' => '古いお知らせ',
                    'body' => '過去',
                    'shown_until' => now()->subDay()->toDateString(),
                ],
            ],
        ]);

        $res = $this->actingAs($f['companyAdminA'])
            ->getJson('/api/admin/billing/subscription')
            ->assertStatus(200);

        $this->assertNull($res->json('data.announcement'));
    }
}
