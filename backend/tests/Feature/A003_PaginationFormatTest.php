<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * A-003: フロントエンドが配列を期待しているエンドポイントで
 * paginate() ではなく get() を使用していることを検証
 *
 * StaffSubmissionController::index() と WeeklyPlanController::index() は
 * フロントエンドが .filter() / .forEach() で直接配列操作するため
 * paginate ラッパーを返してはならない。
 */
class A003_PaginationFormatTest extends TestCase
{
    /**
     * StaffSubmissionController::index が paginate() を使っていないこと
     */
    public function test_staff_submission_controller_does_not_paginate(): void
    {
        $file = app_path('Http/Controllers/Staff/StaffSubmissionController.php');
        $this->assertFileExists($file);

        $contents = file_get_contents($file);

        $this->assertStringNotContainsString(
            '->paginate(',
            $contents,
            'StaffSubmissionController::index() は paginate() を使用してはいけません'
        );
    }

    /**
     * WeeklyPlanController::index が paginate() を使っていないこと
     */
    public function test_weekly_plan_controller_does_not_paginate(): void
    {
        $file = app_path('Http/Controllers/Staff/WeeklyPlanController.php');
        $this->assertFileExists($file);

        $contents = file_get_contents($file);

        $this->assertStringNotContainsString(
            '->paginate(',
            $contents,
            'WeeklyPlanController::index() は paginate() を使用してはいけません'
        );
    }
}
