<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Services\ConsentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * AI学習基盤 同意UI: 施設(company)単位の集計同意(improvement_aggregate)。
 *
 * 施設管理者が自施設の参加可否を設定する。判定・記録は ConsentService に一本化
 * (非正規化フラグ + append-only consent_records を同一Txで更新)。
 *
 * 分類: api
 */
class AiConsentController extends Controller
{
    public function __construct(private ConsentService $consent) {}

    /** GET /api/admin/ai-consent/company : 自施設の集計同意状態 */
    public function companyShow(Request $request): JsonResponse
    {
        $company = $this->resolveCompany($request);
        if (! $company) {
            return response()->json(['success' => false, 'message' => '所属施設が特定できません。'], 409);
        }

        return response()->json(['success' => true, 'data' => $this->payload($company)]);
    }

    /** PUT /api/admin/ai-consent/company {granted} : 自施設の集計同意を設定 */
    public function companyUpdate(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user->isCompanyAdmin() && ! $user->isMasterAdmin()) {
            return response()->json(['success' => false, 'message' => '施設の同意を変更する権限がありません。'], 403);
        }

        $validated = $request->validate(['granted' => 'required|boolean']);

        $company = $this->resolveCompany($request);
        if (! $company) {
            return response()->json(['success' => false, 'message' => '所属施設が特定できません。'], 409);
        }

        $this->consent->recordCompanyConsent(
            $company,
            (bool) $validated['granted'],
            $user->id,
            role: $user->isMasterAdmin() ? 'master_admin' : 'company_admin',
            method: 'web_ui',
        );

        return response()->json([
            'success' => true,
            'data' => $this->payload($company->fresh()),
            'message' => $validated['granted']
                ? 'AI学習基盤への参加(施設の集計同意)をONにしました。'
                : 'AI学習基盤への参加(施設の集計同意)をOFFにしました。',
        ]);
    }

    private function resolveCompany(Request $request): ?Company
    {
        $companyId = $request->user()->company_id;

        return $companyId ? Company::find($companyId) : null;
    }

    private function payload(Company $company): array
    {
        return [
            'company_id' => $company->id,
            'company_name' => $company->name,
            'ai_consent_aggregate' => (bool) $company->ai_consent_aggregate,
            'ai_consent_aggregate_at' => $company->ai_consent_aggregate_at,
        ];
    }
}
