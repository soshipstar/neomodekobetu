# kiduri AI Learning Platform 企画書（再構成版）

> 障害福祉支援知識基盤 設計仕様書 v1.0（添付PDF）を基に、**現状の kiduri 実装と接続**し、
> 今後の方針を再議論するためのたたき台として再構成したもの。
> 本書は「実装済み」「未実装(=これから蓄積/構築)」「論点」を明確に分けて記述する。

作成日: 2026-06-14 ／ 版: 再構成 v0.1（議論用ドラフト）

---

## 0. 一行サマリ

kiduri は「AI文章生成システム」ではなく、**障害福祉現場の支援知識(なぜ修正したか・なぜその支援を選んだか・なぜ成果が出たか)を資産として蓄積し続ける知識基盤**である。
AIモデルは交換可能（OpenAI/Claude → 将来ローカル Ollama/vLLM）だが、蓄積された知識は交換できない。
**最優先は「将来のローカルAIが使える形でデータを今から貯め始めること」**。

---

## 1. ビジョンと基本思想

### 1.1 知識の循環（kiduri が目指す形）

```
支援記録 → AI生成 → 人間修正 → 支援実施 → モニタリング → 成果確認 → 知識蓄積 → 次回AI改善
                ↑__________________________________________________________|
```

従来の「入力 → AI生成 → 人間利用」で終わる一方向ではなく、**人間の修正と成果を毎回フィードバックして蓄積する循環**にする。

### 1.2 基本思想（仕様書より）

- **AIを学習させるのではなく、まず人間の知識を蓄積する。**
- AIはいつでも交換できる。しかし「なぜ修正したか／なぜその支援を選んだか／なぜ成果が出たか」は交換できない。
- kiduri はこの“理由”と“成果”を保存する。
- 学習単位は**文書全体ではなくセクション単位**（健康・生活／運動・感覚／認知・行動／言語・コミュニケーション／人間関係・社会性／総合所見／保護者コメント／支援目標／支援内容）。

> 補足: このセクション体系は、本セッションで構築した**能力評価システムの5領域**および `student_records` の5領域カラムと一致しており、知識基盤の“軸”として既に整合している。

---

## 2. 中核：蓄積するデータモデル（本書の心臓部）

仕様書は「AI生成 → 人間修正 → 差分 → 修正理由」をイベントとして残すことを求めている。これが将来のローカルAIの学習素材になる。

### 2.1 データ階層

| 層 | 目的 | 保存内容 | 保存期間/更新 |
|---|---|---|---|
| **Layer1 原本層** | 監査・再分析・再学習 | AI生成文／修正文／最終採用文／差分／プロンプト／モデル情報／施設情報 | 無期限 |
| **Layer2 集約層** | AI改善 | 修正率／採用率／編集量／編集時間／修正理由統計 | 日次更新 |
| **Layer3 学習層** | 将来の独自(ローカル)AI | 良い例／悪い例／修正理由／理想文 | 蓄積 |

### 2.2 イベント（仕様書のテーブル設計）

- **`ai_generation_events`**: id / facility_id / child_id / document_type / section_type / prompt / model_name / model_version / engine_name / generated_text / created_by / created_at
- **`ai_edit_events`**: id / generation_event_id / edited_by / before_text / after_text / edit_duration_seconds / created_at
- **`ai_text_diffs`**: id / edit_event_id / diff_type / before_fragment / after_fragment / position_start / position_end
- **`ai_edit_reasons`**: reason_type（下記の固定タクソノミ）

### 2.3 修正理由タクソノミ（`ai_edit_reasons.reason_type`）

`too_abstract`（抽象的すぎる）／`lack_specific_behavior`（具体行動が無い）／`fact_error`（事実誤り）／`goal_mismatch`（目標とずれ）／`domain_mismatch`（領域違い）／`tone_parent`（保護者向け語調）／`tone_admin`（行政向け語調）／`negative_expression`（否定表現）／`strength_added`（強みを追記）／`facility_style`（施設の書き方）／`other`

> この**修正理由の構造化が最重要**。「AI: 社会性の向上が見られた → 人間: 他児へ自ら声をかける場面が増えた（理由: too_abstract）」のように、抽象→具体への変換ルールを抽出できる。

---

## 3. 現状 kiduri とのギャップ分析（最重要セクション）

| 仕様書が求めるもの | 現状の kiduri | 差分・必要対応 |
|---|---|---|
| AI生成イベントの記録 | ✅ `AiGenerationLog`（user/type/model/prompt/tokens/input_data/output_data/duration/検証可能性列） | 概ね有り。ただし **facility_id/child_id/document_type/section_type が構造化列でなく** input_data JSON 依存。section単位でない |
| 人間修正（before/after）の記録 | △ 支援計画のみ `proposal_snapshot`(AI原案) + `revision_annotations`(変更点) | **汎用の `ai_edit_events`(before/after/編集時間) が無い**。連絡帳・モニタリング・保護者コメント等は未捕捉 |
| 文章差分（diff）の保存 | △ revision_annotations が計画限定 | **`ai_text_diffs`(セクション内の断片差分) が無い** |
| 修正理由の構造化 | ❌ 無し | **`ai_edit_reasons` タクソノミ + 入力UI が未実装**（＝最大の不足） |
| Layer2 集約（修正率/採用率/理由統計） | ❌ 無し | 日次集計バッチ・統計テーブルが必要 |
| Facility / Staff Writing Profile | ❌ 無し | 施設別・職員別の文体プロファイル蓄積が必要 |
| Phrase Learning（頻出修正） | ❌ 無し | before/after + reason + frequency の蓄積 |
| Learning Score（ルール信頼度） | ❌ 無し | 修正回数/修正者数/施設数/採用率から算出 |
| Outcome Learning（支援↔成果） | ❌ 無し（将来） | モニタリング成果との関連付け |
| AIエンジン交換可能性 | △ `AiGenerationService`/`AiGenerationTask`（OpenAI前提, gpt-5.4系） | **ローカル(Ollama/vLLM)を差し替えられる抽象化**が必要 |
| Vector DB（類似事例検索） | △ pgvector 拡張は基盤にあり（embedding生成関数は存在） | **類似事例検索の用途設計・索引化が未整備** |
| プライバシー保護 | ✅ `PiiMasker`(A005ガードで全AI経路を強制) | 外部AIへはマスク済み。**ローカルAI移行で“非マスク学習”の是非**を要設計 |

### 3.1 すでに知識基盤として動き始めている資産（本セッション成果）

- **能力評価システム（P0〜P5 本番稼働）**: 評価マスタ80項目・到達目安・観察記録・ルールベース採点・個別支援計画反映・別添。**客観評価データの蓄積が開始済み**。
- **mynameis 連携（主観×客観）**: 本人の主観自己評価を member_code で突合し統合表示。**主観データの蓄積経路が確立**。
- これらは仕様書の「5領域セクション × 児童 × 時系列」という**知識の骨格**そのもの。

> 結論: kiduri は「生成ログ」までは持っているが、**“人間がどう直し、なぜ直し、結果どうなったか”の構造化蓄積が決定的に不足**している。ここを今から埋めることが、将来ローカルAIを動かす前提になる。

---

## 4. システムアーキテクチャ（目標像）

```
利用者 → kiduri Application → API Layer → AI Learning Platform → Database Layer
                                            ├─ AI Engine（交換可能: OpenAI/Claude/Ollama/vLLM）
                                            ├─ Knowledge Base（全国共通知識/施設/職員/利用者）
                                            ├─ Facility Profile（施設文体）
                                            ├─ Staff Profile（職員文体）
                                            ├─ Learning Engine（修正→ルール抽出→次回反映）
                                            └─ Analytics Engine（日次/月次集計）
```

### 4.1 AI生成時のコンテキスト優先順位（仕様書）

```
全国共通知識 → 施設知識 → 職員知識 → 利用者情報 → 支援記録 → 文章生成
```

### 4.2 ストレージ構成（仕様書）

- **PostgreSQL**: ID・タグ・構造化データ（既存DB）
- **Object Storage**: 長文・JSONログ（原本層の大容量保存）
- **Vector Database**: 検索索引・類似事例検索（pgvector を活用可能）
- **Analytics Database**: 日次/月次集計（Layer2）

---

## 5. ローカルAI移行の構想

仕様書の核心は「**AIは交換可能**」。将来 OpenAI/Claude → ローカル(Ollama/vLLM)へ移しても、蓄積知識でむしろ精度を上げられる状態を作る。

- **短期（RAG/プロンプト注入）**: Facility/Staff Profile + Phrase Learning + 類似事例(Vector DB) を**プロンプトに注入**して、モデル非依存で“施設らしい文章”を生成。ローカルモデルでも同じ仕組みが効く。
- **中期（施設別チューニング）**: Layer3（良い例/悪い例/理想文/修正理由）を**評価データセット**化し、プロンプト最適化・小規模ファインチューニングに使う。
- **長期（独自/ローカルAI）**: 蓄積データで自前モデルを学習/評価。**外部送信ゼロ運用**（要配慮個人情報をクラウドに出さない）を実現。
- 設計原則: **kiduri 本体は特定モデルに依存しない**（`AiGenerationService` の背後に Engine 抽象を置き、`engine_name/model_name/model_version` を全生成イベントに記録）。

---

## 6. 段階的ロードマップ（仕様書 × kiduri の現実）

| Phase | 仕様書の狙い | kiduri 具体アクション（提案） |
|---|---|---|
| **Phase1 (2026)** AI文章生成+差分・理由保存 | 支援記録/モニタリング/個別支援計画のAI生成、差分保存、修正理由保存 | ① `ai_generation_events`/`ai_edit_events`/`ai_text_diffs`/`ai_edit_reasons` を新設 ② 既存 `AiGenerationLog` を section/document/child/facility で構造化 ③ **編集保存時に before/after を捕捉**し、**修正理由をUIで1クリック選択**できるようにする（最優先） |
| **Phase2 (2027)** 施設学習 | Facility Profile・Phrase Learning・自動プロンプト改善 | 日次集計(Layer2)→ 施設文体プロファイル → 生成プロンプトへ自動注入 |
| **Phase3 (2028)** 職員学習 | Staff Profile・文章最適化・施設別AI | 職員別文体、Learning Score によるルール信頼度づけ |
| **Phase4 (2029)** 成果分析 | 支援と成果の関連分析 | モニタリング成果と支援内容の関連付け（能力評価スコアの時系列が素材） |
| **Phase5 (2030)** 成果予測 | Outcome Learning Engine | 「どの支援がどの成果につながるか」を学習・予測。**ローカルAI運用へ** |

---

## 7. データ蓄積の“いますぐ”優先施策（Phase1の中でも先頭）

将来のローカルAIの質は、**今日からどれだけ正しい形で貯め始めるか**で決まる。以下を最優先で提案:

1. **編集イベント捕捉の標準化**: AI生成文（原本）と人間の最終採用文を全AI機能で必ず保存（before/after + 編集時間）。
2. **修正理由の1クリック入力**: 保存時に上記タクソノミから理由を選ぶ軽量UI（現場負担を最小化）。これが Layer3 の質を決める。
3. **セクション単位の粒度**: 文書全体でなく5領域+各セクション単位で生成・差分・理由を紐づける。
4. **メタの構造化**: 全イベントに facility_id / child_id / document_type / section_type / engine/model/version を構造化列で付与。
5. **集計の自動化(Layer2)**: 修正率・採用率・編集理由統計を日次で集計（後の施設プロファイルの原資）。

> これらは「新しいAI機能」ではなく「**蓄積の配管**」。地味だが、これ無しに将来のローカルAIは成立しない。

---

## 8. プライバシー・倫理・ガバナンス（福祉データ特有）

- 支援記録・評価は**要配慮個人情報**。現状、外部AIへは `PiiMasker`（A005ガード）で氏名等を仮名化済み。
- **原本層(Layer1)は実名を含む**ため、保存先（DB/Object Storage）のアクセス制御・暗号化・保持ポリシーを明文化する。
- **学習利用の同意**: 施設/保護者/本人の同意範囲（記録の二次利用＝AI改善・学習）を整理（mynameis 側に同意管理あり=参考）。
- **ローカルAI化の利点**: 外部送信ゼロにできれば、マスクなしで知識を活かせる余地（ただし内部統制は必須）。
- 監査性: 全生成・編集にモデル/版/プロンプトを残し「なぜこの文章になったか」を後から説明可能にする（既に検証可能性列を一部実装済み）。

---

## 9. 再議論したい論点（次回ディスカッション用）

1. **着手範囲**: Phase1の「蓄積の配管」（編集イベント+修正理由+差分の構造化）から始めてよいか。対象文書はまず何から？（個別支援計画／連絡帳／モニタリングのどれを先行）
2. **修正理由UIの形**: 現場負担を抑えるため、理由入力は「任意・1クリック・後付け可」でよいか。タクソノミは仕様書の11分類で確定か。
3. **AIエンジン抽象化の優先度**: いつローカル(Ollama/vLLM)対応の抽象化を入れるか（早めに `engine_name` 等を records しておくと後が楽）。
4. **ストレージ方針**: 原本長文を Object Storage に分離するか、当面 PostgreSQL/JSONB で持つか。Vector DB(pgvector) の類似事例検索をどの機能で最初に使うか。
5. **同意・ガバナンス**: 二次利用（学習）の同意モデルをどう設計するか。原本層の保持・アクセス方針。
6. **成果の定義**: Phase4以降の「成果」を何で測るか（能力評価スコアの変化／モニタリング達成度／主観×客観の一致 など）。
7. **能力評価・mynameis連携との統合**: 既に貯まり始めた客観/主観データを、本知識基盤の Layer1/3 にどう織り込むか。

---

## 付録A. 用語と現状実装の対応早見表

| 仕様書の語 | 現状の kiduri 実体 |
|---|---|
| AI Engine | `AiGenerationService` / `AiGenerationTask`（OpenAI gpt-5.4系） |
| 生成ログ | `AiGenerationLog`（生成のみ・要拡張） |
| 5領域セクション | `student_records` の5領域 / 能力評価マスタの領域 |
| プライバシー保護 | `PiiMasker`（A005静的ガード） |
| 客観評価の蓄積 | 能力評価システム（observations/scores） |
| 主観評価の蓄積 | mynameis 連携（subjective scores, member_code突合） |
| 個別支援計画の原案/改訂 | `proposal_snapshot` / `revision_annotations` |

## 付録B. 出典

- 添付PDF: 「kiduri AI Learning Platform 障害福祉支援知識基盤 設計仕様書 v1.0」（全13ページ）
- 本書はその内容を、現状コードベースと突き合わせて再構成した議論用ドラフトである。

---
---

# Part II — 実装設計 (v0.2)

> Part I(vision/ギャップ)を踏まえ、現状コードに接地した**実装可能な具体設計**。
> 5データソースの並列調査 + 4設計(配管/修正理由UI/同意保持/成果) + 整合レビュー の成果を統合し、
> さらにユーザー追加要件(多軸解析・自己改善ループ・支援案学習・職員傾向レポート)を反映した。
> Part I の高レベル・ロードマップは本Part IIの §15 横断ロードマップで上書きする。

## 10. 統一データモデル(正典)

> ⚠️ 整合レビューの最重要指摘: 配管/修正理由/同意の各設計が**同名テーブルを別スキーマで3重定義**していた(`ai_edit_events` vs `ai_revision_events`、`ai_edit_reasons` の二重定義、差分の置き場所不一致)。さらに **PII方針が正面衝突**(「マスク済保存」vs「実名+暗号化」)。
> **結論(採用): 同意設計のLayer分けを正典とする。** マスク済だけ貯めると、学習すべき固有表現(例「○○小学校」)が消え、撤回時の児童特定削除も不可能になるため。以下は3設計を統合した単一スキーマ。すべて**新規・追記専用(append-only)テーブル**で、既存テーブルは一切変更しない(後方互換)。

### 10.1 PIIレイヤの原則(全テーブル共通の鉄則)

| 層 | テーブル | PII状態 | 根拠 |
|---|---|---|---|
| 生成(外部AI送信) | `ai_generation_events` | **マスク済**(プレースホルダ) | OpenAIへ出すのはマスク後(A005)。その写しを保存 |
| Layer1 原本(人間修正) | `ai_revision_events` | **実名**(Laravel `encrypted` cast で暗号化) | 学習素材の正典。固有表現を残す。撤回時に児童特定削除するため実名連結が必要 |
| Layer2 集約 | `*_stats_daily` | **個人データなし**(統計のみ) | `improvement_aggregate`(施設同意)で可 |
| Layer3 学習 | `learning_corpus` | **仮名化**(`source_student_hash` のみ。`LearningPiiScrubber` fail-closed 通過必須) | `model_learning`(保護者/本人同意)で初めて生成 |

### 10.2 中核テーブル

- **`ai_generation_events`**(生成=before の源泉, マスク済): `id / ai_generation_log_id(FK,既存ログ参照) / document_type / document_id / student_id / classroom_id / company_id / user_id / generation_type / model / prompt_version(=sha1(system_prompt)) / sources_used(jsonb) / generated_payload(jsonb: section_key→text, マスク済) / generated_at`。
- **`ai_revision_events`**(人間修正=after, Layer1 原本・実名暗号化・セクション単位): `id / company_id / classroom_id / student_id(nullable) / document_type / document_id / section_key / ai_generation_event_id(FK,nullable) / before_text(enc) / after_text(enc) / diff(jsonb) / change_ratio / edit_kind / editor_user_id / editor_role / sensitivity('raw'|'pseudonymized') / created_at(append-only)`。**学習の主単位**。
- **`ai_edit_reasons`**(修正理由イベント): `id / ai_revision_event_id(FK) / category_id(FK,nullable) / free_text(任意) / reason_source('human_manual'|'ai_annotation'|'guardian_comment'|'meeting_minutes'|'monitoring_data') / source_ref(jsonb) / user_id / created_at`。1修正に「チップ複数+自由記述」を別行で。
- **`ai_edit_reason_categories`**(修正理由カテゴリ=固定11+動的追加): `id / code / label_ja / description / company_id(NULL=全社共通/値=法人内) / is_seeded / status / sort_order / usage_count / centroid_meta(jsonb) / timestamps`。`unique(company_id, code)`。
- **`ai_edit_reason_candidates`**(新カテゴリ候補=昇格待ち): `id / company_id / normalized_text / member_texts(jsonb) / frequency / nearest_category_sim / status('pending'|'approved'|'rejected'|'merged') / merged_into_category_id / detection_meta(jsonb) / reviewed_by / timestamps`。
- **`document_type` × `section_key` マッピング**(5ソース): `support_plan`(life_intention/overall_policy/long_term_goal/short_term_goal/detail:<sub_category>)、`monitoring`(overall_comment/detail:<domain>)、`assessment_staff`/`assessment_guardian`(student_wish/各5領域/目標)、`integrated_note`(integrated_text)、`ability_eval`(subjective_vs_objective:<item_id> = スコア差分)。5領域の語彙揺れ(`health_life`/`domain_health_life`/`category`)は `domain_canonical` 変換表で吸収。

### 10.3 フック挿入方針(行番号でなくアンカーで)

> レビュー指摘: 行番号は改修でずれる。**「メソッド名+処理の直後」をアンカーに**する。
- 生成フック: 各 `*Controller` の **`AiGenerationLog::create` 直後**(同一Tx内)で `ai_generation_events` を作成。
- 編集保存フック: 各 `*Controller@update/store` の **DB保存直後**(同一Tx内)で `ai_revision_events`(+diff算出)を作成。
- 共通の薄いサービス `AiLearningCapture`(`recordGeneration()` / `recordRevision()`)に集約。**フック失敗は try/catch で握りつぶす**が、**同意ゲート違反は握りつぶさず fail-closed**(§12と整合)。
- 対象: 個別支援計画 `SupportPlanController`(generateAi/update/generateRevisedDraft/publish)、`MonitoringController`(generateAi/update)、`AssessmentController`(staff/guardian)、`RenrakuchoController`(generateIntegrated/sendToGuardians)、`MynameisSyncController`(ingest=主観差分)。

## 11. 修正理由UI(1クリック + 自由記述 + 動的タクソノミ)

> ユーザー要件: 「1クリック(チップ)+自由記述。自由記述が新カテゴリと判断されたらボタンが追加される」。
> レビュー指摘を反映し、**埋め込み類似・自動昇格はP1へ後送り**(要件E「ローカルAIは先」「過剰実装しない」の精神)。

- **P0(最小)**: 固定11チップ + 自由記述。自由記述は `ai_edit_reason_candidates` に正規化して蓄積し、**管理者が1クリックでチップ昇格**(`useReasonCategories` でサーバ動的取得=再デプロイなしでボタン追加 → 要件充足)。固定11分類: 抽象的すぎる/冗長/事実誤り・創作/文体不一致/用語・言い回し/記載漏れ/不要・蛇足/プライバシー配慮/不適切/体裁・構成/本人像に合わない。
- **P1(動的タクソノミ自動化)**: 既存 pgvector(`EmbeddingService`, 1536次元)で自由記述の類似判定→候補クラスタリング→**施設内 自動昇格**(頻度≥5 かつ 関与ユーザー≥2 かつ 既存と非重複)→ さらに複数法人で頻出すれば**横断(グローバル)昇格は運営承認制**。⚠️ レビュー指摘: `vector_embeddings` に `company_id` 実列が無く `metadata` jsonb 依存だと近傍検索がインデックスに乗らない → カテゴリ/候補 centroid 専用に vector列+複合インデックスを持つ改修が前提。
- **PII(必須)**: 自由記述も外部送信(埋め込み)前に **PiiMasker 強制**(運用注意に頼らない=A005思想)。
- **現場負担最小**: 任意・後付け可・1タップ・確定ボタンなし(トグル自動保存)。`change_ratio` 極小(句読点だけ等)の編集は理由UIを出さない。「あなたの言葉がチップになりました」通知で記録動機づけ。

## 12. 学習利用の同意モデル & データ保持方針(提案)

> 福祉の支援記録は**要配慮個人情報**。生成(運用)は契約内業務だが、**学習利用は当初目的を超える二次利用**のため独立オプトイン同意を必須とする。

- **同意の3層 × 目的別(AND条件)**:
  - `service_generation`(運用生成): 同意不要(契約内包)。ただし「外部AIへマスク済送信」を規約明記。
  - `improvement_aggregate`(Layer2統計): **施設(company)同意**(既定OFF)。統計化で個人識別性が消えるため施設判断で可。
  - `model_learning`(Layer3学習): **保護者/本人同意 必須**(既定OFF)。`company.aggregate=ON かつ student.learning=ON` の**両成立**の記録のみ Layer3 へ昇格。欠ければ Layer1 に留め学習に使わない。
  - `local_ai`(将来): 別フラグ(§後述)。
- **データモデル**: `consent_definitions`(目的×版×文面) + `consent_records`(**append-only**: granted/revoked, subject_type(company/student/user), version, 取得方法, evidence_ref, 取得者) + `companies`/`students` に現在値の非正規化フラグ(キャッシュ。正史は履歴)。判定は `ConsentService::isAllowed()` 一本化。
- **保持・暗号化・削除**:
  - Layer1(`ai_revision_events` 実名)は `encrypted` cast + **専用鍵(APP_KEYと分離)**。Layer3生成プロセスに鍵を持たせず構造的に raw を読めなくする。アクセスは担当教室職員のみ(`AiRevisionEventPolicy`)。学習基盤運用者(開発側)は Layer2/3 のみ。
  - 法定保存年限(支援記録 ≒ 5年程度、自治体差)まで Layer1 保持 → 満了でバッチ物理削除。
  - **撤回伝播(2段)**: 同意撤回API → 即フラグOFF&新規昇格停止(同期) → `PurgeLearningDataJob`(該当児童の未学習Layer3削除・逆引き表エントリ削除)を非同期。
- **仮名化と削除権**: Layer3 は `source_student_hash = HMAC(pepper, student_id)` のみ保持(連結可能匿名化)。逆引き表 `learning_pseudonym_map` を raw鍵環境のみに保持し、撤回/削除請求時に逆引き削除。法定満了で逆引き表破棄 → 真の匿名化へ。
- **PiiMasker 強化(`LearningPiiScrubber`)**: 現行 PiiMasker は氏名4項目のみ。学習層は電話/住所/生年月日/受給者証番号/学校名/きょうだい名/医療機関名まで**fail-closed**(残存検知で昇格停止+`needs_review`)。
- **ローカルAI移行の備え**: `config('ai.transport')`(external|local)でマスク適用を設定駆動にしておく(今やる足場のみ。本体は先)。
- **⚠️ 要法務確認(未確定だとLayer3起動不可)**: ①支援記録の法定保存年限 ②学習の地理的範囲(自テナント内 or 全テナント横断=仮名化恒久必須) ③保護者同意取得UI(本人ログイン/職員代行+紙同意) ④外部AI送信の規約明記状況。**これらが固まるまでは Layer1原本+同意モデルまで実装し、Layer3昇格は無効化**。

## 13. 「成果(Outcome)」の定義(具体提案)

> 原則: 既存実カラムだけで算出 / 算出元の行ID(`source_row_ids`)を必ず保持し再計算・監査可能 / 他児比較せず個人内評価 / `scoring_version` 固定。

- **指標A: 能力評価スコア変化(個人内成長Δ)★最優先**
  - 式: 領域Δ = `mean(該当領域の項目 latest score) − mean(同 baseline score)`(baseline=期間開始時点で有効な最新スコア、latest=期間終了時点)。項目単位/傾き(回帰)版は後。
  - 源: `ability_scores`(append型・`evidence_record_ids`・`needs_review`)。→ Δ→根拠観察まで完全トレース。
  - 監査: `needs_review` 行を含むΔは要確認。最小有効=領域内2項目以上が baseline/latest 双方を持つ。
- **指標B: モニタリング達成度**
  - 式: `achievement_level`(現状 string ラベル)を**順序マップ表 `monitoring_achievement_scale`**(未達=0/一部=0.5/達成=1.0)で数値化 → 明細/領域/児童で平均。`*_goal_achievement`(text)は定性根拠として保持(数値化しない)。
  - 源: `MonitoringDetail.plan_detail_id` → `SupportPlanDetail` 直結 = **最も直接的な「支援↔成果」ペア**。
  - ⚠️ 依存: `MonitoringController@update` が明細を delete→recreate で履歴消失 → **確定(`is_official`)のイミュータブル化を先に**。集計は確定分のみ。**着手前に `achievement_level` の distinct 値棚卸し**(表記揺れ対策)。
- **指標C: 主観(mynameis)×客観(kiduri)一致度**
  - 式: 項目一致度 = `1 − |subjective_norm − objective| / 10`(`subjective_norm=(v−1)/4*10`、既存 `AbilitySummaryService` 実装)。符号付きギャップ `gap = objective − subjective_norm`(負=自己過小/正=自己過大)。
  - 制約: 主観は `unique(student_id,item_id)`=**履歴なし → 時点スナップショットのみ**。時系列Δが欲しければ主観を append 型へ拡張(将来)。`|gap|≥4` を面談トリガに。
- **Outcome Learning 接続**: `outcome_link{student_id, domain, period, plan_id, support_plan_detail_id, metric, value, source_row_ids, needs_review, scoring_version}`。「どの `support_content` がどの領域で平均どれだけΔを生んだか」を集約 → 支援案学習(§14.3)へ。**個票で支援↔成果を持つ=実質Layer3相当 → 同意ゲート必須**(レビュー指摘)。
- **MVP順**: A(領域Δ・既存データで即) → B(plan_detail単位・要マップ表&確定化) → C(一致度スナップショット・既存ロジック流用)。

## 14. 【新要件】多軸解析・自己改善ループ・支援案学習・職員傾向レポート

### 14.1 修正の多軸解析(施設/児童/学年/性別/特性)

- 集計軸: 施設(`classroom_id`/`company_id` ✓)、児童(`student_id` ✓)、学年(`grade_level`/`birth_date`から段階 ✓)、**性別(❌ `students` に列なし→要追加)**、**特性(❌ 構造化なし→要追加 or 受給者証/アセスメント由来)**。
- ⚠️ 前提整備: 性別・特性は現状未保持。**性別は要配慮性が高く収集目的・同意の検討が必要**。特性は「タグ方式(複数可: ASD/ADHD/感覚過敏 等)」を `student_traits` として構造化するのが解析向き(自由記述 notes は集計不可)。
- 解析テーブル: `ai_revision_events` × `ai_edit_reasons` を、上記軸で日次集計する **`ai_edit_stats_daily`(Layer2)**。指標: 修正率(=changed/生成数)・採用率(=unchanged率)・編集量(`change_ratio`平均)・**修正理由カテゴリ別頻度**・セクション別/文書別。軸を増やすほど n が小さくなるため**最小n閾値(例 n≥5)未満は非表示**(個人特定・統計的無意味の回避)。

### 14.2 自己改善ループ(「入力するほど真実に近づく」)

- 仕組み: 蓄積した修正(§10)→ **施設/職員の文体プロファイル + 頻出フレーズ(before→after)抽出** → **生成プロンプトに注入**(優先順位: 全国共通知識→施設→職員→利用者→支援記録)→ 次回生成の質向上 → **修正率の経時低下**を計測(=真実に近づく の定量化)。
- データ: `facility_writing_profiles`(specificity/strength_focus/parent_tone/avoid_negative 等)、`staff_writing_profiles`、`phrase_rules`(before/after/reason/frequency/**learning_score**=修正回数×修正者数×施設数×採用率)。
- KPI(自己改善の証跡): 文書別・施設別の**修正率/採用率の時系列**が改善しているか。これを §14.4 レポートにも出す。
- モデル非依存: プロファイル/フレーズは**プロンプト注入(RAG的)**で効かせるため、OpenAIでもローカルでも同じ仕組みで効く(将来移行に強い)。

### 14.3 支援案(支援内容)の学習・最適化

- 文章だけでなく**支援内容そのもの**を学習対象に: `outcome_link`(§13)で「`support_content` パターン → 成果(Δ/達成度)」を集約し、領域・特性別に**「効果が出やすい支援案」候補**を生成時に提示。
- 安全策(必須): **人間が最終判断(human-in-the-loop)**。AIは「過去に同様の児童/領域でこの支援が成果と相関した」を**根拠付きで提案**するに留め、自動採用はしない。相関≠因果のため、件数・`needs_review`・`scoring_version` を併記。
- 段階: まず「支援↔成果の相関レポート」→ 次に「生成時の支援案サジェスト」→ 将来 Outcome Learning Engine(成果予測)。

### 14.4 施設別・記入者別の傾向レポート(管理者閲覧=職員評価)

- 施設管理者向けダッシュボード: 職員別・施設別に「修正率/採用率/修正理由の傾向/編集量/経時改善」を可視化(§14.1 の集計を描画)。`document_type`/領域別ドリルダウン。
- ⚠️ **職員評価の設計上の強い留意(逆インセンティブ回避)**: 「修正“量”が多い職員=低評価」は危険。AIをそのまま採用すれば修正ゼロにできてしまい、**丁寧な推敲が不利・手抜きが有利**になる。評価は以下の**多面的・成長志向・支援的**指標で:
  - 採用率(AI原案をどれだけ活かせたか)だけでなく、**修正の質**(修正後の文章が成果に結びついたか=`outcome_link` 連携)。
  - **経時的な修正率低下=本人の習熟/AI改善への寄与**(成長を褒める)。
  - 「この職員の修正がチップ(新カテゴリ)に昇格した」=**知識への貢献**をプラス評価。
  - 評価はランク付けより**気づき・育成**(OJT・ナレッジ共有)に使う前提を明記。最終運用方針はユーザー判断。
- プライバシー: 職員データも集計。閲覧権限は施設管理者に限定(既存 `user_type:admin` + 自社スコープ)。

## 15. 横断ロードマップ(統合・正典)

> レビュー指摘: 4設計のフェーズ番号が独立。以下に**横断の依存順**を確定する(これが Part I §6 を上書き)。

| 段階 | 内容 | ゲート/依存 |
|---|---|---|
| **S0 統一スキーマ確定** | §10 の単一ER(命名・粒度・PIIレイヤ)を確定。`AiLearningCapture` サービス雛形。テーブルのみ。回帰ゼロ | — |
| **S1 同意基盤** | `consent_definitions`/`consent_records`/非正規化フラグ + **同意取得UI/オペ**(これが無いと学習データが空) | 法務4点の方針 |
| **S2 Layer1蓄積(配管)** | 個別支援計画から `ai_generation_events`+`ai_revision_events`(実名暗号化) を捕捉。次にモニタリング(確定イミュータブル化込み)→アセスメント→連絡帳→mynameis主観差分(取込時スナップショット保存) | S0 |
| **S3 修正理由 P0** | 固定11チップ+自由記述+管理者1クリック昇格。**埋め込みなし**。A005回帰テスト | S2 |
| **S4 成果A** | 能力評価 領域Δ(既存データ)。`outcome_link` 雛形 | S2 |
| **S5 Layer2集計+多軸解析** | `ai_edit_stats_daily`(施設/児童/学年/性別/特性)。**性別・特性の構造化**を先に。最小n閾値 | S2/S3、性別特性整備 |
| **S6 自己改善ループ** | Facility/Staff Profile + Phrase Rules → プロンプト注入。修正率の経時計測 | S2/S3 |
| **S7 職員傾向レポート** | 管理者ダッシュボード(多面的・成長志向) | S5/S6 |
| **S8 Layer3学習** | `LearningPiiScrubber`(fail-closed)+`learning_corpus`+逆引き表。**法務確定後に有効化** | S1法務確定 |
| **S9 成果B/C + 支援案学習** | モニタリング達成度(マップ表)・主観客観一致度・`outcome_link` 集約→支援案サジェスト | S4/S8 |
| **S10 修正理由 P1 / ローカルAI** | 埋め込み動的タクソノミ・横断昇格 / `config('ai.transport')=local` | 後送り(要件E) |

## 16. 重要な前提・先に潰すべき点(整合レビュー要約)

1. **統一スキーマを最初に確定**(3設計の同名テーブル衝突・PII方針矛盾を解消。同意設計のLayerが正)。
2. **同意ゲートは fail-closed**(配管の try/catch 握りつぶしと切り分け。同意なし児童のLayer3昇格を無言で起こさない)。
3. **`vector_embeddings` のテナント絞り込み前提**(company_id列なし)を修正、または埋め込み類似自体をP1へ後送り。
4. **mynameis主観は履歴なし** → 取込時に差分スナップショットを append しないと再現不能。
5. **モニタリング delete→recreate** を確定イミュータブル化(成果B・修正履歴喪失の両方を止める)。
6. **`achievement_level` ラベル棚卸し**(表記揺れ)を着手前に実施。
7. **性別・特性は未保持** → 多軸解析の前に構造化(性別は要配慮=収集目的/同意を検討)。
8. **法務4点**(保存年限/学習の地理範囲/同意取得UI/規約明記)確定までLayer3は起動しない。
9. **職員評価は多面的・成長志向**(修正量単独評価の逆インセンティブ回避)。

## 17. 次の議論で決めたいこと

- S1/S2 の着手可否(まず統一スキーマ+同意基盤+個別支援計画の配管から、で良いか)。
- 性別・特性の構造化を行うか(特に**性別収集の是非**=要配慮)。特性は固定タグ語彙をどう定義するか。
- 職員評価レポートの**使い方の方針**(育成目的に限定 / 評価指標の重み)。
- 法務4点の確認担当・期限(Layer3起動の前提)。
- 支援案学習の提示強度(根拠付き提案のみ / どこまでサジェストするか)。

## 18. 上位ビジョン: 支援知蒸留エンジン

本基盤の上位構想として「支援知蒸留エンジン」を別書に取り込んだ
(`docs/kiduri-支援知蒸留エンジン_構想書と実装計画.md`、出典: 報告者提供PDF)。
要点: 全国の支援実践を AI で構造化・蒸留し再利用可能な「支援知」へ変換する知識基盤。
核心の学習ループ「AIの推論 → 人間の修正 を保存して改善」は本基盤 S2〜S5 で実現済み。
追加で必要な要素 = L2構造化(事実/支援/結果/仮説)/問い返し・仮説提示/支援者成長モデル/
支援知DB(Case/Observation/Intervention/Outcome/Hypothesis/Evaluation/Knowledge)/横断検索(根拠付き提示)。
実装フェーズ D1〜D5 と統合ロードマップは当該別書の §4・§5 を参照。
