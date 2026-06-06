<?php

namespace App\Services;

/**
 * 全 AI 呼出に共通して適用する system message 規律と、PDF 等に付与する医療免責文言を一元化する。
 *
 * AISI ヘルスケア AI セーフティ評価観点ガイド v1.0 R4 (2026-05-17):
 *  - V1 有害情報の出力制御 / 表 3-2 ③ 多層防御 (モデル層: システムプロンプトによる役割・禁止事項)
 *  - V2 偽誤情報の出力・誘導の防止 / 表 3-3 ③ 「不明」「追加情報が必要」と出力する制御
 *  - V4 ハイリスク利用・目的外利用への対処 / 表 3-5 ③ 拒否・免責機能 (「私は医師ではありません」等)
 *  - V7 説明可能性 / 表 3-8 ③ AI 生成であることの明示と免責事項の表示
 *  - 4.2.5 (3) ヒューマン・イン・ザ・ループ
 *  - 4.2.5 (4) 透明性・説明可能性: 免責事項の適切な表示
 *
 * 使い方:
 *  $san = new AiPromptSanitizer();
 *  $messages = [
 *      ['role' => 'system', 'content' => AiPromptDirectives::systemBase($san) . $existingSystemMessage],
 *      ...
 *  ];
 *
 *  // PDF blade では:
 *  <small>{{ \App\Services\AiPromptDirectives::medicalDisclaimerFooter() }}</small>
 */
class AiPromptDirectives
{
    /**
     * すべての AI 呼出 system content の先頭に置く規律句。
     *
     * - セキュリティ規律 (Sanitizer)
     * - 出力規律 (ハルシネーション抑制 / 医療助言禁止 / 呼称統一)
     */
    public static function systemBase(AiPromptSanitizer $sanitizer): string
    {
        return $sanitizer->systemGuardClause()
             . "【出力規律】\n"
             . "・事実に基づき記述し、推測・架空のエビデンス・存在しない数値や引用を作成しません。\n"
             . "・不明な事項は『未確認』『情報なし』『追加情報が必要』と明示し、もっともらしく断定しません。\n"
             . "・医療的診断・投薬の助言・治療方針の指示は行いません。健康上の判断が必要な場合は、"
             .   "医師等の有資格者への相談を促す表現にとどめます。\n"
             . "・要配慮個人情報 (病歴、診断名、遺伝情報、精神疾患等) は、与えられた範囲を超えて推論しません。\n"
             . "・対象児童を指す表現は『本人』または与えられた児童名 (placeholder) を使用し、"
             .   "『子ども』『お子様』は使いません。\n"
             . "・他の児童に言及する場合は『友だち』と表記し、『友達』は使いません。\n"
             . "・保護者の呼称は『保護者』で統一し、『保護者様』は使いません。\n"
             . "・指定された出力フォーマット (JSON 等) を逸脱せず、与えられた業務スコープに限定して回答します。\n\n";
    }

    /**
     * PDF / 印刷物 / 保護者公開画面に付与する医療免責フッタ。
     *
     * AISI V4 表 3-5 ③ の「拒否・免責機能」を、care-bridge の業務文脈に合わせて
     * 「Non-SaMD として位置付けた業務記録」である旨を明示する。
     */
    public static function medicalDisclaimerFooter(): string
    {
        return '※ 本書は障害福祉サービスにおける業務記録であり、'
             . '医療行為・診断・投薬助言を目的としていません。'
             . '健康上の不安や医療判断が必要な場合は、医師等の有資格者にご相談ください。';
    }

    /**
     * 保護者画面に表示する「AI 関与」注記の標準文言。
     */
    public static function aiAssistanceNotice(): string
    {
        return '✨ この文章は、職員が AI による下書きを参考に作成・確認した内容です。';
    }
}
