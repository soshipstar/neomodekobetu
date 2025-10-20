-- 提出期限リクエストにファイル添付機能を追加
ALTER TABLE submission_requests
ADD COLUMN attachment_path VARCHAR(255) COMMENT '添付ファイルパス',
ADD COLUMN attachment_original_name VARCHAR(255) COMMENT '添付ファイル元のファイル名',
ADD COLUMN attachment_size INT COMMENT '添付ファイルサイズ（バイト）';
