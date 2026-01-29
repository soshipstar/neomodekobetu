# デプロイ手順

## 本番環境情報

| 項目 | 値 |
|------|-----|
| 本番URL | https://kobetu.narze.xyz |
| サーバー | ssh-tortonie.heteml.net |
| ユーザー | tortonie |
| ポート | 2222 |
| ドキュメントルート | ~/web/kobetu/ |

## SSH接続

SSH configが設定済みのため、以下のコマンドで接続可能：

```bash
ssh heteml
```

### SSH config設定（~/.ssh/config）

```
Host heteml
  HostName ssh-tortonie.heteml.net
  User tortonie
  Port 2222
  IdentityFile C:\Users\holho\.ssh\soship_ed25519
  ServerAliveInterval 30
  ServerAliveCountMax 3
```

## ファイルアップロード

### 単一ファイルのアップロード

```bash
scp -P 2222 <ローカルファイルパス> heteml:~/web/kobetu/<サーバーパス>
```

または、SSH config使用時：

```bash
scp <ローカルファイルパス> heteml:~/web/kobetu/<サーバーパス>
```

### 例：guardian/meeting_response.php をアップロード

```bash
scp public/guardian/meeting_response.php heteml:~/web/kobetu/public/guardian/
```

### ディレクトリ全体のアップロード

```bash
scp -r <ローカルディレクトリ> heteml:~/web/kobetu/<サーバーパス>
```

## よく使うコマンド

### サーバー上のファイル確認

```bash
ssh heteml "ls -la ~/web/kobetu/public/"
```

### サーバー上のファイル内容確認

```bash
ssh heteml "cat ~/web/kobetu/public/guardian/meeting_response.php"
```

### 複数ファイルの一括アップロード

```bash
# publicディレクトリ全体を同期（--dry-run で確認後、実行）
rsync -avz --dry-run -e "ssh -p 2222" public/ tortonie@ssh-tortonie.heteml.net:~/web/kobetu/public/

# 実際に同期
rsync -avz -e "ssh -p 2222" public/ tortonie@ssh-tortonie.heteml.net:~/web/kobetu/public/
```

## ディレクトリ構造

```
~/web/kobetu/
├── config/
├── includes/
├── migrations/
├── public/
│   ├── admin/
│   ├── guardian/
│   ├── staff/
│   ├── assets/
│   └── uploads/
└── ...
```

## 注意事項

- uploadsディレクトリは本番環境のデータを上書きしないよう注意
- .envファイルは本番用の設定があるため上書き禁止
- マイグレーションファイルは手動実行が必要な場合あり
