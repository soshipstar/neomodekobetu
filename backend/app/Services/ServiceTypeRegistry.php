<?php

namespace App\Services;

/**
 * 事業所のサービス種別ごとの設定を一元管理するレジストリ。
 *
 * syuro26 の serviceType 駆動設計（lib/ai/prompts.ts, types/index.ts）を
 * carebridge に移植したもの。各画面・API は本クラス経由で文言・キー・
 * プロンプト断片を取得し、サービス種別による分岐をここに集約する。
 *
 * 値:
 *   after_school   : 放課後等デイサービス（児童発達支援含む）
 *   employment_a   : 就労継続支援A型
 *   employment_b   : 就労継続支援B型
 *   transition     : 就労移行支援
 */
class ServiceTypeRegistry
{
    public const AFTER_SCHOOL = 'after_school';
    public const EMPLOYMENT_A = 'employment_a';
    public const EMPLOYMENT_B = 'employment_b';
    public const TRANSITION   = 'transition';

    public const ALL = [
        self::AFTER_SCHOOL,
        self::EMPLOYMENT_A,
        self::EMPLOYMENT_B,
        self::TRANSITION,
    ];

    /**
     * 表示名（フル/短縮/英語ラベル）。
     *
     * @return array<string, array{label: string, short: string}>
     */
    public static function labels(): array
    {
        return [
            self::AFTER_SCHOOL => ['label' => '放課後等デイサービス', 'short' => '放デイ'],
            self::EMPLOYMENT_A => ['label' => '就労継続支援A型',     'short' => '就A'],
            self::EMPLOYMENT_B => ['label' => '就労継続支援B型',     'short' => '就B'],
            self::TRANSITION   => ['label' => '就労移行支援',         'short' => '就移'],
        ];
    }

    public static function label(string $serviceType): string
    {
        return self::labels()[$serviceType]['label'] ?? $serviceType;
    }

    public static function shortLabel(string $serviceType): string
    {
        return self::labels()[$serviceType]['short'] ?? $serviceType;
    }

    /**
     * 強み(才能)チェック 10 項目（種別ごとに別セット）。
     * syuro26 の diaries/new/page.tsx の各テンプレートと同じ並び。
     *
     * @return array<int, string>
     */
    public static function strengthKeys(string $serviceType): array
    {
        return match ($serviceType) {
            self::AFTER_SCHOOL => [
                '集中力', '持続力', '丁寧さ', '発想力', '観察力',
                '思いやり', '情報処理の速さ', '手先の器用さ', '自分で選ぶ力', 'コミュニケーションの工夫',
            ],
            self::EMPLOYMENT_A => [
                '集中力', '正確性', '協調性', '計画力', '報連相',
                '継続力', '柔軟性', '改善意欲', '手先の器用さ', '冷静さ',
            ],
            self::EMPLOYMENT_B => [
                '集中力', '持続力', '丁寧さ', 'コミュニケーションの工夫', '柔軟さ',
                '穏やかさ', '気づきの力', '協力しようとする姿勢', '興味の芽ばえ', '自分で選ぶ力',
            ],
            self::TRANSITION => [
                '自己理解', 'ビジネスマナー', '報連相', '時間管理', '体調管理',
                'ストレス対処', '作業遂行力', '柔軟性', '主体性', '対人関係構築',
            ],
            default => self::strengthKeys(self::AFTER_SCHOOL),
        };
    }

    /**
     * 強み項目 → 領域名 のマッピング（モニタリング集計の domain 表示用）。
     *
     * @return array<string, string>
     */
    public static function strengthDomainMapping(string $serviceType): array
    {
        return match ($serviceType) {
            self::AFTER_SCHOOL => [
                '集中力'                 => '認知・行動',
                '持続力'                 => '認知・行動',
                '丁寧さ'                 => '運動・感覚',
                '発想力'                 => '認知・行動',
                '観察力'                 => '認知・行動',
                '思いやり'               => '人間関係・社会性',
                '情報処理の速さ'         => '認知・行動',
                '手先の器用さ'           => '運動・感覚',
                '自分で選ぶ力'           => '健康・生活',
                'コミュニケーションの工夫' => '言語・コミュニケーション',
            ],
            self::EMPLOYMENT_A, self::EMPLOYMENT_B => [
                '集中力'                 => '就労スキル',
                '持続力'                 => '就労スキル',
                '正確性'                 => '就労スキル',
                '丁寧さ'                 => '就労スキル',
                '協調性'                 => '対人関係・社会性',
                '計画力'                 => '就労スキル',
                '報連相'                 => 'コミュニケーション',
                '継続力'                 => '就労スキル',
                '柔軟性'                 => '行動特性',
                '柔軟さ'                 => '行動特性',
                '改善意欲'               => '行動特性',
                '手先の器用さ'           => '就労スキル',
                '冷静さ'                 => '行動特性',
                '穏やかさ'               => '行動特性',
                '気づきの力'             => '就労スキル',
                '協力しようとする姿勢'   => '対人関係・社会性',
                '興味の芽ばえ'           => '行動特性',
                '自分で選ぶ力'           => '日常生活',
                'コミュニケーションの工夫' => 'コミュニケーション',
            ],
            self::TRANSITION => [
                '自己理解'           => '自己理解',
                'ビジネスマナー'     => '就職準備',
                '報連相'             => '対人関係・社会性',
                '時間管理'           => '就職準備',
                '体調管理'           => '生活基盤',
                'ストレス対処'       => '自己理解',
                '作業遂行力'         => '作業スキル',
                '柔軟性'             => '対人関係・社会性',
                '主体性'             => '自己理解',
                '対人関係構築'       => '対人関係・社会性',
            ],
            default => self::strengthDomainMapping(self::AFTER_SCHOOL),
        };
    }

    /**
     * 個別支援計画の領域 (domain) キー一覧。
     * syuro26 の clients/[id]/plans/page.tsx の DOMAINS と一致。
     *
     * @return array<int, array{key: string, label: string}>
     */
    public static function planDomains(string $serviceType): array
    {
        return match ($serviceType) {
            self::AFTER_SCHOOL => [
                ['key' => 'health_life',             'label' => '健康・生活'],
                ['key' => 'motor_sensory',           'label' => '運動・感覚'],
                ['key' => 'cognitive_behavior',      'label' => '認知・行動'],
                ['key' => 'language_communication',  'label' => '言語・コミュニケーション'],
                ['key' => 'social_relations',        'label' => '人間関係・社会性'],
            ],
            self::EMPLOYMENT_A, self::EMPLOYMENT_B => [
                ['key' => 'health_physical',  'label' => '健康・体調管理'],
                ['key' => 'daily_living',     'label' => '日常生活'],
                ['key' => 'social_skills',    'label' => '対人関係・社会性'],
                ['key' => 'communication',    'label' => 'コミュニケーション'],
                ['key' => 'work_skills',      'label' => '就労スキル'],
                ['key' => 'behavior',         'label' => '行動特性'],
            ],
            self::TRANSITION => [
                ['key' => 'job_readiness',      'label' => '就職準備'],
                ['key' => 'work_skills',        'label' => '作業スキル'],
                ['key' => 'social_skills',      'label' => '対人関係・社会性'],
                ['key' => 'daily_living',       'label' => '生活基盤'],
                ['key' => 'self_understanding', 'label' => '自己理解'],
            ],
            default => self::planDomains(self::AFTER_SCHOOL),
        };
    }

    /**
     * UI 文言の呼称（生徒/利用者、保護者/家族 など）。
     *
     * @return array{
     *   client: string,
     *   client_plural: string,
     *   guardian: string,
     *   facility_role: string,
     *   service_manager: string,
     *   diary: string,
     * }
     */
    public static function terms(string $serviceType): array
    {
        return match ($serviceType) {
            self::AFTER_SCHOOL => [
                'client'          => '生徒',
                'client_plural'   => '生徒',
                'guardian'        => '保護者',
                'facility_role'   => '児童発達支援施設',
                'service_manager' => '児童発達支援管理責任者',
                'diary'           => '連絡帳',
            ],
            self::EMPLOYMENT_A, self::EMPLOYMENT_B => [
                'client'          => '利用者',
                'client_plural'   => '利用者',
                'guardian'        => '家族',
                'facility_role'   => '就労継続支援事業所',
                'service_manager' => 'サービス管理責任者',
                'diary'           => '利用者日誌',
            ],
            self::TRANSITION => [
                'client'          => '利用者',
                'client_plural'   => '利用者',
                'guardian'        => '家族',
                'facility_role'   => '就労移行支援事業所',
                'service_manager' => 'サービス管理責任者',
                'diary'           => '利用者日誌',
            ],
            default => self::terms(self::AFTER_SCHOOL),
        };
    }

    /**
     * AI プロンプトでサービス種別の視点を説明する文章。
     * syuro26 lib/ai/prompts.ts の getServiceFocusDescription を移植。
     */
    public static function aiServiceFocus(string $serviceType): string
    {
        return match ($serviceType) {
            self::AFTER_SCHOOL => "放課後等デイサービスの視点（5領域）:\n"
                . "- 健康・生活: 体調管理、食事、排泄、着替え、身辺自立、生活リズム\n"
                . "- 運動・感覚: 粗大運動、微細運動、感覚過敏/鈍麻、ボディイメージ\n"
                . "- 認知・行動: 集中力、危険回避、ルール理解、見通しを持った行動、学習態度\n"
                . "- 言語・コミュニケーション: 意思表示、会話のやりとり、読み書き、絵カード/AAC\n"
                . "- 人間関係・社会性: 他児との関わり、集団活動への参加、順番待ち、感情コントロール",
            self::EMPLOYMENT_A => "就労継続支援A型の視点:\n"
                . "- 就労能力: 作業の正確性、速度、持続力\n"
                . "- 勤怠管理: 出勤率、遅刻早退、体調管理\n"
                . "- 職場適応: 指示理解、報連相、同僚との関係\n"
                . "- 工賃・給与: 生産性の推移\n"
                . "- 一般就労への移行可能性",
            self::EMPLOYMENT_B => "就労継続支援B型の視点:\n"
                . "- 作業能力: 作業の理解度、遂行力、集中持続時間\n"
                . "- 生活リズム: 通所の安定性、日中活動への参加\n"
                . "- 対人関係: 仲間との協調、スタッフとのやりとり\n"
                . "- 工賃: 作業量と質の推移\n"
                . "- A型や一般就労へのステップアップ可能性",
            self::TRANSITION => "就労移行支援の視点:\n"
                . "- 就職準備: 職業適性、ビジネスマナー、PC スキル\n"
                . "- 求職活動: 履歴書作成、面接練習、企業見学・実習\n"
                . "- 自己理解: 障害理解、配慮事項の整理、ストレス対処\n"
                . "- 生活基盤: 生活リズム、金銭管理、移動手段\n"
                . "- 定着支援の見通し",
            default => self::aiServiceFocus(self::AFTER_SCHOOL),
        };
    }

    public static function isValid(string $serviceType): bool
    {
        return in_array($serviceType, self::ALL, true);
    }
}
