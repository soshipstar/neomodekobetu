-- Migration v21: チャット添付ファイル機能の追加

-- chat_messagesテーブルに添付ファイル関連カラムを追加
ALTER TABLE chat_messages
ADD COLUMN attachment_path VARCHAR(255) NULL COMMENT '添付ファイルパス',
ADD COLUMN attachment_original_name VARCHAR(255) NULL COMMENT '添付ファイル元のファイル名',
ADD COLUMN attachment_size INT NULL COMMENT '添付ファイルサイズ（バイト）',
ADD COLUMN attachment_type VARCHAR(100) NULL COMMENT '添付ファイルMIMEタイプ';
