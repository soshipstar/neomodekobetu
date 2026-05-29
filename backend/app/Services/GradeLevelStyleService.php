<?php

namespace App\Services;

/**
 * 学年区分 (preschool / elementary / junior_high / high_school) ごとに、
 * AI 生成プロンプトに添える「対象年齢相応の文言・配慮ガイドライン」を返す。
 *
 * 要望:
 *   支援案や連絡帳の AI 生成で、対象児童の学年に合った語彙・表現・配慮事項
 *   が選ばれるようにする。複数学年が選択されている場合は「もっとも学年の低い方」
 *   の文言を採用する (中学生＋高校生 → 中学生レベル)。
 *
 * 使い方:
 *   $style = GradeLevelStyleService::forTargetGrade('junior_high,high_school');
 *   // $style['effective'] = 'junior_high'
 *   // $style['label']     = '中学生'
 *   // $style['guideline'] = "対象は中学生です。…"
 *
 *   $prompt .= "\n【対象年齢層と表現スタイル】\n{$style['guideline']}\n";
 */
class GradeLevelStyleService
{
    /** 学年区分の順序 (若い方から並べる) */
    private const ORDER = [
        'preschool'   => 0,
        'elementary'  => 1,
        'junior_high' => 2,
        'high_school' => 3,
    ];

    private const LABELS = [
        'preschool'   => '小学生未満',
        'elementary'  => '小学生',
        'junior_high' => '中学生',
        'high_school' => '高校生',
    ];

    /**
     * カンマ区切りの target_grade 文字列、または配列を受け取り、
     * 最も若い学年のスタイル情報を返す。
     *
     * @param  string|array<string>|null $targetGrade
     * @return array{effective:string,label:string,guideline:string,considerations:string}
     */
    public static function forTargetGrade(string|array|null $targetGrade): array
    {
        $grades = self::normalizeGrades($targetGrade);
        $effective = self::pickLowestGrade($grades);
        return [
            'effective'      => $effective,
            'label'          => self::LABELS[$effective] ?? '小学生',
            'guideline'      => self::guideline($effective),
            'considerations' => self::considerations($effective),
        ];
    }

    /**
     * 個別の児童の grade_level (例: 'elementary_3', 'junior_high_2', 'preschool') を
     * 4 大区分 (preschool / elementary / junior_high / high_school) にまとめて
     * スタイル情報を返す。連絡帳の AI 生成のように対象児童 1 人を相手にする
     * 場面で使用。
     *
     * @return array{effective:string,label:string,guideline:string,considerations:string}
     */
    public static function forStudentGrade(?string $studentGrade): array
    {
        $broad = self::broaden($studentGrade);
        return self::forTargetGrade($broad);
    }

    private static function broaden(?string $g): ?string
    {
        if (! $g) return null;
        if (str_starts_with($g, 'preschool')) return 'preschool';
        if (str_starts_with($g, 'elementary')) return 'elementary';
        if (str_starts_with($g, 'junior_high')) return 'junior_high';
        if (str_starts_with($g, 'high_school')) return 'high_school';
        return null;
    }

    /**
     * @param  string|array<string>|null $targetGrade
     * @return array<string>
     */
    private static function normalizeGrades(string|array|null $targetGrade): array
    {
        if ($targetGrade === null || $targetGrade === '') {
            return [];
        }
        $list = is_array($targetGrade) ? $targetGrade : explode(',', $targetGrade);
        return array_values(array_filter(array_map(
            fn ($g) => trim((string) $g),
            $list,
        ), fn ($g) => $g !== '' && isset(self::ORDER[$g])));
    }

    /**
     * 複数指定がある場合は「もっとも低い学年」を採用する
     * (報告者要望: 中学+高校なら中学生レベルに合わせる)。
     *
     * @param  array<string> $grades
     */
    private static function pickLowestGrade(array $grades): string
    {
        if (empty($grades)) {
            // 未指定なら一番一般的な「小学生」を既定にする
            return 'elementary';
        }
        usort($grades, fn ($a, $b) => self::ORDER[$a] <=> self::ORDER[$b]);
        return $grades[0];
    }

    /**
     * プロンプトに直接挿入できる、学年別の「表現スタイルと配慮」ガイドライン。
     */
    private static function guideline(string $grade): string
    {
        return match ($grade) {
            'preschool' => "対象は小学生未満 (未就学児) です。\n"
                . "・ひらがな中心の短く優しい言葉を使ってください (例:「あそぼう」「がんばったね」「たのしかったね」)。\n"
                . "・専門用語や難しい漢語は避け、身近な具体例で表現してください。\n"
                . "・親しみのある呼びかけ (「みんな」「いっしょに」) を使い、五感を意識した表現 (色・音・触り心地) を入れてください。\n"
                . "・1 文を短く (おおむね 30 字以内) し、励まし・共感の表現を多めにしてください。\n"
                . "・保護者向けの記述では、家庭でも楽しめる遊び方を具体的に添えてください。",

            'elementary' => "対象は小学生です。\n"
                . "・小学校で習う基本的な漢字とひらがなを使い、平易で具体的な表現にしてください。\n"
                . "・自分で考えて行動する力 (自主性・選択) を育む声かけを意識した文言にしてください (例:「自分で決めてみよう」「やってみよう」)。\n"
                . "・友だちとの協力・ルール・約束を学ぶ視点を取り入れてください。\n"
                . "・1 文は中程度 (40 字前後) にし、達成感・成功体験を具体的に書いてください。\n"
                . "・抽象概念は身近な例とセットで説明してください。",

            'junior_high' => "対象は中学生です。\n"
                . "・年齢相応の語彙を使い、子ども扱いせず、本人の意思・自己決定を尊重する表現にしてください (例:「自分で計画する」「目標を立てる」)。\n"
                . "・学習・友人関係・部活動・進路への関心など、思春期特有の課題に配慮してください。\n"
                . "・自尊感情を傷つけない言い回しを心がけ、「できなかった」より「次はこうしてみる」と前向きに書いてください。\n"
                . "・保護者向け記述では「家庭での過度な介入を避けつつ見守る」視点を含めてください。\n"
                . "・専門的な言葉も中学生が読んで理解できる範囲で使用可能です。",

            'high_school' => "対象は高校生です。\n"
                . "・大人に近い丁寧で論理的な文体を使い、本人の主体性・意思決定・進路選択を尊重する表現にしてください。\n"
                . "・自己理解・社会参加・就労準備・余暇活動の視点を取り入れてください。\n"
                . "・本人が自分の支援計画を読んでも違和感がない、対等な書き方を心がけてください (上から目線・幼児向け表現は避ける)。\n"
                . "・保護者向け記述は、本人の意思を中心にして「家族で話し合いながら…」のような対話的な表現にしてください。\n"
                . "・専門用語は可。ただし、解説がわかりやすい範囲で使ってください。",

            default => '',
        };
    }

    /**
     * 五領域への配慮で特に重視すべき観点 (学年別)。
     * generateAiFiveDomains のプロンプトに挿入する。
     */
    private static function considerations(string $grade): string
    {
        return match ($grade) {
            'preschool' => "・健康・生活: 排泄・食事・睡眠など基本的生活習慣の獲得を重視。\n"
                . "・運動・感覚: 粗大運動 (走る・跳ぶ) と感覚遊びを中心に。\n"
                . "・認知・行動: 簡単な指示理解、模倣、見立て遊び。\n"
                . "・言語・コミュニケーション: 単語・短文の表出、目線合わせ。\n"
                . "・人間関係・社会性: 大人との愛着、並行遊びから始める。",

            'elementary' => "・健康・生活: 衛生習慣、時間管理、身の回りの整理。\n"
                . "・運動・感覚: 微細運動 (書く・描く)、ルール遊び。\n"
                . "・認知・行動: 学習面でのつまずきへの配慮、課題達成の段階づけ。\n"
                . "・言語・コミュニケーション: 説明・質問・気持ちの表現。\n"
                . "・人間関係・社会性: 友だちとの関わり、順番、トラブル時の解決。",

            'junior_high' => "・健康・生活: 自己管理 (体調・睡眠・食事)、性教育的配慮。\n"
                . "・運動・感覚: 体力維持、スポーツや趣味活動を通じた自己効力感。\n"
                . "・認知・行動: 抽象的思考、計画立案、選択と責任。\n"
                . "・言語・コミュニケーション: 議論・交渉・気持ちの言語化、SNS リテラシー。\n"
                . "・人間関係・社会性: 同年代との対等な関係、進路に向けた自己理解。",

            'high_school' => "・健康・生活: 自立に向けた生活管理、就労を見据えた生活リズム。\n"
                . "・運動・感覚: 余暇・健康維持としての運動、職業に必要な持久力。\n"
                . "・認知・行動: 進路決定、長期計画、メタ認知。\n"
                . "・言語・コミュニケーション: 社会人並みの敬語、報連相、自己 PR。\n"
                . "・人間関係・社会性: 多様な他者との関わり、就労・進学先での適応。",

            default => '',
        };
    }
}
