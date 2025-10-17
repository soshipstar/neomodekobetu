# OpenAI API設定方法

かけはしの自動生成機能を使用するには、OpenAI APIキーの設定が必要です。

## 方法1: ファイルに直接記述（簡単）

1. `includes/openai_helper.php` を開く
2. 以下の行を探す：
   ```php
   define('OPENAI_API_KEY', getenv('OPENAI_API_KEY') ?: 'YOUR_OPENAI_API_KEY_HERE');
   ```
3. `YOUR_OPENAI_API_KEY_HERE` の部分をあなたのAPIキーに置き換える：
   ```php
   define('OPENAI_API_KEY', getenv('OPENAI_API_KEY') ?: 'sk-proj-xxxxxxxxxxxxxx');
   ```

## 方法2: 環境変数を使用（推奨・セキュア）

### Apacheの場合

1. `.htaccess` ファイルをプロジェクトルートに作成（既にある場合は追記）
2. 以下を追加：
   ```apache
   SetEnv OPENAI_API_KEY sk-proj-xxxxxxxxxxxxxx
   ```

### Nginxの場合

1. サーバー設定ファイルに追加：
   ```nginx
   location / {
       fastcgi_param OPENAI_API_KEY sk-proj-xxxxxxxxxxxxxx;
   }
   ```

## OpenAI APIキーの取得方法

1. https://platform.openai.com/ にアクセス
2. アカウント作成またはログイン
3. 「API keys」セクションでキーを生成
4. 生成されたキー（`sk-proj-...` で始まる）をコピー

## 使用モデルと料金

- 使用モデル: `gpt-4o-mini`（コスパ重視）
- 1回の生成で約2,000-3,000トークン使用
- 料金: $0.15 per 1M input tokens, $0.60 per 1M output tokens
- 参考: 1回の生成で約$0.001-0.002程度

## トラブルシューティング

### エラー: "OpenAI APIキーが設定されていません"
- APIキーが正しく設定されているか確認
- 環境変数を使用している場合は、サーバーを再起動

### エラー: "OpenAI APIエラー (HTTP 401)"
- APIキーが間違っている可能性
- APIキーの有効期限を確認

### エラー: "直近5か月の連絡帳データが見つかりません"
- 対象生徒の連絡帳データが5か月分存在するか確認
- 五領域（健康・生活、運動・感覚等）の記録があるか確認
