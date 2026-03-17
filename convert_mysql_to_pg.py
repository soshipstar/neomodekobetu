#!/usr/bin/env python3
"""
Convert MySQL dump (data-only) INSERT statements to PostgreSQL-compatible format.
Supports classroom-level selective sync to allow phased migration.

Usage:
  # Full sync (all classrooms) - DANGEROUS: overwrites narZE data
  python convert_mysql_to_pg.py --full

  # Sync only specified classrooms (default: all except narZE/classroom 2)
  python convert_mysql_to_pg.py
  python convert_mysql_to_pg.py --sync-classrooms 1,3,4,5

  # Custom input/output files
  python convert_mysql_to_pg.py --input dump.sql --output pg_data.sql

Protected classroom (narZE, id=2) is never overwritten unless --full is used.

Handles:
  - Table name mapping (MySQL -> Laravel PG)
  - Column stripping (MySQL columns not in PG schema)
  - Boolean conversion (MySQL 0/1 -> PG true/false)
  - MySQL syntax cleanup (backticks, comments, LOCK/UNLOCK)
  - NULL constraint fixes
  - Sequence reset
  - Classroom-level selective sync
"""
import re
import sys
import os
import argparse

# Table name mapping: MySQL -> PostgreSQL (Laravel)
TABLE_MAP = {
    'absence_notifications': 'absence_notifications',
    'activity_types': 'activity_types',
    'additional_usages': 'additional_usages',
    'chat_message_staff_reads': 'chat_message_staff_reads',
    'chat_messages': 'chat_messages',
    'chat_room_pins': 'chat_room_pins',
    'chat_rooms': 'chat_rooms',
    'classroom_capacity': 'classroom_capacity',
    'classroom_tags': 'classroom_tags',
    'classrooms': 'classrooms',
    'daily_records': 'daily_records',
    'daily_routines': 'daily_routines',
    'event_registrations': 'event_registrations',
    'events': 'events',
    'facility_evaluation_periods': 'facility_evaluation_periods',
    'facility_evaluation_questions': 'facility_evaluation_questions',
    'facility_evaluation_summaries': 'facility_evaluation_summaries',
    'facility_guardian_evaluation_answers': 'facility_guardian_evaluation_answers',
    'facility_guardian_evaluations': 'facility_guardian_evaluations',
    'facility_staff_evaluation_answers': 'facility_staff_evaluation_answers',
    'facility_staff_evaluations': 'facility_staff_evaluations',
    'holidays': 'holidays',
    'individual_support_plan_details': 'support_plan_details',
    'individual_support_plans': 'individual_support_plans',
    'integrated_notes': 'integrated_notes',
    'kakehashi_guardian': 'kakehashi_guardian',
    'kakehashi_periods': 'kakehashi_periods',
    'kakehashi_staff': 'kakehashi_staff',
    'meeting_requests': 'meeting_requests',
    'monitoring_details': 'monitoring_details',
    'monitoring_records': 'monitoring_records',
    'newsletter_settings': 'newsletter_settings',
    'newsletters': 'newsletters',
    'school_holiday_activities': 'school_holiday_activities',
    'send_history': 'send_history',
    'student_chat_messages': 'student_chat_messages',
    'student_chat_rooms': 'student_chat_rooms',
    'student_interviews': 'student_interviews',
    'student_records': 'student_records',
    'student_submissions': 'student_submissions',
    'students': 'students',
    'submission_requests': 'submission_requests',
    'support_plans': 'activity_support_plans',
    'users': 'users',
    'weekly_plan_comments': 'weekly_plan_comments',
    'weekly_plan_submissions': 'weekly_plan_submissions',
    'weekly_plans': 'weekly_plans',
    'work_diaries': 'work_diaries',
}

# Tables to skip (not in PG schema)
SKIP_TABLES = {
    'push_subscriptions',
    'staff_chat_members',
    'staff_chat_messages',
    'staff_chat_reads',
    'staff_chat_rooms',
    'facility_self_evaluation_summary',
    'meeting_notes',
    'training_plans',
    'training_records',
}

# Value mappings: { table: { column: { old_value: new_value } } }
VALUE_MAPPINGS = {
    'chat_messages': {
        'message_type': {
            'normal': 'text',
            'absence_notification': 'text',
            'event_registration': 'text',
        },
    },
}

# Columns that exist in MySQL but NOT in PG (will be stripped from INSERT)
STRIP_COLUMNS = {
    'users': ['remember_token'],
    'chat_messages': ['meeting_request_id', 'attachment_path', 'attachment_original_name', 'attachment_size', 'attachment_type'],
    'students': ['hide_initial_monitoring_by', 'hide_initial_monitoring_at'],
    'monitoring_records': ['hidden_by', 'hidden_at'],
    'individual_support_plans': ['hidden_by', 'hidden_at', 'source_period_id', 'target_period_start', 'target_period_end', 'plan_number'],
    'kakehashi_guardian': ['hidden_by', 'hidden_at'],
    'kakehashi_staff': ['hidden_by', 'hidden_at'],
    'classrooms': ['service_type', 'target_grades'],
    'event_registrations': ['registered_at', 'notes'],
    'facility_evaluation_periods': ['guardian_eval_start_date', 'guardian_eval_end_date', 'staff_eval_start_date', 'staff_eval_end_date', 'self_eval_created_date', 'publish_date'],
    'individual_support_plan_details': ['row_order'],
    'submission_requests': ['attachment_path', 'attachment_original_name', 'attachment_size'],
    'meeting_requests': ['guardian_counter_date1', 'guardian_counter_date2', 'guardian_counter_date3', 'staff_counter_date1', 'staff_counter_date2', 'staff_counter_date3'],
    'weekly_plans': ['evaluated_by_type', 'evaluated_by_id', 'created_by_type'],
    'monitoring_records': ['hidden_by', 'hidden_at', 'guardian_signature_image', 'guardian_signature_date', 'staff_signature_image', 'staff_signature_date'],
    'newsletters': ['report_start_date', 'report_end_date', 'schedule_start_date', 'schedule_end_date'],
    'weekly_plan_submissions': ['completed_by_type', 'completed_by_id'],
    'weekly_plan_comments': ['commenter_type'],
    'student_chat_messages': ['attachment_path', 'attachment_original_name', 'attachment_size'],
    'students': ['hide_initial_monitoring_by', 'hide_initial_monitoring_at', 'last_login'],
    'newsletter_settings': ['show_facility_name', 'show_logo', 'show_greeting', 'show_event_calendar', 'show_event_details', 'show_weekly_reports', 'show_weekly_intro', 'show_event_results', 'show_requests', 'show_others', 'show_elementary_report', 'show_junior_report', 'show_custom_section', 'calendar_format', 'default_requests', 'default_others', 'greeting_instructions', 'event_details_instructions', 'weekly_reports_instructions', 'weekly_intro_instructions', 'event_results_instructions', 'elementary_report_instructions', 'junior_report_instructions', 'custom_section_title', 'custom_section_content'],
}

# Columns that are boolean in PG but integer in MySQL
BOOLEAN_COLUMNS = {
    'classrooms': [],
    'classroom_capacity': ['is_open'],
    'classroom_tags': ['is_active'],
    'daily_routines': ['is_active'],
    'users': ['is_master', 'is_active'],
    'students': ['is_active', 'hide_initial_monitoring',
                 'desired_monday', 'desired_tuesday', 'desired_wednesday',
                 'desired_thursday', 'desired_friday', 'desired_saturday', 'desired_sunday',
                 'scheduled_monday', 'scheduled_tuesday', 'scheduled_wednesday',
                 'scheduled_thursday', 'scheduled_friday', 'scheduled_saturday', 'scheduled_sunday'],
    'chat_messages': ['is_read', 'is_deleted'],
    'individual_support_plans': ['is_draft', 'guardian_confirmed', 'is_hidden', 'is_official'],
    'support_plan_details': [],
    'monitoring_records': ['is_draft', 'guardian_confirmed', 'is_hidden', 'is_official'],
    'monitoring_details': [],
    'meeting_requests': ['is_completed'],
    'kakehashi_periods': ['is_active', 'is_auto_generated'],
    'kakehashi_guardian': ['is_submitted', 'is_hidden'],
    'kakehashi_staff': ['is_submitted', 'is_hidden', 'guardian_confirmed'],
    'integrated_notes': ['is_sent', 'guardian_confirmed'],
    'newsletters': [],
    'newsletter_settings': ['show_facility_name', 'show_logo', 'show_greeting',
                           'show_event_calendar', 'show_event_details',
                           'show_weekly_reports', 'show_weekly_intro',
                           'show_event_results', 'show_requests', 'show_others',
                           'show_elementary_report', 'show_junior_report', 'show_custom_section'],
    'facility_evaluation_questions': ['is_active'],
    'facility_guardian_evaluations': ['is_submitted'],
    'facility_staff_evaluations': ['is_submitted'],
    'student_submissions': ['is_completed'],
    'submission_requests': ['is_completed'],
    'student_chat_messages': ['is_deleted', 'is_read'],
    'student_interviews': ['check_school', 'check_home', 'check_troubles'],
    'weekly_plan_submissions': ['is_completed'],
}

# Column name mapping: MySQL column -> PG column (per table)
RENAME_COLUMNS = {
    'kakehashi_staff': {
        'domain_health_life': 'health_life',
        'domain_motor_sensory': 'motor_sensory',
        'domain_cognitive_behavior': 'cognitive_behavior',
        'domain_language_communication': 'language_communication',
        'domain_social_relations': 'social_relations',
    },
    # kakehashi_guardian uses domain_* names in both MySQL and PG
    'individual_support_plans': {
        'long_term_goal_text': 'long_term_goal',
        'short_term_goal_text': 'short_term_goal',
        'is_draft': 'status',  # is_draft -> status mapping handled in value conversion
        'guardian_review_comment': 'guardian_review_comment',
    },
    'individual_support_plan_details': {
        'category': 'domain',
        'support_goal': 'goal',
    },
    'monitoring_details': {
        'achievement_status': 'achievement_level',
        'monitoring_comment': 'comment',
    },
    # monitoring_records has is_draft column (no rename needed)
    'student_records': {
        'daily_note': 'notes',
        'domain1': 'health_life',
        'domain1_content': 'motor_sensory',
        'domain2': 'cognitive_behavior',
        'domain2_content': 'language_communication',
    },
    'students': {
        'last_login': 'last_login_at',
    },
    'weekly_plan_comments': {
        'weekly_plan_id': 'plan_id',
        'commenter_id': 'user_id',
    },
    'weekly_plans': {
        # student_id is now kept as-is (was incorrectly mapped to classroom_id)
        'created_by_id': 'created_by',
    },
    'student_chat_rooms': {
        'updated_at': None,
    },
    'student_interviews': {
        'check_school_note': 'check_school_notes',
        'check_home_note': 'check_home_notes',
        'check_troubles_note': 'check_troubles_notes',
    },
    'newsletter_settings': {
        'created_at': None,
    },
}


# Column transformations: combine multiple MySQL columns into a single PG column.
# Format: { mysql_table: { pg_column_name: [source_col1, source_col2, ...] } }
# Source columns are read, non-NULL values are collected into a JSON array,
# and the result is inserted as the pg_column_name value.
# Source columns are automatically added to STRIP_COLUMNS.
TRANSFORM_COLUMNS = {
    'meeting_requests': {
        'candidate_dates': ['candidate_date1', 'candidate_date2', 'candidate_date3'],
    },
}

# Domain key names that should never appear as actual values in student_records
DOMAIN_KEY_NAMES = {'health_life', 'motor_sensory', 'cognitive_behavior', 'language_communication', 'social_relations'}

# Post-migration SQL to fix data integrity issues
POST_MIGRATION_SQL = [
    # D-003: Derive classroom_id for daily_records from staff's classroom
    'UPDATE daily_records dr SET classroom_id = u.classroom_id FROM users u WHERE u.id = dr.staff_id AND dr.classroom_id IS NULL;',
]

# Auto-strip transform source columns
for _tbl, _transforms in TRANSFORM_COLUMNS.items():
    for _pg_col, _src_cols in _transforms.items():
        if _tbl not in STRIP_COLUMNS:
            STRIP_COLUMNS[_tbl] = []
        STRIP_COLUMNS[_tbl].extend(_src_cols)


def sanitize_text_value(v):
    """Sanitize a quoted string value: fix literal \\r\\n sequences."""
    if v.startswith("'") and v.endswith("'"):
        inner = v[1:-1]
        # Replace literal \r\n (4 chars: backslash r backslash n) with actual newline
        inner = inner.replace('\\r\\n', '\n')
        # Replace remaining literal \r with empty
        inner = inner.replace('\\r', '')
        v = "'" + inner + "'"
    return v


def sanitize_domain_value(v, col_name):
    """D-002: NULL out domain column values that are just domain key names."""
    if col_name in DOMAIN_KEY_NAMES and v.startswith("'") and v.endswith("'"):
        inner = v[1:-1]
        if inner in DOMAIN_KEY_NAMES:
            return 'NULL'
    return v


def parse_values(values_str):
    """Parse a comma-separated VALUES string respecting quoted strings and nested parens."""
    tuples = []
    i = 0
    while i < len(values_str):
        if values_str[i] == '(':
            depth = 1
            j = i + 1
            in_string = False
            escape_next = False
            while j < len(values_str) and depth > 0:
                ch = values_str[j]
                if escape_next:
                    escape_next = False
                elif ch == '\\':
                    escape_next = True
                elif ch == "'":
                    in_string = not in_string
                elif not in_string:
                    if ch == '(':
                        depth += 1
                    elif ch == ')':
                        depth -= 1
                j += 1
            tuples.append(values_str[i+1:j-1])
            i = j
        else:
            i += 1
    return tuples


def parse_tuple_values(tuple_str):
    """Parse individual values from a tuple string."""
    vals = []
    k = 0
    current = []
    in_str = False
    esc = False
    while k < len(tuple_str):
        ch = tuple_str[k]
        if esc:
            current.append(ch)
            esc = False
        elif ch == '\\':
            current.append(ch)
            esc = True
        elif ch == "'":
            current.append(ch)
            in_str = not in_str
        elif ch == ',' and not in_str:
            vals.append(''.join(current).strip())
            current = []
        else:
            current.append(ch)
        k += 1
    if current:
        vals.append(''.join(current).strip())
    return vals


def build_transform_value(vals, cols, transform_src_cols):
    """Build a JSONB array value from multiple source columns, filtering NULLs."""
    json_items = []
    for src_col in transform_src_cols:
        if src_col in cols:
            idx = cols.index(src_col)
            if idx < len(vals):
                v = vals[idx]
                # Only include non-NULL values
                if v != 'NULL' and v.upper() != 'NULL':
                    # Strip surrounding quotes if present
                    if v.startswith("'") and v.endswith("'"):
                        json_items.append('"' + v[1:-1] + '"')
                    else:
                        json_items.append('"' + v + '"')
    if not json_items:
        return 'NULL'
    return "'[" + ','.join(json_items) + "]'"


def convert_insert(insert_stmt, mysql_table, pg_table):
    """Convert a single INSERT statement from MySQL to PostgreSQL."""
    # Parse the INSERT INTO ... (...) VALUES ... structure
    match = re.match(r"INSERT INTO `(\w+)` \((.+?)\) VALUES\s*(.*)", insert_stmt, re.DOTALL)
    if not match:
        return None

    cols_str = match.group(2)
    values_part = match.group(3).rstrip(';').rstrip()

    cols = [c.strip().strip('`') for c in cols_str.split(',')]

    # Determine which columns to strip
    strip = set(STRIP_COLUMNS.get(mysql_table, []))
    bool_cols = set(BOOLEAN_COLUMNS.get(mysql_table, []))
    renames = RENAME_COLUMNS.get(mysql_table, {})
    transforms = TRANSFORM_COLUMNS.get(mysql_table, {})
    val_mappings = VALUE_MAPPINGS.get(mysql_table, {})

    # Columns renamed to None should also be stripped
    for old_col, new_col in renames.items():
        if new_col is None:
            strip.add(old_col)

    # Find indices to remove
    strip_indices = {i for i, c in enumerate(cols) if c in strip}
    bool_indices = {i for i, c in enumerate(cols) if c in bool_cols}
    val_map_indices = {i: val_mappings[c] for i, c in enumerate(cols) if c in val_mappings}

    # Build new column list with renames applied
    new_cols = []
    for i, c in enumerate(cols):
        if i in strip_indices:
            continue
        if c in renames and renames[c] is not None:
            new_cols.append(renames[c])
        else:
            new_cols.append(c)

    # Add transform target columns
    for pg_col in transforms:
        if pg_col not in new_cols:
            new_cols.append(pg_col)

    # Parse all value tuples
    tuples = parse_values(values_part)

    new_tuples = []
    for t in tuples:
        vals = parse_tuple_values(t)

        # Process each value
        new_vals = []
        for i, v in enumerate(vals):
            if i in strip_indices:
                continue
            # Convert boolean values
            if i in bool_indices:
                if v == '0':
                    v = 'false'
                elif v == '1':
                    v = 'true'
            # Special: is_draft -> status conversion
            col_name = new_cols[len(new_vals)] if len(new_vals) < len(new_cols) else None
            if col_name == 'status' and i < len(cols) and cols[i] == 'is_draft':
                if v == '1' or v == 'true':
                    v = "'draft'"
                else:
                    v = "'published'"
            # D-001: Sanitize text values (fix literal \r\n)
            v = sanitize_text_value(v)
            # D-002: NULL out domain key name contamination in student_records
            if mysql_table == 'student_records' and col_name:
                v = sanitize_domain_value(v, col_name)
            # S-004: Apply value mappings (e.g. message_type: normal→text)
            if i in val_map_indices:
                stripped = v.strip("'")
                if stripped in val_map_indices[i]:
                    v = f"'{val_map_indices[i][stripped]}'"
            new_vals.append(v)

        # Append transformed column values
        for pg_col, src_cols in transforms.items():
            transform_val = build_transform_value(vals, cols, src_cols)
            new_vals.append(transform_val)

        new_tuples.append('(' + ','.join(new_vals) + ')')

    # Build PostgreSQL INSERT
    pg_cols = ','.join(f'"{c}"' for c in new_cols)
    pg_values = ','.join(new_tuples)

    return f'INSERT INTO "{pg_table}" ({pg_cols}) VALUES {pg_values};'


# ============================================================
# Classroom-aware sync configuration
# ============================================================

# narZE classroom ID - protected by default
PROTECTED_CLASSROOM = 2

# Tables with direct classroom_id column
TABLES_WITH_CLASSROOM_ID = {
    'activity_support_plans', 'activity_types', 'classroom_capacity',
    'classroom_tags', 'daily_records', 'daily_routines', 'events',
    'holidays', 'individual_support_plans', 'meeting_requests',
    'monitoring_records', 'newsletter_settings', 'newsletters',
    'school_holiday_activities', 'student_interviews',
    'students', 'users', 'weekly_plans', 'work_diaries',
}

# Tables linked via student_id (need student list to filter)
TABLES_VIA_STUDENT = {
    'absence_notifications',  # student_id
    'additional_usages',      # student_id -> created_by(user)
    'chat_rooms',             # student_id
    'integrated_notes',       # student_id
    'kakehashi_periods',      # student_id
    'kakehashi_guardian',     # student_id
    'kakehashi_staff',        # student_id
    'student_chat_rooms',     # student_id
    'student_submissions',    # student_id
    'student_records',        # via daily_record_id -> daily_records
}

# Tables linked via other foreign keys
TABLES_VIA_FK = {
    'chat_messages': ('room_id', 'chat_rooms'),           # room_id -> chat_rooms
    'chat_message_staff_reads': ('message_id', 'chat_messages'),
    'chat_room_pins': ('room_id', 'chat_rooms'),
    'event_registrations': ('event_id', 'events'),
    'support_plan_details': ('plan_id', 'individual_support_plans'),
    'monitoring_details': ('monitoring_id', 'monitoring_records'),
    'send_history': ('integrated_note_id', 'integrated_notes'),
    'weekly_plan_comments': ('plan_id', 'weekly_plans'),
    'weekly_plan_submissions': ('weekly_plan_id', 'weekly_plans'),
    'student_chat_messages': ('room_id', 'student_chat_rooms'),
}

# Tables that are shared/global (sync with ON CONFLICT DO NOTHING)
SHARED_TABLES = {
    'classrooms',
    'facility_evaluation_periods', 'facility_evaluation_questions',
    'facility_evaluation_summaries', 'facility_guardian_evaluations',
    'facility_guardian_evaluation_answers', 'facility_staff_evaluations',
    'facility_staff_evaluation_answers',
}


def build_student_classroom_map(inserts):
    """Pre-scan the dump to build student_id -> classroom_id mapping."""
    student_map = {}
    for stmt in inserts:
        if not stmt.startswith("INSERT INTO `students`"):
            continue
        match = re.match(r"INSERT INTO `students` \((.+?)\) VALUES\s*(.*)", stmt, re.DOTALL)
        if not match:
            continue
        cols = [c.strip().strip('`') for c in match.group(1).split(',')]
        if 'id' not in cols or 'classroom_id' not in cols:
            continue
        id_idx = cols.index('id')
        cl_idx = cols.index('classroom_id')
        for t in parse_values(match.group(2).rstrip(';').rstrip()):
            vals = parse_tuple_values(t)
            try:
                sid = int(vals[id_idx])
                cid_val = vals[cl_idx]
                cid = int(cid_val) if cid_val != 'NULL' else None
                student_map[sid] = cid
            except (ValueError, IndexError):
                pass
    return student_map


def build_parent_id_map(inserts, parent_mysql_table, filter_ids, filter_col='student_id'):
    """Build set of IDs from a parent table that match filter_ids on filter_col."""
    matching_ids = set()
    for stmt in inserts:
        if not stmt.startswith(f"INSERT INTO `{parent_mysql_table}`"):
            continue
        match = re.match(r"INSERT INTO `\w+` \((.+?)\) VALUES\s*(.*)", stmt, re.DOTALL)
        if not match:
            continue
        cols = [c.strip().strip('`') for c in match.group(1).split(',')]
        if 'id' not in cols or filter_col not in cols:
            continue
        id_idx = cols.index('id')
        fk_idx = cols.index(filter_col)
        for t in parse_values(match.group(2).rstrip(';').rstrip()):
            vals = parse_tuple_values(t)
            try:
                row_id = int(vals[id_idx])
                fk_val = int(vals[fk_idx]) if vals[fk_idx] != 'NULL' else None
                if fk_val in filter_ids:
                    matching_ids.add(row_id)
            except (ValueError, IndexError):
                pass
    return matching_ids


def convert_insert_filtered(insert_stmt, mysql_table, pg_table, sync_classrooms,
                           mode='full', student_map=None, allowed_ids=None):
    """Convert INSERT with optional classroom filtering.

    mode:
      'full'      - no filtering, insert all rows
      'classroom'  - filter by classroom_id column
      'student'    - filter by student_id (using student_map)
      'shared'     - use ON CONFLICT DO NOTHING
      'by_ids'     - filter by id column (pre-computed allowed IDs)
    """
    match = re.match(r"INSERT INTO `(\w+)` \((.+?)\) VALUES\s*(.*)", insert_stmt, re.DOTALL)
    if not match:
        return None

    cols_str = match.group(2)
    values_part = match.group(3).rstrip(';').rstrip()
    cols = [c.strip().strip('`') for c in cols_str.split(',')]

    strip = set(STRIP_COLUMNS.get(mysql_table, []))
    bool_cols = set(BOOLEAN_COLUMNS.get(mysql_table, []))
    renames = RENAME_COLUMNS.get(mysql_table, {})
    transforms = TRANSFORM_COLUMNS.get(mysql_table, {})
    val_mappings = VALUE_MAPPINGS.get(mysql_table, {})

    for old_col, new_col in renames.items():
        if new_col is None:
            strip.add(old_col)

    strip_indices = {i for i, c in enumerate(cols) if c in strip}
    bool_indices = {i for i, c in enumerate(cols) if c in bool_cols}
    val_map_indices = {i: val_mappings[c] for i, c in enumerate(cols) if c in val_mappings}

    # Find filter column index
    filter_col_idx = None
    if mode == 'classroom' and 'classroom_id' in cols:
        filter_col_idx = cols.index('classroom_id')
    elif mode == 'student' and 'student_id' in cols:
        filter_col_idx = cols.index('student_id')
    elif mode == 'by_ids' and 'id' in cols:
        filter_col_idx = cols.index('id')

    new_cols = []
    for i, c in enumerate(cols):
        if i in strip_indices:
            continue
        if c in renames and renames[c] is not None:
            new_cols.append(renames[c])
        else:
            new_cols.append(c)

    # Add transform target columns
    for pg_col in transforms:
        if pg_col not in new_cols:
            new_cols.append(pg_col)

    tuples = parse_values(values_part)
    new_tuples = []

    for t in tuples:
        vals = parse_tuple_values(t)

        # Apply filter
        if filter_col_idx is not None and sync_classrooms is not None:
            try:
                filter_val = int(vals[filter_col_idx]) if vals[filter_col_idx] != 'NULL' else None
            except (ValueError, IndexError):
                filter_val = None

            if mode == 'classroom':
                if filter_val not in sync_classrooms:
                    continue  # Skip this row
            elif mode == 'student':
                if student_map and filter_val is not None:
                    student_classroom = student_map.get(filter_val)
                    if student_classroom not in sync_classrooms:
                        continue
                elif filter_val is None:
                    continue
            elif mode == 'by_ids':
                if allowed_ids is not None and filter_val not in allowed_ids:
                    continue

        new_vals = []
        for i, v in enumerate(vals):
            if i in strip_indices:
                continue
            if i in bool_indices:
                if v == '0':
                    v = 'false'
                elif v == '1':
                    v = 'true'
            col_name = new_cols[len(new_vals)] if len(new_vals) < len(new_cols) else None
            if col_name == 'status' and i < len(cols) and cols[i] == 'is_draft':
                if v == '1' or v == 'true':
                    v = "'draft'"
                else:
                    v = "'published'"
            # D-001: Sanitize text values (fix literal \r\n)
            v = sanitize_text_value(v)
            # D-002: NULL out domain key name contamination in student_records
            if mysql_table == 'student_records' and col_name:
                v = sanitize_domain_value(v, col_name)
            # S-004: Apply value mappings (e.g. message_type: normal→text)
            if i in val_map_indices:
                stripped = v.strip("'")
                if stripped in val_map_indices[i]:
                    v = f"'{val_map_indices[i][stripped]}'"
            new_vals.append(v)

        # Append transformed column values
        for pg_col, src_cols in transforms.items():
            transform_val = build_transform_value(vals, cols, src_cols)
            new_vals.append(transform_val)

        new_tuples.append('(' + ','.join(new_vals) + ')')

    if not new_tuples:
        return None

    pg_cols = ','.join(f'"{c}"' for c in new_cols)
    pg_values = ','.join(new_tuples)

    if mode == 'shared':
        return f'INSERT INTO "{pg_table}" ({pg_cols}) VALUES {pg_values} ON CONFLICT DO NOTHING;'
    else:
        return f'INSERT INTO "{pg_table}" ({pg_cols}) VALUES {pg_values};'


def convert_mysql_to_pg(input_file, output_file, sync_classrooms=None, full_mode=False):
    """
    Convert MySQL dump to PostgreSQL.

    sync_classrooms: list of classroom IDs to sync (None = all except protected)
    full_mode: if True, overwrite ALL data including protected classrooms
    """
    with open(input_file, 'r', encoding='utf-8') as f:
        content = f.read()

    # Default: sync all except narZE (classroom 2)
    if sync_classrooms is None and not full_mode:
        sync_classrooms = [1, 3, 4, 5]

    selective = sync_classrooms is not None and not full_mode

    # Extract INSERT statements
    lines = content.split('\n')
    inserts = []
    current_insert = []
    in_insert = False

    for line in lines:
        if line.startswith('/*!') or line.startswith('LOCK TABLES') or line.startswith('UNLOCK TABLES'):
            continue
        if line.startswith('INSERT INTO'):
            if line.rstrip().endswith(';'):
                inserts.append(line)
            else:
                in_insert = True
                current_insert = [line]
        elif in_insert:
            current_insert.append(line)
            if line.rstrip().endswith(';'):
                inserts.append('\n'.join(current_insert))
                in_insert = False
                current_insert = []

    output = []
    output.append("-- ============================================================")
    output.append("-- Converted from MySQL dump to PostgreSQL")
    output.append("-- Auto-generated by convert_mysql_to_pg.py")
    if selective:
        output.append(f"-- Selective sync: classrooms {sync_classrooms}")
        output.append(f"-- Protected: classroom {PROTECTED_CLASSROOM} (narZE)")
    else:
        output.append("-- FULL sync mode (all classrooms)")
    output.append("-- ============================================================")
    output.append("")
    output.append("SET session_replication_role = 'replica';")
    output.append("")

    all_pg_tables = sorted(set(TABLE_MAP.values()))

    if selective:
        classroom_ids = ','.join(str(c) for c in sync_classrooms)
        classroom_set = set(sync_classrooms)

        # Pre-build student->classroom mapping from the dump
        output.append("-- Pre-scanning dump for student-classroom mapping...")
        student_map = build_student_classroom_map(inserts)
        sync_student_ids = {sid for sid, cid in student_map.items() if cid in classroom_set}
        print(f"  Students in sync classrooms: {len(sync_student_ids)}")

        # Step 1: Delete existing data for sync classrooms
        output.append(f"-- Step 1: Delete existing data for classrooms {list(sync_classrooms)}")
        output.append("")

        # Delete FK-dependent tables first
        for table, (fk_col, parent) in TABLES_VIA_FK.items():
            pg_table = TABLE_MAP.get(table, table)
            parent_pg = TABLE_MAP.get(parent, parent)
            if parent in TABLES_WITH_CLASSROOM_ID:
                output.append(f'DELETE FROM "{pg_table}" WHERE "{fk_col}" IN (SELECT id FROM "{parent_pg}" WHERE classroom_id IN ({classroom_ids}));')
            elif parent in TABLES_VIA_STUDENT:
                output.append(f'DELETE FROM "{pg_table}" WHERE "{fk_col}" IN (SELECT id FROM "{parent_pg}" WHERE student_id IN (SELECT id FROM students WHERE classroom_id IN ({classroom_ids})));')
        output.append("")

        for table in TABLES_VIA_STUDENT:
            pg_table = TABLE_MAP.get(table, table)
            output.append(f'DELETE FROM "{pg_table}" WHERE student_id IN (SELECT id FROM students WHERE classroom_id IN ({classroom_ids}));')
        output.append("")

        for table in TABLES_WITH_CLASSROOM_ID:
            pg_table = TABLE_MAP.get(table, table)
            output.append(f'DELETE FROM "{pg_table}" WHERE classroom_id IN ({classroom_ids});')
        output.append("")

        output.append(f"-- Step 2: Insert filtered data (only classrooms {list(sync_classrooms)})")
        output.append("")
    else:
        # Full mode: truncate everything
        for t in all_pg_tables:
            output.append(f"TRUNCATE TABLE {t} CASCADE;")
        output.append("")

    converted_count = 0
    skipped_count = 0
    filtered_count = 0
    error_tables = []

    for insert_stmt in inserts:
        match = re.match(r"INSERT INTO `(\w+)`", insert_stmt)
        if not match:
            continue
        mysql_table = match.group(1)

        if mysql_table in SKIP_TABLES:
            skipped_count += 1
            continue

        pg_table = TABLE_MAP.get(mysql_table)
        if not pg_table:
            output.append(f"-- SKIPPED: {mysql_table} (no mapping)")
            skipped_count += 1
            continue

        # Determine sync mode for this table
        if selective:
            if mysql_table in SHARED_TABLES or pg_table in SHARED_TABLES:
                mode = 'shared'
            elif mysql_table in TABLES_WITH_CLASSROOM_ID:
                mode = 'classroom'
            elif mysql_table in TABLES_VIA_STUDENT:
                mode = 'student'
            else:
                mode = 'shared'  # Unknown tables: insert with ON CONFLICT
        else:
            mode = 'full'

        try:
            converted = convert_insert_filtered(
                insert_stmt, mysql_table, pg_table, classroom_set if selective else None,
                mode=mode,
                student_map=student_map if selective else None,
            )
            if converted:
                converted = converted.replace("'0000-00-00'", "NULL")
                converted = converted.replace("'0000-00-00 00:00:00'", "NULL")
                output.append(converted)
                output.append("")
                converted_count += 1
            else:
                if selective and mode in ('classroom', 'student'):
                    filtered_count += 1  # All rows filtered out - normal
                else:
                    output.append(f"-- FAILED TO PARSE: {mysql_table}")
                    error_tables.append(mysql_table)
        except Exception as e:
            output.append(f"-- ERROR converting {mysql_table}: {e}")
            error_tables.append(mysql_table)

    output.append("")
    output.append("-- Reset sequences")
    for pg_table in all_pg_tables:
        output.append(
            f"DO $$ BEGIN "
            f"IF EXISTS (SELECT 1 FROM pg_sequences WHERE schemaname='public' AND sequencename='{pg_table}_id_seq') THEN "
            f"PERFORM setval('{pg_table}_id_seq', COALESCE((SELECT MAX(id) FROM {pg_table}), 1)); "
            f"END IF; END $$;"
        )

    output.append("")
    # D-003: Post-migration fixes
    output.append("-- Post-migration data integrity fixes")
    for sql in POST_MIGRATION_SQL:
        output.append(sql)
    output.append("")
    output.append("SET session_replication_role = 'origin';")

    with open(output_file, 'w', encoding='utf-8', newline='\n') as f:
        f.write('\n'.join(output))

    print(f"Mode: {'FULL' if full_mode else f'Selective (classrooms: {sync_classrooms})'}")
    print(f"Converted: {converted_count} INSERT statements")
    print(f"Skipped: {skipped_count}")
    if selective:
        print(f"Filtered out (no matching rows): {filtered_count}")
    if error_tables:
        print(f"Errors: {error_tables}")
    print(f"Output: {output_file}")


if __name__ == '__main__':
    parser = argparse.ArgumentParser(description='Convert MySQL dump to PostgreSQL with classroom-level sync')
    parser.add_argument('--input', '-i', help='Input MySQL dump file')
    parser.add_argument('--output', '-o', help='Output PostgreSQL file')
    parser.add_argument('--sync-classrooms', '-c', help='Comma-separated classroom IDs to sync (default: 1,3,4,5)')
    parser.add_argument('--full', action='store_true', help='Full sync - overwrite ALL data including narZE')
    args = parser.parse_args()

    tmp = os.environ.get('TEMP', os.environ.get('TMP', '/tmp'))
    input_f = args.input or os.path.join(tmp, 'latest_production_data.sql')
    output_f = args.output or os.path.join(tmp, 'pg_production_data.sql')

    sync_classrooms = None
    if args.sync_classrooms:
        sync_classrooms = [int(x) for x in args.sync_classrooms.split(',')]
    elif not args.full:
        sync_classrooms = [1, 3, 4, 5]  # Default: all except narZE(2)

    convert_mysql_to_pg(input_f, output_f, sync_classrooms=sync_classrooms, full_mode=args.full)
