# AI学習基盤 S4: 多次元分析 + 実施プログラム分類 — 計画書 (v0.1)

対象: kiduri2026 / 放課後等デイサービス
前提: S0統一スキーマ・S1同意基盤・S2/S3配管(個別支援計画/モニタリング/職員アセスメント/連絡帳)が本番稼働済み。
目的(報告者の依頼):
1. 支援案(=支援内容)および他文書の「AI出力 vs 人間修正」の差分を、**対象(未就学/小学生/中学生/高校生)×作者×施設**(+性別・特性)で分析し、是正・改善に使えるようにする。
2. **実施プログラムを分類**し、分析結果を解析可能なデータとして蓄積する。
3. 「どう分類し、どう保存するか」の計画を確定する(本書)。

---

## 0. 設計原則(S0〜S3を踏襲)

- **同意ゲート(AND)・fail-closed**: 分析・蓄積は施設の集計同意 AND 児童の学習同意がある記録のみ(`canUseForLearning`)。
- **PII階層**: 実名は Layer1(暗号化)のみ。分析次元・集計は仮名化/数値のみ。集計セルは **k-匿名(n<5は秘匿)**。
- **追記専用 + 履歴正確性**: 次元は「修正が起きた時点の値」をスナップショットで保存(学年は毎年変わるため)。
- **職員評価は成長志向**: 修正『回数』で人を評価しない(逆インセンティブ)。改善余地・到達度で見る。

---

## 1. 分析次元(軸)の確定

| 次元 | 値 | 取得元 | 状態 |
|---|---|---|---|
| 対象(コホート) | 未就学/小学生/中学生/高校生/その他 | `grade_level` のプレフィックス | ✅既存(束ねるだけ) |
| 成長段階 | S1〜S6 | `AbilityGrowthStage::forStudent()` | ✅既存・再利用 |
| 作者(生成) | user | `ai_generation_events.user_id` | ✅既存 |
| 作者(修正) | editor + role | `ai_revision_events.editor_user_id`/`editor_role` | ✅既存 |
| 施設 | company / classroom | events の `company_id`/`classroom_id` | ✅既存 |
| 文書種別 | support_plan / monitoring / assessment_staff / integrated_note | `document_type` | ✅既存 |
| 支援区分 | 本人支援(5領域)/家族支援/移行支援 | `section_key`(support系) | ✅導出可 |
| 実施プログラム種別 | 5領域 × プログラム分類 | **新設(本計画)** | 🆕 |
| 性別 | male/female/other/unspecified | **students に追加(要配慮)** | 🆕 |
| 特性 | 統制語彙(自由記述PII不可) | **新設(統制タグ)** | 🆕 |

コホート束ね規則: `preschool`→未就学 / `elementary*`→小学生 / `junior_high*`→中学生 / `high_school*`→高校生 / それ以外→その他。

---

## 2. 実施プログラムの分類体系(タクソノミー)

### 2.1 方針
- **第1軸 = 5領域**(システム共通の正典)を最上位に採用。
- **第2軸 = プログラム種別**(5領域配下の統制語彙)。放デイの実態に即した初期セットを seed。
- 既存資産を最大活用:
  - `ActivitySupportPlan`(定型プログラムmaster, `daily_records.support_plan_id` で連結) … マスタを1度分類すれば日々の記録へ継承。
  - 支援計画の `category/sub_category` … 支援案(support_plan_detail)側の分類はこれを正規化して流用。
  - SUP0〜SUP6 … 「支援強度」の直交軸として別途記録可能(任意)。

### 2.2 初期タクソノミー(seed 案: `program_categories`)
> 第1軸(domain)= 5領域 / 第2軸(code,label)。各カテゴリに `aliases`(キーワード)を持たせ自動分類に使う。

- 健康・生活 (health_life)
  - `daily_living` 生活習慣・身辺自立(着替え/排泄/手洗い/片付け)
  - `meal` 食事・調理(おやつ/クッキング/食育)
  - `health` 健康・運動衛生(休息/体調管理)
- 運動・感覚 (motor_sensory)
  - `gross_motor` 粗大運動(運動遊び/体操/公園)
  - `fine_motor` 微細運動(制作/工作/書字)
  - `sensory` 感覚遊び(感覚統合/水遊び/粘土)
- 認知・行動 (cognitive_behavior)
  - `learning` 学習支援(宿題/読み書き/計算)
  - `cognition_play` 認知課題遊び(パズル/ルール遊び/プログラミング)
  - `behavior` 行動・自己調整(順番/切替/SST行動面)
- 言語・コミュニケーション (language_communication)
  - `language` 言語・発語(言語訓練/絵カード)
  - `expression` 発表・表現(音楽/劇/発表)
- 人間関係・社会性 (social_relations)
  - `group_play` 集団遊び・協同活動(ゲーム/共同制作)
  - `sst` SST(ソーシャルスキル)
  - `social_experience` 社会体験・外出(買い物/公共マナー)
- 横断 (cross / domain=null)
  - `event` 行事・季節イベント
  - `other` その他

> 法人独自カテゴリは `company_id` 付きで追加可能(固定seedは `company_id=NULL, is_seeded=true`)。修正理由カテゴリ(§11)と同じ「固定+動的昇格」方式。

---

## 3. 保存設計(スキーマ)

### 3.1 次元スナップショット(Layer1 拡張・追記専用に追加)
`ai_revision_events` / `ai_generation_events` に「記録時点の次元」を付与する(履歴正確性)。AiLearningCapture が記録時に算出。

```
ALTER ai_revision_events / ai_generation_events ADD:
  subj_cohort        varchar(16)   -- preschool/elementary/junior_high/high_school/other
  subj_growth_stage  varchar(4)    -- S1..S6 (AbilityGrowthStage)
  subj_grade_level   varchar(20)   -- 記録時点の grade_level 生値
  subj_gender        varchar(12) null
  support_category   varchar(40) null  -- section_keyから導出(本人支援:健康・生活 等)
  program_category_id bigint null       -- 連絡帳/活動由来の修正に付与
  dim_meta           jsonb null         -- traits[], sup_code, 拡張用
index: (company_id, document_type, subj_cohort), (editor_user_id), (program_category_id), (subj_growth_stage)
```
- 既存の `editor_user_id`/`user_id`/`company_id`/`classroom_id` は変更不要(作者・施設はそのまま軸になる)。

### 3.2 プログラム分類マスタ + 付与
```
program_categories
  id, domain varchar(40) null, code varchar(64), label_ja varchar(100),
  parent_id bigint null, aliases jsonb (キーワード配列), description varchar(255) null,
  company_id bigint null (NULL=全社共通), is_seeded bool, status varchar(16), sort_order int,
  unique(company_id, code)

program_classifications  (どの記録が何プログラムか。多相)
  id, classifiable_type varchar(40) (daily_record/support_plan_detail/monitoring_detail/activity_support_plan),
  classifiable_id bigint, program_category_id bigint,
  method varchar(16) (rule/embedding/manual), confidence float null,
  classified_by bigint null (manual時の職員), is_primary bool default true,
  created_at  -- 上書きは新method優先(manual > embedding > rule)
  index(classifiable_type, classifiable_id), (program_category_id)

program_category_candidates  (動的タクソノミー: 自由入力の新カテゴリ候補)
  id, company_id null, normalized_text, frequency, distinct_users,
  nearest_category_sim float null, status (pending/approved/rejected/merged),
  merged_into_category_id null, reviewed_by null, timestamps
```
- `activity_support_plans` に `program_category_id`(任意)を後付けし、マスタ単位で1度分類→`daily_records.support_plan_id` 経由で継承。

### 3.3 集計レイヤ(Layer2 / 解析用)
```
ai_edit_metrics  (期間×次元のロールアップ。スケジュールジョブで冪等再計算)
  id, period_ym char(7),               -- 2026-06
  company_id, classroom_id null,
  subj_cohort null, subj_growth_stage null, author_user_id null,
  document_type null, support_category null, program_category_id null,
  gen_count int, revision_count int, changed_count int,
  edit_rate float,                      -- changed/対象
  change_ratio_avg float, change_ratio_p50 float, change_ratio_p90 float,
  ai_acceptance float,                  -- 1 - change_ratio_avg
  top_reason_categories jsonb,          -- [{category_id,count}]
  sample_n int,                         -- k-匿名判定用(n<5は表示抑止)
  computed_at
  unique(period_ym, company_id, classroom_id, subj_cohort, subj_growth_stage, author_user_id, document_type, support_category, program_category_id)
```
- 集計対象は **同意済みデータのみ**。`sample_n < 5` のセルはレポートで秘匿。
- これが報告者の言う「解析できるデータ」。BIや管理者レポートはこの表を読む。

---

## 4. 分類方法(どう振り分けるか)

段階的・自己改善:
1. **P0 ルール**: `program_categories.aliases` のキーワードで `activity_name`/`common_activity`/`support_content` を照合 → `method=rule`(confidence=一致強度)。
2. **P1 埋め込み**: 未マッチは `vector_embeddings`(text-embedding-3-small)でカテゴリ重心と類似度比較 → `method=embedding`。
   - 注: `vector_embeddings` に company_id列が無いため metadata に company_id を入れてフィルタ(既存方針)。
3. **人手修正(正)**: 職員がUIで分類を訂正 → `method=manual`(最優先)。自由入力で新カテゴリらしき語は `program_category_candidates` へ → 管理者が昇格(動的タクソノミー)。
   - 訂正自体が分類器の学習素材(分類精度が上がる=入力するほど自動分類が当たる)。

---

## 5. 分析 → 是正ループ

- **管理者レポート(画面)**: 次元(コホート/作者/施設/プログラム/文書)で `edit_rate`・`change_ratio`・`ai_acceptance`・主要修正理由・時系列推移。成長志向の表示(回数ランキングではなく改善余地と到達)。
- **自己改善ループ(S5)**: (コホート×プログラム×施設)で頻出する修正パターンを「ライティング/支援プロファイル」に集約 → 生成プロンプトへ few-shot/文体ガイドとして注入 → 修正が減る。
- **支援案の最適化**: 支援内容(support_plan_detail)について、修正の多いプログラム種別・コホートを特定し、生成側のテンプレ/語彙を是正。

---

## 6. 性別・特性・プライバシー

- **性別**: `students.gender`(nullable enum)を追加。入力任意・要配慮。**集計のみ**に使用(k-匿名 n≥5)。プロンプトには既定で入れない。
- **特性**: 自由記述PIIは禁止。`ability_talent_signs`(才能サイン)や face_sheet の配慮事項を**統制語彙**へ正規化した `student_trait_tags`(統制リスト)で持つ。集計のみ・k-匿名。
- 全分析は `canUseForLearning` の同意済みデータに限定。レポートは小セル秘匿。
- 監査: 次元追加(gender等)も consent_definitions の目的の範囲内かを確認(必要なら版を上げる)。

---

## 7. フェーズ計画(S4〜S5)

| フェーズ | 分類 | 内容 |
|---|---|---|
| **S4a** | schema/data | 次元スナップショット列を events に追加 + AiLearningCapture が記録時に算出。`program_categories` master + seeder(2.2)。`program_classifications`/`program_category_candidates` 新設。 |
| **S4b** | logic | 分類エンジン(ルール→埋め込み)+ 連絡帳/活動・支援詳細の保存にフック。職員の分類訂正API + 動的候補。 |
| **S4c** | logic/schema | `ai_edit_metrics` 集計ジョブ(scheduler, 冪等, 同意済みのみ, k-匿名)。 |
| **S4d** | screen | 管理者レポート画面(次元フィルタ・推移・主要修正理由)。成長志向表示。 |
| **S4e** | schema | `students.gender` 追加 + 特性統制タグ(要配慮・同意確認)。集計のみ。 |
| **S5** | logic | ライティング/支援プロファイルを生成プロンプトへ注入(自己改善ループ)。 |

> 推奨着手順: S4a(土台)→ S4b(分類)→ S4c(集計)→ S4d(レポート)→ S4e(性別/特性)→ S5(フィードバック)。
> 1コミット=1分類、各フェーズ後にテスト追加・デプロイ承認(CLAUDE.md準拠)。

---

## 8. 確認したい論点(着手前)

1. **性別の入力**: 児童マスタに性別カラムを追加してよいか(任意入力・集計のみ・要配慮)。または当面コホート/成長段階/特性のみで進めるか。
2. **特性の語彙**: 統制タグの初期セットをこちらで提案してよいか(診断名などの医療情報は扱わず、支援上の特性カテゴリに限定)。
3. **実施プログラム分類の初期語彙**(2.2)で過不足ないか(現場の呼び方に合わせて調整)。
4. **レポートの粒度**: 施設管理者が見るのは「自施設のみ」/マスターは「全社」でよいか。職員個人別は本人と管理者のみ等の可視範囲。
5. **着手範囲**: まず S4a+S4b(分類の土台と付与)から始め、集計・レポートは次段で良いか。
