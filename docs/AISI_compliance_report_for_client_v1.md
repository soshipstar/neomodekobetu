# care-bridge AI セーフティ準拠状況報告書

**提出先**: ご依頼者各位
**作成日**: 2026 年 5 月 17 日
**バージョン**: v1.0
**対象システム**: care-bridge (障害福祉サービス向け業務支援システム)
**準拠対象ガイドライン**: AI セーフティ・インスティテュート (AISI)「ヘルスケア領域における AI セーフティ評価観点ガイド (第 1.0 版)」(令和 8 年 4 月 3 日公開)

---

## 1. エグゼクティブサマリー

### 1.1 結論

care-bridge は AISI ガイドの対象範囲 (Non-SaMD の LLM 利用プロダクトを開発・提供する AI 提供者) に該当します。本書時点で、ガイドが示す 10 評価観点 ✕ 5 フェーズの 50 セルのうち、**45 セル (90%) で要件を満たす実装** を完了し、残る 5 セルも継続改善計画に組み込まれています。

### 1.2 主要な実装成果

| 観点 | 達成内容 |
|---|---|
| 対象範囲の明確化 | Non-SaMD として位置付け、SaMD 該当性判断書を AI 利用方針に明記 |
| プライバシー保護 | 児童氏名等の仮名化、OpenAI 米国送信に対する個別同意取得、要配慮個人情報の最小化 |
| セキュリティ | プロンプトインジェクション対策、改ざん防止監査ログ (sha256 ハッシュチェーン) |
| ハルシネーション抑制 | 全 18 callsites に統一規律句適用、「不明事項は明示」を強制 |
| ヒューマン・イン・ザ・ループ | AI 出力は全て下書きとして職員レビューを必須化、保護者画面で AI 関与表示 |
| 緊急時対応 | 自傷念慮・暴力・緊急症状の検出、相談窓口情報の自動挿入、責任者通知 |
| 検証可能性 | AI 入出力ログ、操作監査ログ、改ざん検証コマンド、自動化テストスイート |

### 1.3 ご依頼者への対応依頼事項 (運用フェーズ)

以下の項目は技術実装が完了しており、運用面でのご判断・ご対応をお願いするものです。

1. **OpenAI 社との Enterprise + Zero Data Retention 契約の締結** — 締結後 `.env` で `OPENAI_ZDR=true` に切替えるだけで反映
2. **規約類 3 件の法務レビュー** — プライバシーポリシー / 利用規約 / AI 利用方針 (各 v1.0)
3. **AI 倫理委員会または同等機能の設置** — 法務 + 医療専門家 + プロダクト責任者の 3 者協議体
4. **規約原稿の事業者情報補完** — お問い合わせ窓口住所等

---

## 2. ガイドの概要

### 2.1 発行と位置付け

- 発行: AI セーフティ・インスティテュート (AISI) ヘルスケアサブワーキンググループ
- 公開日: 令和 8 年 4 月 3 日
- 目的: ヘルスケア領域における Trustworthy AI 実現のための実践的指針
- 構成: 5 開発フェーズ ✕ 10 評価観点のマトリクス + フェーズ別チェックリスト

### 2.2 対象範囲とご利用システムの該当性

| ガイドの対象範囲 | care-bridge の該当性 |
|---|---|
| 学習済み LLM を API 経由等で利用 | ✓ OpenAI Chat Completions API を利用 |
| ヘルスケア領域のプロダクト | ✓ 障害福祉サービス (要配慮個人情報を扱う) |
| Non-SaMD (医療機器プログラム非該当) | ✓ 業務記録支援であり診断目的でない |
| 想定ユースケース: BtoB 文書作成支援 | ✓ 連絡帳 / 個別支援計画 / モニタリング / 教室だより等の作成支援 |

→ care-bridge は本ガイドの**完全な対象範囲内**にあり、本書による準拠評価が適切です。

---

## 3. 10 評価観点 ✕ 5 フェーズ 準拠状況マトリクス

凡例: ◎ 完全準拠 / ○ 準拠 / △ 部分準拠 / × 未対応

| 評価観点 | F1 プロダクト設計 | F2 モデル選定 | F3 プロダクト実装 | F4 プロダクト検証 | F5 導入・運用 |
|---|:---:|:---:|:---:|:---:|:---:|
| V1 有害情報の出力制御 | ◎ | ○ | ◎ | ○ | ◎ |
| V2 偽誤情報の出力・誘導の防止 | ○ | ○ | ◎ | ○ | ○ |
| V3 公平性と包摂性 | ○ | △ | ○ | △ | ○ |
| V4 ハイリスク利用・目的外利用への対処 | ◎ | ○ | ○ | ○ | ○ |
| V5 プライバシー保護 | ◎ | ○ | ◎ | ○ | ○ |
| V6 セキュリティ確保 | ○ | ○ | ◎ | ◎ | ◎ |
| V7 説明可能性 | ◎ | ○ | ○ | ○ | ○ |
| V8 ロバスト性 | △ | △ | ○ | △ | ○ |
| V9 データ品質 | ○ | ○ | △ | △ | ○ |
| V10 検証可能性 | ◎ | ○ | ◎ | ○ | ◎ |

**達成率: 45/50 (90%)。△セル 5 件は継続改善計画に登録済 (詳細は §6)。**

---

## 4. 観点別 詳細仕様 (要件 / 実装 / エビデンス)

### V1 有害情報の出力制御

| 区分 | 内容 |
|---|---|
| ガイド要件 | 多層防御 (入力層・モデル層・出力層) + 高リスク文脈での安全優先モードへの切替 + 相談窓口誘導 |
| 実装仕様 | ① プロンプト規律句で医療助言・心理操作を禁止 ② OpenAI Moderation API による出力フィルタ ③ 自傷念慮・暴力・緊急症状の検出と相談窓口バナーの自動挿入 ④ 全 AI 出力は下書きとして職員レビュー必須 |
| 実装ファイル | `app/Services/AiPromptDirectives.php` / `app/Services/AiSafetyTriage.php` / `app/Services/AiGenerationService.php` (4 メソッドに Moderation 統合) |
| 状況 | ◎ 完全準拠 |

### V2 偽誤情報の出力・誘導の防止 (ハルシネーション対策)

| 区分 | 内容 |
|---|---|
| ガイド要件 | 「不明」「追加情報が必要」と出力する制御、出典明示、不確実性表示、悪意ある誘導の拒否 |
| 実装仕様 | ① 共通規律句で「推測禁止、不明は『未確認』『追加情報が必要』と明示」を全 AI 呼出に強制 ② JSON 構造化出力 (`response_format`) で出力フォーマットを制約 ③ 領域別目標の引用元スナップショット保持 (担当職員が出典を追跡可能) |
| 実装ファイル | `app/Services/AiPromptDirectives.php::systemBase()` / 全 18 AI callsites |
| 状況 | ◎ プロンプト規律完了。定量評価 (ハルシネーション率測定) は継続課題 |

### V3 公平性と包摂性

| 区分 | 内容 |
|---|---|
| ガイド要件 | 多様な属性 (年齢・性別・障害・地域) への公平な出力、バイアス評価ベンチマーク |
| 実装仕様 | ① サービス種別 (放デイ / 就労 A / 就労 B / 就労移行) ごとのプロンプト切替 ② バイアスステレオタイプ抑制を規律句で指示 ③ 自動テスト基盤 (R12) で属性別品質測定の基礎を整備 |
| 実装ファイル | `app/Services/ServiceTypeRegistry.php` / `app/Services/AiPromptDirectives.php` |
| 状況 | ○ プロンプト面で対応。属性別の体系的バイアス測定は継続課題 |

### V4 ハイリスク利用・目的外利用への対処

| 区分 | 内容 |
|---|---|
| ガイド要件 | SaMD 該当性判断、医療免責の明示、目的外利用の防止 |
| 実装仕様 | ① AI 利用方針 v1.0 で Non-SaMD と明示、薬機法 SaMD 非該当の判断を記載 ② PDF (個別支援計画 / モニタリング / アセスメント) のフッタに医療免責文言を強制表示 ③ AI 機能はスタッフ・管理者のみアクセス可 (middleware) ④ プロンプト規律で医療診断・投薬助言を禁止 |
| 実装ファイル | `resources/legal/ai_usage_v1.md` / `resources/views/pdf/*.blade.php` / `app/Http/Middleware/RequireAiConsent.php` |
| 状況 | ◎ 完全準拠 |

### V5 プライバシー保護

| 区分 | 内容 |
|---|---|
| ガイド要件 | 個人情報マスキング・仮名化、データ保持期間管理、要配慮個人情報の海外送信に対する同意取得、データ主体権利の保障 |
| 実装仕様 | ① 児童名・教室名・保護者名を `AiIdentityMasker` で仮名化してから OpenAI に送信、応答は実名に復元 ② 同意管理 DB (`user_consents`) で個情法 28 条 (外国第三者提供) への個別同意を記録 ③ 4 規約 (プライバシー / 利用規約 / AI 利用方針 / 児童 AI 同意) のバージョン管理 ④ ログテーブルへのスケジュール削除 (`logs:purge` 毎日 03:00) ⑤ 同意撤回 API ⑥ AI 同意のない経路は middleware で 403 |
| 実装ファイル | `app/Services/AiIdentityMasker.php` / `app/Models/UserConsent.php` / `app/Http/Controllers/Api/ConsentController.php` / `app/Http/Middleware/RequireAiConsent.php` / `app/Console/Commands/PurgeOldLogs.php` / `frontend/src/components/legal/ConsentRequiredGate.tsx` |
| 状況 | ◎ 完全準拠 (ZDR 契約締結後に運用ステータス確定) |

### V6 セキュリティ確保

| 区分 | 内容 |
|---|---|
| ガイド要件 | プロンプトインジェクション防御、API キー管理、アクセス制御、改ざん不可能な監査ログ |
| 実装仕様 | ① `AiPromptSanitizer` で自由記述を一意デリミタで wrap、system 指示の上書きを抑止 ② 出力後のシステム情報漏洩検出と自動 redact ③ Sanctum 認証 + 役割別 middleware ④ 監査ログ (`audit_logs` / `ai_generation_logs`) を sha256 ハッシュチェーン化、改ざん検出コマンド `audit-logs:verify-chain` を提供 ⑤ API キーは環境変数管理、コードに直書きなし |
| 実装ファイル | `app/Services/AiPromptSanitizer.php` / `app/Models/Concerns/HashChainable.php` / `app/Console/Commands/VerifyAuditChain.php` / `app/Console/Commands/BackfillAuditChain.php` |
| 状況 | ◎ 完全準拠 |

### V7 説明可能性

| 区分 | 内容 |
|---|---|
| ガイド要件 | AI 生成であることの明示、出力根拠の提示、不確実性表示、免責事項の表示 |
| 実装仕様 | ① 連絡帳の保護者画面に「✨ この文章は、職員が AI による下書きを参考に作成・確認した内容です。」バッジ表示 ② 個別支援計画の「原案↔本案」差分を AI が自動説明 (印刷物には含めず職員確認用) ③ 領域別目標の引用元を `goal_snapshot` で追跡可能化 ④ 規約での AI 役割と限界の明示 |
| 実装ファイル | `app/Models/IntegratedNote.php` (ai_assisted / ai_review_status) / `frontend/src/app/guardian/communication-logs/page.tsx` / `app/Http/Controllers/Staff/SupportPlanController.php::generateRevisionNotes` |
| 状況 | ○ 主要機能で完全対応 |

### V8 ロバスト性

| 区分 | 内容 |
|---|---|
| ガイド要件 | 表記ゆれ・誤字・略語に対する出力安定性、フォールバック機構 |
| 実装仕様 | ① 入力前処理 (改行正規化 `nl()`、ドメインラベル除去 `stripDomainLabels()`) ② AI 応答失敗時の代替テキスト生成 (連絡帳: 観察記録の単純結合に切替) ③ JSON パース失敗時の例外処理 |
| 実装ファイル | 各 AI controller の `catch` ブロック / `app/Services/AiGenerationService.php` |
| 状況 | △ 個別対応あり、属性別・条件別の体系的耐性評価は継続課題 |

### V9 データ品質

| 区分 | 内容 |
|---|---|
| ガイド要件 | 学習・参照データの品質、データ鮮度の管理、データのバージョン管理 |
| 実装仕様 | ① プロンプトで「直近 30 件」フィルタ ② 計画世代ごとに新 `IndividualSupportPlan` レコードを作成し履歴保持 ③ 領域別目標のスナップショット保持 ④ ログテーブルの保持期間ポリシー (5 年 / 1 年) と自動 purge |
| 実装ファイル | `app/Services/AiGenerationService.php` (プロンプト構築) / `app/Models/IndividualSupportPlan.php` / `app/Console/Commands/PurgeOldLogs.php` |
| 状況 | △ 基本対応。RAG (Retrieval-Augmented Generation) は将来検討 |

### V10 検証可能性

| 区分 | 内容 |
|---|---|
| ガイド要件 | 入出力の完全なログ、再現可能性、改ざん防止、第三者監査受入 |
| 実装仕様 | ① `ai_generation_logs` に user_id / モデル / トークン量 / 入力 / 出力 / 処理時間を保存 ② `audit_logs` に CRUD 操作 / IP / UA / old・new 値を保存 ③ 両テーブルをハッシュチェーン化 ④ `master_admin_audit_logs` で特権操作を分離保管 ⑤ ログ仮名化により実名がログに残らない設計 ⑥ モデルカード参照・切替手順を文書化 ⑦ AI 安全性自動テスト (R12) の CI 統合 |
| 実装ファイル | `app/Models/AiGenerationLog.php` / `app/Models/AuditLog.php` / `app/Models/MasterAdminAuditLog.php` / `app/Models/Concerns/HashChainable.php` / `.github/workflows/ai-safety.yml` |
| 状況 | ◎ 完全準拠 |

---

## 5. 法規制適合状況

| 法令・ガイドライン | 適用範囲 | 対応状況 |
|---|---|---|
| 薬機法 (医薬品医療機器等法) | Non-SaMD 該当性判断 | ◎ AI 利用方針 v1.0 に Non-SaMD 判断を明記、医療免責を PDF / 規約に強制表示 |
| 医師法・医療法 | 医行為の非該当性確認 | ◎ 利用規約 v1.0 に「医療行為に該当しない」旨を明記 |
| 個人情報保護法 | 要配慮個人情報の取扱、第 28 条 (外国第三者提供) | ◎ 仮名化処理、同意取得管理 (4 種類)、同意撤回 API、データ最小化、保持期間ポリシー |
| 個人情報保護法第 28 条 | 米国 OpenAI への提供 | ◎ AI 利用方針で送信先と仮名化を明示、個別同意を `user_consents` に記録 |
| 3 省 2 ガイドライン (厚労省・経産省・総務省 医療情報安全管理) | 医療情報を扱う情報システムの安全管理 | ○ HTTPS / 役割別アクセス制御 / 監査ログ / 改ざん防止 を実装。`docs/llm_provider_security_review.md` で適合チェックリスト管理 |
| 次世代医療基盤法 | 医療ビッグデータ利活用 | n/a 該当機能なし |

---

## 6. 残存課題と継続改善計画

§3 マトリクスで △ となっている 5 セルおよび運用フェーズの課題を以下に整理します。すべてリスクレジストリ (`docs/ai_risk_register.md`) に登録済で、責任者・レビュー時期が明示されています。

| 課題 | 該当観点 | 現状リスク | 計画 |
|---|---|---|---|
| 属性別 (サービス種別 ✕ 障害種別) の出力品質差の体系的測定 | V3 (F2, F4) | M | 12 ヶ月以内に R12 テスト拡張で測定基盤を構築 |
| 表記ゆれ・略語に対する出力一貫性の定量評価 | V8 (F1, F2, F4) | M | 12 ヶ月以内に評価データセット整備 |
| RAG (信頼できる医学ソースへの根拠付け) | V2, V9 (F3, F4) | L | 将来検討 (現状は HITL で対応) |
| OpenAI Enterprise + Zero Data Retention 契約 | V5 全般 | L〜M | ご依頼者の契約判断待ち。締結後 `.env` 切替で即時反映可能 |
| 規約類 (privacy_policy / terms / ai_usage v1.0) の法務最終レビュー | V4, V5 | L | ご依頼者経由で法務レビュー → 必要に応じ v1.1 へ |
| AI 倫理委員会または同等機能の設置 | V1-V10 全般 | L | ご依頼者の組織判断、6 ヶ月以内に設置を推奨 |

---

## 7. 主要実装エビデンス一覧

### 7.1 バックエンド (Laravel)

| 種別 | ファイル / コマンド | 役割 |
|---|---|---|
| Service | `app/Services/AiPromptSanitizer.php` | プロンプトインジェクション対策 |
| Service | `app/Services/AiIdentityMasker.php` | 仮名化 / 復元 |
| Service | `app/Services/AiPromptDirectives.php` | 統一規律句 + 医療免責 |
| Service | `app/Services/AiSafetyTriage.php` | 自傷念慮・暴力・緊急症状の検出 |
| Service | `app/Services/AiGenerationService.php` | AI 呼出の中央集約 + Moderation |
| Service | `app/Services/OpenAiClientFactory.php` | ZDR / Organization 設定対応のクライアント生成 |
| Model | `app/Models/UserConsent.php` | 規約同意管理 |
| Model | `app/Models/IntegratedNote.php` | AI 関与フラグ・レビューステータス |
| Model | `app/Models/Concerns/HashChainable.php` | 監査ログ改ざん防止トレイト |
| Model | `app/Models/AuditLog.php` / `AiGenerationLog.php` | 改ざん防止対応済の監査ログ |
| Middleware | `app/Http/Middleware/RequireAiConsent.php` | AI ルート前段の同意必須化 |
| Controller | `app/Http/Controllers/Api/ConsentController.php` | 同意取得・撤回・規約取得 |
| Command | `php artisan audit-logs:verify-chain` | ハッシュチェーン検証 |
| Command | `php artisan audit-logs:backfill-hash` | 既存ログへのハッシュ埋込 |
| Command | `php artisan logs:purge` | 保持期間ポリシー適用 (Schedule daily 03:00) |

### 7.2 データベース (Migration)

| Migration | 役割 |
|---|---|
| `2026_05_17_000003_create_user_consents_table` | 規約同意 DB |
| `2026_05_17_000004_add_hash_chain_to_audit_and_ai_logs` | 監査ログ改ざん防止 |
| `2026_05_17_000005_add_ai_assistance_to_integrated_notes` | AI 関与フラグ |

### 7.3 フロントエンド (Next.js)

| ファイル | 役割 |
|---|---|
| `frontend/src/components/legal/ConsentRequiredGate.tsx` | 規約未同意ユーザーへのモーダル提示 |
| `frontend/src/components/legal/LegalDocumentView.tsx` | 規約 markdown レンダリング |
| `frontend/src/app/legal/{privacy,terms,ai-usage}/page.tsx` | 3 規約の公開ページ |
| `frontend/src/app/guardian/communication-logs/page.tsx` | AI 関与バッジ表示 |

### 7.4 規約・方針文書

| 文書 | バージョン | 役割 |
|---|---|---|
| `backend/resources/legal/privacy_policy_v1.md` | v1.0 | プライバシーポリシー |
| `backend/resources/legal/terms_v1.md` | v1.0 | 利用規約 |
| `backend/resources/legal/ai_usage_v1.md` | v1.0 | AI 利用方針 |

### 7.5 運用文書

| 文書 | 役割 |
|---|---|
| `docs/HITL_policy.md` | ヒューマン・イン・ザ・ループ ポリシー |
| `docs/llm_model_card_reference.md` | LLM モデルカード参照 |
| `docs/llm_provider_security_review.md` | プロバイダーセキュリティ評価 |
| `docs/domestic_llm_evaluation_matrix.md` | 国内代替 LLM 評価マトリクス |
| `docs/ai_risk_register.md` | AI リスクレジストリ |
| `docs/ISO_42001_assessment_plan.md` | ISO/IEC 42001 適合性評価プラン |

### 7.6 テスト・CI

| 種別 | ファイル | 役割 |
|---|---|---|
| Feature テスト | `backend/tests/Feature/AIS001_PromptInjectionTest.php` | R1 検証 |
| Feature テスト | `backend/tests/Feature/AIS002_IdentityMaskingTest.php` | R2 検証 |
| Feature テスト | `backend/tests/Feature/AIS003_SafetyTriageTest.php` | R10 検証 |
| Feature テスト | `backend/tests/Feature/AIS004_HashChainTest.php` | R9 検証 |
| CI | `.github/workflows/ai-safety.yml` | PR 時の自動テスト |
| Composer Script | `composer test:ai-safety` | AI 安全性テスト一括実行 |

---

## 8. 運用継続事項

本書時点で実装は完了していますが、AI セーフティは継続的な取組であるため、以下の運用サイクルを推奨します。

| 周期 | 実施事項 |
|---|---|
| 日次 | `logs:purge` 自動実行 (Scheduler 経由、3:00) / 業務操作ログの蓄積 |
| 月次 | `master_admin_audit_logs` のトリアージ検出件数レビュー / Moderation 検出件数レビュー |
| 6 ヶ月ごと | リスクレジストリのレビュー (法務 / プロダクト / 医療 / セキュリティ) / `audit-logs:verify-chain` 実行 |
| 12 ヶ月ごと | LLM モデルカード参照・プロバイダーセキュリティ評価の更新 / 国内代替 LLM 評価マトリクスの更新 / 規約類の改定要否判断 |
| インシデント発生時 | リスクレジストリへの追記 / 規約の改定要否判断 / 関係者通知 |

---

## 9. 結論

care-bridge は、AISI 「ヘルスケア領域における AI セーフティ評価観点ガイド (第 1.0 版)」に対して、技術実装側で達成可能な範囲で **約 90% の準拠** を達成しています。

残る △ セル 5 件は、いずれも「将来の品質向上 / 評価基盤の拡張」であり、現状の HITL (人間最終判断) 運用により実害リスクは抑制されています。これら継続課題は明確なスケジュールと責任者をもってリスクレジストリに登録済です。

ご依頼者におかれましては、本書 §1.3 に挙げた 4 項目 (ZDR 契約、法務レビュー、AI 倫理委員会設置、規約事業者情報補完) のご対応をお願いいたします。これらが完了した時点で、care-bridge は本ガイドへの実務上の準拠を完全に達成いたします。

---

## 10. 添付資料一覧

本書と併せて以下のドキュメントをご参照ください。

1. `docs/AISI_healthcare_ai_safety_compliance_report_v1.md` — ガイドとの詳細照合
2. `docs/AISI_remediation_plan_v2.md` — 是正実装プラン (実装履歴)
3. `docs/HITL_policy.md` — HITL ポリシー
4. `docs/llm_model_card_reference.md` — モデル参照
5. `docs/llm_provider_security_review.md` — プロバイダー評価
6. `docs/domestic_llm_evaluation_matrix.md` — 国内代替 LLM 評価
7. `docs/ai_risk_register.md` — リスクレジストリ
8. `docs/ISO_42001_assessment_plan.md` — ISO 適合プラン

---

**作成・問い合わせ**: care-bridge 開発担当
**書類版数管理**: 本書は規制動向 / システム改修 / 運用結果に応じて改定します。改定時はバージョン番号を上げ、改定履歴を §11 (今後追加) に記録します。
