# care-bridge ヒューマン・イン・ザ・ループ (HITL) ポリシー

**バージョン**: v1.0
**最終更新**: 2026-05-17
**準拠**: AISI ヘルスケア AI セーフティ評価観点ガイド v1.0 / 4.2.5 (3)

---

## 1. 目的

care-bridge の AI 機能はすべて、業務担当者 (スタッフ・管理者) の判断と確認を前提とする。本ポリシーは、AI と人間の役割分担、介入ポイント、確認プロセスを明確化する。

ガイド 4.2.5 (3) の原則に従い:
- 介在レベルの設計
- 介入ポイントの明確化
- 確認疲れを防ぐ UI/UX
- フォールバック機構

を care-bridge の業務に即して文書化する。

---

## 2. 介在レベルの分類

| レベル | 定義 | care-bridge での該当機能 |
|---|---|---|
| **L1: 人間が最終判断** | AI 出力は下書き。人間が確認・修正してから初めて業務記録として確定する。 | 連絡帳統合文 / 個別支援計画 (本案) / モニタリング報告 / アセスメント / 計画根拠 |
| **L2: 監視・介入可能** | AI 出力がデフォルトで確定するが、人間がいつでも修正・取消できる。 | 教室だより / 活動支援計画 (素案) |
| **L3: 完全自動** | 人間の介入なしに AI 出力が反映される。 | (care-bridge では原則使用しない) |

L1 と L2 のいずれにおいても、保護者公開前に必ず職員が承認する。

---

## 3. 機能ごとの介在ポリシー

### 3.1 連絡帳統合文 (`RenrakuchoController::generateIntegrated`)
- **介在レベル**: L1 (人間が最終判断)
- **介入ポイント**:
  1. AI 生成 → `integrated_notes.ai_assisted = true`, `ai_review_status = 'pending'`
  2. 職員レビュー → `saveDraft` で編集 → `ai_review_status = 'modified'`
  3. 送信 → `sendToGuardians` で保護者公開
- **保護者画面表示**: 「✨ この文章は、職員が AI による下書きを参考に作成・確認した内容です。」
- **フォールバック**: AI 生成失敗時は領域別観察記録を単純結合した文章を返す (既存実装)

### 3.2 個別支援計画 (`SupportPlanController::generateAi`)
- **介在レベル**: L1
- **介入ポイント**:
  1. AI 生成 → `status = 'draft'`
  2. スタッフ編集 → `status = 'draft'` のまま
  3. 確認依頼 (publish) → `status = 'submitted'`
  4. 正式化 (makeOfficial) → `status = 'official'`
  5. 署名 (sign) → 保護者が承認

### 3.3 モニタリング報告 (`MonitoringController::generateAi`)
- **介在レベル**: L1
- **介入ポイント**: AI 生成 → 職員確認 → 確定保存

### 3.4 教室だより (`NewsletterController::generateAi`)
- **介在レベル**: L2 (個人情報を含まない一般情報のため)
- **介入ポイント**: AI 生成 → 編集・送信
- **個人情報遮断**: 児童名・保護者名等は AI プロンプトに含まれない

### 3.5 原案↔本案の変更説明 (`SupportPlanController::generateRevisionNotes`)
- **介在レベル**: L1 (印刷物に含めずスタッフ確認用)
- **介入ポイント**: AI 生成 → `revision_notes` テキストフィールドに保存 → 再生成可能

---

## 4. 確認疲れ対策

ガイド 4.2.5 (3) の「『確認疲れ』を防ぎ、実効的な確認が行われるよう工夫」に従い:

1. **AI 生成と人手記入の視覚的区別**: `ai_assisted` フラグで UI バッジ表示。
2. **段階化された確認フロー**: draft → submitted → official の状態遷移で「次に何を確認すべきか」を明示。
3. **変更履歴の保持**: `ai_review_status` (pending / reviewed / modified / rejected) と `ai_reviewed_by` / `ai_reviewed_at` で「誰がいつ確認したか」を追跡。
4. **差分表示**: 原案↔本案で `revision_notes` (AI 生成サマリ) を提示し、何が変わったかを 200〜500 字で確認可能に。

---

## 5. フォールバック機構

ガイド 4.2.5 (3) の「AI が適切に応答できない場合や、システム障害時に、人間の専門家にエスカレーションする仕組み」に従い:

| 状況 | 動作 |
|---|---|
| OpenAI API キー未設定 | 422 + 「AI 機能が利用できません」メッセージ |
| AI 応答が空 | 連絡帳の場合、領域別観察記録を単純結合した代替文章を返す |
| プロンプトインジェクション検出 | `[REDACTED]` 化 + Warning log |
| 自傷念慮・緊急症状検出 | 相談窓口バナーを冒頭挿入 + `master_admin_audit_logs` に記録 |
| AI 利用同意未取得 | 403 + 同意モーダル表示 |

---

## 6. 監査・トレーサビリティ

- **ai_generation_logs**: 全 AI 呼出の仮名化後の入出力 (R2 + R9 ハッシュチェーン)
- **audit_logs**: AI 関連操作 (publish, sign 等) の old/new 値 (R9 ハッシュチェーン)
- **master_admin_audit_logs**: 安全トリアージ検出記録 (R10)
- **error_logs**: AI 呼出時の例外

これらの監査ログは `php artisan audit-logs:verify-chain` で改ざん検証可能。

---

## 7. 同意撤回時の挙動

- guardian が `ai_usage` または `child_ai_consent` を撤回した場合:
  - 新規 AI 生成リクエストは `RequireAiConsent` middleware で 403
  - 過去に生成済の `ai_assisted=true` の note は保持 (業務記録の完全性のため)
  - 保護者画面の AI 関与表示は引き続き表示 (透明性のため)

---

## 8. 改定

本ポリシーは、ガイドの改定、機能追加、運用上の知見に応じて改定する。改定時は `version` を上げ、本文書冒頭の最終更新日を更新する。
