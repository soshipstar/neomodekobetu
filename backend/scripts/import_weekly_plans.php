<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$kernel->bootstrap();

$lines = file('/tmp/weekly_json.txt', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
$imported = 0;
$errors = 0;

foreach ($lines as $line) {
    $row = json_decode($line, true);
    if (!$row || !isset($row['id'])) {
        $errors++;
        if ($errors <= 5) echo "Parse error: " . substr($line, 0, 100) . " | " . json_last_error_msg() . "\n";
        continue;
    }

    $student = DB::table('students')->where('id', $row['student_id'])->first(['classroom_id']);
    $classroomId = $student ? $student->classroom_id : 2;

    $planData = $row['plan_data'];
    if (is_array($planData)) {
        $planData = json_encode($planData);
    }

    $data = [
        'classroom_id' => $classroomId,
        'student_id' => $row['student_id'],
        'week_start_date' => $row['week_start_date'],
        'weekly_goal' => $row['weekly_goal'] ?: null,
        'shared_goal' => $row['shared_goal'] ?: null,
        'must_do' => $row['must_do'] ?: null,
        'should_do' => $row['should_do'] ?: null,
        'want_to_do' => $row['want_to_do'] ?: null,
        'weekly_goal_achievement' => $row['weekly_goal_achievement'] ?? null,
        'weekly_goal_comment' => $row['weekly_goal_comment'] ?: null,
        'shared_goal_achievement' => $row['shared_goal_achievement'] ?? null,
        'shared_goal_comment' => $row['shared_goal_comment'] ?: null,
        'must_do_achievement' => $row['must_do_achievement'] ?? null,
        'must_do_comment' => $row['must_do_comment'] ?: null,
        'should_do_achievement' => $row['should_do_achievement'] ?? null,
        'should_do_comment' => $row['should_do_comment'] ?: null,
        'want_to_do_achievement' => $row['want_to_do_achievement'] ?? null,
        'want_to_do_comment' => $row['want_to_do_comment'] ?: null,
        'daily_achievement' => ($row['daily_achievement'] && $row['daily_achievement'] !== '{}') ? (is_string($row['daily_achievement']) ? $row['daily_achievement'] : json_encode($row['daily_achievement'])) : null,
        'overall_comment' => $row['overall_comment'] ?: null,
        'evaluated_at' => $row['evaluated_at'] ?: null,
        'plan_data' => $planData,
        'created_by' => $row['created_by'] ?: null,
        'status' => 'draft',
        'created_at' => $row['created_at'] ?: now(),
        'updated_at' => $row['updated_at'] ?: now(),
    ];

    try {
        DB::table('weekly_plans')->upsert(
            array_merge(['id' => $row['id']], $data),
            ['id'],
            array_keys($data)
        );
        $imported++;
    } catch (Throwable $e) {
        $errors++;
        if ($errors <= 10) echo "Error id={$row['id']}: {$e->getMessage()}\n";
    }
}

$maxId = DB::table('weekly_plans')->max('id');
if ($maxId) {
    DB::statement("SELECT setval(pg_get_serial_sequence('weekly_plans', 'id'), {$maxId})");
}

echo "Imported: {$imported}, Errors: {$errors}\n";
echo "Total in DB: " . DB::table('weekly_plans')->count() . "\n";
