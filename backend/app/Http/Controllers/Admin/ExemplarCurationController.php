<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AiRevisionEvent;
use App\Models\AuditLog;
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

    /** 推奨候補の最低文字数(WritingProfileService の例示最低長と同水準)。 */
    private const MIN_RECOMMEND_LENGTH = 10;

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

        // 確定済み記録(学習対象になりうる母集団)。
        // 学習に実際に使われるのは同意(児童 ai_consent_learning AND 施設 ai_consent_aggregate)済みのみ
        // (WritingProfileService::maskedExamples と同条件)。同意撤回済みの記録は学習に使われないため、
        // キュレーション候補・統計からも除外して整合させる(撤回反映の非対称を解消)。
        $base = fn () => AiRevisionEvent::query()
            ->when(! $user->isMasterAdmin(), fn ($q) => $q->where('company_id', $companyId))
            ->where('changed', true)->whereNotNull('after_text')
            ->whereIn('edit_kind', ['official', 'submit', 'publish'])
            ->whereHas('student', function ($q) {
                $q->where('ai_consent_learning', true)
                    ->whereHas('classroom.company', fn ($c) => $c->where('ai_consent_aggregate', true));
            });

        // 運用可視化(rank7): キュレーションの進捗と「採用すべき良質な未判定候補」数。
        $stats = [
            'finalized_total' => $base()->count(),
            'adopted' => $base()->where('exemplar_status', 'adopted')->count(),
            'excluded' => $base()->where('exemplar_status', 'excluded')->count(),
            'uncurated' => $base()->whereNull('exemplar_status')->count(),
            'recommended_uncurated' => $base()->whereNull('exemplar_status')
                ->whereRaw("(structured->>'text_length')::int >= ?", [self::MIN_RECOMMEND_LENGTH])
                ->whereRaw("((structured->>'has_hypothesis_marker')::boolean = true OR (structured->>'has_result_marker')::boolean = true)")
                ->count(),
        ];

        $rows = $base()
            ->orderByRaw('case when exemplar_status is null then 0 else 1 end') // 未判定を先に
            ->orderByDesc('id')->limit(60)->get();

        // 施設ごとのマスカー+短名集合をまとめて用意(プレビューを実名なしにする)。
        // 抜粋は学習例示・支援知と同じ scrubExcerpt(短名 fail-safe 込み)に統一する。
        $maskers = [];
        $data = $rows->map(function (AiRevisionEvent $r) use (&$maskers) {
            $cid = (int) $r->company_id;
            $maskers[$cid] ??= $this->profiles->companyMaskerAndShortNames($cid);
            [$cmasker, $shortNames] = $maskers[$cid];
            $preview = $this->profiles->scrubExcerpt((string) $r->after_text, $cmasker, $shortNames, 140);

            $hasHyp = (bool) ($r->structured['has_hypothesis_marker'] ?? false);
            $hasRes = (bool) ($r->structured['has_result_marker'] ?? false);
            $len = (int) ($r->structured['text_length'] ?? 0);
            // 推奨=未判定 かつ 因果/結果の記述あり かつ 一定の長さ(見本に値する素地)
            $recommended = $r->exemplar_status === null && ($hasHyp || $hasRes) && $len >= self::MIN_RECOMMEND_LENGTH;

            return [
                'id' => $r->id,
                'document_type' => $r->document_type,
                'document_label' => self::DOC_LABELS[$r->document_type] ?? $r->document_type,
                'section_key' => $r->section_key,
                'change_ratio' => $r->change_ratio,
                'edit_kind' => $r->edit_kind,
                'has_hypothesis' => $hasHyp,
                'has_result' => $hasRes,
                'recommended' => $recommended,
                'exemplar_status' => $r->exemplar_status,
                'preview' => $preview ?? '(プレビュー不可: 個人情報保護のため非表示)',
            ];
        })->sortByDesc('recommended')->values(); // 推奨を先頭へ(PHP8の安定ソートで既存順を保持)

        return response()->json(['success' => true, 'data' => ['stats' => $stats, 'items' => $data]]);
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

        $oldStatus = $revision->exemplar_status;
        $newStatus = $validated['status'] === 'cleared' ? null : $validated['status'];

        $revision->update([
            'exemplar_status' => $newStatus,
            'curated_by' => $user->id,
            'curated_at' => now(),
        ]);

        // ガバナンス: 見本の採否操作を監査ログに残す(誰が・どの記録を・どう変えたか)。監査失敗は本処理を止めない。
        try {
            AuditLog::create([
                'user_id' => $user->id,
                'company_id' => $revision->company_id,
                'action' => 'exemplar_curation',
                'target_table' => 'ai_revision_events',
                'target_id' => $revision->id,
                'old_values' => ['exemplar_status' => $oldStatus],
                'new_values' => ['exemplar_status' => $newStatus],
            ]);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('exemplar curation audit log failed: '.$e->getMessage());
        }

        return response()->json(['success' => true, 'message' => '見本の採否を更新しました。']);
    }
}
