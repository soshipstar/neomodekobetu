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
    if (!$row || !isset($row['id'])) { $errors++; continue; }

    $student = DB::table('students')->where('id', $row['student_id'])->first(['classroom_id']);
    $classroomId = $student ? $student->classroom_id : 2;

    $planData = $row['plan_data'];
    if (is_array($planData)) {
        $planData = json_encode($planData);
    }

    try {
        DB::table('weekly_plans')->insert([
            'id' => $row['id'],
            'classroom_id' => $classroomId,
            'student_id' => $row['student_id'],
            'week_start_date' => $row['week_start_date'],
            'weekly_goal' => $row['weekly_goal'],
            'shared_goal' => $row['shared_goal'],
            'must_do' => $row['must_do'],
            'should_do' => $row['should_do'],
            'want_to_do' => $row['want_to_do'],
            'plan_data' => $planData,
            'overall_comment' => $row['overall_comment'],
            'status' => 'draft',
            'created_at' => $row['created_at'],
            'updated_at' => $row['updated_at'],
        ]);
        $imported++;
    } catch (Throwable $e) {
        $errors++;
        if ($errors <= 5) echo "Error id={$row['id']}: {$e->getMessage()}\n";
    }
}

$maxId = DB::table('weekly_plans')->max('id');
if ($maxId) {
    DB::statement("SELECT setval(pg_get_serial_sequence('weekly_plans', 'id'), {$maxId})");
}

echo "Imported: {$imported}, Errors: {$errors}\n";
echo "Total in DB: " . DB::table('weekly_plans')->count() . "\n";
