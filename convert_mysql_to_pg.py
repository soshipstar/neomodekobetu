#!/usr/bin/env python3
"""
Convert MySQL dump (data-only) INSERT statements to PostgreSQL-compatible format.

Usage:
  python convert_mysql_to_pg.py [input_file] [output_file]

Handles:
  - Table name mapping (MySQL -> Laravel PG)
  - Column stripping (MySQL columns not in PG schema)
  - Boolean conversion (MySQL 0/1 -> PG true/false)
  - MySQL syntax cleanup (backticks, comments, LOCK/UNLOCK)
  - NULL constraint fixes
  - Sequence reset
"""
import re
import sys
import os

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

# Columns that exist in MySQL but NOT in PG (will be stripped from INSERT)
STRIP_COLUMNS = {
    'users': ['remember_token'],
    'chat_messages': ['meeting_request_id', 'attachment_path', 'attachment_original_name', 'attachment_size', 'attachment_type'],
    'students': ['hide_initial_monitoring_by', 'hide_initial_monitoring_at'],
    'monitoring_records': ['hidden_by', 'hidden_at'],
    'individual_support_plans': ['hidden_by', 'hidden_at', 'source_period_id', 'target_period_start', 'target_period_end', 'plan_number', 'guardian_signature_image', 'guardian_signature_date', 'staff_signature_image', 'staff_signature_date', 'guardian_review_comment_at'],
    'kakehashi_guardian': ['hidden_by', 'hidden_at'],
    'kakehashi_staff': ['hidden_by', 'hidden_at'],
    'classrooms': ['service_type', 'target_grades'],
    'event_registrations': ['registered_at', 'notes'],
    'facility_evaluation_periods': ['classroom_id', 'guardian_eval_start_date', 'guardian_eval_end_date', 'staff_eval_start_date', 'staff_eval_end_date', 'self_eval_created_date', 'publish_date'],
    'individual_support_plan_details': ['row_order'],
    'submission_requests': ['attachment_path', 'attachment_original_name', 'attachment_size'],
    'meeting_requests': ['guardian_counter_date1', 'guardian_counter_date2', 'guardian_counter_date3', 'staff_counter_date1', 'staff_counter_date2', 'staff_counter_date3', 'candidate_date1', 'candidate_date2', 'candidate_date3'],
    'weekly_plans': ['weekly_goal', 'shared_goal', 'must_do', 'should_do', 'want_to_do', 'weekly_goal_achievement', 'weekly_goal_comment', 'shared_goal_achievement', 'shared_goal_comment', 'must_do_achievement', 'must_do_comment', 'should_do_achievement', 'should_do_comment', 'want_to_do_achievement', 'want_to_do_comment', 'daily_achievement', 'overall_comment', 'evaluated_at', 'evaluated_by_type', 'evaluated_by_id', 'plan_data', 'created_by_type', 'created_by_id'],
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
        'student_id': 'classroom_id',
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

    # Columns renamed to None should also be stripped
    for old_col, new_col in renames.items():
        if new_col is None:
            strip.add(old_col)

    # Find indices to remove
    strip_indices = {i for i, c in enumerate(cols) if c in strip}
    bool_indices = {i for i, c in enumerate(cols) if c in bool_cols}

    # Build new column list with renames applied
    new_cols = []
    for i, c in enumerate(cols):
        if i in strip_indices:
            continue
        if c in renames and renames[c] is not None:
            new_cols.append(renames[c])
        else:
            new_cols.append(c)

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
            new_vals.append(v)

        new_tuples.append('(' + ','.join(new_vals) + ')')

    # Build PostgreSQL INSERT
    pg_cols = ','.join(f'"{c}"' for c in new_cols)
    pg_values = ','.join(new_tuples)

    return f'INSERT INTO "{pg_table}" ({pg_cols}) VALUES {pg_values};'


def convert_mysql_to_pg(input_file, output_file):
    with open(input_file, 'r', encoding='utf-8') as f:
        content = f.read()

    # Extract INSERT statements
    lines = content.split('\n')
    inserts = []
    current_insert = []
    in_insert = False

    for line in lines:
        # Skip MySQL-specific lines
        if line.startswith('/*!') or line.startswith('LOCK TABLES') or line.startswith('UNLOCK TABLES'):
            continue
        if line.startswith('INSERT INTO'):
            if line.rstrip().endswith(';'):
                # Single-line INSERT (most common in mysqldump --no-create-info)
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
    output.append("-- ============================================================")
    output.append("")
    output.append("SET session_replication_role = 'replica';")
    output.append("")

    # Truncate all target tables
    all_pg_tables = sorted(set(TABLE_MAP.values()))
    for t in all_pg_tables:
        output.append(f"TRUNCATE TABLE {t} CASCADE;")
    output.append("")

    converted_count = 0
    skipped_count = 0
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

        try:
            converted = convert_insert(insert_stmt, mysql_table, pg_table)
            if converted:
                # Fix MySQL '0000-00-00' dates -> NULL
                converted = converted.replace("'0000-00-00'", "NULL")
                converted = converted.replace("'0000-00-00 00:00:00'", "NULL")
                output.append(converted)
                output.append("")
                converted_count += 1
            else:
                output.append(f"-- FAILED TO PARSE: {mysql_table}")
                error_tables.append(mysql_table)
        except Exception as e:
            output.append(f"-- ERROR converting {mysql_table}: {e}")
            error_tables.append(mysql_table)

    # Reset all sequences
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
    output.append("SET session_replication_role = 'origin';")

    with open(output_file, 'w', encoding='utf-8', newline='\n') as f:
        f.write('\n'.join(output))

    print(f"Converted: {converted_count} INSERT statements")
    print(f"Skipped: {skipped_count}")
    if error_tables:
        print(f"Errors: {error_tables}")
    print(f"Output: {output_file}")


if __name__ == '__main__':
    tmp = os.environ.get('TEMP', os.environ.get('TMP', '/tmp'))
    input_f = sys.argv[1] if len(sys.argv) > 1 else os.path.join(tmp, 'latest_production_data.sql')
    output_f = sys.argv[2] if len(sys.argv) > 2 else os.path.join(tmp, 'pg_production_data.sql')
    convert_mysql_to_pg(input_f, output_f)
