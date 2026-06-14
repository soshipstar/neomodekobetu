<?php

namespace App\Services;

use App\Models\AiEditMetric;
use App\Models\AiEditReasonCategory;
use App\Models\AiRevisionEvent;
use App\Models\Student;
use App\Models\User;
use App\Support\PiiMasker;
use Illuminate\Support\Facades\Log;

/**
 * AI学習基盤 S5: 自己改善ループ。蓄積した「人間の最終稿(after_text)」と修正傾向から、
 * 生成プロンプトへ注入する施設別ガイダンス(文体・観点の参考)を組み立てる。
 *
 * 方針(ローカルAIは将来。現状はルール/統計ベース):
 *  - 施設(company)が集計同意(canAggregate)している場合のみ作用。
 *  - 例示は学習同意(AND)済みの過去確定稿のみ。各例は当該児童の PiiMasker でマスクして注入
 *    (=外部AIへ実名を送らない。A005準拠)。
 *  - 主要修正理由(ai_edit_metrics)→ 簡潔な指示文に変換。
 *  - 失敗は握りつぶし、生成本処理を絶対に止めない(ガイダンス無し=従来どおり)。
 */
class WritingProfileService
{
    /** 修正理由コード → 生成時の指示文。 */
    private const REASON_DIRECTIVES = [
        'too_abstract' => '具体的に(場面・頻度・手立て・環境設定を明確に)記述する',
        'too_verbose' => '簡潔に。冗長な繰り返しを避ける',
        'factual_error' => '入力にない事実・出来事を創作しない',
        'tone_mismatch' => '敬体で統一し、施設の文体に合わせる',
        'terminology' => '施設で用いられている用語・言い回しに合わせる',
        'missing_info' => '必要な観点(本人の願い・強み・課題)を漏らさない',
        'redundant_info' => '不要・蛇足な情報を入れない',
        'privacy_concern' => 'プライバシーに配慮した表現にする',
        'inappropriate' => '当事者に配慮した適切な表現にする',
        'format_structure' => '段落・順序など構成を整える',
        'personalization' => '本人の実態・特性に即した記述にする',
    ];

    private const SECTION_LABELS = [
        'long_term_goal' => '長期目標', 'short_term_goal' => '短期目標',
        'overall_policy' => '支援方針', 'life_intention' => '本人・家族の意向',
        'student_wish' => '本人の願い', 'overall_comment' => '総合所見',
        'integrated_content' => '連絡帳本文',
        'health_life' => '健康・生活', 'motor_sensory' => '運動・感覚', 'cognitive_behavior' => '認知・行動',
        'language_communication' => '言語・コミュニケーション', 'social_relations' => '人間関係・社会性',
    ];

    public function __construct(private ConsentService $consent) {}

    /**
     * 生成プロンプトへ注入するガイダンス(マスク済)を返す。不足/未同意なら null。
     */
    public function buildGuidance(Student $student, string $documentType, int $maxPerSection = 2, int $maxSections = 5): ?string
    {
        try {
            $student->loadMissing('classroom.company');
            $company = $student->classroom?->company;
            if ($company === null || ! $this->consent->canAggregate($company)) {
                return null; // 施設が改善利用に同意していなければ使わない
            }

            $directives = $this->reasonDirectives($company->id, $documentType);
            $examples = $this->maskedExamples($company->id, $documentType, $maxPerSection, $maxSections);

            if ($directives === [] && $examples === []) {
                return null;
            }

            $parts = ['【この施設のこれまでの記述傾向(文体・観点の参考。事実は創作しない)】'];
            if ($directives !== []) {
                $parts[] = '・心がけ: '.implode(' / ', $directives);
            }
            foreach ($examples as $label => $texts) {
                $parts[] = "・{$label}の確定記述例: ".implode(' ｜ ', array_map(fn ($t) => "「{$t}」", $texts));
            }
            $parts[] = '※上記は文体・観点の参考です。他児の情報や入力に無い事実は含めないでください。';

            return implode("\n", $parts);
        } catch (\Throwable $e) {
            Log::warning('WritingProfileService.buildGuidance failed: '.$e->getMessage());

            return null;
        }
    }

    /** 主要修正理由 → 指示文(最新の company facet メトリクスから)。 */
    private function reasonDirectives(int $companyId, string $documentType): array
    {
        $metric = AiEditMetric::where('company_id', $companyId)->where('facet', 'company')
            ->whereNotNull('top_reason_categories')
            ->orderByDesc('period_ym')->first();
        if (! $metric || empty($metric->top_reason_categories)) {
            return [];
        }
        $ids = collect($metric->top_reason_categories)->pluck('category_id')->filter()->all();
        $codes = AiEditReasonCategory::whereIn('id', $ids)->pluck('code');

        $out = [];
        foreach ($codes as $code) {
            if (isset(self::REASON_DIRECTIVES[$code])) {
                $out[] = self::REASON_DIRECTIVES[$code];
            }
        }

        return array_slice(array_values(array_unique($out)), 0, 3);
    }

    /**
     * 同意済みの過去確定稿(after_text)を section 別に数例、マスクして返す。
     *
     * @return array<string,array<int,string>> セクション表示名 => [マスク済例...]
     */
    private function maskedExamples(int $companyId, string $documentType, int $maxPerSection, int $maxSections): array
    {
        $events = AiRevisionEvent::where('company_id', $companyId)
            ->where('document_type', $documentType)
            ->where('changed', true)
            ->whereNotNull('after_text')
            ->whereHas('student', function ($q) {
                $q->where('ai_consent_learning', true)
                    ->whereHas('classroom.company', fn ($c) => $c->where('ai_consent_aggregate', true));
            })
            ->orderByDesc('id')
            ->limit(300)
            ->get(['id', 'section_key', 'student_id', 'after_text']);

        if ($events->isEmpty()) {
            return [];
        }

        // ★施設全体のマスカー(他児・他保護者の氏名も確実にマスク)を1回のロードで構築。
        [$masker, $shortNames] = $this->companyMaskerAndShortNames($companyId);

        $bySection = [];
        foreach ($events as $e) {
            $text = trim((string) $e->after_text);
            if ($text === '') {
                continue;
            }
            $label = $this->sectionLabel($e->section_key);
            $bySection[$label] ??= [];
            if (count($bySection[$label]) >= $maxPerSection) {
                continue;
            }
            // 1) 施設の氏名マスク → 2) 構造化PII(日付・電話・番号・敬称付き人物名)を除去
            $masked = PiiMasker::scrubStructuredPii(trim($masker->mask($text)));
            // 3) fail-safe: MIN_LENGTH未満(1文字)でマスクできない氏名が残る例文は捨てる
            foreach ($shortNames as $sn) {
                if (mb_strpos($masked, $sn) !== false) {
                    $masked = '';
                    break;
                }
            }
            $masked = mb_substr(trim($masked), 0, 180);
            if ($masked !== '' && ! in_array($masked, $bySection[$label], true)) {
                $bySection[$label][] = $masked;
            }
        }

        return array_slice($bySection, 0, $maxSections, true);
    }

    /**
     * 施設の全児童+保護者の氏名を登録したマスカーと、マスク不能な短い氏名(1文字)の集合を返す。
     * 児童クエリは1回のみ(保護者は取得済みコレクションから導出)。
     *
     * @return array{0:PiiMasker,1:array<int,string>}
     */
    private function companyMaskerAndShortNames(int $companyId): array
    {
        $masker = new PiiMasker();
        $shortNames = [];
        $register = function (?string $name, string $placeholder) use ($masker, &$shortNames) {
            $name = is_string($name) ? trim($name) : '';
            if ($name === '') {
                return;
            }
            if (mb_strlen($name) < 2) {
                $shortNames[] = $name; // PiiMaskerが登録しない=漏れ防止のため例文ごと除外する対象
            } else {
                $masker->add($name, $placeholder);
            }
        };

        $students = Student::whereHas('classroom', fn ($q) => $q->where('company_id', $companyId))
            ->get(['student_name', 'student_name_kana', 'guardian_id']);
        foreach ($students as $s) {
            $register($s->student_name, '【児童】');
            $register($s->student_name_kana, '【児童カナ】');
        }
        $guardianIds = $students->pluck('guardian_id')->filter()->unique();
        if ($guardianIds->isNotEmpty()) {
            foreach (User::whereIn('id', $guardianIds)->get(['full_name', 'full_name_kana']) as $g) {
                $register($g->full_name, '【保護者】');
                $register($g->full_name_kana, '【保護者カナ】');
            }
        }

        return [$masker, array_values(array_unique($shortNames))];
    }

    private function sectionLabel(string $sectionKey): string
    {
        if (str_starts_with($sectionKey, 'detail:')) {
            $token = explode(':', $sectionKey)[1] ?? '';

            return self::SECTION_LABELS[$token] ?? '支援内容';
        }

        return self::SECTION_LABELS[$sectionKey] ?? $sectionKey;
    }
}
