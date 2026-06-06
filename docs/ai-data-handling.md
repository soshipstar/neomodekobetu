# きづり 生成AI データ取扱い方針（OpenAI連携）

> AIセーフティ評価観点ガイド（ヘルスケア領域 第1.0版）**観点5 プライバシー保護** / **観点10 検証可能性** 対応。
> 本書は「きづり」が生成AI（OpenAI）へどのデータを、どう送り、どう守るかを明文化したもの。
> 契約・設定の**確定状態**は `backend/config/services.php` の `services.openai.data_processing`（`.env` 駆動）に記録する。

最終更新: 2026-06-06

---

## 1. 利用するAIと用途（Non-SaMD・事務支援）

きづりのAIは**書類作成の下書き支援**であり、医療診断・治療判断には用いない（SaMD非該当）。最終判断・確定は必ず専門職員が行う（人間最終確認が必須）。

| 用途 | 実装 | 送信先 | モデル |
|---|---|---|---|
| 個別支援計画ドラフト | `AiGenerationService::generateSupportPlan` / `Api\AiGenerationController` | OpenAI Chat | `gpt-5.4-2026-03-05` |
| モニタリング評価 | `AiGenerationService::generateMonitoringReport` / `Api\AiGenerationController` | OpenAI Chat | 同上 |
| フリースクール報告書 | `FreeSchoolReportAiService` | OpenAI Chat | 同上 |
| おたより | `AiGenerationService::generateNewsletter` | OpenAI Chat | 同上 |
| 事業所自己評価 | `AiGenerationService::generateSelfEvaluationSummary` | OpenAI Chat | 同上 |
| 類似検索の埋め込み | `EmbeddingService` / `GenerateEmbeddingJob` | OpenAI Embeddings | `text-embedding-3-small` |

送信経路は OpenAI API への直接接続（プロキシなし）。APIキーは `OPENAI_API_KEY`（`.env`）で管理。

---

## 2. 送信データと保護措置（コード側統制）

### 2.1 仮名化（PIIマスキング）— 実装済み
要配慮個人情報（障害児の氏名・支援内容、保護者氏名）を**外部AIへ送る前に仮名プレースホルダへ置換**する。

- 実装: `App\Support\PiiMasker`（`PiiMasker::forStudent($student)`）
- 適用: 上表の児童データを扱う全経路（計画・モニタリング・フリースクール報告書・埋め込み）
- 出力（職員が使う下書き）は `unmask` で実名へ復元。**ログ（`ai_generation_logs`）にはマスク版を保存**し実名を残さない。
- おたより・自己評価は集計・教室情報のみで個人情報を含まない。

### 2.2 送信されるデータの性質
- マスキング後：児童名→`【児童】`等、保護者名→`【保護者】`。学年・活動記録本文・支援目標などの**本文は送信される**（仮名化対象は氏名類）。
- 埋め込みは氏名をマスクし、識別は `vector_embeddings.metadata.student_id` で行う。

---

## 3. 確定すべき契約・設定（運用アクション）

OpenAI API は **既定で送信データをモデル学習に使用しない**（2023年以降のAPIポリシー）。ただし要配慮個人情報を扱うため、以下を確認・確定し `.env` に記録すること。

| 項目 | 確認内容 | env | 既定 |
|---|---|---|---|
| 学習不使用 | API利用がモデル学習に使われない設定であること | `OPENAI_TRAINING_OPT_OUT` | `true` |
| ゼロデータ保持(ZDR) | 不正監視用の最大30日保持を無効化するZDRの適用可否をOpenAIへ申請・確認 | `OPENAI_ZERO_DATA_RETENTION` | `false`（未確認）|
| データ処理契約(DPA) | OpenAIとDPAを締結済みであること | `OPENAI_DPA_SIGNED` | `false`（未締結）|
| 確認日 | 上記を直近に確認した日付 | `OPENAI_DATA_POLICY_REVIEWED_AT` | 空 |

> 確認が取れたら該当envを `true` / 日付に更新する。設定値は `config('services.openai.data_processing')` から参照可能（コンプライアンス記録）。

### 3.1 法令・ガイドライン整合（要法務確認）
- **個人情報保護法（APPI）**: 障害児の氏名・障害情報は**要配慮個人情報**。米国OpenAIへの送信は「外部第三者への提供／委託」かつ**越境移転**に該当し得る。委託構成・本人（保護者）同意・委託先監督のいずれで適法化するかを整理する。本システムは2.1の仮名化により送信PIIを最小化している。
- **医療情報システムの安全管理に関するガイドライン（3省2ガイドライン）**: 保存場所・アクセス管理・監査の要件適合を確認する。

### 3.2 保護者同意の取扱い方針（決定: 2026-06-06, 方針A）
AI利用に関する保護者同意は、**個別のオプトイン同意ゲートを設けず、透明性開示で対応する**方針とする。根拠は以下。
- AI提供者（OpenAI）を**業務委託先（データ処理者）**として位置づけ、APPI上は委託構成で取り扱う（第三者提供の本人同意を要しない構成）。
- 送信前に氏名等を**仮名化（`PiiMasker`）**し、外部送信する個人情報を最小化している。
- AI利用を**利用規約（第6条 生成AIの利用について）**および**保護者マニュアル**で明記済み（実施済）。
- AIは**書類作成の下書き支援**であり、出力は必ず職員が確認・確定する（人間最終確認）。

> 将来、規制動向や運用方針の変更により**明示的な同意取得（オプトイン）**が必要となった場合は、利用者単位の同意記録（同意日時カラム等）と同意モーダルを追加して対応する。

---

## 4. ログ・検証可能性（観点10）

- `ai_generation_logs` に user_id / generation_type / model / トークン数 / 入力(マスク済・先頭5000字) / 出力(マスク済) / 所要時間 / 作成日時を記録。
- 今後の拡充候補: temperature・max_tokens・systemプロンプト版・RAG/embedding参照ID・embeddingモデル版の記録（後続P1）。

---

## 5. 変更履歴
- 2026-06-06: 初版。PIIマスキング層（`PiiMasker`）導入に合わせデータ取扱い方針を明文化。データ取扱い確定状態の設定枠（`services.openai.data_processing`）を追加。
- 2026-06-06: 保護者同意の取扱い方針を決定（方針A=透明性開示で対応、個別オプトイン同意ゲートは設けない）。利用規約第6条・保護者マニュアルにAI利用を明記。
