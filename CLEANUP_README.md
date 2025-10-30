# データクリーンアップスクリプト

このシステムでは、古いデータを自動的に削除するスクリプトが用意されています。

## クリーンアップの内容

### 1. 提出物データの削除
- **対象**: 完了から半年（6か月）以上経過した提出物
- **削除内容**:
  - `submission_requests` テーブルのレコード
  - 添付ファイル（ある場合）
- **スクリプト**: `cleanup_old_submissions.php`

### 2. チャット添付ファイルの削除
- **対象**: 送信から3か月以上経過したチャット添付ファイル
- **削除内容**:
  - チャット添付ファイル（実ファイル）
  - データベースの添付ファイル情報（メッセージ本文は残る）
- **スクリプト**: `cleanup_old_chat_attachments.php`

## 使用方法

### 手動実行

```bash
# 提出物データのみクリーンアップ
php cleanup_old_submissions.php

# チャット添付ファイルのみクリーンアップ
php cleanup_old_chat_attachments.php

# すべてを一括クリーンアップ
php cleanup_all.php
```

### 自動実行（cron設定）

毎日午前2時に自動実行する例:

```bash
# crontabを編集
crontab -e

# 以下の行を追加
0 2 * * * cd /c/xampp/htdocs/kobetu && /c/xampp/php/php.exe cleanup_all.php >> logs/cleanup.log 2>&1
```

Windowsの場合はタスクスケジューラを使用:

1. タスクスケジューラを開く
2. 「基本タスクの作成」を選択
3. トリガー: 毎日午前2時
4. 操作: プログラムの開始
   - プログラム: `C:\xampp\php\php.exe`
   - 引数: `cleanup_all.php`
   - 開始: `C:\Users\TANI\app\kobetu`

## ログの確認

スクリプトの実行結果は標準出力に表示されます。
cronで実行する場合は、ログファイルにリダイレクトすることをお勧めします。

```bash
# ログディレクトリを作成
mkdir -p logs

# cronで実行し、ログを保存
0 2 * * * cd /path/to/kobetu && php cleanup_all.php >> logs/cleanup.log 2>&1
```

## 注意事項

- クリーンアップされたデータは復元できません
- 本番環境で実行する前に、テスト環境で動作確認をしてください
- 初回実行時は大量のデータが削除される可能性があります
- データベースのバックアップを定期的に取得してください

## トラブルシューティング

### ファイル削除の権限エラー
```
警告: ファイル削除失敗: uploads/chat_attachments/xxx.pdf
```

解決方法:
```bash
# uploadsディレクトリの権限を確認
ls -la uploads/

# 必要に応じて権限を変更
chmod -R 755 uploads/
```

### データベース接続エラー

`config/database.php` の設定を確認してください。
