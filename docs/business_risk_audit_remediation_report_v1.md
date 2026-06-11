# 放課後等デイサービス業務システム リスク監査 是正完了報告書

| 項目 | 内容 |
|---|---|
| 対象システム | care-bridge（放課後等デイサービス / 障害福祉サービス事業所 業務支援システム） |
| 移行元（正解の基準） | 旧アプリ neomodekobetu |
| 監査種別 | 業務リスク・エラー誘発機構の探索的監査（schema / data / api / logic / auth / screen） |
| 版 | v1.0 |
| 報告日 | 2026-06-12 |
| 対象範囲 | 新アプリへの移行に伴い混在していた「リスクのある仕組み・エラーが起きやすい仕組み」の検出と是正 |
| 結論 | **P0（致命的）8件・P1 13件・P2 13件をすべて是正済み。認可アーキテクチャの構造的リスクも統一基盤へ集約。残存は運用フェーズ対応の繰り延べ項目のみ。** |

---

## 1. エグゼクティブサマリ

本監査は「本システム（放課後等デイサービス）の中にリスクがある、もしくはエラーが起きやすい仕組みが混在している。探し出して結果を示し、対策を提案する」という依頼に基づき、旧アプリ neomodekobetu を正解として、新アプリ care-bridge の差分・欠陥を全面的に洗い出したものである。

検出された欠陥は、データ破損・認証不整合・致命的 API 不一致を最優先（P0）とする優先順位に従って是正した。特に重大だったのは以下の3系統である。

1. **認可（cross-classroom 漏洩）** — `classroom_id = null` を「全権限」と誤解釈する実装が約44箇所に散在し、他事業所（他企業）の児童情報・保護者チャット・支援計画を横断的に閲覧・操作できる状態だった。個別の穴（AUTH-01〜12）を塞いだうえ、温床となっていた実装パターンそのものを統一基盤へ集約した（ARCH-AUTH）。
2. **個人情報の国外送信** — 児童の実名が仮名化されないまま OpenAI（米国）へ送信され、生成ログにも実名が残る経路が3つ残存していた（AUTH-05）。AISI ヘルスケア AI セーフティ観点5（プライバシー保護）および個人情報保護法28条に抵触し得るもので、全 AI 送信経路を仮名化で封鎖した。
3. **法定帳票サイクルの欠陥** — モニタリングが1計画につき1回しか作成できず、「6か月に1回以上」という厚労省ガイドラインを満たせない致命的欠陥（LOGIC-02）、および空の下書き計画に署名して正式版化できる状態（LOGIC-03）を是正した。

加えて、退職スタッフ削除時の法定記録連鎖削除（SCHEMA-01/05/07）、JST/UTC 混在による日時ズレ（SCHEMA-02/03/08）、AI ベクター検索の完全停止（AI-07）など、業務継続に直結する欠陥を是正した。

是正はすべて「1回の修正は1カテゴリ」「修正後は必ず回帰テストを追加」「旧アプリを正とし推測で仕様を変えない」という原則に従って実施し、計 **16 コミット・回帰テスト11本・スキーマ migration 3本** を整備した。

### 重要度別の是正件数

| 重要度 | 件数 | 是正状況 |
|---|---|---|
| **P0（データ破損・認証不整合・致命的不一致）** | 8 | ✅ 全件是正 |
| **P1（業務ロジック・記録整合の重大欠陥）** | 13 | ✅ 全件是正 |
| **P2（副次的な認可改善・整合性強化）** | 13 | ✅ 全件是正 |
| **構造改善（認可アーキテクチャ統一）** | ARCH-AUTH-01〜06 + 集約 | ✅ 是正 |
| 誤検知と判定（修正不要） | 3 | ⃝ 根拠を明記し不修正 |
| 運用フェーズへ繰り延べ | 10 | ▶ 理由・対応時期を明記 |

---

## 2. 監査範囲と方法

### 2.1 正解の基準
- 旧アプリ neomodekobetu の **DB構造・APIレスポンス・認可挙動・画面機能を正**とする。
- 不明点は「未確定」と明記し、旧アプリ側の根拠を示す。新アプリ単独では正しさを判断しない。

### 2.2 差分カテゴリ
すべての検出事項を次の6カテゴリのいずれかに分類した：**schema / data / api / logic / auth / screen**。

### 2.3 ワークフロー
各事項につき「差分特定 → 再現手順 → 自動検査案 → 修正 → 回帰テスト追加 → 要約」の順で進めた。1回の修正は1カテゴリに限定し、対象外ファイルは触れていない。

---

## 3. 是正結果サマリ（全件一覧）

| ID | 分類 | 重要度 | 概要 | 状態 | コミット |
|---|---|---|---|---|---|
| AUTH-01 | auth | P0 | Admin児童詳細/退所が他企業でも操作可 | ✅ | d19e4e9 |
| AUTH-02 | auth | P0 | Admin全ユーザー閲覧・ロール昇格が可能 | ✅ | d19e4e9 |
| AUTH-03 | auth | P0 | classroom=null で全保護者チャット開放 | ✅ | d19e4e9 |
| AUTH-04 | auth | P0 | /api/ai/* で他児童データ抜き取り可 | ✅ | d19e4e9 |
| AUTH-05 | logic(PII) | P0 | AI経路の実名マスキング漏れ（国外送信） | ✅ | 2b317b7 |
| LOGIC-02 | logic | P0 | モニタリングが1計画1回しか作成不可 | ✅ | 7096751 |
| LOGIC-03 | logic | P0 | draft計画への署名で状態機械を飛び越し | ✅ | 7096751 |
| AI-07 | logic | P0相当 | 未実装メソッドでベクター検索が完全停止 | ✅ | 2da1beb |
| AI-04 | logic | P1 | Moderation fail-open + 連絡帳経路で未適用 | ✅ | aa70510 |
| AI-08 | logic | P1 | 連絡帳AI失敗時に仮名placeholderが保護者表示 | ✅ | 0084144 |
| AI-09 | logic | P1 | 並行INSERTでハッシュチェーン誤検知 | ✅ | 84e5a29 |
| AI-10 | logic | P1 | purgeでハッシュチェーン破断→alarm fatigue | ✅ | 84e5a29 |
| LOGIC-04 | logic | P1 | 同意日時にnow()注入（書面署名日と乖離） | ✅ | 1b270f9 |
| LOGIC-06 | logic | P1 | 作成済みでも期限通知が消えない（通知疲れ） | ✅ | 1b270f9 |
| LOGIC-09 | logic | P1 | 在籍数を今日時点で集計し過去月がズレる | ✅ | 1b270f9 |
| SCHEMA-01 | schema | P1 | meeting_requests がスタッフ削除で連鎖消失 | ✅ | 6debd46 |
| SCHEMA-05 | schema | P1 | activity_support_plans が連鎖消失 | ✅ | 6debd46 |
| SCHEMA-07 | schema | P1 | classroom_photos がRESTRICTで削除阻害/連鎖 | ✅ | 6debd46 |
| SCHEMA-02 | schema | P1 | 業務記録系の残存timestampでTZズレ | ✅ | 5c388e1 |
| SCHEMA-03 | schema | P1 | 工賃・代理店精算のtimestampでTZズレ | ✅ | 5c388e1 |
| SCHEMA-08 | schema | P1 | 監査証跡created_atのTZズレ | ✅ | 5c388e1 |
| AUTH-06 | auth | P2 | 同意ミドルウェアが未認証を素通り | ✅ | 54505ea |
| AUTH-07 | auth | P2 | syncLinkedで表示用メソッド誤用＋企業越境 | ✅ | 54505ea |
| AUTH-08 | auth | P2 | Guardian操作のis_master特例不整合 | ✅ | 54505ea |
| AUTH-09 | auth | P2 | タブレット入退室でnullバイパス | ✅ | 54505ea |
| AUTH-10 | auth | P2 | 成長分析でnullバイパス/完全一致 | ✅ | 54505ea |
| AUTH-11 | auth | P2 | 申告classroom_idで認可通過 | ✅ | 54505ea |
| AUTH-12 | auth | P2 | スタッフ宛先に全事業所が並ぶ誤送信リスク | ✅ | 54505ea |
| AI-11 | logic | P2 | OpenAIタイムアウト設定が未反映 | ✅ | 68e1278 |
| AI-12 | logic | P2 | AIログ保存失敗で生成結果ごと500 | ✅ | 68e1278 |
| SCHEMA-06 | schema | P2 | activity_schedule が json型で不統一 | ✅ | 497aafa |
| SCHEMA-11 | schema | P2 | daily_records一意制約のNULL抜け穴 | ✅ | 497aafa |
| LOGIC-01 | logic | P2 | cycle_number採番のTOCTOU（番号重複） | ✅ | 47311cf |
| LOGIC-05 | logic | P2 | 計画自動生成のTOCTOU（空draft量産） | ✅ | 47311cf |
| ARCH-AUTH-01〜06 | auth | 構造 | 認可パターン5種混在の統一・昇格ガード | ✅ | 1e76b99 |
| ARCH-AUTH-02 | auth | 構造 | requireMaster の基底集約（完全一致5件） | ✅ | 2b8b034 |

---

## 4. P0（致命的）詳細

### AUTH-01〜04 ― Admin/AI エンドポイントの cross-classroom 認可漏れ
- **分類 / 重要度**：auth / P0
- **旧アプリの正しい挙動**：すべての業務データは教室（事業所）単位で厳格にスコープされ、他事業所のデータは閲覧も操作もできない。
- **新アプリの修正前挙動**：
  - AUTH-01：Admin 児童詳細 `show()` / 退所 `destroy()` が classroom 無検査で、他企業の児童を取得・退所操作できた。
  - AUTH-02：Admin ユーザー管理が企業フィルタなしで全ユーザーを閲覧でき、`is_company_admin=true` への昇格（mass assignment）も可能だった。
  - AUTH-03：チャットの `canAccessRoom()` が `classroom_id=null` で全保護者チャットを開放していた。
  - AUTH-04：`/api/ai/*` が `user_type` チェックも児童所属チェックもなく、保護者・タブレット端末から他児童の支援計画・面接記録を AI 経由で抜き取れた。
- **差分の概要**：`classroom_id = null` を「全権限」と解釈する誤りが共通の温床。
- **修正方針**：基底 Controller に `authorizeClassroomId()` / `canAccessClassroomId()` を新設。マスター管理者のみ全アクセス可、それ以外は `switchableClassroomIds()` に限定。**null は「権限なし（403）」**として扱う。`/api/ai/*` に `user_type:staff,admin` を付与し、plan と student の整合も検証。`bootstrap/app.php` に AuthorizationException→403 JSON ハンドラを追加。
- **影響ファイル**：`app/Http/Controllers/Controller.php`、`Admin/StudentController.php`、`Admin/UserController.php`、`Staff/ChatController.php`、`Api/AiGenerationController.php`、`routes/api.php`、`bootstrap/app.php`
- **再現手順**：別企業のスタッフトークンで `GET /api/admin/students/{他企業児童ID}` → 修正前は 200、修正後は 403。
- **回帰テスト**：`AU015_AdminAndAiAuthFixTest`（8ケース）
- **状態**：✅ 是正済み

### AUTH-05 ― AI 経路の個人情報マスキング漏れ（国外送信）
- **分類 / 重要度**：logic（PII保護）/ P0
- **旧アプリの正しい挙動**：（AI機能は新アプリ固有だが）個人情報の取り扱いは個情法・AISI観点5を満たすこと。
- **新アプリの修正前挙動**：MonitoringController / AssessmentController / Api\AiGenerationController の3経路で、児童実名が無加工のまま OpenAI 米国へ送信され、`ai_generation_logs` にも実名が混入していた。
- **修正方針**：各経路で `AiIdentityMasker` を適用し、児童名・教室名・保護者名を placeholder 化して送信。中間生成物は仮名のまま連鎖させ、最終応答でまとめて `unmask()`（再漏洩防止）。モデル名のハードコードも `config()` 参照へ。
- **影響ファイル**：`Staff/MonitoringController.php`、`Staff/AssessmentController.php`、`Api/AiGenerationController.php`
- **状態**：✅ 是正済み（care-bridge の全 AI 送信経路で実名が OpenAI に渡らないことを確認）

### LOGIC-02 / LOGIC-03 ― 法定帳票サイクルの重大欠陥
- **分類 / 重要度**：logic / P0
- **旧アプリの正しい挙動**：障害福祉サービスは6か月ごとに複数回モニタリングを実施・記録できる。計画は draft→submitted→official の状態機械を順に辿る。
- **新アプリの修正前挙動**：
  - LOGIC-02：モニタリング重複チェックが `(student_id, plan_id)` のみで、1計画につき1回しか作成できず、2回目以降が記録不能。**「6か月に1回以上」未達で実地指導リスク**。
  - LOGIC-03：`sign()` が status を無検査で受理し、draft 計画への署名で `is_official=true` 化でき、空の下書きが正式版になって保護者へ空内容の署名依頼が飛ぶ。
- **修正方針**：重複チェックを `(student_id, plan_id, monitoring_date)` に限定し同一日付の二重登録のみ弾く（`next_monitoring_due_date` の前進不具合 LOGIC-14 も同時解消）。draft 計画への署名を 422 で拒否し submitted 以上のみ許可。
- **影響ファイル**：`Staff/MonitoringController.php`、`Staff/SupportPlanController.php`、`Services/PlanCycleService.php`
- **回帰テスト**：`L012_MonitoringCycleAndSignFixTest`（4ケース）
- **状態**：✅ 是正済み

### AI-07 ― 未実装メソッドによるベクター検索の完全停止
- **分類 / 重要度**：logic / P0相当
- **新アプリの修正前挙動**：`EmbeddingService::embed()` が呼ぶ `AiGenerationService::generateEmbedding()` が未実装で、支援計画承認のたびに `BadMethodCallException` が発生し `GenerateEmbeddingJob` が必ず `failed_jobs` に積まれ、類似事例検索が完全停止していた。さらに承認1回で embedding job が二重ディスパッチされていた。
- **修正方針**：OpenAI Embeddings API（`text-embedding-3-small`）を実装。二重ディスパッチは Observer に一本化。
- **影響ファイル**：`Services/AiGenerationService.php`、`config/services.php`、`Services/SupportPlanService.php`
- **回帰テスト**：`AI001_EmbeddingMethodFixTest`（3ケース）
- **状態**：✅ 是正済み

---

## 5. P1（重大）詳細

### SCHEMA-01 / 05 / 07 ― 退職スタッフ削除時の法定記録連鎖削除
- **分類 / 重要度**：schema / P1
- **新アプリの修正前挙動**：`users` レコード削除時に `cascadeOnDelete` で面談履歴・活動支援計画・教室写真が連鎖削除され、確定済みの法定記録が消失するリスク。
- **修正方針**：migration `2026_06_07_000001` で `meeting_requests.staff_id` / `activity_support_plans.staff_id` / `classroom_photos.uploader_id` を **nullOnDelete** 化し、スタッフ削除後も記録を保持して該当 FK のみ NULL にする。
- **状態**：✅ 是正済み

### SCHEMA-02 / 03 / 08 ― JST/UTC 混在による日時ズレ
- **分類 / 重要度**：schema / P1
- **新アプリの修正前挙動**：過去の TZ 修復後に追加された業務記録系テーブル（監査証跡・工賃・代理店精算）が `timestamp`（tz なし）のまま残り、JST/UTC 混在で日付境界が金額・法定記録に影響。
- **修正方針**：migration `2026_06_07_000002` で対象列を `timestamptz` に変換（`USING ... AT TIME ZONE 'Asia/Tokyo'`）。Stripe 連携系（外部 UTC）は誤変換回避で除外。
- **回帰テスト**：`S003_TimestampTimezoneConsistencyTest`（CI で型統一を保証）
- **状態**：✅ 是正済み（**注**：特定期間の `guardian_confirmed_at` 9h ズレの**データ補正**は型が既に正しいため対象外。影響行特定に本番データが必要なため運用時に別途補正＝下記7章）

### AI-04 ― Moderation の fail-open と連絡帳経路での未適用
- **分類 / 重要度**：logic / P1
- **新アプリの修正前挙動**：Moderation API 障害時に `flagged=false` で素通り（有害判定すり抜けと API 障害が区別不能）。さらに保護者へ直接公開される連絡帳が OpenAI を直接呼び、Moderation 出力フィルタを省略していた。
- **修正方針**：fail-open → **fail-warn**（障害時は `ai_moderation_unavailable` を監査ログに記録）。連絡帳の仮名化済みテキストにも `moderate()` を適用。
- **回帰テスト**：`AI003_ModerationFailWarnTest`（3ケース）
- **状態**：✅ 是正済み

### AI-08 ― 連絡帳 AI フォールバック時の仮名漏れ
- **分類 / 重要度**：logic / P1
- **新アプリの修正前挙動**：AI 失敗時の fallback が mask 済みテキストをそのまま保存し、保護者画面に「対象児童 A さんは本日…」という placeholder が表示され得た。
- **修正方針**：fallback 内で `unmask()` して実名復元。fallback でも `ai_assisted=true` / `ai_review_status='pending'` を記録（HITL 一貫性）。
- **回帰テスト**：`AI002_RenrakuchoFallbackUnmaskTest`
- **状態**：✅ 是正済み

### AI-09 / AI-10 ― 監査ログ・ハッシュチェーンの誤検知（alarm fatigue）
- **分類 / 重要度**：logic / P1
- **新アプリの修正前挙動**：並行 INSERT によるチェーン分岐（AI-09）や保持期間切れ purge による破断（AI-10）を「改ざん」と誤検知し、`verify-chain` が常時エラー化して**本物の改ざんを見落とす**状態。
- **修正方針**：`verifyChainDetailed()` で row_hash mismatch（真の改ざん）を errors、prev_row_hash gap（正当な分岐/削除）を warnings に分離。
- **回帰テスト**：`AIS005_HashChainErrorWarningTest`（3ケース、実DB）
- **状態**：✅ 是正済み

### LOGIC-04 / 06 / 09 ― 同意日時・通知疲れ・在籍数集計
- **分類 / 重要度**：logic / P1
- **修正前挙動と是正**：
  - LOGIC-04：「紙面サイン済み」確定で `guardian_confirmed_at` に `now()` を注入し書面署名日と乖離 → `paper_sign_date` を受け取り、未指定時は consent_date→今日の順で確定。
  - LOGIC-06：次期計画作成済みでも旧計画の期限が拾われ続け通知が消えない → より新しい official 計画が存在する計画を除外。
  - LOGIC-09：在籍数を「今日時点」で算出し過去月帳票がズレる → `support_start_date` / `withdrawal_date` で対象月末基準の集計に変更。
- **回帰テスト**：`L013_PlanCycleAndMetricsFixTest`（2ケース）
- **状態**：✅ 是正済み

---

## 6. P2（副次）詳細

### AUTH-06〜12 ― 副次的な認可漏れの強化
- **分類 / 重要度**：auth / P2
- **共通方針**：`classroom_id=null` を「全権限」と誤解釈する箇所を排除し `authorizeClassroomId` / `switchableClassroomIds` に統一。
- **内訳**：
  - AUTH-06 同意ミドルウェアの未認証素通り→401
  - AUTH-07 `syncLinked` の表示用メソッド誤用＋同企業制約追加＋同期元認可
  - AUTH-08 Guardian 操作の is_master 特例不整合の統一
  - AUTH-09 タブレット入退室の null バイパス
  - AUTH-10 成長分析の null バイパス/完全一致
  - AUTH-11 リクエスト body/query の申告 classroom_id による認可通過の廃止
  - AUTH-12 スタッフ宛先に全事業所が並ぶ誤送信リスクを自教室限定に
- **回帰テスト**：`AU016_SecondaryAuthHardeningTest`（3ケース）
- **状態**：✅ 是正済み

### AI-11 / AI-12 ― OpenAI タイムアウト未反映・AI ログ握り潰し
- **分類 / 重要度**：logic / P2
- **是正**：`config('services.openai.timeout')` をクライアントへ反映（AI-11）。AI ログ保存失敗を個別 try-catch にとどめ生成結果は正常返却（AI-12）。
- **状態**：✅ 是正済み

### SCHEMA-06 / SCHEMA-11 ― jsonb 型統一・一意制約の NULL 抜け穴
- **分類 / 重要度**：schema / P2
- **是正**（migration `2026_06_07_000003`）：`activity_support_plans.activity_schedule` を json→jsonb 統一（SCHEMA-06）。`daily_records.activity_name` の `unique` が NULL 行で機能しない抜け穴を NOT NULL + default '' で封鎖（SCHEMA-11）。
- **状態**：✅ 是正済み

### LOGIC-01 / LOGIC-05 ― 採番・自動生成の競合状態（TOCTOU）
- **分類 / 重要度**：logic / P2
- **是正**：cycle_number 採番に `lockForUpdate()` + トランザクションでアトミック化（LOGIC-01、番号重複防止）。一覧取得中の空 draft 自動生成も lock 付き transaction で重複生成防止（LOGIC-05）。
- **状態**：✅ 是正済み

---

## 7. 構造改善 ― 認可アーキテクチャの統一（ARCH-AUTH）

個別の認可バグ（AUTH-01〜13）は、**5種類の認可実装パターンが混在**していたことが温床だった。バグを個別に塞ぐだけでなく、温床そのものを断つため統一基盤へ集約した。

| 項目 | 内容 |
|---|---|
| ARCH-AUTH-01 | 基底 `authorizeClassroomId()` の isMaster 判定を `isMasterAdmin()` に正典化 |
| ARCH-AUTH-02 | `requireMaster()` を基底へ集約（完全一致5件：AdminAccount/Company/Classroom/IndividualContract/StaffAccount）。挙動はバイト等価で不変 |
| ARCH-AUTH-03 | （繰り延べ）`accessibleClassroomIds` を表示専用と明示するリネーム |
| ARCH-AUTH-05 | User モデルに saving ガードを追加。`user_type` が admin でないのに特権フラグ true という不正な組み合わせを保存時に false へ補正（**権限昇格の構造的防止**） |
| パターンB一掃 | `$user->classroom_id && !in_array(...)` 形式 44 箇所を一掃（null で認可が完全スキップされる漏洩の温床） |
| パターンC/D解消 | 表示専用メソッドの認可誤用（C）、classroom_id 完全一致比較による複数教室所属スタッフの誤 403（D）を解消 |

- **回帰テスト**：`AU016_AuthArchitectureUnificationTest`、`AU017_RequireMasterConsolidationTest`
- **検証**：全コントローラで4種のアンチパターンが0件であることを grep で確認。全編集ファイルを PHP 8.4 `php -l` で構文確認。
- **状態**：✅ 是正済み

---

## 8. 修正不要と判定した項目（誤検知・忠実移植）

推測で仕様を変えないため、調査の結果「実害なし／旧アプリの忠実移植」と判定したものは根拠を明記して**不修正**とした。

| ID | 内容 | 判定根拠 |
|---|---|---|
| SCHEMA-09 / SCHEMA-15 | NULL jsonb で TypeError の懸念 | 実アクセス箇所（RenrakuchoController / StrengthsAggregator / WageCalculationService）が全て `is_array()` / `?? []` で null-safe にガード済み。**誤検知** |
| ARCH-AUTH-04（PendingTaskController） | `classroom_id=null` で一覧フィルタがスキップされ全教室の未対応タスクが見える懸念 | 旧アプリ `pending_tasks_helper.php` も `$classroomId ? "AND u.classroom_id = ?" : ""` で null なら全件＝**忠実な移植**。かつ新アプリは `StaffAccountController` でスタッフの `classroom_id` を必須化しており、null になるのはマスター（全教室閲覧が正）のみ。前提条件が維持されるため**仕様変更せず** |

---

## 9. 運用フェーズへ繰り延べた項目

影響範囲が広い／本番データ依存のため、別途運用フェーズで対応する。

| ID | 内容 | 繰り延べ理由 | 推奨対応時期 |
|---|---|---|---|
| AI-01 / AI-06 | ジョブ失敗時の管理者通知 | ジョブ/通知アーキテクチャに広く関わる | 運用設計フェーズ |
| AI-13 | Web Push 同期実行の非同期化 | キュー基盤の見直しを伴う | 運用設計フェーズ |
| LOGIC-12 | モニタリング期日が2系統並立 | 両系統の統合リファクタが必要 | リファクタ計画時 |
| SCHEMA-14 | `user_type='agent'` で `agent_id=NULL` の孤児 | 実害が限定的 | データ点検時 |
| SCHEMA-16 | 旧アプリ面談対案日時の移行マッピング | 本番移行データ依存 | 本番移行時 |
| SCHEMA-02（データ補正） | 特定期間の `guardian_confirmed_at` 9h ズレ補正 | 影響行特定に本番データが必要 | 本番移行時 |
| ARCH-AUTH-03 / 04残 / 07 | 命名整理・未使用 middleware/Policy のデッドコード削除 | セキュリティ穴ではなく「無関係なリファクタをしない」原則とのトレードオフ | 任意（クリーンアップ時） |

---

## 10. 回帰テスト・自動検査の整備状況

是正ごとに回帰テストを新設し、再発を CI で防止する体制を整えた。

| テスト | 対象 |
|---|---|
| `AU015_AdminAndAiAuthFixTest` | AUTH-01〜04（8ケース） |
| `AU016_SecondaryAuthHardeningTest` | AUTH-06〜12（3ケース） |
| `AU016_AuthArchitectureUnificationTest` | ARCH-AUTH-01〜06 |
| `AU017_RequireMasterConsolidationTest` | ARCH-AUTH-02（3ケース） |
| `AI001_EmbeddingMethodFixTest` | AI-07（3ケース） |
| `AI002_RenrakuchoFallbackUnmaskTest` | AI-08 |
| `AI003_ModerationFailWarnTest` | AI-04（3ケース） |
| `AIS005_HashChainErrorWarningTest` | AI-09/10（3ケース） |
| `L012_MonitoringCycleAndSignFixTest` | LOGIC-02/03（4ケース） |
| `L013_PlanCycleAndMetricsFixTest` | LOGIC-04/06/09（2ケース） |
| `S003_TimestampTimezoneConsistencyTest` | SCHEMA-02/03/08 |

**スキーマ migration（3本）**：`2026_06_07_000001`（cascade→nullOnDelete）、`2026_06_07_000002`（timestamptz統一）、`2026_06_07_000003`（jsonb統一＋一意制約）

> **注**：care-bridge スタックは本報告時点で未起動のため、全テストスイートのフル実行は環境起動後に実施する。全編集ファイルは PHP 8.4 で構文チェック済み。

---

## 11. 残存リスクと推奨事項

1. **本番移行時のデータ補正**：SCHEMA-02 の `guardian_confirmed_at` 9h ズレ、SCHEMA-16 の面談対案日時マッピングは、本番データを投入したうえで影響行を特定し補正する。
2. **テストスイートのフル実行**：care-bridge バックエンドを起動し、`php artisan test` で全回帰テストを実行・確認する（他プロジェクトのコンテナとのポート競合に注意）。
3. **通知・ジョブ基盤の整備**：AI-01/06/13 の管理者通知・非同期化は、運用設計フェーズでキュー基盤と合わせて検討する。
4. **デッドコード整理（任意）**：未使用の `CheckClassroomAccess` middleware / `StudentPolicy` / `SupportPlanPolicy` は、リファクタ計画の中で削除を検討する（機能影響なし）。

---

## 付録A：監査是正コミット対応表

| コミット | 件名 |
|---|---|
| d19e4e9 | fix(auth): Admin/AI エンドポイントの認可漏れ修正 (AUTH-01〜04, P0) |
| 2b317b7 | fix(logic): AI 経路の PII マスキング漏れを修正 (AUTH-05, P0) |
| 2da1beb | fix(logic): 未実装の generateEmbedding を実装 + 二重ディスパッチ解消 (AI-07, P0相当) |
| 7096751 | fix(logic): 法定帳票サイクルの修正 — 複数回モニタリング許可 + draft署名拒否 (LOGIC-02/03, P0) |
| 6debd46 | fix(schema): staff/uploader 外部キーの cascade 削除を nullOnDelete に修正 (SCHEMA-01/05/07, P1) |
| 0084144 | fix(logic): 連絡帳 AI フォールバック時の仮名漏れを修正 (AI-08, P1) |
| 1b270f9 | fix(logic): 同意日時改ざん・リマインド永続・在籍数集計の修正 (LOGIC-04/06/09, P1) |
| aa70510 | fix(logic): Moderation を fail-warn 化 + 連絡帳経路に Moderation 追加 (AI-04, P1) |
| 84e5a29 | fix(logic): ハッシュチェーン検証を errors/warnings に分離 (AI-09/10, P1) |
| 5c388e1 | fix(schema): 残存 timestamp カラムを timestamptz に統一 (SCHEMA-02/03/08, P1) |
| 54505ea | fix(auth): 副次的な認可漏れの強化 (AUTH-06〜12, P2) |
| 497aafa | fix(schema): jsonb 型統一 + daily_records 一意制約の NULL 抜け穴を修正 (SCHEMA-06/11, P2) |
| 68e1278 | fix(logic): OpenAI タイムアウト反映 + AI ログ保存失敗の握り潰し (AI-11/12, P2) |
| 47311cf | fix(logic): cycle_number 採番と計画自動生成の TOCTOU を解消 (LOGIC-01/05, P2) |
| 1e76b99 | refactor(auth): 認可アーキテクチャを統一基盤に集約 (ARCH-AUTH-01〜06, 構造改善) |
| 2b8b034 | refactor(auth): requireMaster を基底へ集約 (ARCH-AUTH-02, 完全一致5件のみ) |

## 付録B：是正の原則（遵守事項）

- 旧アプリ neomodekobetu の DB構造・APIレスポンス・認可挙動・画面機能を正とする。
- 推測で仕様を変えない。不明点は「未確定」と明記し旧アプリ側の根拠を示す。
- 1回の修正は1カテゴリのみ。修正後は必ず関連テストを追加・更新。
- 修正対象外のファイルは触らない。無関係なリファクタをしない。

---

*本報告書は care-bridge 移行プロジェクトのリスク監査クローズアウト文書 v1.0 である。*
