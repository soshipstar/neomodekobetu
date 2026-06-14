<?php

namespace App\Services;

use App\Models\AiEditReason;
use App\Models\AiEditReasonCandidate;
use App\Models\AiEditReasonCategory;
use App\Models\AiRevisionEvent;
use App\Support\PiiMasker;
use Illuminate\Support\Facades\DB;

/**
 * AI学習基盤 §11: 修正理由(1クリックchips + 自由記述)の記録と動的カテゴリ候補化。
 *
 * - 人手理由(reason_source=human_manual)は revision ごとに置換(再選択で上書き)。
 * - 自由記述は当該児童の PiiMasker でマスク + 構造化PIIスクラブ後に保存(at-rest PII防止)。
 * - 自由記述が既存カテゴリ名と一致しなければ候補(ai_edit_reason_candidates)へ束ねる(昇格待ち)。
 */
class EditReasonService
{
    /**
     * @param  array<int,int>  $categoryIds
     */
    public function attach(AiRevisionEvent $rev, array $categoryIds, ?string $freeText, ?int $userId): void
    {
        $companyId = $rev->company_id;
        $rev->loadMissing('student.guardian');
        $masker = $rev->student ? PiiMasker::forStudent($rev->student) : new PiiMasker();

        DB::transaction(function () use ($rev, $categoryIds, $freeText, $userId, $companyId, $masker) {
            // 既存の人手理由を置換(チップの再選択を反映)
            AiEditReason::where('ai_revision_event_id', $rev->id)->where('reason_source', 'human_manual')->delete();

            $cats = AiEditReasonCategory::whereIn('id', $categoryIds)->where('status', 'active')
                ->where(fn ($q) => $q->whereNull('company_id')->orWhere('company_id', $companyId))->get();
            foreach ($cats as $cat) {
                AiEditReason::create([
                    'ai_revision_event_id' => $rev->id,
                    'category_id' => $cat->id,
                    'reason_source' => 'human_manual',
                    'user_id' => $userId,
                ]);
                AiEditReasonCategory::whereKey($cat->id)->increment('usage_count');
            }

            $masked = $freeText !== null ? trim(PiiMasker::scrubStructuredPii($masker->mask(trim($freeText)))) : '';
            if ($masked !== '') {
                AiEditReason::create([
                    'ai_revision_event_id' => $rev->id,
                    'category_id' => null,
                    'free_text' => mb_substr($masked, 0, 1000),
                    'reason_source' => 'human_manual',
                    'user_id' => $userId,
                ]);
                // 既存カテゴリ名に一致しなければ候補化(動的タクソノミー)
                $match = AiEditReasonCategory::where('status', 'active')->where('label_ja', $masked)
                    ->where(fn ($q) => $q->whereNull('company_id')->orWhere('company_id', $companyId))->exists();
                if (! $match) {
                    $this->bumpCandidate($masked, $companyId, $userId);
                }
            }
        });
    }

    /** 自由記述を候補へ束ねる(同一正規化テキストで集約。1人の口癖を弾くため distinct_users を数える)。 */
    private function bumpCandidate(string $maskedText, ?int $companyId, ?int $userId): void
    {
        $norm = mb_substr(trim(preg_replace('/\s+/u', ' ', $maskedText) ?? ''), 0, 255);
        if ($norm === '') {
            return;
        }
        $cand = AiEditReasonCandidate::firstOrNew(['company_id' => $companyId, 'normalized_text' => $norm]);
        $meta = $cand->detection_meta ?? ['user_ids' => []];
        $userIds = $meta['user_ids'] ?? [];
        if ($userId && ! in_array($userId, $userIds, true)) {
            $userIds[] = $userId;
        }
        $samples = array_values(array_slice(array_unique(array_merge($cand->member_texts ?? [], [$norm])), 0, 5));

        $cand->frequency = ($cand->frequency ?? 0) + 1;
        $cand->distinct_users = max(1, count($userIds));
        $cand->member_texts = $samples;
        $cand->detection_meta = ['user_ids' => $userIds, 'method' => 'free_text_exact'];
        if (! $cand->exists || $cand->status === null) {
            $cand->status = 'pending';
        }
        $cand->save();
    }

    /** 候補をカテゴリへ昇格する(管理者)。 */
    public function promote(AiEditReasonCandidate $cand, string $code, string $labelJa, ?int $reviewerId): AiEditReasonCategory
    {
        return DB::transaction(function () use ($cand, $code, $labelJa, $reviewerId) {
            $cat = AiEditReasonCategory::updateOrCreate(
                ['company_id' => $cand->company_id, 'code' => $code],
                [
                    'label_ja' => $labelJa,
                    'is_seeded' => false,
                    'status' => 'active',
                    'sort_order' => 500,
                    'promoted_from_candidate_id' => $cand->id,
                ],
            );
            $cand->update([
                'status' => 'merged',
                'merged_into_category_id' => $cat->id,
                'reviewed_by' => $reviewerId,
                'reviewed_at' => now(),
            ]);

            return $cat;
        });
    }
}
