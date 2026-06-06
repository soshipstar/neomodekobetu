#!/usr/bin/env python3
"""
旧 MySQL バックアップ (backup_production.sql) を「Sample 放デイ」教室に取り込む。

設計:
- 既存データを壊さない (TRUNCATE しない)
- 全ての classroom_id を target_classroom_id (デフォルト 4 = Sample 放デイ) に書き換え
- 全ての PK / FK を ID_OFFSET (デフォルト 100000) でシフトして既存と衝突しない
- 互換性のないテーブル (kakehashi_* 等) はスキップ
- 出力は PostgreSQL の INSERT 文の SQL ファイル

使い方:
  python import_legacy_to_sample.py \
    --input  backup_production.sql \
    --output /tmp/sample_legacy.sql \
    --classroom-id 4 --id-offset 100000
"""

import argparse
import re
import sys
from typing import Dict, List, Optional

# ----------------------------------------------------------------------
# Mapping: MySQL table -> PG table (Laravel)
# ----------------------------------------------------------------------
TABLE_MAP: Dict[str, str] = {
    'classrooms': '_SKIP_',  # Sample 放デイは既に存在
    'users': '_SKIP_',  # ユーザーは別途、既存ユーザーと衝突するのでスキップ
    'students': 'students',
    'absence_notifications': 'absence_notifications',
    'chat_messages': 'chat_messages',
    'chat_rooms': 'chat_rooms',
    'daily_records': 'daily_records',
    'student_records': 'student_records',
    'integrated_notes': 'integrated_notes',
    'events': 'events',
    'event_registrations': 'event_registrations',
    'holidays': 'holidays',
    'newsletters': 'newsletters',
    'send_history': '_SKIP_',  # 送信履歴は不要
    'support_plans': '_SKIP_',  # 旧 support_plans は activity_support_plans に対応するが構造差大
    'individual_support_plans': 'individual_support_plans',
    'individual_support_plan_details': 'support_plan_details',
    'monitoring_records': 'monitoring_records',
    'monitoring_details': 'monitoring_details',
    'submission_requests': 'submission_requests',
    'student_submissions': 'student_submissions',
    'weekly_plans': 'weekly_plans',
    'weekly_plan_submissions': 'weekly_plan_submissions',
    'weekly_plan_comments': 'weekly_plan_comments',
    'student_chat_rooms': 'student_chat_rooms',
    'student_chat_messages': 'student_chat_messages',
    # kakehashi_* は新 schema 構造が大きく異なるためスキップ
    'kakehashi_periods': '_SKIP_',
    'kakehashi_staff': '_SKIP_',
    'kakehashi_guardian': '_SKIP_',
}

# 各テーブルでスキップすべき MySQL 由来のカラム (新 schema にないもの)
DROP_COLUMNS: Dict[str, List[str]] = {
    'students': [
        'last_login_at',  # PG にも存在するが nullable / timezone 差で除外
        'password_plain',
        'password_hash',
        'username',  # ユーザー衝突を避けるためスキップ
    ],
    'daily_records': [
        'updated_at',  # PG 側は updated_at 列がない / Eloquent timestamp で自動
    ],
    'chat_messages': [
        'is_quick',  # 旧フラグ
    ],
}

# ID をオフセットすべきカラム (FK)
ID_COLUMNS: Dict[str, List[str]] = {
    'students': ['id', 'guardian_id'],
    'absence_notifications': ['id', 'message_id', 'student_id', 'makeup_approved_by'],
    'chat_messages': ['id', 'room_id', 'sender_id', 'reply_to_message_id'],
    'chat_rooms': ['id', 'student_id', 'guardian_id', 'created_by'],
    'daily_records': ['id', 'staff_id', 'support_plan_id'],
    'student_records': ['id', 'daily_record_id', 'student_id'],
    'integrated_notes': ['id', 'daily_record_id', 'student_id', 'integrated_by', 'sent_by', 'confirmed_by'],
    'events': ['id', 'created_by'],
    'event_registrations': ['id', 'event_id', 'student_id', 'guardian_id'],
    'newsletters': ['id', 'created_by'],
    'individual_support_plans': ['id', 'student_id', 'created_by', 'source_monitoring_id'],
    'support_plan_details': ['id', 'plan_id'],
    'monitoring_records': ['id', 'plan_id', 'student_id', 'created_by'],
    'monitoring_details': ['id', 'monitoring_record_id', 'plan_detail_id'],
    'submission_requests': ['id', 'created_by'],
    'student_submissions': ['id', 'request_id', 'student_id'],
    'weekly_plans': ['id', 'student_id', 'created_by'],
    'weekly_plan_submissions': ['id', 'weekly_plan_id'],
    'weekly_plan_comments': ['id', 'weekly_plan_id', 'commenter_id'],
    'student_chat_rooms': ['id', 'student_id'],
    'student_chat_messages': ['id', 'room_id', 'sender_id'],
    'holidays': ['id'],
}

# classroom_id を持つテーブル (target_classroom_id に書き換える)
HAS_CLASSROOM_ID: List[str] = [
    'students', 'chat_messages', 'daily_records',
    'events', 'newsletters', 'holidays', 'individual_support_plans',
    'monitoring_records', 'submission_requests', 'weekly_plans',
    'student_chat_rooms',
]

# guardian_id / staff_id を NULL にすべきテーブル (旧 users.id への参照を断つ)
NULLIFY_USER_FKS: Dict[str, List[str]] = {
    'students': ['guardian_id'],
    'chat_messages': ['sender_id'],
    'chat_rooms': ['guardian_id', 'created_by'],
    'daily_records': ['staff_id'],
    'integrated_notes': ['integrated_by', 'sent_by', 'confirmed_by'],
    'events': ['created_by'],
    'newsletters': ['created_by'],
    'individual_support_plans': ['created_by'],
    'monitoring_records': ['created_by'],
    'submission_requests': ['created_by'],
    'weekly_plans': ['created_by'],
    'weekly_plan_comments': ['commenter_id'],
    'absence_notifications': ['makeup_approved_by'],
}


def parse_insert(line: str) -> Optional[tuple]:
    """ INSERT INTO `table` (col1, col2, ...) VALUES (v1, v2, ...), (...); 形式を解析 """
    m = re.match(r'^INSERT INTO `([^`]+)`\s*\(([^)]+)\)\s*VALUES\s*(.+);?\s*$', line.strip(), re.DOTALL)
    if not m:
        return None
    table = m.group(1)
    cols = [c.strip().strip('`') for c in m.group(2).split(',')]
    values_str = m.group(3).rstrip(';').strip()
    return table, cols, values_str


def split_value_tuples(values_str: str) -> List[str]:
    """ '(v1, v2, ...),(v1, v2, ...)' を tuple ごとに分割 """
    tuples = []
    depth = 0
    in_str = False
    escape = False
    start = 0
    for i, c in enumerate(values_str):
        if escape:
            escape = False
            continue
        if c == '\\':
            escape = True
            continue
        if c == "'" and not escape:
            in_str = not in_str
            continue
        if in_str:
            continue
        if c == '(':
            if depth == 0:
                start = i + 1
            depth += 1
        elif c == ')':
            depth -= 1
            if depth == 0:
                tuples.append(values_str[start:i])
    return tuples


def split_row_values(row: str) -> List[str]:
    """ 1 つの tuple "'a', NULL, 'b\\'s'" を field 単位に分割 """
    fields = []
    in_str = False
    escape = False
    cur = []
    for c in row:
        if escape:
            cur.append(c)
            escape = False
            continue
        if c == '\\':
            cur.append(c)
            escape = True
            continue
        if c == "'":
            in_str = not in_str
            cur.append(c)
            continue
        if c == ',' and not in_str:
            fields.append(''.join(cur).strip())
            cur = []
            continue
        cur.append(c)
    if cur:
        fields.append(''.join(cur).strip())
    return fields


def quote_pg(val: str) -> str:
    """ MySQL → PostgreSQL 値の正規化 """
    v = val.strip()
    if v.upper() == 'NULL':
        return 'NULL'
    # MySQL の bool は 0/1。PG は true/false。ここでは judge せず数値のまま渡す (列が boolean cast されていれば PG が解釈)
    return v


def main():
    p = argparse.ArgumentParser()
    p.add_argument('--input', required=True)
    p.add_argument('--output', required=True)
    p.add_argument('--classroom-id', type=int, default=4, help='Target classroom_id (Sample 放デイ)')
    p.add_argument('--id-offset', type=int, default=100000, help='ID offset to avoid conflicts')
    p.add_argument('--source-classroom-ids', default='1,2,3',
                   help='Source classroom IDs to include (comma-separated)')
    args = p.parse_args()

    src_classrooms = set(int(x) for x in args.source_classroom_ids.split(','))
    target_classroom = args.classroom_id
    offset = args.id_offset

    with open(args.input, 'r', encoding='utf-8') as f:
        content = f.read()

    # INSERT 文を抽出
    insert_pattern = re.compile(
        r'^INSERT INTO `([^`]+)`\s*\(([^)]+)\)\s*VALUES\s*(.+?);\s*$',
        re.MULTILINE | re.DOTALL
    )

    out_lines = [
        '-- ===== 旧本番 → Sample 放デイ への取り込み SQL =====',
        f'-- target classroom_id={target_classroom}, id offset=+{offset}',
        f'-- source classrooms: {sorted(src_classrooms)}',
        '-- 既存データを壊さないように TRUNCATE せず INSERT のみ',
        'SET session_replication_role = \'replica\';',
        '',
    ]

    stats = {}

    for m in insert_pattern.finditer(content):
        table = m.group(1)
        cols_str = m.group(2)
        values_str = m.group(3).strip()

        if table not in TABLE_MAP:
            stats.setdefault(f'UNKNOWN:{table}', 0)
            stats[f'UNKNOWN:{table}'] += 1
            continue
        pg_table = TABLE_MAP[table]
        if pg_table == '_SKIP_':
            continue

        cols = [c.strip().strip('`') for c in cols_str.split(',')]

        # drop columns
        drop_set = set(DROP_COLUMNS.get(pg_table, []))
        keep_indices = [i for i, c in enumerate(cols) if c not in drop_set]
        kept_cols = [cols[i] for i in keep_indices]

        id_cols = set(ID_COLUMNS.get(pg_table, []))
        nullify_cols = set(NULLIFY_USER_FKS.get(pg_table, []))
        has_classroom = pg_table in HAS_CLASSROOM_ID

        tuples = split_value_tuples(values_str)
        for raw in tuples:
            fields = split_row_values(raw)
            if len(fields) != len(cols):
                continue

            # classroom_id 確認 + remap
            if has_classroom and 'classroom_id' in cols:
                idx = cols.index('classroom_id')
                cid_val = fields[idx].strip()
                if cid_val.upper() == 'NULL':
                    continue
                try:
                    cid_int = int(cid_val)
                except ValueError:
                    continue
                if cid_int not in src_classrooms:
                    continue
                fields[idx] = str(target_classroom)

            # ID オフセット
            for col in id_cols:
                if col not in cols:
                    continue
                idx = cols.index(col)
                val = fields[idx].strip()
                if val.upper() == 'NULL':
                    continue
                try:
                    fields[idx] = str(int(val) + offset)
                except ValueError:
                    pass

            # User FK は NULL に
            for col in nullify_cols:
                if col not in cols:
                    continue
                idx = cols.index(col)
                fields[idx] = 'NULL'

            # drop columns を除く
            kept_fields = [fields[i] for i in keep_indices]
            # PG 値に正規化
            kept_fields = [quote_pg(v) for v in kept_fields]

            cols_sql = ', '.join(f'"{c}"' for c in kept_cols)
            vals_sql = ', '.join(kept_fields)
            out_lines.append(f'INSERT INTO "{pg_table}" ({cols_sql}) VALUES ({vals_sql}) ON CONFLICT (id) DO NOTHING;')
            stats[pg_table] = stats.get(pg_table, 0) + 1

    out_lines.append('')
    out_lines.append('SET session_replication_role = \'origin\';')
    out_lines.append('')

    # シーケンス更新 (各テーブルの id 最大値 + 1 に)
    out_lines.append('-- Sequences')
    for pg_table in sorted(set(TABLE_MAP.values()) - {'_SKIP_'}):
        out_lines.append(
            f"SELECT setval(pg_get_serial_sequence('{pg_table}', 'id'), "
            f"COALESCE((SELECT MAX(id) FROM \"{pg_table}\"), 1), true);"
        )

    with open(args.output, 'w', encoding='utf-8') as f:
        f.write('\n'.join(out_lines))

    print(f'Written: {args.output}', file=sys.stderr)
    print('Stats:', file=sys.stderr)
    for k in sorted(stats.keys()):
        print(f'  {k}: {stats[k]}', file=sys.stderr)


if __name__ == '__main__':
    main()
