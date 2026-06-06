<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

/**
 * 自傷念慮・暴力・緊急症状など高リスク文脈を検出し、相談窓口情報を返すヘルパー。
 *
 * AISI ヘルスケア AI セーフティ評価観点ガイド v1.0 R10 (2026-05-17):
 *  - V1 有害情報の出力制御 / 表 3-2 ③
 *      「高リスク文脈 (自傷念慮、緊急症状等) では安全優先モードへの動的切替および
 *        相談窓口への誘導が行われ、AI への心理的依存や感情的操作を防止する設計」
 *  - V4 ハイリスク利用 / 表 3-5 ③ 相談窓口・救急対応への誘導フロー
 *
 * care-bridge での適用場面:
 *  - 連絡帳の自由記述 (notes) / 個別支援計画の希望 / 面談記録 / アセスメントに
 *    自傷・他害・緊急症状の表現が含まれる場合
 *  - AI 生成出力は継続するが、冒頭に強制的に相談窓口情報を挿入し、
 *    マスター管理者に通知ログを残す
 *
 * 仕様:
 *  - containsHighRiskContent(): キーワードマッチで検出
 *  - safetyBanner(): 冒頭挿入用の固定バナー (連絡先 + 業務上の確認指示)
 *  - notifyDetection(): master_admin_audit_logs に検出記録 (担当責任者通知の基礎)
 */
class AiSafetyTriage
{
    /**
     * 検出対象キーワード (大カテゴリ別)。
     * 大文字小文字を区別せず、部分一致でマッチング。
     * 過剰検出 (偽陽性) は許容する設計 — 「相談窓口の挿入」自体は実害がないため。
     */
    public const KEYWORDS = [
        'self_harm' => [
            '自殺', '死にたい', '消えたい', 'リストカット', 'リスカ',
            '自傷', '自分を傷つけ', 'OD', '過量服薬',
        ],
        'violence' => [
            '殴られた', '叩かれた', '蹴られた', '暴力を受けた',
            '虐待', 'DV', 'ネグレクト',
        ],
        'emergency' => [
            '意識がない', '意識不明', '呼吸が', '痙攣', 'けいれん',
            'アナフィラキシー', '誤嚥', '異物誤飲',
        ],
    ];

    /**
     * 入力文字列に高リスク表現が含まれているか検出。
     *
     * @return array{detected: bool, categories: string[], hits: string[]}
     */
    public function containsHighRiskContent(string $text): array
    {
        $detectedCategories = [];
        $hits = [];

        foreach (self::KEYWORDS as $category => $words) {
            foreach ($words as $w) {
                if (mb_stripos($text, $w) !== false) {
                    $detectedCategories[$category] = true;
                    $hits[] = $w;
                }
            }
        }

        return [
            'detected'   => ! empty($hits),
            'categories' => array_keys($detectedCategories),
            'hits'       => array_values(array_unique($hits)),
        ];
    }

    /**
     * AI 出力の冒頭に挿入する相談窓口バナー。
     * 連絡帳本文 / 個別支援計画 / モニタリング等の生成結果の先頭に追加する。
     *
     * 連絡先は日本国内の主要な公的窓口を掲載 (本サービスが障害福祉サービス向けのため、
     * 児童相談所と「いのちの SOS」を中心に提示)。
     */
    public function safetyBanner(array $categories): string
    {
        $lines = [];
        $lines[] = '※【担当者確認のお願い】';
        $lines[] = '入力された記録に、安全上の確認が必要な可能性のある表現が含まれていました。';
        $lines[] = '内容を職員間で共有のうえ、本人および保護者の安全を確認してください。';
        $lines[] = '';
        $lines[] = '【主な相談窓口】';
        $lines[] = '・いのちの電話:  0570-783-556';
        $lines[] = '・よりそいホットライン: 0120-279-338';
        $lines[] = '・児童相談所虐待対応ダイヤル: 189 (いちはやく)';
        $lines[] = '・救急の必要性が高い症状の場合: 119 へ通報';
        $lines[] = '';
        if (in_array('self_harm', $categories, true)) {
            $lines[] = '※ 自傷・自殺の念慮が疑われる表現がありました。';
        }
        if (in_array('violence', $categories, true)) {
            $lines[] = '※ 暴力・虐待が疑われる表現がありました。';
        }
        if (in_array('emergency', $categories, true)) {
            $lines[] = '※ 急性症状が疑われる表現がありました。';
        }
        $lines[] = '';
        $lines[] = '---';
        $lines[] = '';

        return implode("\n", $lines);
    }

    /**
     * 検出を master_admin_audit_logs に記録 (担当責任者通知の基礎)。
     *
     * @param array{detected: bool, categories: string[], hits: string[]} $detection
     */
    public function notifyDetection(
        array $detection,
        ?int $userId,
        ?int $studentId,
        string $context,
    ): void {
        if (! $detection['detected']) return;

        try {
            // master_admin_audit_logs に記録 (MasterAdminAuditLog の実カラム構造に合わせる)
            if (class_exists('\\App\\Models\\MasterAdminAuditLog')) {
                \App\Models\MasterAdminAuditLog::create([
                    'master_user_id' => $userId,
                    'action'         => 'ai_safety_triage',
                    'context'        => [
                        'source'      => $context,           // 例: 'renrakucho.generate_integrated'
                        'student_id'  => $studentId,
                        'categories'  => $detection['categories'],
                        'hits'        => $detection['hits'],
                    ],
                ]);
                return;
            }
        } catch (\Throwable $e) {
            // 通知記録失敗は無視 (本処理は継続)
        }

        Log::warning('AI safety triage detected high-risk content', [
            'user_id'    => $userId,
            'student_id' => $studentId,
            'context'    => $context,
            'categories' => $detection['categories'],
            'hits'       => $detection['hits'],
        ]);
    }
}
