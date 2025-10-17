-- バージョン5へのマイグレーション
-- イベントテーブルに対象者フィールドを追加

ALTER TABLE events
ADD COLUMN target_audience ENUM('elementary', 'junior_high_school', 'all', 'guardian', 'other') DEFAULT 'all' COMMENT '対象者: elementary=小学生, junior_high_school=中高生, all=全体, guardian=保護者, other=その他'
AFTER event_description;
