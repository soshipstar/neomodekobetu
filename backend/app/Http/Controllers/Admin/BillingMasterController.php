<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\MasterAdminAuditLog;
use App\Policies\BillingPolicy;
use App\Services\Billing\DisplaySettingsFilter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Stripe\Stripe;
use Stripe\StripeClient;

/**
 * マスター管理者専用の課金管理API。全企業の契約・価格・表示制御を扱う。
 *
 * 操作はすべて監査ログ（master_admin_audit_logs）に記録する。
 */
class BillingMasterController extends Controller
{
    public function __construct(private readonly BillingPolicy $policy) {}

    /**
     * 全企業の契約状況一覧（MRRサマリ・ステータス別）。
     */
    public function overview(Request $request): JsonResponse
    {
        if ($deny = $this->requireMaster($request)) return $deny;

        $companies = Company::query()
            ->select(['id', 'name', 'code', 'stripe_id', 'subscription_status',
                'current_price_id', 'custom_amount', 'is_custom_pricing',
                'current_period_end', 'cancel_at_period_end',
                'trial_ends_at', 'contract_started_at'])
            ->orderBy('name')
            ->get();

        // 各企業の「現在オープン中の請求合計」と「当月支払い済み合計」を計算する。
        // open    : status = open (確定済み・未払い、または auto_collect 中)
        // paid_mtd: status = paid AND paid_at が当月内
        // 月額 + スポット の実際の請求総額が一覧で把握できる。
        $now = now();
        $invoiceTotals = Invoice::selectRaw("
                company_id,
                SUM(CASE WHEN status = 'open' THEN total ELSE 0 END) AS open_total,
                SUM(CASE WHEN status = 'paid' AND paid_at >= ? THEN total ELSE 0 END) AS paid_mtd_total
            ", [$now->copy()->startOfMonth()])
            ->whereIn('company_id', $companies->pluck('id'))
            ->groupBy('company_id')
            ->get()
            ->keyBy('company_id');

        $companies = $companies->map(function (Company $c) use ($invoiceTotals) {
            $row = $invoiceTotals->get($c->id);
            $arr = $c->toArray();
            $arr['open_invoice_total'] = (int) ($row->open_total ?? 0);
            $arr['paid_mtd_total'] = (int) ($row->paid_mtd_total ?? 0);
            return $arr;
        });

        $statusCounts = $companies->groupBy('subscription_status')
            ->map->count();

        return response()->json([
            'success' => true,
            'data' => [
                'companies' => $companies,
                'status_counts' => $statusCounts,
            ],
        ]);
    }

    /**
     * 単一企業の契約詳細（マスター視点：内部メモ・全InvoiceなどFull）。
     *
     * Stripe Webhook が未着 / 開発環境で stripe listen していない場合でも
     * Invoice 一覧が見えるよう、ここで Stripe API から直接取得して
     * ローカル invoices テーブルに upsert する（pull 同期）。
     */
    public function show(Request $request, Company $company): JsonResponse
    {
        if ($deny = $this->requireMaster($request)) return $deny;

        if ($company->stripe_id) {
            $this->syncSubscriptionFromStripe($company);
            $this->syncInvoicesFromStripe($company);
            $company->refresh();
        }

        $invoices = Invoice::where('company_id', $company->id)
            ->orderByDesc('id')
            ->limit(60)
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'company' => $company->makeVisible(['contract_notes']),
                'invoices' => $invoices,
                'display_settings' => DisplaySettingsFilter::settingsFor($company),
            ],
        ]);
    }

    /**
     * Stripe側のSubscriptionから companies の冗長カラムを更新する（Webhookの代わりの保険）。
     * 「次回請求日」（current_period_end）など、画面で見える主要カラムを更新する。
     */
    private function syncSubscriptionFromStripe(Company $company): void
    {
        try {
            $stripe = $this->stripe();
            $list = $stripe->subscriptions->all([
                'customer' => $company->stripe_id,
                'status' => 'all',
                'limit' => 5,
            ]);
            // 優先度: active > trialing > past_due > その他。最後の subscription を採用。
            $sub = collect($list->data)
                ->sortByDesc(fn ($s) => match ($s->status) {
                    'active' => 4,
                    'trialing' => 3,
                    'past_due' => 2,
                    'incomplete' => 1,
                    default => 0,
                })
                ->first();
            if (!$sub) {
                return;
            }
            $arr = $sub->toArray();
            $priceId = data_get($arr, 'items.data.0.price.id');
            // Stripe API 2024-09 以降、Subscription.current_period_end は廃止予定で
            // 値は items[].current_period_end / items[].current_period_start に移った。
            // 旧 / 新どちらでも拾えるようにフォールバックする。
            $periodEnd = data_get($arr, 'current_period_end')
                ?? data_get($arr, 'items.data.0.current_period_end');
            $trialEnd = data_get($arr, 'trial_end');
            $company->update([
                'subscription_status' => $sub->status,
                'current_price_id' => $priceId ?? $company->current_price_id,
                'current_period_end' => $periodEnd ? \Illuminate\Support\Carbon::createFromTimestamp((int) $periodEnd) : null,
                'cancel_at_period_end' => (bool) data_get($arr, 'cancel_at_period_end', false),
                'trial_ends_at' => $trialEnd ? \Illuminate\Support\Carbon::createFromTimestamp((int) $trialEnd) : null,
            ]);
        } catch (\Throwable $e) {
            // 同期失敗してもローカル値で表示は続ける
        }
    }

    /**
     * Stripe側のInvoiceをローカルテーブルへ upsert する（Webhookの代わりの保険）。
     */
    private function syncInvoicesFromStripe(Company $company): void
    {
        try {
            $stripe = $this->stripe();
            $list = $stripe->invoices->all(['customer' => $company->stripe_id, 'limit' => 50]);
            foreach ($list->data as $obj) {
                Invoice::updateOrCreate(
                    ['stripe_invoice_id' => $obj->id],
                    [
                        'company_id' => $company->id,
                        'stripe_subscription_id' => $obj->subscription ?? null,
                        'number' => $obj->number ?? null,
                        'status' => $obj->status ?? 'open',
                        'amount_due' => (int) ($obj->amount_due ?? 0),
                        'amount_paid' => (int) ($obj->amount_paid ?? 0),
                        'amount_remaining' => (int) ($obj->amount_remaining ?? 0),
                        'subtotal' => (int) ($obj->subtotal ?? 0),
                        'tax' => isset($obj->tax) ? (int) $obj->tax : null,
                        'total' => (int) ($obj->total ?? 0),
                        'currency' => $obj->currency ?? 'jpy',
                        'hosted_invoice_url' => $obj->hosted_invoice_url ?? null,
                        'invoice_pdf' => $obj->invoice_pdf ?? null,
                        'period_start' => isset($obj->period_start) ? \Illuminate\Support\Carbon::createFromTimestamp($obj->period_start) : null,
                        'period_end' => isset($obj->period_end) ? \Illuminate\Support\Carbon::createFromTimestamp($obj->period_end) : null,
                        'due_date' => isset($obj->due_date) ? \Illuminate\Support\Carbon::createFromTimestamp($obj->due_date) : null,
                        'finalized_at' => isset($obj->status_transitions->finalized_at) ? \Illuminate\Support\Carbon::createFromTimestamp($obj->status_transitions->finalized_at) : null,
                        'paid_at' => isset($obj->status_transitions->paid_at) ? \Illuminate\Support\Carbon::createFromTimestamp($obj->status_transitions->paid_at) : null,
                    ]
                );
            }
        } catch (\Throwable $e) {
            // 同期失敗してもローカルキャッシュから表示は続ける
        }
    }

    /**
     * Stripe Customer の請求先情報（名前・メール・電話・住所）を更新する。
     * 請求書の「Bill To」欄や領収書に表示される情報。
     * Customer 未作成の場合は createAsStripeCustomer で作成しつつセットする。
     */
    public function updateCustomerInfo(Request $request, Company $company): JsonResponse
    {
        if ($deny = $this->requireMaster($request)) return $deny;

        $validated = $request->validate([
            'name' => 'nullable|string|max:200',
            'email' => 'nullable|email|max:200',
            'phone' => 'nullable|string|max:50',
            'address' => 'nullable|array',
            'address.line1' => 'nullable|string|max:200',
            'address.line2' => 'nullable|string|max:200',
            'address.city' => 'nullable|string|max:100',
            'address.state' => 'nullable|string|max:100',
            'address.postal_code' => 'nullable|string|max:20',
            'address.country' => 'nullable|string|size:2',
        ]);

        $payload = array_filter([
            'name' => $validated['name'] ?? null,
            'email' => $validated['email'] ?? null,
            'phone' => $validated['phone'] ?? null,
        ], fn ($v) => $v !== null && $v !== '');

        if (!empty($validated['address'])) {
            $address = array_filter($validated['address'], fn ($v) => $v !== null && $v !== '');
            if (!empty($address)) {
                // Stripe は country が無いと address 全体を保存しない。デフォルト JP。
                $address['country'] = $address['country'] ?? 'JP';
                $payload['address'] = $address;
            }
        }

        if (empty($payload)) {
            return response()->json(['success' => false, 'message' => '更新する項目がありません。'], 422);
        }

        $before = [
            'name' => $company->name,
            'stripe_id' => $company->stripe_id,
        ];

        if (!$company->stripe_id) {
            $company->createAsStripeCustomer($payload);
        } else {
            $company->updateStripeCustomer($payload);
        }

        $this->log($request, $company, 'update_customer_info', $before, $payload);

        $stripe = $this->stripe();
        $customer = $stripe->customers->retrieve($company->stripe_id);

        return response()->json([
            'success' => true,
            'data' => [
                'name' => $customer->name,
                'email' => $customer->email,
                'phone' => $customer->phone,
                'address' => $customer->address,
            ],
            'message' => '請求先情報を更新しました。',
        ]);
    }

    /**
     * Stripe Customer の現在の請求先情報を取得する（編集画面の初期値用）。
     */
    public function customerInfo(Request $request, Company $company): JsonResponse
    {
        if ($deny = $this->requireMaster($request)) return $deny;

        if (!$company->stripe_id) {
            return response()->json([
                'success' => true,
                'data' => ['name' => $company->name, 'email' => null, 'phone' => null, 'address' => null],
            ]);
        }

        $stripe = $this->stripe();
        $customer = $stripe->customers->retrieve($company->stripe_id);

        return response()->json([
            'success' => true,
            'data' => [
                'name' => $customer->name,
                'email' => $customer->email,
                'phone' => $customer->phone,
                'address' => $customer->address ? $customer->address->toArray() : null,
            ],
        ]);
    }

    /**
     * 企業のカスタム価格を作成・更新する。
     * Stripe側で新しい Price を作成し、Subscription があれば更新する。
     * 既存 Price は編集不可なので、新Price + subscription.update が標準。
     */
    public function updatePrice(Request $request, Company $company): JsonResponse
    {
        if ($deny = $this->requireMaster($request)) return $deny;

        $validated = $request->validate([
            'amount' => 'required|integer|min:0',
            'tax_mode' => 'sometimes|string|in:inclusive,exclusive',
            'interval' => 'sometimes|string|in:month,year',
            'product_id' => 'sometimes|string',
            'apply_immediately' => 'sometimes|boolean',
        ]);

        $taxInclusive = ($validated['tax_mode'] ?? 'inclusive') === 'inclusive';
        $stripeAmount = $this->toStripeAmount((int) $validated['amount'], $taxInclusive);

        $stripe = $this->stripe();
        $product = $validated['product_id'] ?? config('billing.default_product_id');

        $price = $stripe->prices->create([
            'currency' => 'jpy',
            'unit_amount' => $stripeAmount,
            'recurring' => ['interval' => $validated['interval'] ?? 'month'],
            'product' => $product,
            'metadata' => [
                'company_id' => (string) $company->id,
                'kind' => 'custom',
                'tax_mode' => $taxInclusive ? 'inclusive' : 'exclusive',
                'input_amount' => (string) $validated['amount'],
            ],
        ]);

        $before = $company->only(['current_price_id', 'custom_amount', 'is_custom_pricing', 'tax_inclusive']);

        $company->update([
            'current_price_id' => $price->id,
            'custom_amount' => $validated['amount'],
            'is_custom_pricing' => true,
            'tax_inclusive' => $taxInclusive,
        ]);

        $sub = $company->subscription();
        if ($sub && ($validated['apply_immediately'] ?? true)) {
            $sub->swapAndInvoice($price->id);
        }

        $this->log($request, $company, 'update_price', $before, $company->only(['current_price_id', 'custom_amount', 'is_custom_pricing']));

        return response()->json([
            'success' => true,
            'data' => [
                'price_id' => $price->id,
                'amount' => $validated['amount'],
            ],
            'message' => 'カスタム価格を設定しました。',
        ]);
    }

    /**
     * 企業のサブスクリプションを開始する（既存価格 or カスタム価格）。
     */
    public function subscribe(Request $request, Company $company): JsonResponse
    {
        if ($deny = $this->requireMaster($request)) return $deny;

        $validated = $request->validate([
            'price_id' => 'required|string',
            'trial_days' => 'sometimes|integer|min:0|max:365',
            'billing_day' => 'sometimes|integer|min:1|max:28',
            'payment_method' => 'sometimes|string',
        ]);

        if ($company->subscription() && $company->subscription()->active()) {
            return response()->json(['success' => false, 'message' => '既に有効な契約があります。'], 422);
        }

        $builder = $company->newSubscription('default', $validated['price_id']);

        // 「毎月X日に請求」を指定された場合、その日までを trial として無料化することで
        // Stripe 上の billing cycle anchor を指定日に揃える。
        // billing_day と trial_days が両方来た場合は明示の trial_days を優先。
        if (!empty($validated['trial_days'])) {
            $builder->trialDays((int) $validated['trial_days']);
        } elseif (!empty($validated['billing_day'])) {
            $now = now();
            $anchor = $now->copy()->day((int) $validated['billing_day'])->startOfDay();
            if (!$anchor->isFuture()) {
                $anchor = $anchor->addMonth();
            }
            $builder->trialUntil($anchor);
        }

        if (!empty($validated['payment_method'])) {
            $sub = $builder->create($validated['payment_method']);
        } else {
            // 後で Customer Portal で支払い方法を登録させる
            $sub = $builder->create();
        }

        $company->update([
            'current_price_id' => $validated['price_id'],
            'contract_started_at' => $company->contract_started_at ?? now(),
        ]);

        // Webhook 未受信でも subscription_status / current_period_end を即時反映
        $this->syncSubscriptionFromStripe($company);
        $company->refresh();

        $this->log($request, $company, 'subscribe', null, [
            'price_id' => $validated['price_id'],
            'subscription_id' => $sub->stripe_id,
        ]);

        return response()->json([
            'success' => true,
            'data' => ['subscription_id' => $sub->stripe_id],
            'message' => '契約を開始しました。',
        ]);
    }

    /**
     * マスター管理者による強制解約（即時 or 期間末）。
     */
    public function cancel(Request $request, Company $company): JsonResponse
    {
        if ($deny = $this->requireMaster($request)) return $deny;

        $validated = $request->validate([
            'mode' => 'sometimes|string|in:end_of_period,now',
        ]);
        $mode = $validated['mode'] ?? 'end_of_period';

        $sub = $company->subscription();
        if (!$sub) {
            return response()->json(['success' => false, 'message' => '契約がありません。'], 422);
        }

        $before = ['cancel_at_period_end' => (bool) $company->cancel_at_period_end];

        if ($mode === 'now') {
            $sub->cancelNow();
        } else {
            $sub->cancel();
            $company->update(['cancel_at_period_end' => true]);
        }

        $this->log($request, $company, 'cancel_subscription', $before, ['mode' => $mode]);

        return response()->json([
            'success' => true,
            'message' => '解約を実行しました。',
        ]);
    }

    /**
     * スポット請求書を発行（初期費用や追加機能などの都度請求）。
     */
    public function spotInvoice(Request $request, Company $company): JsonResponse
    {
        if ($deny = $this->requireMaster($request)) return $deny;

        $validated = $request->validate([
            'amount' => 'required|integer|min:1',
            'tax_mode' => 'sometimes|string|in:inclusive,exclusive',
            'description' => 'required|string|max:255',
            'auto_advance' => 'sometimes|boolean',
        ]);

        if (!$company->stripe_id) {
            return response()->json(['success' => false, 'message' => 'Stripe顧客が未作成です。'], 422);
        }

        $taxInclusive = ($validated['tax_mode'] ?? 'inclusive') === 'inclusive';
        $stripeAmount = $this->toStripeAmount((int) $validated['amount'], $taxInclusive);

        $stripe = $this->stripe();

        // Stripe API 2022-11-15 以降は pending_invoice_items_behavior のデフォルトが
        // 'exclude' のため、Invoice 作成時に明示的に 'include' を指定しないと
        // 直前に作った InvoiceItem が紐付かず、合計 ¥0 の Invoice が生成されてしまう。
        // 確実に紐付けるため、Invoice を先に作って InvoiceItem に invoice ID を渡し、
        // 最後に finalize する流れにする。
        $invoice = $stripe->invoices->create([
            'customer' => $company->stripe_id,
            'auto_advance' => $validated['auto_advance'] ?? true,
            'collection_method' => 'charge_automatically',
            'pending_invoice_items_behavior' => 'exclude',
            'metadata' => [
                'company_id' => (string) $company->id,
                'kind' => 'spot',
            ],
        ]);

        $stripe->invoiceItems->create([
            'customer' => $company->stripe_id,
            'invoice' => $invoice->id,
            'amount' => $stripeAmount,
            'currency' => 'jpy',
            'description' => $validated['description']
                . ($taxInclusive ? '' : '（税込）'),
            'metadata' => [
                'tax_mode' => $taxInclusive ? 'inclusive' : 'exclusive',
                'input_amount' => (string) $validated['amount'],
            ],
        ]);

        if (($validated['auto_advance'] ?? true)) {
            $invoice = $stripe->invoices->finalizeInvoice($invoice->id);
        }

        $this->log($request, $company, 'spot_invoice', null, [
            'invoice_id' => $invoice->id,
            'amount' => $validated['amount'],
            'description' => $validated['description'],
        ]);

        return response()->json([
            'success' => true,
            'data' => ['invoice_id' => $invoice->id],
            'message' => 'スポット請求書を発行しました。',
        ]);
    }

    /**
     * 表示制御 (display_settings) を一括更新する。
     */
    public function updateDisplaySettings(Request $request, Company $company): JsonResponse
    {
        if ($deny = $this->requireMaster($request)) return $deny;

        $validated = $request->validate([
            'plan_label' => 'nullable|string|max:100',
            'show_amount' => 'sometimes|boolean',
            'show_breakdown' => 'sometimes|boolean',
            'show_next_billing_date' => 'sometimes|boolean',
            'show_invoice_history' => 'sometimes|string|in:all,last_12_months,hidden',
            'allow_invoice_download' => 'sometimes|boolean',
            'allow_payment_method_edit' => 'sometimes|boolean',
            'allow_self_cancel' => 'sometimes|boolean',
            'announcement' => 'nullable|array',
            'announcement.level' => 'nullable|string|in:info,warning,critical',
            'announcement.title' => 'nullable|string|max:200',
            'announcement.body' => 'nullable|string|max:2000',
            'announcement.shown_until' => 'nullable|date',
            'support_contact' => 'nullable|array',
            'support_contact.name' => 'nullable|string|max:100',
            'support_contact.email' => 'nullable|email',
            'support_contact.phone' => 'nullable|string|max:50',
        ]);

        $before = ['display_settings' => $company->display_settings];

        $current = $company->display_settings ?? [];
        $next = array_replace($current, $validated);

        $company->update(['display_settings' => $next]);

        $this->log($request, $company, 'update_display_settings', $before, ['display_settings' => $next]);

        return response()->json([
            'success' => true,
            'data' => ['display_settings' => $next],
            'message' => '表示設定を更新しました。',
        ]);
    }

    /**
     * 個別条件書（individual_terms）を取得する。マスター管理者用。
     */
    public function individualTerms(Request $request, Company $company): JsonResponse
    {
        if ($deny = $this->requireMaster($request)) return $deny;

        return response()->json([
            'success' => true,
            'data' => [
                'individual_terms' => $company->individual_terms ?? $this->defaultIndividualTerms($company),
            ],
        ]);
    }

    /**
     * 個別条件書を更新する。マスター管理者用。監査ログに記録。
     */
    public function updateIndividualTerms(Request $request, Company $company): JsonResponse
    {
        if ($deny = $this->requireMaster($request)) return $deny;

        $validated = $request->validate([
            'individual_terms' => 'present|array',
            'individual_terms.monthly_fee' => 'nullable|string|max:200',
            'individual_terms.initial_setup_fee' => 'nullable|string|max:200',
            'individual_terms.registration_proxy_fee' => 'nullable|string|max:200',
            'individual_terms.service_start_date' => 'nullable|string|max:50',
            'individual_terms.contract_term_months' => 'nullable|integer|min:1|max:120',
            'individual_terms.minimum_term_months' => 'nullable|integer|min:0|max:120',
            'individual_terms.cancellation_notice_months' => 'nullable|integer|min:0|max:24',
            'individual_terms.early_termination_fee' => 'nullable|string|max:500',
            'individual_terms.billing_day' => 'nullable|integer|min:1|max:31',
            'individual_terms.training_visit_count' => 'nullable|integer|min:0|max:100',
            'individual_terms.training_web_count' => 'nullable|integer|min:0|max:100',
            'individual_terms.target_classrooms' => 'nullable|array',
            'individual_terms.target_classrooms.*' => 'string|max:200',
            'individual_terms.contractor_name' => 'nullable|string|max:200',
            'individual_terms.contractor_address' => 'nullable|string|max:500',
            'individual_terms.representative' => 'nullable|string|max:200',
            'individual_terms.executed_at' => 'nullable|string|max:50',
            'individual_terms.additional_notes' => 'nullable|string|max:5000',
        ]);

        $before = ['individual_terms' => $company->individual_terms];
        $company->update(['individual_terms' => $validated['individual_terms']]);

        $this->log($request, $company, 'update_individual_terms', $before, ['individual_terms' => $validated['individual_terms']]);

        return response()->json([
            'success' => true,
            'data' => ['individual_terms' => $validated['individual_terms']],
            'message' => '個別条件書を更新しました。',
        ]);
    }

    /**
     * 既定値（companies の現状値や Stripe 連動値から推定）。
     * 編集画面の初期表示で「未入力フィールドは推定値で埋める」ためのヘルパ。
     */
    private function defaultIndividualTerms(Company $company): array
    {
        $billingDay = null;
        if ($company->current_period_end) {
            $billingDay = (int) $company->current_period_end->format('j');
        }

        return [
            'monthly_fee' => $company->custom_amount ? '¥'.number_format($company->custom_amount).'（税別）' : '',
            'initial_setup_fee' => '',
            'registration_proxy_fee' => '',
            'service_start_date' => $company->contract_started_at?->format('Y-m-d') ?? '',
            'contract_term_months' => 12,
            'minimum_term_months' => 12,
            'cancellation_notice_months' => 1,
            'early_termination_fee' => '残期間の月額利用料相当額',
            'billing_day' => $billingDay,
            'training_visit_count' => 1,
            'training_web_count' => 2,
            'target_classrooms' => [],
            'contractor_name' => $company->name,
            'contractor_address' => '',
            'representative' => '',
            'executed_at' => '',
            'additional_notes' => '',
        ];
    }

    /**
     * 機能フラグを更新する（feature_flags JSON 全置換）。
     */
    public function updateFeatureFlags(Request $request, Company $company): JsonResponse
    {
        if ($deny = $this->requireMaster($request)) return $deny;

        $validated = $request->validate([
            'feature_flags' => 'present|array',
        ]);

        $before = ['feature_flags' => $company->feature_flags];
        $company->update(['feature_flags' => $validated['feature_flags']]);

        $this->log($request, $company, 'update_feature_flags', $before, ['feature_flags' => $validated['feature_flags']]);

        return response()->json([
            'success' => true,
            'data' => ['feature_flags' => $validated['feature_flags']],
            'message' => '機能フラグを更新しました。',
        ]);
    }

    private function requireMaster(Request $request): ?JsonResponse
    {
        $user = $request->user();
        if (!$user || !$this->policy->manageAsMaster($user)) {
            return response()->json([
                'success' => false,
                'message' => 'マスター管理者権限が必要です。',
            ], 403);
        }
        return null;
    }

    private function stripe(): StripeClient
    {
        return new StripeClient(config('cashier.secret'));
    }

    /**
     * 入力金額を Stripe に送る金額（税込総額の整数）に変換する。
     * - 税込モード: そのまま
     * - 税別モード: amount * (1 + tax_rate) を四捨五入
     * Stripe Tax を有効化する場合は、ここで税計算しないよう常に inclusive 扱いにする。
     */
    private function toStripeAmount(int $amount, bool $taxInclusive): int
    {
        if ($taxInclusive) {
            return $amount;
        }
        $rate = (float) config('billing.tax_rate', 0.10);
        return (int) round($amount * (1 + $rate));
    }

    private function log(Request $request, Company $company, string $action, ?array $before, ?array $after): void
    {
        MasterAdminAuditLog::create([
            'master_user_id' => $request->user()->id,
            'company_id' => $company->id,
            'action' => $action,
            'before' => $before,
            'after' => $after,
            'context' => [
                'ip' => $request->ip(),
                'user_agent' => mb_substr($request->userAgent() ?? '', 0, 500),
            ],
        ]);
    }
}
