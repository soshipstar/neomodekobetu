# 補足資料 ― 支援記録生成・個別支援計画作成ロジックのアカデミック詳説

**対象**: 技術・論理を厳密に検討される読者(共同開発者・技術評価者)
**位置づけ**: 『kiduri 紹介資料』別添。本書は本番コードベースに準拠し、生成系の論理を形式的に記述する。
**作成**: 2026年6月15日 / **準拠コミット**: 本番 `d9912d8`

> 本書の主張はすべて実装に準拠する。実装されていない事項(例: 検索拡張生成)は「**未配線**」と明記し、理想と現状を混同しない。記号は厳密な数学というより、論理構造を明確化するための記法である。

---

## 0. 記法と前提

- 文書集合 \(D = \{\text{support\_plan}, \text{monitoring}, \text{assessment\_staff}, \text{integrated\_note}\}\)。
- 児童 \(s\)、施設(法人)\(c=\mathrm{company}(s)\)。
- 生成モデル \(M_\theta\)(現行は OpenAI `gpt-5.4-2026-03-05` 系。連絡帳は `gpt-5.4-mini-2026-03-17`)。生成は確率的写像 \(M_\theta(\cdot;\tau)\)、\(\tau\) は温度。
- 仮名化作用素 \(\Pi_s\)(PiiMasker。後述 §7)。\(\Pi_s\) は実名→プレースホルダ、\(\Pi_s^{-1}\) はその逆。
- ガイダンス作用素 \(G\)(§4)。同意述語 \(\mathrm{Agg}(c)\)、\(\mathrm{Learn}(s)\)(§7.3)。

**設計上の不変条件(全生成系で成立)**
1. **PII境界**: 外部モデル \(M_\theta\) へ渡るテキストは必ず \(\Pi_s\) を通過する(静的検査 A005 が全 `->chat()->create` 呼出箇所に \(\Pi_s\) 参照を強制)。
2. **説明可能性**: 各生成は入力根拠の要約 \(\mathrm{src}\)(sources)を返す。
3. **追記専用の学習捕捉**: 生成・修正は学習基盤へ副作用として記録される(本処理を止めない fail-open な捕捉。ただし同意は fail-closed)。

---

## 1. 問題設定 ― 支援文書生成は「条件付き生成」である

支援文書 \(y\) の生成は、児童 \(s\) について収集した文脈 \(x\) を条件とする条件付き生成

\[
y \sim M_\theta\big(\,\cdot \mid \Pi_s(x)\ \Vert\ G(c,d)\,;\ \tau\big)
\]

として定式化される。ここで \(\Vert\) は連結、\(G(c,d)\) は施設 \(c\)・文書種別 \(d\) のガイダンス(§4)。生成後、出力は \(\Pi_s^{-1}\) で実名復元され、**下書き**として人間に提示される(自動確定はしない)。

本システムの非自明な特徴は、\(x\) が単一テキストではなく**複数の業務オブジェクトを階層的に集約**したものであること(§2)、\(y\) が**多段の条件付き生成の合成**で得られること(§3)、そして \(G\) が**明示基準と暗黙学習の合成**であること(§4)、さらに人間の修正 \(y \to y'\) が**次回の \(G\) を更新する**こと(§5)である。

---

## 2. 文脈の階層的集約(Staged Aggregation)

個別支援計画 (`SupportPlanController@generateAi`) を例に、文脈 \(x\) の構成を示す。\(x = \bigoplus_{i} \phi_i(s)\)、各 \(\phi_i\) は業務オブジェクトのテキスト射影:

| 段 | \(\phi_i\) | 取得元 | 範囲 | 位置づけ |
|---|---|---|---|---|
| 1 | 児童基本情報 | `student` | 氏名・教室 | 文脈 |
| 2 | 保護者アセスメント | `AssessmentGuardian` | 最新期間(本人/家庭の願い・5領域・目標) | **土台** |
| 3 | 職員アセスメント | `AssessmentStaff` | 最新期間(5領域・目標) | **土台** |
| 4 | モニタリング | `MonitoringRecord` | 最新1件(総合所見・領域別達成度) | **土台** |
| 5 | 前回計画 | `IndividualSupportPlan` (`is_official`) | 直近の確定計画 | 連続性 |
| 6 | 能力評価別添(任意) | `AbilitySummaryService` | `ability_*_enabled` 時のみ(§8) | 客観 |

**コアの目標ロジック**: 個別支援計画の目標は、(i) **継続すべき目標はモニタリング**(達成度)から、(ii) **新たな目標は職員・保護者アセスメント**から抽出して合成する。重要なのは、**連絡帳の徹底的な解析は計画生成ではなく上流のアセスメント生成で行われる**点である(§3.3: `AssessmentController@generate` は直近 **6か月の連絡帳を全件**、二段集約(蒸留)で詳細分析し、今後6か月分の5領域課題と目標を生成する)。したがって計画生成 (`generateAi`) は連絡帳を直接の根拠とせず、アセスメントとモニタリングを土台とする(commit `d9912d8` で連絡帳の直接入力を廃止)。

集約は決定的(モデル非依存)であり、生成の前段で完結する。**この設計は説明可能性を担保する**: どの段が空でないかを \(\mathrm{src}\) として返す(§9)。

> 形式的には、\(x\) は「業務データベースの構造化スナップショット」であり、自由文の塊ではない。これは後述する検索拡張生成(RAG, §10)とは異なる ― 文脈は**決定的に構築**され、確率的検索で選ばれてはいない。

---

## 3. 多段条件付き生成

### 3.1 個別支援計画(単一呼出・構造化出力)

\(M_\theta\)(`gpt-5.4`, \(\tau=0.8\), max 4000)を1回呼び、JSON

\[
y=\{\text{life\_intention},\ \text{overall\_policy},\ \text{long\_term\_goal},\ \text{short\_term\_goal},\ \text{details}[\,]\}
\]

を得る。プロンプトは (i) 専門家ペルソナ+反ハルシネーション制約(「入力に無い事実を創作しない」)、(ii) §2 の構造化文脈、(iii) 12項の制約ブロック(保護者・スタッフ目標の尊重と連続性、施設内実施可能性、具体度下限、期間表現の禁止 等)、(iv) JSON スキーマ、(v) ガイダンス \(G\)(§4)からなる。

出力健全性: `json_decode` が配列でなければ **502 を返して明示的に拒否**(部分的・壊れた構造を採用しない)。配列なら \(\Pi_s^{-1}\) で復元。

### 3.2 計画改訂(保守的編集作用素)

`generateRevisedDraft`(\(\tau=0.3\))は、原案スナップショット \(y_0\)・保護者コメント \(r\)・個別支援会議議事録 \(m\) を条件に

\[
(y_1,\ A) = M_\theta(\,\cdot \mid y_0, r, m\,)
\]

を生成する。\(A\) は変更注釈集合 \(\{(\text{field},\text{type}\in\{add,remove\},\text{text},\text{reason})\}\)。プロンプト制約は「**指摘の無い箇所は一字一句保持**」であり、これは編集作用素 \(E\) が恒等に近い(\(\|E(y_0)-y_0\|\) を最小化する)ことを要求する低温度設計と整合する。低 \(\tau\) は確率質量を原案近傍に集中させ、過剰な書き換えを抑制する。

### 3.3 職員アセスメント(3段の連鎖条件付き生成)

`AssessmentController@generate` は**逐次的な3呼出の合成**で、教育評価の論理(現状把握→近接目標→遠隔目標)を模す:

\[
\underbrace{u_{\text{dom}}}_{\text{5領域課題}} \!=\! M_\theta(\cdot\mid \text{連絡帳6か月・全件を蒸留},\,\text{家庭視点}\,;\,\tau{=}0.6,\,2500)
\]
\[
u_{\text{short}} = M_\theta(\cdot \mid u_{\text{dom}},\,\text{面談},\,\text{家庭視点}\,;\,0.6,\,800)
\]
\[
u_{\text{long}} = M_\theta(\cdot \mid u_{\text{short}},\,\text{面談},\,\text{家庭視点},\,\text{前回長期目標}\,;\,0.6,\,900)
\]

各段は前段の出力を条件に取り、**条件付き分布の連鎖**を成す:
\[
p(u_{\text{dom}},u_{\text{short}},u_{\text{long}}\mid x) = p(u_{\text{dom}}\mid x)\,p(u_{\text{short}}\mid u_{\text{dom}},x)\,p(u_{\text{long}}\mid u_{\text{short}},u_{\text{dom}},x).
\]

PII の扱いが重要: 仮名化は**連鎖全体で保持**され、中間生成物 \(u_{\text{dom}},u_{\text{short}}\) は仮名のまま次段へ渡り、**最終出力でのみ** \(\Pi_s^{-1}\) を適用する。これにより外部モデルは一度も実名に触れない(§7.1 の対合性が連鎖でも保たれる)。「家庭視点」は同期間の提出済み保護者アセスメントで、家庭という構造的に異なる情報源を生成条件に加える(これは本セッションで追加した拡張であり、`sources.guardian_assessment_used` に記録される)。

### 3.4 連絡帳統合文(軽量モデル+副系+フォールバック)

`RenrakuchoController@generateIntegrated`(`gpt-5.4-mini`, \(\tau=0.7\), max 1000)は、5領域観察・活動・領域別目標引用・発達段階別文体(GradeLevelStyle)を条件に保護者向け一文章を生成する。後処理に (i) 残存領域ラベル除去 `stripDomainLabels`、(ii) **ヒヤリハット検出の副呼出**(別 \(M_\theta\) 呼出で `{detected, severity, category, ...}` を構造化抽出)、(iii) \(\Pi_s^{-1}\)。

**フォールバックの定式化**: \(M_\theta\) が失敗した場合、決定的連結
\[
y_{\text{fallback}} = \text{活動名} \,\Vert\, \text{stripDomainLabels}(\text{5領域観察}) \,\Vert\, \text{notes}
\]
を返す(成功扱い・下書き)。これは可用性を生成品質より優先する設計で、現場運用上「画面が止まらない」ことを保証する(graceful degradation)。

---

## 4. ガイダンス作用素 ― 明示基準と暗黙学習の合成

\(G(c,d)\)(`WritingProfileService::buildGuidance`)は2系統の直和である:

\[
G(c,d) = G_{\text{explicit}}(c)\ \oplus\ \big[\mathrm{Agg}(c)\big]\cdot G_{\text{implicit}}(c,d)
\]

- **\(G_{\text{explicit}}\)(明示基準, E1)**: 企業管理者が GPT-5.4 との対話で確定した施設記録基準 `facility_writing_standards.guidance_text`。**施設自身の方針であり PII を含まないため、同意ゲートを要さず常に作用**。プロンプトでは「**この施設が定めた記録基準(必ず従う)**」として最優先で提示。
- **\(G_{\text{implicit}}\)(暗黙学習, S5)**: 集計同意 \(\mathrm{Agg}(c)\) が真のときのみ作用。内容は (a) 主要修正理由 \(\to\) 指示文の写像(`ai_edit_metrics.top_reason_categories` 由来)、(b) **品質ゲートを通った確定稿の例示**(後述)。
- \([\,\cdot\,]\) はアイバーソン括弧(同意成立で1、否なら0)。両系統が空なら \(G=\varnothing\) を返し、生成は素の条件付き生成に退化する。

**例示の品質ゲート \(Q\)**(ノイズ抑制): 例示集合は
\[
Q = \{\text{adopted}\} \cup \big(\{\text{finalized}\} \cap \{\text{len} \ge \ell_{\min}\}\big) \setminus \{\text{excluded}\}
\]
で、`adopted`(管理者採用見本)を優先、`excluded`(学習除外)を排除、`finalized`(official/submit/publish)かつ最低長 \(\ell_{\min}=10\) を満たすもののみ。各例示は \(\Pi_c\)(施設全体マスカー)→`scrubStructuredPii`→**短名 fail-safe**(1文字氏名が残る例は破棄、§7.2)で外部提示安全化(`scrubExcerpt`)。

> 含意: 「入力するほど真実に近づく」とは、人間の確定稿と修正理由が \(G_{\text{implicit}}\) を通じて生成分布を**現場の規範に向けて条件付ける**こと。\(G_{\text{explicit}}\) はその規範を管理者が明示的に与える経路。両者は加法的に作用し、暗黙が薄い導入初期でも明示で品質を担保できる。

---

## 5. 自己改善ループの形式化

人間の修正 \(y \to y'\) はセクション単位で捕捉される(`AiLearningCapture::recordSectionRevisions`)。各セクション \(k\) について

\[
\delta_k = 1 - \frac{\mathrm{similar\_text}(y_k, y'_k)}{100}\ \in[0,1]
\]

を変更率として記録する(`change_ratio`)。\(y_k\)(before)・\(y'_k\)(after)は**暗号化保存**(Layer1)。集計(`AiEditMetricsService`)は期間×8ファセットで

\[
\overline{\delta}=\text{mean}(\delta),\quad \text{edit\_rate}=\frac{\#\{\text{修正された文書}\}}{\#\{\text{生成}\}},\quad \text{ai\_acceptance}=1-\overline{\delta}
\]

と percentile \(\delta_{p50},\delta_{p90}\) を算出(k匿名 \(n\ge5\))。\(\text{ai\_acceptance}\) は「AI下書きがどれだけそのまま使われたか」の指標。

**ループの不変式**:
\[
G^{(t+1)} = \Gamma\big(G^{(t)},\ \{(y,y',\text{reason},\text{outcome})\}^{(t)}\big)
\]
ここで \(\Gamma\) は修正・理由・成果から次期ガイダンスを再構成する写像(現実装は統計的: 主要理由→指示文、確定稿→例示)。理想状態では、生成分布が現場規範へ条件付けられるにつれ \(\overline{\delta}^{(t)}\) は単調非増加に向かう(=修正が減る)。重要なのは、\(\Gamma\) が \(M_\theta\) に依存しないこと ― **モデルを交換しても \(\Gamma\) と蓄積 \(\{(y,y',\dots)\}\) は不変**であり、これがローカルAI移行時の精度維持の理論的根拠である(§10)。

> 逆インセンティブの回避(設計制約): \(\overline\delta\) を職員評価に直結させてはならない。AI出力を無修正採用すれば \(\delta=0\) にでき、丁寧な推敲が不利になる。評価は成長・気づきの多面指標として用いる(企画書 §14.4)。これは「最適化指標と評価指標の分離」という設計原則である。

---

## 6. 構造化抽出と蒸留(L1 → L4)

### 6.1 L1→L2: 構造化抽出(D1)

`StructuredExtractionService::extract` は修正後テキスト \(y'_k\) から、**外部AIを使わず**規則ベースで
\[
\sigma(y'_k)=\{\text{tags},\ \text{has\_result\_marker},\ \text{has\_hypothesis\_marker},\ \text{text\_length},\ \text{method}{=}\text{rule}\}
\]
を抽出する。**本文は保存せず**、結果語・仮説語マーカーと統制タグ(5領域・プログラム・成長段階・コホート)のみ。これにより L2 は PII を持たない構造化層となる。

### 6.2 L2→L3: 支援知蒸留(D4)

`KnowledgeDistillationService::rebuild(c)` は \(\mathrm{Agg}(c)\) 下で、児童を条件キー \(\kappa=(\text{cohort}\times\text{growth\_stage})\) でグループ化し、\(|\,\text{group}\,|\ge K\)(\(K=5\), k匿名)のグループのみ蒸留する:

\[
L3(\kappa)=\Big\langle\ \text{top\_support\_categories},\ \text{top\_programs},\ \overline{\text{outcome}}_{A,B,C},\ \text{exemplar\_excerpts}\ \Big\rangle
\]

成果平均は §8 の \(A,B,C\) をメンバごとに算出して平均。見本抜粋は `scrubExcerpt`(§4)で外部提示安全化。冪等(法人単位 delete→insert)。横断検索 D5(`SupportKnowledgeController`)は児童 \(s\) の \(\kappa\) に一致する \(L3\) を返す(法人スコープ・該当無しは null)。

### 6.3 L3→L4: 原理(将来)

複数 \(L3\) からの原理抽出(見通し形成・自己決定・安心基地形成 等)は構想層であり、全国横断(§10)とともに法務要件クリア後に解禁。

### 6.4 支援者成長モデル(D3)

`SupporterLevelService::levelFor` は職員の構造化マーカー密度と採用見本数から成長段階 \(\mathrm{Lv}\in\{1,2,3,4\}\) を推定し、`inquiryPolicy(Lv)` で問い返し(D2)の量・様式を出し分ける。これは生成の補助ではなく**人間の思考を育てる介入の適応制御**であり、\(\mathrm{Lv}\) が上がるほど介入を減らす(足場かけの漸減, fading)。

---

## 7. プライバシーの形式モデル

### 7.1 仮名化の対合性

\(\Pi_s\)(PiiMasker)は児童・保護者の既知氏名 \(N_s\) をプレースホルダ \(P\)(【児童】等)へ写す。マスク済集合上で
\[
\Pi_s^{-1}\circ \Pi_s\big|_{\text{text}(N_s)} \approx \mathrm{id}
\]
が成り立つ(既知氏名に対する近似対合)。`maskArray/unmaskArray` は構造体に対し再帰適用。外部モデルへは \(\Pi_s(\cdot)\) のみが渡るため、\(M_\theta\) は \(N_s\) を観測しない(不変条件1)。

### 7.2 構造化スクラブと短名 fail-safe

`scrubStructuredPii` は日付・電話・郵便番号・連続数字・敬称付き人物名を決定的に除去する(文字クラスは明示Unicodeレンジで CJK 記号の巻き込みを回避)。1文字氏名は \(\Pi_s\) が確実にマスクできないため `shortNames` に退避し、**残存する例示は丸ごと破棄**(`scrubExcerpt`)。これは「マスク不能なら出さない」という fail-closed な外部提示規律。

### 7.3 同意の論理(AND・fail-closed)

\[
\mathrm{Agg}(c) \equiv c.\text{ai\_consent\_aggregate},\qquad
\mathrm{Learn}(s) \equiv \mathrm{Agg}(\mathrm{company}(s))\ \wedge\ s.\text{ai\_consent\_learning}
\]

- 生成イベント記録は \(\mathrm{Agg}(c)\) を要し、payload は \(\Pi_s\) 済(L2)。
- 修正イベント(before/after 暗号化, Layer1)は \(\mathrm{Learn}(s)\)(AND)を要す。
- **fail-closed**: 同意定義(版・文面)が未投入のとき、grant は例外で中断しトランザクションをロールバック(版に紐づかない「立証不能な同意」を積まない)。撤回(revoke)は本人の権利として常に許可。
- **証拠性**: 同意時の提示文面を `definition_snapshot` として不変保存し、版改訂後も「何に同意したか」を立証可能(APPI 立証責任, rank9)。

### 7.4 3層のPIIと格納規律

| 層 | 対象 | 規律 |
|---|---|---|
| L1 原本 | `ai_revision_events.before/after_text` | encrypted cast で保存時暗号化 |
| L2 仮名化 | `ai_generation_events.generated_payload` | \(\Pi_s\) 済・同意下のみ |
| 非暗号化メタ | `diff`, `source_ref`, `section_key`, `support_category` | **実名を入れない**(数値・統制コードのみ。section は安全トークン化) |

### 7.5 テナント分離

ベクトル埋め込み・監査ログに `company_id` を付与し、検索・閲覧を法人スコープに限定(rank5/6)。法人不明の埋め込みは横断検索から不可視(fail-closed)。監査閲覧は非マスター=自施設のみ、施設不明は0件。これは多テナント環境での**情報フローの非干渉性(non-interference)**を構造的に担保する。

---

## 8. 成果(Outcome)と別添「評価状況の全体像」(§13/S6)

個別支援計画には別添として「評価状況の全体像」(`AbilitySummaryService::forStudent`)が付く。能力評価の項目別最新スコアを**領域**でまとめ、(i) 客観(支援者)と本人の主観をレーダーで重ね、(ii) 領域別の詳細表(点数・段階/水準・保護者向けのことば・要確認)、(iii) 下記の成果3指標、を一覧する。スコアは **0–10 の個人内評価**(他児比較ではなく「過去の自分からの成長」)で、福祉の非競争原則に合致する。本人の主観は外部サービス mynameis の自己評価 \(v\in\{1..5\}\) を \(b=\frac{v-1}{4}\times10\) で 0–10 へ正規化して並置する(教室名一致チェックで取り違えを防止)。さらにこの別添の客観要約 (`toPromptText`) は**個別支援計画AIのプロンプトへ還流**し、記録 → 評価 → 計画の循環を閉じる。別添は PDF 出力して計画書に添付でき、能力評価が無効な教室では自己ゲート(409)で非表示。

`OutcomeService::forStudent` は3指標を返す(いずれも閲覧用・外部送信なし):

- **A 客観スコア変化**: 能力評価 `ability_scores.change` を項目最新値で集計。\(\overline{\Delta},\ \#\text{improved},\ \#\text{declined}\)。
- **B モニタリング達成度**: 達成度 \(a\in\{1..5\}\) を \(\text{pct}=\frac{\bar a-1}{4}\times100\) で正規化。
- **C 主観×客観の一致**: 領域 \(j\) で客観 \(o_j\)・主観正規化 \(b_j\)(いずれも0–10)から
\[
\text{agree}_j=\max\!\Big(0,\ 1-\frac{|o_j-b_j|}{10}\Big)\times100,\qquad \text{overall}=\text{mean}_j(\text{agree}_j).
\]

これらは蒸留(§6.2)で条件 \(\kappa\) 別に平均され、「同条件で何が効くか」を成果で裏づける。\(A\) は将来 S9(介入↔成果連結)で支援パターンと相関分析される基盤となる。

---

## 9. 説明可能性とトレーサビリティ

各生成は \(\mathrm{src}\)(`sources`)を返す: アセスメント・モニタリング・連絡帳件数と期間・前計画・能力評価の有無。職員は「何を根拠に生成したか」を確認できる。さらに `AiGenerationLog` に **モデル・温度・最大トークン・(マスク済)プロンプト・トークン使用量・所要時間** を記録し、生成の**再現性・監査可能性**(観点10)を担保する。学習基盤側は生成イベント(L2)と修正イベント(L1)を追記し、\(\Gamma\)(§5)の素材とする。

---

## 10. 限界と理論的拡張

誠実な開示として、現状の論理的境界を明示する。

1. **検索拡張生成(RAG)は生成へ未配線**: `EmbeddingService`/`VectorSearchService`(pgvector・cosine・法人スコープ)は実装済だが、**生成プロンプトに「過去の類似計画」を確率的検索で挿入する経路は未配線**。現行の文脈 \(x\) は §2 の決定的集約であり、検索で選ばれてはいない。RAG 配線は将来拡張(§5 の \(\Gamma\) に検索項を加える形)であり、配線前にテナント分離(§7.5)を先行実装済(漏洩の予防)。
2. **\(\Gamma\) は現状ヒューリスティック**: 修正→指示文・確定稿→例示の統計写像であり、勾配学習ではない。蓄積(L1)は将来のローカルAI微調整・蒸留の素材として保存されているが、その学習自体は未実施(法務4点クリア後)。
3. **因果の留保**: §8 の成果は相関・記述統計であり、支援→成果の**因果**は主張しない。S9(モニタリング確定版の不変化→介入↔成果連結)が因果的分析の前提を整える先行投資。
4. **全国横断は未解禁**: §6.3/§7.5 の通り、法人をまたぐ蒸留は法務4点(保存年限・地理範囲・同意UI・規約)と恒久匿名化のクリアを前提にゲートしている。
5. **モデル非依存性の含意**: §5 の \(\Gamma\) と蓄積が \(M_\theta\) と独立であることは、\(M_\theta\) を OpenAI からローカル蒸留モデルへ置換しても、\(G\)(明示+暗黙)と成果フィードバックがそのまま機能することを意味する。これが「AIは交換可能、蓄積知識は交換できない」という事業命題の**技術的裏づけ**である。

---

## 付録A: 生成系の一覧(実装準拠)

| エンドポイント | 生成 | モデル | \(\tau\) | max | 出力 | RAG | 学習捕捉 |
|---|---|---|---|---|---|---|---|
| `generateAi` | 個別支援計画 | gpt-5.4 | 0.8 | 4000 | JSON 5項 | 未配線 | 生成(L2) |
| `generateRevisedDraft` | 計画改訂 | gpt-5.4 | 0.3 | 4000 | JSON+注釈 | 未配線 | 修正(L1) |
| `generate`(assessment) | 5領域→短期→長期 | gpt-5.4 | 0.6 | 2500/800/900 | JSON+text | 未配線 | 生成/修正 |
| `generateIntegrated` | 連絡帳統合文 | gpt-5.4-mini | 0.7 | 1000 | text(+ヒヤリハット) | 未配線 | 生成(L2) |
| `generateBasis` | 計画の全体所感 | gpt-5.4 | 0.7 | — | text | 未配線 | — |
| `generateWishFromInterview` | 本人の願い | gpt-5.4 | 0.7 | — | text | 未配線 | — |

## 付録B: 記号

\(s\) 児童 / \(c\) 施設(法人) / \(M_\theta\) 生成モデル / \(\Pi_s\) 仮名化 / \(G\) ガイダンス / \(\delta\) 変更率 / \(\kappa\) 蒸留条件キー / \(\mathrm{Agg},\mathrm{Learn}\) 同意述語 / \(\Gamma\) 自己改善写像。

---

*本書は本番コードベース(`SupportPlanController`, `AssessmentController`, `RenrakuchoController`, `WritingProfileService`, `RecordingStandardAdvisor`, `AiLearningCapture`, `StructuredExtractionService`, `KnowledgeDistillationService`, `SupporterLevelService`, `OutcomeService`, `AiEditMetricsService`, `ConsentService`, `PiiMasker`, `EmbeddingService`, `VectorSearchService` 等)に準拠して作成。*
