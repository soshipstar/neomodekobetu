<?php

namespace App\Services;

use App\Models\ProgramCategory;
use App\Models\ProgramClassification;
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

        $cats = ProgramCategory::where('status', 'active')
            ->where(function ($q) use ($companyId) {
                $q->whereNull('company_id');
                if ($companyId) {
                    $q->orWhere('company_id', $companyId);
                }
            })
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
            // 同点は法人固有を優先(company_id 有り)
            if ($hits > $bestScore || ($hits === $bestScore && $hits > 0 && $cat->company_id && ! ($best?->company_id))) {
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
            $hasManual = ProgramClassification::where('classifiable_type', $type)
                ->where('classifiable_id', $id)->where('method', 'manual')->exists();
            if ($hasManual) {
                return null;
            }

            $res = $this->classify($text, $companyId);
            if (! $res) {
                return null;
            }

            // 既存の自動分類(rule/embedding)を置換して1件(primary)にする
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
            ProgramCategory::whereKey($res['program_category_id'])->increment('usage_count');

            return $pc;
        } catch (\Throwable $e) {
            Log::warning('ProgramClassifier.classifyAndStore failed: '.$e->getMessage());

            return null;
        }
    }

    /** 人手で分類を設定/訂正する(最優先)。既存分類を置換。 */
    public function setManual(string $type, int $id, int $categoryId, ?int $userId): ProgramClassification
    {
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
        ProgramCategory::whereKey($categoryId)->increment('usage_count');

        return $pc;
    }
}
