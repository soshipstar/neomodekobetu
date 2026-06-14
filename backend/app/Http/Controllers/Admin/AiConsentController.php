<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Student;
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

    /**
     * GET /api/admin/ai-consent/status : 同意の充足状況(施設の集計同意 + 児童の学習同意の件数)。
     *
     * 「同意が入らず蓄積0」というボトルネックを可視化するための集計。件数のみ(実名・個人は返さない)。
     * 学習が実際に有効な児童数 = 施設の集計同意 AND 児童の学習同意(AND成立数)。
     */
    public function status(Request $request): JsonResponse
    {
        $company = $this->resolveCompany($request);
        if (! $company) {
            return response()->json(['success' => false, 'message' => '所属施設が特定できません。'], 409);
        }

        $base = Student::whereHas('classroom', fn ($q) => $q->where('company_id', $company->id))
            ->whereIn('status', ['active', 'trial', 'short_term']);
        $total = (clone $base)->count();
        $consented = (clone $base)->where('ai_consent_learning', true)->count();
        $aggregate = (bool) $company->ai_consent_aggregate;

        return response()->json(['success' => true, 'data' => [
            'company_id' => $company->id,
            'company_name' => $company->name,
            'ai_consent_aggregate' => $aggregate,
            'ai_consent_aggregate_at' => $company->ai_consent_aggregate_at,
            'students_total' => $total,
            'students_consented' => $consented,
            // AND成立(施設OFFなら児童同意があっても学習対象は0)。
            'students_learning_active' => $aggregate ? $consented : 0,
        ]]);
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
