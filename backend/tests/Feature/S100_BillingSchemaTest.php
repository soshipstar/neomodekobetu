<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Invoice;
use App\Models\MasterAdminAuditLog;
use App\Models\SubscriptionEvent;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Laravel\Cashier\Billable;
use Laravel\Cashier\Cashier;
use Tests\TestCase;

/**
 * S-100: 課金スキーマ移行（Stripe / Cashier 連携）
 * - companies に Billable 標準カラムと表示制御カラムが追加される
 * - subscriptions / subscription_items テーブルが Cashier 標準で作成され、FKは company_id
 * - invoices / subscription_events / master_admin_audit_logs テーブルが追加される
 * - Company モデルが Billable trait を持ち、Cashier の Customer Model に設定される
 */
class S100_BillingSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_companies_has_cashier_billable_columns(): void
    {
        $expected = ['stripe_id', 'pm_type', 'pm_last_four', 'trial_ends_at'];
        foreach ($expected as $column) {
            $this->assertTrue(
                Schema::hasColumn('companies', $column),
                "Company should have Cashier billable column: {$column}"
            );
        }
    }

    public function test_companies_has_billing_management_columns(): void
    {
        $expected = [
            'subscription_status',
            'current_price_id',
            'custom_amount',
            'is_custom_pricing',
            'current_period_end',
            'cancel_at_period_end',
            'contract_started_at',
            'contract_notes',
            'contract_document_path',
            'display_settings',
            'feature_flags',
        ];
        foreach ($expected as $column) {
            $this->assertTrue(
                Schema::hasColumn('companies', $column),
                "Company should have billing management column: {$column}"
            );
        }
    }

    public function test_subscriptions_table_uses_company_id(): void
    {
        $this->assertTrue(Schema::hasTable('subscriptions'));
        $this->assertTrue(
            Schema::hasColumn('subscriptions', 'company_id'),
            'subscriptions FK must be company_id (not user_id) since Billable model is Company'
        );
        $this->assertFalse(
            Schema::hasColumn('subscriptions', 'user_id'),
            'subscriptions.user_id should not exist after migration to companies'
        );
    }

    public function test_subscription_items_table_exists_with_meter_columns(): void
    {
        $this->assertTrue(Schema::hasTable('subscription_items'));
        foreach (['subscription_id', 'stripe_price', 'meter_id', 'meter_event_name'] as $col) {
            $this->assertTrue(
                Schema::hasColumn('subscription_items', $col),
                "subscription_items should have column: {$col}"
            );
        }
    }

    public function test_invoices_table_has_required_columns(): void
    {
        $this->assertTrue(Schema::hasTable('invoices'));
        foreach ([
            'company_id', 'stripe_invoice_id', 'status',
            'amount_due', 'amount_paid', 'currency',
            'hosted_invoice_url', 'invoice_pdf',
            'period_start', 'period_end', 'paid_at',
        ] as $col) {
            $this->assertTrue(
                Schema::hasColumn('invoices', $col),
                "invoices should have column: {$col}"
            );
        }
    }

    public function test_subscription_events_table_has_required_columns(): void
    {
        $this->assertTrue(Schema::hasTable('subscription_events'));
        foreach (['stripe_event_id', 'type', 'company_id', 'payload', 'processed_at'] as $col) {
            $this->assertTrue(
                Schema::hasColumn('subscription_events', $col),
                "subscription_events should have column: {$col}"
            );
        }
    }

    public function test_master_admin_audit_logs_has_required_columns(): void
    {
        $this->assertTrue(Schema::hasTable('master_admin_audit_logs'));
        foreach (['master_user_id', 'company_id', 'action', 'before', 'after'] as $col) {
            $this->assertTrue(
                Schema::hasColumn('master_admin_audit_logs', $col),
                "master_admin_audit_logs should have column: {$col}"
            );
        }
    }

    public function test_company_has_billable_trait(): void
    {
        $this->assertContains(
            Billable::class,
            class_uses_recursive(Company::class),
            'Company must use Billable trait'
        );
    }

    public function test_cashier_customer_model_is_company(): void
    {
        $this->assertSame(
            Company::class,
            Cashier::$customerModel,
            'Cashier::$customerModel should be Company (set via Cashier::useCustomerModel)'
        );
    }

    public function test_company_subscriptions_relation_works_with_company_id_fk(): void
    {
        $company = Company::factory()->create();

        // Billable trait の subscriptions() リレーションが
        // company_id を FK として正しく動作することを確認
        $relation = $company->subscriptions();
        $this->assertSame('company_id', $relation->getForeignKeyName());
    }

    public function test_company_invoice_records_relation_returns_local_invoices(): void
    {
        $company = Company::factory()->create();

        // Billable::invoices() は Stripe API 経由なので、ローカルキャッシュは invoiceRecords()
        $relation = $company->invoiceRecords();
        $this->assertInstanceOf(HasMany::class, $relation);
        $this->assertSame(Invoice::class, $relation->getRelated()::class);
    }

    public function test_company_billing_columns_are_cast_correctly(): void
    {
        $company = Company::factory()->create([
            'is_custom_pricing' => true,
            'cancel_at_period_end' => false,
            'display_settings' => ['plan_label' => 'Acme特別プラン', 'show_amount' => true],
            'feature_flags' => ['ai_analysis' => true],
        ]);
        $company->refresh();

        $this->assertTrue($company->is_custom_pricing);
        $this->assertFalse($company->cancel_at_period_end);
        $this->assertSame('Acme特別プラン', $company->display_settings['plan_label']);
        $this->assertTrue($company->feature_flags['ai_analysis']);
    }

    public function test_invoice_amount_columns_are_integer_cast(): void
    {
        $company = Company::factory()->create();
        $invoice = Invoice::create([
            'company_id' => $company->id,
            'stripe_invoice_id' => 'in_test_'.uniqid(),
            'status' => 'paid',
            'amount_due' => 25000,
            'amount_paid' => 25000,
            'amount_remaining' => 0,
            'subtotal' => 25000,
            'total' => 25000,
            'currency' => 'jpy',
        ]);
        $invoice->refresh();

        $this->assertSame(25000, $invoice->amount_paid);
        $this->assertSame('jpy', $invoice->currency);
    }

    public function test_subscription_event_payload_is_json_cast(): void
    {
        $event = SubscriptionEvent::create([
            'stripe_event_id' => 'evt_test_'.uniqid(),
            'type' => 'invoice.paid',
            'payload' => ['object' => ['id' => 'in_xyz']],
        ]);
        $event->refresh();

        $this->assertIsArray($event->payload);
        $this->assertSame('in_xyz', $event->payload['object']['id']);
    }

    public function test_master_admin_audit_log_has_no_updated_at(): void
    {
        // append-only のため updated_at は使わない
        $this->assertNull(MasterAdminAuditLog::UPDATED_AT);
    }
}
