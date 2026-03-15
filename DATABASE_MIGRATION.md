# データベース移行ガイド (MySQL → PostgreSQL)

## 概要
- **移行元**: MySQL 8.0 (レガシーPHPアプリ `_legacy_php/`)
- **移行先**: PostgreSQL 16 + pgvector (Laravel 12 API)
- **ダンプファイル**: `_kobetudb.sql` (phpMyAdmin形式のMySQLダンプ)
- **移行スクリプト**: `migrate_mysql_to_pg.py` (メイン), `migrate_support_plans.py` (補足)

---

## 移行手順

### Step 1: PostgreSQLスキーマ作成 (Laravelマイグレーション)

```bash
docker compose exec backend php artisan migrate --force
```

50個のマイグレーションが実行される:
- `2026_01_01_000001` ~ `000046`: 初期テーブル46個
- `2026_03_10_093018`: personal_access_tokens (Sanctum)
- `2026_03_11_000001`: activity_support_plans テーブル
- `2026_03_11_100000`: レガシーカラム追加 (14テーブルに追加カラム)

### Step 2: メインデータ移行

```bash
python migrate_mysql_to_pg.py
```

このスクリプトが行うこと:
1. `_kobetudb.sql` からINSERT文を解析
2. テーブル名・カラム名をPostgreSQL用にマッピング
3. 型変換 (ENUM→VARCHAR, TINYINT→BOOLEAN, etc.)
4. `migration_output.sql` を生成
5. Docker経由でPostgreSQLに投入

#### 移行対象テーブル (36テーブル)

| MySQLテーブル | PostgreSQLテーブル | 備考 |
|---|---|---|
| classrooms | classrooms | service_type, target_grades → settings JSONB |
| users | users | そのまま |
| students | students | last_login→last_login_at, desired_*曜日カラムは除外 |
| chat_rooms | chat_rooms | |
| chat_messages | chat_messages | attachment_original_name→attachment_name |
| chat_message_staff_reads | chat_message_staff_reads | |
| chat_room_pins | chat_room_pins | |
| student_chat_rooms | student_chat_rooms | updated_at除外 |
| student_chat_messages | student_chat_messages | attachment_original_name→original_name |
| individual_support_plans | individual_support_plans | is_draft→status, 多数のカラム除外→後で追加 |
| individual_support_plan_details | support_plan_details | テーブル名変更, row_order→sort_order |
| monitoring_records | monitoring_records | is_draft→is_official, student_name除外 |
| monitoring_details | monitoring_details | |
| kakehashi_periods | kakehashi_periods | |
| kakehashi_staff | kakehashi_staff | |
| kakehashi_guardian | kakehashi_guardian | |
| daily_records | daily_records | **support_plan_id除外, classroom_id追加** |
| daily_routines | daily_routines | |
| student_records | student_records | |
| integrated_notes | integrated_notes | |
| newsletters | newsletters | classroom_id追加(NULLの場合あり) |
| newsletter_settings | newsletter_settings | |
| events | events | |
| event_registrations | event_registrations | |
| holidays | holidays | |
| absence_notifications | absence_notifications | |
| submission_requests | submission_requests | |
| student_submissions | student_submissions | |
| weekly_plans | weekly_plans | 特殊変換あり (後述) |
| weekly_plan_comments | weekly_plan_comments | |
| weekly_plan_submissions | weekly_plan_submissions | |
| activity_types | activity_types | |
| additional_usages | additional_usages | |
| classroom_capacity | classroom_capacity | |
| classroom_tags | classroom_tags | |
| facility_* (7テーブル) | facility_* (7テーブル) | |
| meeting_requests | meeting_requests | |
| school_holiday_activities | school_holiday_activities | |
| student_interviews | student_interviews | |
| work_diaries | work_diaries | |

### Step 3: 支援案データ移行 (メインスクリプトで未対応)

```bash
python migrate_support_plans.py
```

**重要**: メインの`migrate_mysql_to_pg.py`は`support_plans`テーブルを移行しない。
レガシーの`support_plans`テーブルは新システムでは`activity_support_plans`に対応する。

- MySQLカラム: id, activity_name, plan_type, target_grade, activity_date, activity_purpose, activity_content, tags, day_of_week, five_domains_consideration, other_notes, staff_id, classroom_id, created_at, updated_at
- PostgreSQLに追加: total_duration (デフォルト180), activity_schedule (NULL)
- 271件中270件移行成功 (1件は日付`0000-00-00`のためスキップ)

### Step 4: 移行後のデータ修正 (**必須**)

#### 4-1. daily_records の classroom_id 修正

レガシーの`daily_records`テーブルには`classroom_id`カラムがない。
`migrate_mysql_to_pg.py`は全レコードを`classroom_id=2`で投入している。
**スタッフのclassroom_idから正しい値に更新する必要がある。**

```sql
UPDATE daily_records
SET classroom_id = users.classroom_id
FROM users
WHERE daily_records.staff_id = users.id
  AND users.classroom_id IS NOT NULL
  AND daily_records.classroom_id != users.classroom_id;
```

2026年3月時点で150件が修正された。

#### 4-2. newsletters の classroom_id 修正

レガシーの一部のnewslettersに`classroom_id`がNULL。

```sql
UPDATE newsletters
SET classroom_id = u.classroom_id
FROM users u
WHERE newsletters.created_by = u.id
  AND u.classroom_id IS NOT NULL
  AND newsletters.classroom_id IS NULL;
```

#### 4-3. シーケンスのリセット

全テーブルのauto-incrementシーケンスをMAX(id)に合わせる必要がある。
`migrate_mysql_to_pg.py`は最後にシーケンスリセットを行うが、
`migrate_support_plans.py`は独自にリセットしている。

---

## 既知の問題・注意点

### テーブルマッピングの特殊ケース

1. **`support_plans` → `activity_support_plans`**
   - メインスクリプトでは対応しない。別スクリプト`migrate_support_plans.py`で処理。

2. **`individual_support_plan_details` → `support_plan_details`**
   - テーブル名が変更されている
   - `row_order` → `sort_order`, `category` → `domain`, `support_goal` → `goal`

3. **`daily_records`に`classroom_id`がない(レガシー)**
   - PostgreSQLでは必須カラム。移行後にスタッフのclassroom_idから推定・更新が必要。

4. **`weekly_plans`のスキーマが大幅に変更**
   - MySQL: student_id, week_start_date, フラットなカラム群
   - PostgreSQL: classroom_id, week_start_date, plan_content (JSONB), status, created_by
   - 特殊変換ロジックが`migrate_mysql_to_pg.py`内にある

5. **`individual_support_plans`の多くのカラムが初期マイグレーションで除外**
   - `2026_03_11_100000_add_missing_legacy_columns`で後から追加
   - manager_name, long_term_goal_date, short_term_goal_date, guardian_signature_image, staff_signature_image, etc.

### 型変換

| MySQL型 | PostgreSQL型 | 備考 |
|---|---|---|
| TINYINT(1) | BOOLEAN | 0→false, 1→true |
| ENUM | VARCHAR + CHECK制約 | Laravel enum() |
| SET | VARCHAR | カンマ区切り文字列 |
| TEXT (JSON) | JSONB | 一部テーブルのみ |
| TIMESTAMP | TIMESTAMP(0) | タイムゾーン付きの場合あり |
| INT AUTO_INCREMENT | BIGINT + SEQUENCE | |

### 文字エスケープ

- MySQL: `\'` → PostgreSQL: `''`
- MySQL: `\n`, `\r` → そのまま (ただし解析に注意)
- MySQL: `\0` → 除去

---

## データ件数 (2026年3月11日時点)

| テーブル | 件数 | 備考 |
|---|---|---|
| users | 117 | admin, staff, guardian, student, tablet |
| students | 110 | 5教室分 |
| classrooms | 5 | かけはし, narZE, そらのはしら, かけはしシンプル, スタジオキッズグリーン |
| chat_rooms | 81 | |
| chat_messages | 1,495 | |
| student_chat_rooms | 21 | |
| student_chat_messages | 45 | |
| daily_records | 346 | 教室1:80, 教室2:196, 教室3:50, 教室5:20 |
| student_records | 1,663 | |
| integrated_notes | 1,540 | |
| individual_support_plans | 32 | |
| support_plan_details | 691 | |
| monitoring_records | 42 | |
| monitoring_details | 208 | |
| kakehashi_periods | 296 | |
| kakehashi_staff | 258 | |
| kakehashi_guardian | 260 | |
| activity_support_plans | 270 | ※別スクリプトで移行 |
| weekly_plans | 143 | |
| newsletters | 7 | |
| events | 36 | |
| holidays | 172 | |
| meeting_requests | 31 | |
| work_diaries | 50 | |
| absence_notifications | 71 | |
| student_interviews | 10 | |
| additional_usages | 15 | |
| classroom_capacity | 35 | |
| classroom_tags | 14 | |
| school_holiday_activities | 19 | |
| facility_evaluation_periods | 4 | |
| facility_guardian_evaluations | 30 | |
| facility_staff_evaluations | 10 | |
| submission_requests | 3 | |
| student_submissions | 1 | |
| newsletter_settings | 1 | |
| daily_routines | 23 | |

---

## 完全移行手順 (本番デプロイ時)

```bash
# 1. Docker起動
docker compose up -d

# 2. Laravelマイグレーション実行 (テーブル作成)
docker compose exec backend php artisan migrate --force

# 3. メインデータ移行
python migrate_mysql_to_pg.py

# 4. 支援案データ移行
python migrate_support_plans.py

# 5. 移行後修正SQL実行
docker compose exec postgres psql -U kiduri -d kiduri -f /path/to/post_migration_fixes.sql

# 6. データ検証
docker compose exec postgres psql -U kiduri -d kiduri -c "
SELECT
  (SELECT count(*) FROM users) as users,
  (SELECT count(*) FROM students) as students,
  (SELECT count(*) FROM daily_records) as daily_records,
  (SELECT count(*) FROM activity_support_plans) as support_plans,
  (SELECT count(*) FROM chat_messages) as chat_messages;
"
```

---

## 接続情報

- **PostgreSQL**: host=postgres (Docker内) / localhost:5432 (ホスト)
- **DB名**: kiduri
- **ユーザー**: kiduri
- **パスワード**: kiduri_secret
