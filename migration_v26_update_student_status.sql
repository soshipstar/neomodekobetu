-- マイグレーション v26: 生徒ステータスに短期利用を追加

-- statusカラムの定義を変更
-- 既存値: active, withdrawn, trial
-- 新定義: trial（体験）, active（在籍）, short_term（短期利用）, withdrawn（退所）

ALTER TABLE students
MODIFY COLUMN status ENUM('trial', 'active', 'short_term', 'withdrawn')
DEFAULT 'active'
COMMENT '在籍状況: trial=体験, active=在籍, short_term=短期利用, withdrawn=退所';

-- 既存のデータは影響を受けない（active, withdrawn, trialは引き続き有効）
