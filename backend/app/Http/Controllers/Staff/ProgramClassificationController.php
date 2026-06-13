<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\DailyRecord;
use App\Models\ProgramCategory;
use App\Models\ProgramClassification;
use App\Services\ProgramClassifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * AI学習基盤 S4b: 実施プログラム分類の閲覧・訂正(職員)。
 * 訂正(setManual)は自動分類より優先され、分類器の精度向上の素材になる。
 *
 * 分類: api
 */
class ProgramClassificationController extends Controller
{
    public function __construct(private ProgramClassifier $classifier) {}

    /** GET /api/staff/program-categories : 選択可能なカテゴリ(全社共通 + 自社) */
    public function categories(Request $request): JsonResponse
    {
        $companyId = $request->user()->company_id;
        $cats = ProgramCategory::where('status', 'active')
            ->where(function ($q) use ($companyId) {
                $q->whereNull('company_id');
                if ($companyId) {
                    $q->orWhere('company_id', $companyId);
                }
            })
            ->orderByRaw('domain is null')
            ->orderBy('domain')
            ->orderBy('sort_order')
            ->get(['id', 'domain', 'code', 'label_ja', 'company_id']);

        return response()->json(['success' => true, 'data' => $cats]);
    }

    /** GET /api/staff/renrakucho/{record}/program-classification : 現在の分類 */
    public function show(Request $request, DailyRecord $record): JsonResponse
    {
        $this->authorizeRecord($request->user(), $record);
        // method 優先(manual>embedding>rule)で決定的に1件を返す(万一primaryが複数残っても人手分類を優先表示)。
        $pc = ProgramClassification::with('category:id,code,label_ja,domain')
            ->where('classifiable_type', 'daily_record')->where('classifiable_id', $record->id)
            ->where('is_primary', true)
            ->orderByRaw("case method when 'manual' then 0 when 'embedding' then 1 else 2 end")
            ->orderByDesc('id')
            ->first();

        return response()->json(['success' => true, 'data' => $pc]);
    }

    /** PUT /api/staff/renrakucho/{record}/program-classification {program_category_id} : 人手で訂正 */
    public function update(Request $request, DailyRecord $record): JsonResponse
    {
        $this->authorizeRecord($request->user(), $record);
        $validated = $request->validate([
            'program_category_id' => 'required|integer|exists:program_categories,id',
        ]);

        $cat = ProgramCategory::findOrFail($validated['program_category_id']);
        // 法人境界: 他社のカテゴリは選べない(全社共通 company_id=null は可)
        if ($cat->company_id !== null && $cat->company_id !== $request->user()->company_id) {
            return response()->json(['success' => false, 'message' => 'このカテゴリは選択できません。'], 403);
        }

        $pc = $this->classifier->setManual('daily_record', $record->id, $cat->id, $request->user()->id);

        return response()->json([
            'success' => true,
            'data' => $pc->load('category:id,code,label_ja,domain'),
            'message' => '実施プログラムの分類を更新しました。',
        ]);
    }

    private function authorizeRecord($user, DailyRecord $record): void
    {
        if ($user->classroom_id) {
            $staffClassroom = $record->staff->classroom_id ?? $record->classroom_id;
            if (! in_array($staffClassroom, $user->switchableClassroomIds(), true)) {
                abort(403, 'この活動へのアクセス権限がありません。');
            }
        }
    }
}
