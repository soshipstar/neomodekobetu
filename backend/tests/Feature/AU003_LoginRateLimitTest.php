<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class AU003_LoginRateLimitTest extends TestCase
{
    use DatabaseMigrations;

    public function test_login_is_rate_limited_after_5_failures(): void
    {
        $classroom = Classroom::create([
            'classroom_name' => 'Test',
            'is_active' => true,
        ]);

        User::create([
            'username' => 'testuser',
            'password' => bcrypt('correct'),
            'full_name' => 'Test',
            'user_type' => 'staff',
            'classroom_id' => $classroom->id,
            'is_active' => true,
        ]);

        // 5回失敗
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/auth/login', [
                'username' => 'testuser',
                'password' => 'wrong',
            ])->assertStatus(422);
        }

        // 6回目はレート制限
        $response = $this->postJson('/api/auth/login', [
            'username' => 'testuser',
            'password' => 'correct',
        ]);

        $response->assertStatus(422);
        $this->assertStringContainsString('上限', $response->json('errors.username.0'));
    }

    public function test_rate_limit_resets_on_success(): void
    {
        RateLimiter::clear('login:127.0.0.1');

        $classroom = Classroom::create([
            'classroom_name' => 'Test',
            'is_active' => true,
        ]);

        User::create([
            'username' => 'testuser2',
            'password' => bcrypt('correct'),
            'full_name' => 'Test',
            'user_type' => 'staff',
            'classroom_id' => $classroom->id,
            'is_active' => true,
        ]);

        // 3回失敗
        for ($i = 0; $i < 3; $i++) {
            $this->postJson('/api/auth/login', [
                'username' => 'testuser2',
                'password' => 'wrong',
            ]);
        }

        // 成功してリセット
        $this->postJson('/api/auth/login', [
            'username' => 'testuser2',
            'password' => 'correct',
        ])->assertStatus(200);

        // リセット後は再び試行可能
        $this->postJson('/api/auth/login', [
            'username' => 'testuser2',
            'password' => 'wrong',
        ])->assertStatus(422)
          ->assertJsonMissing(['上限']);
    }
}
