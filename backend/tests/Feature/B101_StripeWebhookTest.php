<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Invoice;
use App\Models\SubscriptionEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

/**
 * B-101: Stripe Webhook の冪等性・companies 同期・invoices upsert
 *
 * 検証:
 *  - 同一 stripe_event_id は二重処理されない
 *  - customer.subscription.updated で companies の status / current_period_end が更新される
 *  - invoice.paid で invoices テーブルに upsert される
 *  - 不明な customer は無視される（subscription_events には記録される）
 *  - シークレット未設定（dev環境）ではシグネチャ検証を skip
 *  - 不正シグネチャは 400
 */
class B101_StripeWebhookTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Webhook secret 未設定（=シグネチャ検証スキップ）モードでテスト
        Config::set('cashier.webhook.secret', null);
    }

    public function test_subscription_updated_event_syncs_company(): void
    {
        $company = Company::create([
            'name' => 'Webhook対象社',
            'stripe_id' => 'cus_test_aaa',
        ]);

        $this->postJson('/api/webhooks/stripe', $this->subscriptionUpdatedEvent('evt_001', 'cus_test_aaa', 'active', 1735689600))
            ->assertStatus(200)
            ->assertJson(['received' => true]);

        $company->refresh();
        $this->assertSame('active', $company->subscription_status);
        $this->assertNotNull($company->current_period_end);
        $this->assertSame('price_xyz', $company->current_price_id);
    }

    public function test_duplicate_event_id_is_idempotent(): void
    {
        Company::create(['name' => 'X社', 'stripe_id' => 'cus_dup']);

        $payload = $this->subscriptionUpdatedEvent('evt_dup_001', 'cus_dup', 'active', 1735689600);

        $this->postJson('/api/webhooks/stripe', $payload)->assertStatus(200);
        $second = $this->postJson('/api/webhooks/stripe', $payload)->assertStatus(200);

        $second->assertJson(['received' => true, 'duplicate' => true]);
        $this->assertSame(1, SubscriptionEvent::where('stripe_event_id', 'evt_dup_001')->count());
    }

    public function test_invoice_paid_event_upserts_invoice_row(): void
    {
        $company = Company::create(['name' => 'Y社', 'stripe_id' => 'cus_inv_001']);

        $this->postJson('/api/webhooks/stripe', $this->invoicePaidEvent('evt_inv_001', 'in_001', 'cus_inv_001', 25000))
            ->assertStatus(200);

        $invoice = Invoice::where('stripe_invoice_id', 'in_001')->first();
        $this->assertNotNull($invoice);
        $this->assertSame($company->id, $invoice->company_id);
        $this->assertSame(25000, $invoice->total);
        $this->assertSame('paid', $invoice->status);
    }

    public function test_unknown_customer_is_ignored_but_logged(): void
    {
        $this->postJson('/api/webhooks/stripe', $this->subscriptionUpdatedEvent('evt_unknown', 'cus_does_not_exist', 'active', 1735689600))
            ->assertStatus(200);

        $this->assertDatabaseHas('subscription_events', [
            'stripe_event_id' => 'evt_unknown',
            'company_id' => null,
        ]);
    }

    public function test_invalid_signature_returns_400_when_secret_set(): void
    {
        Config::set('cashier.webhook.secret', 'whsec_test_secret');

        $this->withHeaders(['Stripe-Signature' => 'invalid'])
            ->postJson('/api/webhooks/stripe', ['id' => 'evt_x', 'type' => 'invoice.paid'])
            ->assertStatus(400);
    }

    public function test_event_processed_at_is_set_after_handling(): void
    {
        Company::create(['name' => 'Z社', 'stripe_id' => 'cus_proc_001']);
        $this->postJson('/api/webhooks/stripe', $this->subscriptionUpdatedEvent('evt_proc_001', 'cus_proc_001', 'active', 1735689600))
            ->assertStatus(200);

        $event = SubscriptionEvent::where('stripe_event_id', 'evt_proc_001')->first();
        $this->assertNotNull($event->processed_at);
        $this->assertNull($event->error);
    }

    private function subscriptionUpdatedEvent(string $eventId, string $customerId, string $status, int $periodEnd): array
    {
        return [
            'id' => $eventId,
            'object' => 'event',
            'type' => 'customer.subscription.updated',
            'api_version' => '2024-06-20',
            'created' => time(),
            'data' => [
                'object' => [
                    'id' => 'sub_test_001',
                    'object' => 'subscription',
                    'customer' => $customerId,
                    'status' => $status,
                    'current_period_end' => $periodEnd,
                    'cancel_at_period_end' => false,
                    'trial_end' => null,
                    'items' => [
                        'data' => [[
                            'price' => ['id' => 'price_xyz'],
                        ]],
                    ],
                ],
            ],
        ];
    }

    private function invoicePaidEvent(string $eventId, string $invoiceId, string $customerId, int $amount): array
    {
        return [
            'id' => $eventId,
            'object' => 'event',
            'type' => 'invoice.paid',
            'api_version' => '2024-06-20',
            'created' => time(),
            'data' => [
                'object' => [
                    'id' => $invoiceId,
                    'object' => 'invoice',
                    'customer' => $customerId,
                    'subscription' => 'sub_test',
                    'number' => 'INV-001',
                    'status' => 'paid',
                    'amount_due' => $amount,
                    'amount_paid' => $amount,
                    'amount_remaining' => 0,
                    'subtotal' => $amount,
                    'tax' => null,
                    'total' => $amount,
                    'currency' => 'jpy',
                    'hosted_invoice_url' => 'https://stripe.example/hosted',
                    'invoice_pdf' => 'https://stripe.example/pdf',
                    'period_start' => 1735603200,
                    'period_end' => 1735689600,
                    'due_date' => null,
                    'status_transitions' => [
                        'finalized_at' => 1735603300,
                        'paid_at' => 1735603400,
                    ],
                ],
            ],
        ];
    }
}
