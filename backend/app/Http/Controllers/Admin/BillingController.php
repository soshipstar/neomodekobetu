<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Invoice;
use App\Policies\BillingPolicy;
use App\Services\Billing\DisplaySettingsFilter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * 企業管理者用の課金画面API。自社の契約・請求・カードを参照する。
 *
 * - すべての操作は「自社」スコープ。BillingPolicy::view / manageOwn で検証
 * - レスポンスは DisplaySettingsFilter を経由して、マスター管理者の表示制御を適用
 * - カード追加・解約のような操作は Stripe Customer Portal にリダイレクトする
 *   （PCI DSS 対応のため自前でカード番号を扱わない）
 */
class BillingController extends Controller
{
    public function __construct(private readonly BillingPolicy $policy) {}

    /**
     * 自社契約の現在状況。
     */
    public function subscription(Request $request): JsonResponse
    {
        $company = $this->resolveOwnCompany($request);
        if ($company instanceof JsonResponse) return $company;

        $payload = $this->buildSubscriptionPayload($company);
        $payload = DisplaySettingsFilter::applyToSubscription($company, $payload);

        return response()->json([
            'success' => true,
            'data' => $payload,
        ]);
    }

    /**
     * 自社の請求履歴一覧。
     * display_settings.show_invoice_history で表示範囲が変わる。
     */
    public function invoices(Request $request): JsonResponse
    {
        $company = $this->resolveOwnCompany($request);
        if ($company instanceof JsonResponse) return $company;

        $scope = DisplaySettingsFilter::invoiceHistoryScope($company);
        if (!$scope['enabled']) {
            return response()->json([
                'success' => true,
                'data' => [],
                'meta' => ['hidden_by_master' => true],
            ]);
        }

        $query = Invoice::where('company_id', $company->id)->orderByDesc('period_start');
        if ($scope['since']) {
            $query->where('period_start', '>=', $scope['since']);
        }

        $invoices = $query->limit(120)->get()->map(fn (Invoice $inv) => [
            'id' => $inv->id,
            'number' => $inv->number,
            'status' => $inv->status,
            'total' => $inv->total,
            'currency' => $inv->currency,
            'period_start' => $inv->period_start,
            'period_end' => $inv->period_end,
            'paid_at' => $inv->paid_at,
            'can_download' => DisplaySettingsFilter::canDownloadInvoice($company),
        ]);

        return response()->json([
            'success' => true,
            'data' => $invoices,
        ]);
    }

    /**
     * 請求書PDFの一時URL（Stripe側のhosted/pdf URL）を返す。
     * allow_invoice_download = false の場合は 403。
     */
    public function invoicePdf(Request $request, Invoice $invoice): JsonResponse
    {
        $company = $this->resolveOwnCompany($request);
        if ($company instanceof JsonResponse) return $company;

        if ($invoice->company_id !== $company->id) {
            return response()->json(['success' => false, 'message' => '対象の請求書が見つかりません。'], 404);
        }

        if (!DisplaySettingsFilter::canDownloadInvoice($company)) {
            return response()->json(['success' => false, 'message' => '請求書のダウンロードは許可されていません。'], 403);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'invoice_pdf' => $invoice->invoice_pdf,
                'hosted_invoice_url' => $invoice->hosted_invoice_url,
            ],
        ]);
    }

    /**
     * Stripe Customer Portal の URL を返す（クライアントはここにリダイレクトする）。
     * カード変更・支払い方法管理・領収書ダウンロードを Stripe 側UIに委譲。
     */
    public function portal(Request $request): JsonResponse
    {
        $company = $this->resolveOwnCompany($request);
        if ($company instanceof JsonResponse) return $company;

        if (!DisplaySettingsFilter::canEditPaymentMethod($company)) {
            return response()->json(['success' => false, 'message' => '支払い方法の編集は許可されていません。'], 403);
        }

        if (!$company->stripe_id) {
            return response()->json(['success' => false, 'message' => '契約が未設定です。'], 422);
        }

        $returnUrl = $request->input('return_url') ?? config('app.url').'/admin/billing';
        $url = $company->redirectToBillingPortal($returnUrl)->getTargetUrl();

        return response()->json([
            'success' => true,
            'data' => ['url' => $url],
        ]);
    }

    /**
     * 解約予約。期間末で停止。
     * allow_self_cancel = false の場合は 403。
     */
    public function cancel(Request $request): JsonResponse
    {
        $company = $this->resolveOwnCompany($request);
        if ($company instanceof JsonResponse) return $company;

        if (!DisplaySettingsFilter::canSelfCancel($company)) {
            return response()->json(['success' => false, 'message' => '解約はマスター管理者にご連絡ください。'], 403);
        }

        $sub = $company->subscription();
        if (!$sub || !$sub->active()) {
            return response()->json(['success' => false, 'message' => '有効な契約がありません。'], 422);
        }

        $sub->cancel();
        $company->update(['cancel_at_period_end' => true]);

        return response()->json([
            'success' => true,
            'message' => '解約を予約しました。期間末で停止します。',
        ]);
    }

    /**
     * 解約予約の取り消し。
     */
    public function resume(Request $request): JsonResponse
    {
        $company = $this->resolveOwnCompany($request);
        if ($company instanceof JsonResponse) return $company;

        $sub = $company->subscription();
        if (!$sub || !$sub->onGracePeriod()) {
            return response()->json(['success' => false, 'message' => '解約予約された契約がありません。'], 422);
        }

        $sub->resume();
        $company->update(['cancel_at_period_end' => false]);

        return response()->json([
            'success' => true,
            'message' => '契約を継続しました。',
        ]);
    }

    /**
     * 自企業の解決と権限チェック。マスター管理者は ?company_id= で他社を指定可。
     */
    private function resolveOwnCompany(Request $request): Company|JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['success' => false, 'message' => '認証が必要です。'], 401);
        }

        // マスター管理者の閲覧支援（impersonate 的に他社を見るとき用）
        if ($user->user_type === 'admin' && $user->is_master && $request->filled('company_id')) {
            $company = Company::find($request->integer('company_id'));
            if (!$company) {
                return response()->json(['success' => false, 'message' => '企業が見つかりません。'], 404);
            }
            return $company;
        }

        $companyId = $user->company_id;
        if (!$companyId) {
            return response()->json(['success' => false, 'message' => '所属企業が設定されていません。'], 422);
        }

        $company = Company::find($companyId);
        if (!$company) {
            return response()->json(['success' => false, 'message' => '企業が見つかりません。'], 404);
        }

        if (!$this->policy->view($user, $company)) {
            return response()->json(['success' => false, 'message' => '閲覧権限がありません。'], 403);
        }

        return $company;
    }

    /**
     * フロントに返す subscription 情報を組み立てる。
     */
    private function buildSubscriptionPayload(Company $company): array
    {
        $sub = $company->subscription();

        return [
            'company_id' => $company->id,
            'status' => $company->subscription_status,
            'is_custom_pricing' => (bool) $company->is_custom_pricing,
            'amount' => $company->is_custom_pricing
                ? $company->custom_amount
                : null, // 標準プランは Stripe Price 経由（フロントがprice_idで照会）
            'current_price_id' => $company->current_price_id,
            'current_period_end' => $company->current_period_end,
            'trial_ends_at' => $company->trial_ends_at,
            'cancel_at_period_end' => (bool) $company->cancel_at_period_end,
            'on_grace_period' => $sub?->onGracePeriod() ?? false,
            'is_active' => $sub?->active() ?? false,
            'pm_type' => $company->pm_type,
            'pm_last_four' => $company->pm_last_four,
            'contract_started_at' => $company->contract_started_at,
            'contract_document_path' => $company->contract_document_path,
        ];
    }
}
