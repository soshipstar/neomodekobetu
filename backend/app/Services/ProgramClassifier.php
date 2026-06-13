<?php

namespace App\Services;

use App\Models\ProgramCategory;
use App\Models\ProgramClassification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * AI学習基盤 S4b: 実施プログラム分類エンジン。
 *
 * P0=ルール(program_categories.aliases のキーワード照合)。P1で埋め込み類似を追加予定。
 * 人手分類(method=manual)は最優先で尊重し、自動分類で上書きしない。
 * 活動の分類は施設の運用メタ情報であり、児童個人の学習同意ゲートの対象外(活動単位)。
 */
class ProgramClassifier
{
    /**
     * テキストを最もよく説明するカテゴリをルール照合で1つ返す。該当なしは null。
     *
     * @return array{program_category_id:int,confidence:float,method:string}|null
     */
    public function classify(string $text, ?int $companyId = null): ?array
    {
        $text = trim($text);
        if ($text === '') {
            return null;
        }

        // 決定的順序: 法人固有(company_id有り)を先に → sort_order → id。
        // これにより同点時は先頭(=法人固有→sort_order小)が安定して選ばれ、結果が再現的になる。
        $cats = ProgramCategory::where('status', 'active')
            ->where(function ($q) use ($companyId) {
                $q->whereNull('company_id');
                if ($companyId) {
                    $q->orWhere('company_id', $companyId);
                }
            })
            ->orderByRaw('company_id is null')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $best = null;
        $bestScore = 0;
        foreach ($cats as $cat) {
            $hits = 0;
            foreach (($cat->aliases ?? []) as $kw) {
                if (is_string($kw) && $kw !== '' && mb_strpos($text, $kw) !== false) {
                    $hits++;
                }
            }
            // 厳密に多い場合のみ更新。同点は決定的順序の先着が残る(法人固有優先)。
            if ($hits > $bestScore) {
                $bestScore = $hits;
                $best = $cat;
            }
        }

        if (! $best || $bestScore === 0) {
            return null;
        }

        return [
            'program_category_id' => $best->id,
            'confidence' => min(0.95, 0.4 + 0.2 * $bestScore),
            'method' => 'rule',
        ];
    }

    /**
     * 自動分類して保存する。人手分類(manual)があれば尊重して何もしない。失敗は握りつぶす。
     */
    public function classifyAndStore(string $type, int $id, string $text, ?int $companyId = null): ?ProgramClassification
    {
        try {
            $res = $this->classify($text, $companyId);
            if (! $res) {
                return null;
            }

            // TOCTOU回避: 既存行をロックしてから manual 判定・置換・作成を一括で行う。
            return DB::transaction(function () use ($type, $id, $res) {
                $rows = ProgramClassification::where('classifiable_type', $type)
                    ->where('classifiable_id', $id)->lockForUpdate()->get();

                if ($rows->firstWhere('method', 'manual')) {
                    return null; // 人手分類を尊重(自動上書きしない)
                }

                $prevCatId = optional($rows->firstWhere('is_primary', true) ?? $rows->first())->program_category_id;

                ProgramClassification::where('classifiable_type', $type)->where('classifiable_id', $id)
                    ->whereIn('method', ['rule', 'embedding'])->delete();

                $pc = ProgramClassification::create([
                    'classifiable_type' => $type,
                    'classifiable_id' => $id,
                    'program_category_id' => $res['program_category_id'],
                    'method' => 'rule',
                    'confidence' => $res['confidence'],
                    'is_primary' => true,
                ]);
                $this->adjustUsage($prevCatId, $res['program_category_id']);

                return $pc;
            });
        } catch (\Throwable $e) {
            Log::warning('ProgramClassifier.classifyAndStore failed: '.$e->getMessage());

            return null;
        }
    }

    /** 人手で分類を設定/訂正する(最優先)。既存分類を置換。 */
    public function setManual(string $type, int $id, int $categoryId, ?int $userId): ProgramClassification
    {
        return DB::transaction(function () use ($type, $id, $categoryId, $userId) {
            $rows = ProgramClassification::where('classifiable_type', $type)
                ->where('classifiable_id', $id)->lockForUpdate()->get();
            $prevCatId = optional($rows->firstWhere('is_primary', true) ?? $rows->first())->program_category_id;

            ProgramClassification::where('classifiable_type', $type)->where('classifiable_id', $id)->delete();
            $pc = ProgramClassification::create([
                'classifiable_type' => $type,
                'classifiable_id' => $id,
                'program_category_id' => $categoryId,
                'method' => 'manual',
                'confidence' => 1.0,
                'is_primary' => true,
                'classified_by' => $userId,
            ]);
            $this->adjustUsage($prevCatId, $categoryId);

            return $pc;
        });
    }

    /** usage_count を差分更新する(同一カテゴリへの再分類は据え置き。過剰加算を防ぐ)。 */
    private function adjustUsage(?int $oldCategoryId, int $newCategoryId): void
    {
        if ($oldCategoryId === $newCategoryId) {
            return;
        }
        if ($oldCategoryId) {
            ProgramCategory::whereKey($oldCategoryId)->where('usage_count', '>', 0)->decrement('usage_count');
        }
        ProgramCategory::whereKey($newCategoryId)->increment('usage_count');
    }
}
