# Data Migration Checklist (MySQL -> PostgreSQL)

Date: 2026-03-18
Script: `convert_mysql_to_pg.py`

---

## Pre-Migration Steps

- [ ] **Backup current PG database** (even if empty, confirm migrations table is intact)
  ```bash
  ssh kiduri
  docker exec kiduri-postgres pg_dump -U kiduri kiduri > /tmp/pg_backup_$(date +%Y%m%d_%H%M).sql
  ```

- [ ] **Dump latest MySQL data from heteml**
  ```bash
  ssh heteml
  mysqldump -h mysql320.phy.heteml.lan -u USERNAME -p _kobetudb --no-create-info --complete-insert --skip-triggers --skip-lock-tables > /tmp/latest_production_data.sql
  ```

- [ ] **Copy dump file to local machine or ConoHa VPS**
  ```bash
  scp heteml:/tmp/latest_production_data.sql /tmp/latest_production_data.sql
  ```

- [ ] **Stop application services** (optional but recommended to avoid partial state)
  ```bash
  docker compose -f docker-compose.prod.yml stop frontend backend
  ```

- [ ] **Verify all PG migrations have run**
  ```bash
  docker exec kiduri-backend php artisan migrate:status
  ```
  Should show all 55 migrations as "Ran". Key migrations to verify:
  - `2026_03_11_100000_add_missing_legacy_columns` (adds domain columns, unique constraints, send_history)
  - `2026_03_17_000001_add_student_id_to_weekly_plans` (adds student_id + legacy columns)
  - `2026_03_18_000001_add_missing_columns_to_individual_support_plans_table`
  - `2026_03_18_000004_add_classroom_id_to_facility_evaluation_periods_table`

---

## Migration Command

### Default mode (exclude narZE / classroom_id=2):
```bash
python convert_mysql_to_pg.py --input /tmp/latest_production_data.sql --output /tmp/pg_production_data.sql
```

### Full mode (ALL classrooms including narZE):
```bash
python convert_mysql_to_pg.py --input /tmp/latest_production_data.sql --output /tmp/pg_production_data.sql --full
```

### Apply the converted SQL:
```bash
docker exec -i kiduri-postgres psql -U kiduri kiduri < /tmp/pg_production_data.sql
```

---

## Post-Migration Verification Queries

Run inside `docker exec -it kiduri-postgres psql -U kiduri kiduri`:

### Basic Record Counts

```sql
-- Core tables
SELECT 'classrooms' AS tbl, COUNT(*) FROM classrooms
UNION ALL SELECT 'users', COUNT(*) FROM users
UNION ALL SELECT 'students', COUNT(*) FROM students
UNION ALL SELECT 'daily_records', COUNT(*) FROM daily_records
UNION ALL SELECT 'student_records', COUNT(*) FROM student_records
UNION ALL SELECT 'integrated_notes', COUNT(*) FROM integrated_notes
UNION ALL SELECT 'individual_support_plans', COUNT(*) FROM individual_support_plans
UNION ALL SELECT 'support_plan_details', COUNT(*) FROM support_plan_details
UNION ALL SELECT 'monitoring_records', COUNT(*) FROM monitoring_records
UNION ALL SELECT 'monitoring_details', COUNT(*) FROM monitoring_details
UNION ALL SELECT 'kakehashi_periods', COUNT(*) FROM kakehashi_periods
UNION ALL SELECT 'kakehashi_staff', COUNT(*) FROM kakehashi_staff
UNION ALL SELECT 'kakehashi_guardian', COUNT(*) FROM kakehashi_guardian
UNION ALL SELECT 'chat_rooms', COUNT(*) FROM chat_rooms
UNION ALL SELECT 'chat_messages', COUNT(*) FROM chat_messages
UNION ALL SELECT 'weekly_plans', COUNT(*) FROM weekly_plans
UNION ALL SELECT 'events', COUNT(*) FROM events
UNION ALL SELECT 'holidays', COUNT(*) FROM holidays
UNION ALL SELECT 'newsletters', COUNT(*) FROM newsletters
UNION ALL SELECT 'meeting_requests', COUNT(*) FROM meeting_requests
UNION ALL SELECT 'absence_notifications', COUNT(*) FROM absence_notifications
UNION ALL SELECT 'student_interviews', COUNT(*) FROM student_interviews
UNION ALL SELECT 'activity_support_plans', COUNT(*) FROM activity_support_plans
UNION ALL SELECT 'work_diaries', COUNT(*) FROM work_diaries
UNION ALL SELECT 'activity_types', COUNT(*) FROM activity_types
ORDER BY 1;
```

### D-001: No literal \r\n in text fields
```sql
SELECT COUNT(*) AS bad_newlines FROM student_records
WHERE notes LIKE '%\r\n%' OR health_life LIKE '%\r\n%';
-- Expected: 0
```

### D-002: No domain key names as values in student_records
```sql
SELECT COUNT(*) AS domain_key_contamination FROM student_records
WHERE health_life IN ('health_life','motor_sensory','cognitive_behavior','language_communication','social_relations')
   OR motor_sensory IN ('health_life','motor_sensory','cognitive_behavior','language_communication','social_relations')
   OR cognitive_behavior IN ('health_life','motor_sensory','cognitive_behavior','language_communication','social_relations')
   OR language_communication IN ('health_life','motor_sensory','cognitive_behavior','language_communication','social_relations')
   OR social_relations IN ('health_life','motor_sensory','cognitive_behavior','language_communication','social_relations');
-- Expected: 0
```

### D-003: daily_records.classroom_id derived from staff
```sql
SELECT COUNT(*) AS missing_classroom FROM daily_records WHERE classroom_id IS NULL;
-- Expected: 0 (POST_MIGRATION_SQL should have filled these)
```

### D-006: student_records domain columns populated
```sql
SELECT COUNT(*) AS has_domain_data FROM student_records
WHERE health_life IS NOT NULL
   OR motor_sensory IS NOT NULL
   OR cognitive_behavior IS NOT NULL
   OR language_communication IS NOT NULL
   OR social_relations IS NOT NULL;
-- Expected: > 0 (should have data from domain1/domain2 routing)
```

### D-008: No duplicate config keys (verified at script level - no runtime check needed)

### D-009: individual_support_plans.status values
```sql
SELECT status, COUNT(*) FROM individual_support_plans GROUP BY status;
-- Expected: only 'draft' and 'published' (no '0' or '1')
```

### S-004: chat_messages.message_type values
```sql
SELECT message_type, COUNT(*) FROM chat_messages GROUP BY message_type;
-- Expected: 'text' (no 'normal' or 'absence_notification')
```

### Boolean conversions
```sql
SELECT DISTINCT is_active FROM users;
-- Expected: true/false (not 0/1)
```

### Sequence integrity
```sql
-- Check that sequences are properly set (no ID conflicts on next insert)
SELECT 'users' AS tbl, MAX(id) AS max_id, currval('users_id_seq') AS seq_val FROM users
UNION ALL SELECT 'students', MAX(id), currval('students_id_seq') FROM students
UNION ALL SELECT 'daily_records', MAX(id), currval('daily_records_id_seq') FROM daily_records;
-- seq_val should >= max_id for each table
```

### meeting_requests.candidate_dates JSONB
```sql
SELECT COUNT(*) AS has_dates FROM meeting_requests WHERE candidate_dates IS NOT NULL;
SELECT candidate_dates FROM meeting_requests WHERE candidate_dates IS NOT NULL LIMIT 3;
-- Should be JSON arrays like '["2026-01-15","2026-01-16"]'
```

### weekly_plans columns preserved
```sql
SELECT COUNT(*) AS has_student FROM weekly_plans WHERE student_id IS NOT NULL;
SELECT COUNT(*) AS has_created_by FROM weekly_plans WHERE created_by IS NOT NULL;
```

### narZE protection (selective mode only)
```sql
SELECT classroom_id, COUNT(*) FROM students GROUP BY classroom_id ORDER BY 1;
-- classroom_id=2 should have 0 rows (in selective mode)
```

---

## Known Issues / Warnings

### ISSUE: `students.last_login` conflict
The column `last_login` is in both `STRIP_COLUMNS` and `RENAME_COLUMNS` for `students`.
STRIP takes precedence, so `last_login_at` in PG will always be NULL after migration.
**Impact**: Low - `last_login_at` in PG will be populated by new login events going forward.

### Tables NOT migrated (new PG-only tables, no legacy data):
- `notifications` - new notification system
- `audit_logs` - new audit logging
- `login_attempts` - new security feature
- `vector_embeddings` - AI/pgvector feature
- `ai_generation_logs` - AI feature
- `personal_access_tokens` - Laravel Sanctum
- `facility_evaluations` - parent table (legacy used period-based tables directly)
- `facility_self_evaluation_summary` - in SKIP_TABLES
- `meeting_notes` - exists in PG schema but legacy data in SKIP_TABLES
- `staff_chat_rooms/members/messages/reads` - in SKIP_TABLES (new PG feature)
- `error_logs` - new table
- `announcements/announcement_targets/announcement_reads` - new tables

### Tables in SKIP_TABLES (exist in MySQL but not migrated):
- `push_subscriptions` - not in PG schema
- `staff_chat_members/messages/reads/rooms` - rebuilt in PG
- `facility_self_evaluation_summary` - skipped
- `meeting_notes` - skipped
- `training_plans` / `training_records` - not in PG schema

---

## Post-Migration Steps

- [ ] **Restart application services**
  ```bash
  docker compose -f docker-compose.prod.yml up -d frontend backend
  ```

- [ ] **Re-create storage symlink** (if containers were recreated)
  ```bash
  docker exec kiduri-backend php artisan storage:link
  ```

- [ ] **Smoke test the application**
  - Login as staff user
  - View student list
  - Open a daily record
  - Check a kakehashi record
  - Verify chat messages display

- [ ] **Compare record counts with legacy MySQL**
  ```sql
  -- Run on MySQL (heteml) and compare with PG counts above
  SELECT 'users' AS tbl, COUNT(*) FROM users
  UNION ALL SELECT 'students', COUNT(*) FROM students
  UNION ALL SELECT 'daily_records', COUNT(*) FROM daily_records;
  ```
  Note: PG counts will be lower in selective mode (narZE/classroom_id=2 excluded).
