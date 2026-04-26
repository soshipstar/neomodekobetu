<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Invoice;
use App\Models\SubscriptionEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Stripe\Event;
use Stripe\Webhook;
use UnexpectedValueException;

/**
 * Stripe Webhook 受信エンドポイント。
 *
 * 処理方針:
 *  1. シークレットによる署名検証（CASHIER の webhook secret を流用）
 *  2. subscription_events に冪等記録（stripe_event_id unique）
 *  3. 種別ごとに companies / invoices を同期
 *  4. processed_at を更新（処理失敗時は error にメッセージを残す）
 *
 * 認証は不要（middleware で auth:sanctum を外す）。
 * 署名検証で「Stripe からの正規リクエストか」を担保する。
 */
class StripeWebhookController extends Controller
{
    public function handle(Request $request): JsonResponse
    {
        $payload = $request->getContent();
        $signature = $request->header('Stripe-Signature');
        $secret = config('cashier.webhook.secret');

        try {
            $event = $secret
                ? Webhook::constructEvent($payload, $signature ?? '', $secret, (int) config('cashier.webhook.tolerance', 300))
                : Event::constructFrom(json_decode($payload, true) ?: []);
        } catch (UnexpectedValueException|\Stripe\Exception\SignatureVerificationException $e) {
            return response()->json(['message' => 'Invalid signature'], 400);
        }

        // 冪等性: 同じ stripe_event_id は2回処理しない
        $stored = SubscriptionEvent::firstOrCreate(
            ['stripe_event_id' => $event->id],
            [
                'type' => $event->type,
                'payload' => $event->toArray(),
                'company_id' => $this->resolveCompanyId($event),
            ]
        );

        if ($stored->processed_at) {
            return response()->json(['received' => true, 'duplicate' => true]);
        }

        try {
            $this->dispatch($event);
            $stored->update(['processed_at' => now()]);
        } catch (\Throwable $e) {
            $stored->update(['error' => mb_substr($e->getMessage(), 0, 1000)]);
            // Stripe にリトライさせるため 500 を返す
            return response()->json(['received' => false, 'error' => 'processing_failed'], 500);
        }

        return response()->json(['received' => true]);
    }

    /**
     * イベント type に応じてハンドラを呼び分ける。
     */
    private function dispatch(Event $event): void
    {
        match ($event->type) {
            'invoice.created',
            'invoice.finalized',
            'invoice.paid',
            'invoice.payment_failed',
            'invoice.updated',
            'invoice.voided' => $this->syncInvoice($event),
            'customer.subscription.created',
            'customer.subscription.updated',
            'customer.subscription.deleted' => $this->syncSubscription($event),
            default => null, // 他はログだけ残して無視
        };
    }

    /**
     * Invoiceイベントから invoices テーブルを upsert する。
     */
    private function syncInvoice(Event $event): void
    {
        $obj = $this->objectAsArray($event);
        $customerId = data_get($obj, 'customer');
        $company = $this->companyByStripeId(is_string($customerId) ? $customerId : null);
        if (!$company) {
            return; // 不明な顧客のイベントは無視
        }

        Invoice::updateOrCreate(
            ['stripe_invoice_id' => data_get($obj, 'id')],
            [
                'company_id' => $company->id,
                'stripe_subscription_id' => data_get($obj, 'subscription'),
                'number' => data_get($obj, 'number'),
                'status' => data_get($obj, 'status', 'open'),
                'amount_due' => (int) data_get($obj, 'amount_due', 0),
                'amount_paid' => (int) data_get($obj, 'amount_paid', 0),
                'amount_remaining' => (int) data_get($obj, 'amount_remaining', 0),
                'subtotal' => (int) data_get($obj, 'subtotal', 0),
                'tax' => data_get($obj, 'tax') !== null ? (int) data_get($obj, 'tax') : null,
                'total' => (int) data_get($obj, 'total', 0),
                'currency' => data_get($obj, 'currency', 'jpy'),
                'hosted_invoice_url' => data_get($obj, 'hosted_invoice_url'),
                'invoice_pdf' => data_get($obj, 'invoice_pdf'),
                'period_start' => $this->ts(data_get($obj, 'period_start')),
                'period_end' => $this->ts(data_get($obj, 'period_end')),
                'due_date' => $this->ts(data_get($obj, 'due_date')),
                'finalized_at' => $this->ts(data_get($obj, 'status_transitions.finalized_at')),
                'paid_at' => $this->ts(data_get($obj, 'status_transitions.paid_at')),
            ]
        );
    }

    /**
     * Subscriptionイベントから companies の冗長カラムを更新する。
     */
    private function syncSubscription(Event $event): void
    {
        $obj = $this->objectAsArray($event);
        $customerId = data_get($obj, 'customer');
        $company = $this->companyByStripeId(is_string($customerId) ? $customerId : null);
        if (!$company) {
            return;
        }

        $priceId = data_get($obj, 'items.data.0.price.id');

        $company->update([
            'subscription_status' => data_get($obj, 'status'),
            'current_price_id' => $priceId ?? $company->current_price_id,
            'current_period_end' => $this->ts(data_get($obj, 'current_period_end')),
            'cancel_at_period_end' => (bool) data_get($obj, 'cancel_at_period_end', false),
            'trial_ends_at' => $this->ts(data_get($obj, 'trial_end')),
        ]);
    }

    private function companyByStripeId(?string $stripeId): ?Company
    {
        if (!$stripeId) {
            return null;
        }
        return Company::where('stripe_id', $stripeId)->first();
    }

    private function resolveCompanyId(Event $event): ?int
    {
        $obj = $this->objectAsArray($event);
        $stripeId = data_get($obj, 'customer');
        if (!is_string($stripeId)) {
            return null;
        }
        return Company::where('stripe_id', $stripeId)->value('id');
    }

    /**
     * Stripe SDK の Event オブジェクト（StripeObject 階層）を配列に変換して
     * data_get でドット記法アクセスできるようにする。
     */
    private function objectAsArray(Event $event): array
    {
        $data = $event->toArray();
        return data_get($data, 'data.object', []) ?: [];
    }

    private function ts(int|string|null $value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_int($value)) {
            return Carbon::createFromTimestamp($value);
        }
        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }
}
