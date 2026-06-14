<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\FacilityWritingStandard;
use App\Services\RecordingStandardAdvisor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * 施設記録基準(E1): 企業管理者がGPT5.4との対話で施設独自の記録基準を作成・保存する。
 *
 *  - chat: 会話履歴を送るとAIが助言+基準ドラフト案を返す(対話形式の作成)。
 *  - show/save: 確定した構造化基準を保存・有効化。有効な基準はAI生成プロンプトに注入される
 *    (WritingProfileService 経由)。company_admin/master のみ・自施設。
 *
 * 分類: api
 */
class RecordingStandardController extends Controller
{
    public function __construct(private RecordingStandardAdvisor $advisor) {}

    /** POST /api/admin/recording-standard/chat {messages:[{role,content}], current_sections?} */
    public function chat(Request $request): JsonResponse
    {
        $this->authorizeAdmin($request);
        $validated = $request->validate([
            'messages' => 'present|array|max:40',
            'messages.*.role' => 'required|string|in:user,assistant',
            'messages.*.content' => 'required|string|max:4000',
            'current_sections' => 'nullable|array',
        ]);

        $result = $this->advisor->reply($validated['messages'], $validated['current_sections'] ?? null);

        return response()->json(['success' => true, 'data' => $result]);
    }

    /** GET /api/admin/recording-standard : 自施設の現在の記録基準 */
    public function show(Request $request): JsonResponse
    {
        $this->authorizeAdmin($request);
        $company = $this->resolveCompany($request);
        if (! $company) {
            return response()->json(['success' => false, 'message' => '所属施設が特定できません。'], 409);
        }

        $std = FacilityWritingStandard::where('company_id', $company->id)->first();

        return response()->json(['success' => true, 'data' => $std ? [
            'sections' => $std->sections,
            'guidance_text' => $std->guidance_text,
            'status' => $std->status,
            'version' => $std->version,
            'updated_at' => $std->updated_at,
        ] : null]);
    }

    /** PUT /api/admin/recording-standard {sections, status?} : 確定・保存(有効化) */
    public function save(Request $request): JsonResponse
    {
        $this->authorizeAdmin($request);
        $company = $this->resolveCompany($request);
        if (! $company) {
            return response()->json(['success' => false, 'message' => '所属施設が特定できません。'], 409);
        }

        $validated = $request->validate([
            'sections' => 'required|array',
            'status' => 'nullable|string|in:draft,active',
        ]);

        // 統制: 既知キー・文字列配列に正規化してから保存(任意構造の混入を防ぐ)。
        $sections = RecordingStandardAdvisor::normalizeSections($validated['sections']);
        $guidance = RecordingStandardAdvisor::compileGuidance($sections);

        $existing = FacilityWritingStandard::where('company_id', $company->id)->first();
        $std = FacilityWritingStandard::updateOrCreate(
            ['company_id' => $company->id],
            [
                'status' => $validated['status'] ?? 'active',
                'version' => ($existing->version ?? 0) + 1,
                'sections' => $sections,
                'guidance_text' => $guidance,
                'updated_by' => $request->user()->id,
            ]
        );

        return response()->json([
            'success' => true,
            'data' => ['sections' => $std->sections, 'guidance_text' => $std->guidance_text, 'status' => $std->status, 'version' => $std->version],
            'message' => $std->status === 'active' ? '記録基準を保存し、有効化しました。' : '記録基準を下書き保存しました。',
        ]);
    }

    private function authorizeAdmin(Request $request): void
    {
        $user = $request->user();
        if (! $user->isCompanyAdmin() && ! $user->isMasterAdmin()) {
            abort(403, '記録基準を編集する権限がありません。');
        }
    }

    private function resolveCompany(Request $request): ?Company
    {
        $companyId = $request->user()->company_id;

        return $companyId ? Company::find($companyId) : null;
    }
}
