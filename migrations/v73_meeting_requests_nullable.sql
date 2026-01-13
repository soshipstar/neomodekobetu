-- 面談予約: 保護者からの申し込みに対応するため、staff_idとcandidate_date1をNULL許可に変更

-- 外部キー制約を一時的に削除
ALTER TABLE meeting_requests DROP FOREIGN KEY meeting_requests_ibfk_4;

-- staff_idをNULL許可に変更
ALTER TABLE meeting_requests MODIFY COLUMN staff_id INT DEFAULT NULL COMMENT 'スタッフID（保護者からの申し込み時はNULL）';

-- candidate_date1をNULL許可に変更（保護者からの申し込み時は不要）
ALTER TABLE meeting_requests MODIFY COLUMN candidate_date1 DATETIME DEFAULT NULL COMMENT '候補日時1（スタッフ提案時）';

-- 外部キー制約を再追加（ON DELETE SET NULLに変更）
ALTER TABLE meeting_requests ADD CONSTRAINT meeting_requests_ibfk_4
    FOREIGN KEY (staff_id) REFERENCES users(id) ON DELETE SET NULL;
