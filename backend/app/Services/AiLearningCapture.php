<?php

namespace App\Services;

use App\Models\AiEditReason;
use App\Models\AiGenerationEvent;
use App\Models\AiRevisionEvent;
use App\Models\Student;
use App\Support\PiiMasker;
use Illuminate\Support\Facades\Log;

/**
 * AI学習基盤 配管(S2): 生成イベントと人間修正イベントを統一スキーマへ蓄積する。
 *
 * 鉄則:
 *  - generated_payload は必ずマスクして保存(canAggregate=施設の集計同意が前提)。
 *  - before_text/after_text は実名のまま(モデルの encrypted cast で保存時暗号化)。
 *    記録は canUseForLearning(施設×児童のAND)が真のときだけ(fail-closed)。
 *  - diff/source_ref には実名を入れない(数値・構造のみ)。
 *  - 蓄積失敗は握りつぶし、本処理(計画生成・保存)を絶対に止めない。
 */
class AiLearningCapture
{
    public function __construct(private ConsentService $consent) {}

    /**
     * 生成イベントを記録する(施設の集計同意がある場合のみ)。失敗時は null。
     *
     * @param  array<mixed>  $payload  AI生出力(実名復元済みでも可。内部で必ずマスクする)
     * @param  array<string,mixed>  $sources
     */
    public function recordGeneration(
        Student $student,
        string $documentType,
        ?int $documentId,
        string $generationType,
        ?string $model,
        array $payload,
        array $sources = [],
        ?int $aiGenerationLogId = null,
        ?PiiMasker $masker = null,
        ?int $userId = null,
        ?string $promptVersion = null,
    ): ?AiGenerationEvent {
        try {
            $student->loadMissing('classroom.company');
            $company = $student->classroom?->company;
            if ($company === null || ! $this->consent->canAggregate($company)) {
                return null;
            }
            $masker ??= PiiMasker::forStudent($student);
            $dims = $this->subjectDimensions($student);

            return AiGenerationEvent::create([
                'ai_generation_log_id' => $aiGenerationLogId,
                'document_type' => $documentType,
                'document_id' => $documentId,
                'student_id' => $student->id,
                'classroom_id' => $student->classroom_id,
                'company_id' => $company->id,
                'user_id' => $userId,
                'generation_type' => $generationType,
                'model' => $model,
                'prompt_version' => $promptVersion,
                'sources_used' => $sources,
                'generated_payload' => $masker->maskArray($payload),
                'pii_masked' => true,
                // 生成イベントは施設の集計同意のみがゲート。要配慮属性(性別)は児童の学習同意が
                // 無いため乗せない(修正イベント側=canUseForLearning でのみ保持)。
                'subj_cohort' => $dims['cohort'],
                'subj_growth_stage' => $dims['growth_stage'],
                'subj_grade_level' => $dims['grade_level'],
            ]);
        } catch (\Throwable $e) {
            Log::warning('AiLearningCapture.recordGeneration failed: '.$e->getMessage());

            return null;
        }
    }

    /**
     * セクション単位の人間修正を記録する(学習同意=施設×児童のANDがある場合のみ)。
     * 変更があった(before!=after)セクションのみ1行ずつ追記する。失敗時は0。
     *
     * @param  array<string,array{0:?string,1:?string}>  $sections  section_key => [before, after]
     * @param  array<int,array<string,mixed>>  $annotations  AI注釈(['field','type','reason'])。理由として紐づける
     * @return int  記録した変更セクション数
     */
    public function recordSectionRevisions(
        Student $student,
        string $documentType,
        int $documentId,
        array $sections,
        string $editKind = 'save_draft',
        ?int $editorUserId = null,
        string $editorRole = 'staff',
        ?int $generationEventId = null,
        array $annotations = [],
        ?int $programCategoryId = null,
    ): int {
        try {
            if (! $this->consent->canUseForLearning($student)) {
                return 0;
            }
            $student->loadMissing('classroom');
            $companyId = $student->classroom?->company_id;
            $annByField = $this->indexAnnotations($annotations);
            $dims = $this->subjectDimensions($student);

            $count = 0;
            foreach ($sections as $key => $pair) {
                $before = (string) ($pair[0] ?? '');
                $after = (string) ($pair[1] ?? '');
                if ($before === $after) {
                    continue;
                }
                $ratio = $this->changeRatio($before, $after);

                $event = AiRevisionEvent::create([
                    'company_id' => $companyId,
                    'classroom_id' => $student->classroom_id,
                    'student_id' => $student->id,
                    'document_type' => $documentType,
                    'document_id' => $documentId,
                    'section_key' => (string) $key,
                    'ai_generation_event_id' => $generationEventId,
                    'before_text' => $before !== '' ? $before : null,
                    'after_text' => $after !== '' ? $after : null,
                    'diff' => [
                        'algo' => 'similar_text',
                        'before_len' => mb_strlen($before),
                        'after_len' => mb_strlen($after),
                        'change_ratio' => $ratio,
                    ],
                    'change_ratio' => $ratio,
                    'changed' => true,
                    'edit_kind' => $editKind,
                    'editor_user_id' => $editorUserId,
                    'editor_role' => $editorRole,
                    'sensitivity' => 'raw',
                    'subj_cohort' => $dims['cohort'],
                    'subj_growth_stage' => $dims['growth_stage'],
                    'subj_grade_level' => $dims['grade_level'],
                    'subj_gender' => $dims['gender'],
                    'support_category' => $this->supportCategoryFromKey((string) $key),
                    'program_category_id' => $programCategoryId,
                ]);

                foreach ($this->annotationsForKey($annByField, (string) $key) as $a) {
                    // source_ref は実名を入れない(構造情報のみ)。理由本文は before/after(暗号化)に含まれる。
                    AiEditReason::create([
                        'ai_revision_event_id' => $event->id,
                        'category_id' => null,
                        'free_text' => null,
                        'reason_source' => 'ai_annotation',
                        'source_ref' => ['annotation_type' => $a['type'] ?? null],
                        'user_id' => $editorUserId,
                    ]);
                }
                $count++;
            }

            return $count;
        } catch (\Throwable $e) {
            Log::warning('AiLearningCapture.recordSectionRevisions failed: '.$e->getMessage());

            return 0;
        }
    }

    /**
     * before/after の2マップを section_key => [before, after] へ突き合わせる(両者のキーの和集合)。
     * 各コントローラのセクション抽出結果を recordSectionRevisions に渡す前段に使う共有ヘルパ。
     *
     * @param  array<string,string>  $before
     * @param  array<string,string>  $after
     * @return array<string,array{0:string,1:string}>
     */
    public static function pairSections(array $before, array $after): array
    {
        $pairs = [];
        foreach (array_keys($before + $after) as $key) {
            $pairs[$key] = [$before[$key] ?? '', $after[$key] ?? ''];
        }

        return $pairs;
    }

    /**
     * 記録時点の分析次元(コホート/成長段階/学年/性別)を児童から算出する(S4a)。
     * 履歴正確性のためイベントごとに凍結保存する。
     *
     * @return array{cohort:string,growth_stage:string,grade_level:?string,gender:?string}
     */
    private function subjectDimensions(Student $student): array
    {
        return [
            'cohort' => \App\Support\StudentCohort::forStudent($student),
            'growth_stage' => \App\Support\AbilityGrowthStage::forStudent($student),
            'grade_level' => $student->grade_level,
            'gender' => $student->gender,
        ];
    }

    /** 本人支援5領域コード。 */
    public const FIVE_DOMAINS = ['health_life', 'motor_sensory', 'cognitive_behavior', 'language_communication', 'social_relations'];

    /**
     * テキストに含まれる語から5領域コードを検出する。なければ null。
     * 自由入力(区分ラベル)から実名等を除いて統制コードへ落とすのに使う。
     */
    public static function detectDomainCode(string $text): ?string
    {
        $map = [
            'health_life' => ['健康', '生活', '身辺', '食事', '排泄', '睡眠'],
            'motor_sensory' => ['運動', '感覚', '身体'],
            'cognitive_behavior' => ['認知', '行動', '学習'],
            'language_communication' => ['言語', 'コミュニケーション', 'ことば', '発語'],
            'social_relations' => ['人間関係', '社会性', '社会', '対人'],
        ];
        foreach ($map as $code => $keywords) {
            foreach ($keywords as $kw) {
                if (mb_strpos($text, $kw) !== false) {
                    return $code;
                }
            }
        }

        return null;
    }

    /**
     * section_key の detail サブトークンを安全化する(★実名混入防止)。
     * 5領域が検出できれば領域コード、できなければ決定的ハッシュ(sub_xxxxxxxx)。
     * 決定的なので同一subは常に同一トークン=before/afterのマッチングは保たれる。
     * コントローラの planSectionTexts 等で section_key 構築時に用いる。
     */
    public static function safeSectionSub(?string $sub): string
    {
        $sub = trim((string) $sub);
        if ($sub === '') {
            return 'unknown';
        }

        return self::detectDomainCode($sub) ?? ('sub_'.substr(sha1($sub), 0, 8));
    }

    /**
     * section_key から支援区分(5領域)を導出する。section_key は safeSectionSub 済の前提。
     * 'detail:<token>:<field>' の token / 5領域キー直 が5領域コードなら返す。それ以外 null。
     * これにより support_category は常に統制コードのみ(自由入力=実名は入らない)。
     */
    private function supportCategoryFromKey(string $key): ?string
    {
        if (str_starts_with($key, 'detail:')) {
            $token = explode(':', $key)[1] ?? '';

            return in_array($token, self::FIVE_DOMAINS, true) ? $token : null;
        }

        return in_array($key, self::FIVE_DOMAINS, true) ? $key : null;
    }

    /** 0.0(無変更)〜1.0(全置換)。短文中心のため similar_text を採用。 */
    private function changeRatio(string $before, string $after): float
    {
        if ($before === '' && $after === '') {
            return 0.0;
        }
        if ($before === '' || $after === '') {
            return 1.0;
        }
        similar_text($before, $after, $pct);

        return round(1 - $pct / 100, 4);
    }

    /**
     * annotation を field 単位に束ねる。
     *
     * @param  array<int,array<string,mixed>>  $annotations
     * @return array<string,array<int,array<string,mixed>>>
     */
    private function indexAnnotations(array $annotations): array
    {
        $byField = [];
        foreach ($annotations as $a) {
            $field = is_array($a) ? ($a['field'] ?? null) : null;
            if (! is_string($field) || $field === '') {
                continue;
            }
            // 'detail:<sub>' は section_key と同じ安全トークンへ正規化してから突き合わせる。
            if (str_starts_with($field, 'detail:')) {
                $sub = explode(':', substr($field, strlen('detail:')))[0] ?? '';
                $field = 'detail:'.self::safeSectionSub($sub);
            }
            $byField[$field][] = $a;
        }

        return $byField;
    }

    /**
     * section_key に対応する annotation を返す。
     * 'detail:<sub>' という field は 'detail:<sub>:goal' 等のキーに前方一致で対応する。
     *
     * @param  array<string,array<int,array<string,mixed>>>  $byField
     * @return array<int,array<string,mixed>>
     */
    private function annotationsForKey(array $byField, string $key): array
    {
        $matches = [];
        foreach ($byField as $field => $list) {
            if ($field === $key || str_starts_with($key, $field.':')) {
                $matches = array_merge($matches, $list);
            }
        }

        return $matches;
    }
}
