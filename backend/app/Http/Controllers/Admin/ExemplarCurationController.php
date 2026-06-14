<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AiRevisionEvent;
use App\Support\PiiMasker;
use App\Services\WritingProfileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * 見本キュレーション(管理者): 学習に使う「見本」記録の採用/除外。
 *
 * 自己改善ループ(S5)が低品質記録を学習に取り込まないよう、主任/管理者が
 * 良い記録を adopted(優先採用)、不適切な記録を excluded(学習除外)に振り分ける。
 * プレビューは施設マスカー + 構造化PIIスクラブで実名を出さない。
 *
 * 分類: api
 */
class ExemplarCurationController extends Controller
{
    private const DOC_LABELS = [
        'support_plan' => '個別支援計画', 'monitoring' => 'モニタリング',
        'assessment_staff' => 'アセスメント', 'integrated_note' => '連絡帳',
    ];

    public function __construct(private WritingProfileService $profiles) {}

    /** GET /api/admin/exemplars : キュレーション候補(自施設の確定済み記録、新しい順) */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user->isCompanyAdmin() && ! $user->isMasterAdmin()) {
            return response()->json(['success' => false, 'message' => '権限がありません。'], 403);
        }
        $companyId = $user->company_id;
        if (! $user->isMasterAdmin() && ! $companyId) {
            return response()->json(['success' => false, 'message' => '所属施設が特定できません。'], 409);
        }

        $rows = AiRevisionEvent::query()
            ->when(! $user->isMasterAdmin(), fn ($q) => $q->where('company_id', $companyId))
            ->where('changed', true)->whereNotNull('after_text')
            ->whereIn('edit_kind', ['official', 'submit', 'publish'])
            ->orderByRaw("case when exemplar_status is null then 0 else 1 end") // 未判定を先に
            ->orderByDesc('id')->limit(60)->get();

        // 施設ごとのマスカーをまとめて用意(プレビューを実名なしにする)
        $maskers = [];
        $data = $rows->map(function (AiRevisionEvent $r) use (&$maskers) {
            $cid = (int) $r->company_id;
            $maskers[$cid] ??= $this->profiles->companyMasker($cid);
            $preview = PiiMasker::scrubStructuredPii(trim($maskers[$cid]->mask((string) $r->after_text)));

            return [
                'id' => $r->id,
                'document_type' => $r->document_type,
                'document_label' => self::DOC_LABELS[$r->document_type] ?? $r->document_type,
                'section_key' => $r->section_key,
                'change_ratio' => $r->change_ratio,
                'edit_kind' => $r->edit_kind,
                'has_hypothesis' => (bool) ($r->structured['has_hypothesis_marker'] ?? false),
                'exemplar_status' => $r->exemplar_status,
                'preview' => mb_substr($preview, 0, 140),
            ];
        });

        return response()->json(['success' => true, 'data' => $data]);
    }

    /** POST /api/admin/exemplars/{revision} {status: adopted|excluded|cleared} */
    public function setStatus(Request $request, AiRevisionEvent $revision): JsonResponse
    {
        $user = $request->user();
        if (! $user->isCompanyAdmin() && ! $user->isMasterAdmin()) {
            return response()->json(['success' => false, 'message' => '権限がありません。'], 403);
        }
        if (! $user->isMasterAdmin() && $revision->company_id !== $user->company_id) {
            return response()->json(['success' => false, 'message' => 'この記録は操作できません。'], 403);
        }
        $validated = $request->validate(['status' => 'required|string|in:adopted,excluded,cleared']);

        $revision->update([
            'exemplar_status' => $validated['status'] === 'cleared' ? null : $validated['status'],
            'curated_by' => $user->id,
            'curated_at' => now(),
        ]);

        return response()->json(['success' => true, 'message' => '見本の採否を更新しました。']);
    }
}
