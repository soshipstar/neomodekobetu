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
     */
    public function show(Request $request, Company $company): JsonResponse
    {
        if ($deny = $this->requireMaster($request)) return $deny;

        $invoices = Invoice::where('company_id', $company->id)
            ->orderByDesc('period_start')
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
     * 企業のカスタム価格を作成・更新する。
     * Stripe側で新しい Price を作成し、Subscription があれば更新する。
     * 既存 Price は編集不可なので、新Price + subscription.update が標準。
     */
    public function updatePrice(Request $request, Company $company): JsonResponse
    {
        if ($deny = $this->requireMaster($request)) return $deny;

        $validated = $request->validate([
            'amount' => 'required|integer|min:0',
            'interval' => 'sometimes|string|in:month,year',
            'product_id' => 'sometimes|string',
            'apply_immediately' => 'sometimes|boolean',
        ]);

        $stripe = $this->stripe();
        $product = $validated['product_id'] ?? config('billing.default_product_id');

        $price = $stripe->prices->create([
            'currency' => 'jpy',
            'unit_amount' => $validated['amount'],
            'recurring' => ['interval' => $validated['interval'] ?? 'month'],
            'product' => $product,
            'metadata' => [
                'company_id' => (string) $company->id,
                'kind' => 'custom',
            ],
        ]);

        $before = $company->only(['current_price_id', 'custom_amount', 'is_custom_pricing']);

        $company->update([
            'current_price_id' => $price->id,
            'custom_amount' => $validated['amount'],
            'is_custom_pricing' => true,
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
            'payment_method' => 'sometimes|string',
        ]);

        if ($company->subscription() && $company->subscription()->active()) {
            return response()->json(['success' => false, 'message' => '既に有効な契約があります。'], 422);
        }

        $builder = $company->newSubscription('default', $validated['price_id']);
        if (!empty($validated['trial_days'])) {
            $builder->trialDays((int) $validated['trial_days']);
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
            'description' => 'required|string|max:255',
            'auto_advance' => 'sometimes|boolean',
        ]);

        if (!$company->stripe_id) {
            return response()->json(['success' => false, 'message' => 'Stripe顧客が未作成です。'], 422);
        }

        $stripe = $this->stripe();

        $stripe->invoiceItems->create([
            'customer' => $company->stripe_id,
            'amount' => $validated['amount'],
            'currency' => 'jpy',
            'description' => $validated['description'],
        ]);

        $invoice = $stripe->invoices->create([
            'customer' => $company->stripe_id,
            'auto_advance' => $validated['auto_advance'] ?? true,
            'collection_method' => 'charge_automatically',
            'metadata' => [
                'company_id' => (string) $company->id,
                'kind' => 'spot',
            ],
        ]);

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
