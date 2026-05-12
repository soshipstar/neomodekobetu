## Mission
このプロジェクトは旧アプリから移行された新アプリの保守・改修である。
新アプリ単体を対象に作業する。**旧アプリは参照しない。**

## Non-goals
- 推測で仕様を変えない
- 無関係なリファクタをしない
- UIの見た目改善を優先しない
- 1つの作業で複数カテゴリを同時に直さない
- **旧アプリ (neomodekobetu) のコード/DB を参照しない。移行は完了済み。**

## Priority
優先順位は以下の順にする:
1. データ破損・認証不整合・致命的API不一致
2. 業務ロジック不一致
3. 画面の欠落機能
4. 表示や文言の差分

## Source of truth
- 新アプリ自体の現状コード・DB・現場運用 (報告者からのフィードバック) を正とする
- 仕様判定で迷ったら報告者の依頼文をそのまま正とする
- 旧アプリは参照しない (移行済のため不要)
- 不明点はユーザーに確認する

## Required workflow
常に次の順で進める:
1. 差分を特定する (現状コード調査 + 報告者の要望から)
2. 再現手順を書く
3. 自動検査案を出す
4. 修正する
5. 修正後に比較テストまたは回帰テストを追加する
6. 結果を要約する

## Categories
差分は必ず次のどれかに分類する:
- schema
- data
- api
- logic
- auth
- screen

## Output format
各報告は以下の形式にする:
- ID
- 分類
- 重要度(P0/P1/P2)
- 期待される挙動 (報告者の要望またはあるべき仕様)
- 新アプリの現在挙動
- 差分の概要
- 影響ファイル
- 再現手順
- 修正方針
- 自動検査に落とせるか
- 必要なテスト

## Editing rules
- 1回の修正は1カテゴリのみ
- 修正後は必ず関連テストを追加または更新
- 修正対象外のファイルは触らない

## Notes
- 新アプリの場所: C:\dev\apps\conoha1\kiduri2026
- worktree: C:\dev\apps\conoha1\conoha b\kiduri2026\.claude\worktrees\laughing-moore-70f64e
- 本番: kiduri.xyz (ConoHa VPS via `ssh kiduri`, deploy via `deploy.sh frontend|backend|all`)
- 旧アプリ (neomodekobetu) は参照不要 (移行完了済)