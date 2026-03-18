<?php

namespace Tests\Feature;

use App\Models\LoginAttempt;
use Tests\TestCase;

class S010_LoginAttemptFillableTest extends TestCase
{
    public function test_login_attempt_fillable_includes_user_agent(): void
    {
        $model = new LoginAttempt();
        $this->assertContains('user_agent', $model->getFillable());
        $this->assertContains('user_id', $model->getFillable());
    }

    public function test_holiday_fillable_includes_type_and_created_by(): void
    {
        $model = new \App\Models\Holiday();
        $this->assertContains('holiday_type', $model->getFillable());
        $this->assertContains('created_by', $model->getFillable());
    }

    public function test_event_has_creator_relationship(): void
    {
        $model = new \App\Models\Event();
        $this->assertTrue(method_exists($model, 'creator'));
    }
}
