<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('authenticated user can authorize broadcasting channel', function () {
    $user = User::factory()->staff()->create([
        'classroom_id' => 1,
    ]);

    $token = $user->createToken('test-token')->plainTextToken;

    $response = $this->postJson('/api/broadcasting/auth', [
        'channel_name' => 'private-user.1',
        'socket_id' => '123456.789',
    ], [
        'Authorization' => "Bearer {$token}",
    ]);

    // With a valid token, should not return 401
    $response->assertStatus(200);
    $response->assertJsonStructure(['auth']);
});

test('authenticated user can authorize private chat channel they have access to', function () {
    $user = User::factory()->staff()->create([
        'classroom_id' => 1,
    ]);

    $token = $user->createToken('test-token')->plainTextToken;

    // user channel - user should have access to their own channel
    $response = $this->postJson('/api/broadcasting/auth', [
        'channel_name' => "private-user.{$user->id}",
        'socket_id' => '123456.789',
    ], [
        'Authorization' => "Bearer {$token}",
    ]);

    $response->assertStatus(200);
    $response->assertJsonStructure(['auth']);
});

test('broadcasting auth returns 403 for channel user does not have access to', function () {
    $user = User::factory()->staff()->create([
        'classroom_id' => 1,
    ]);

    $token = $user->createToken('test-token')->plainTextToken;

    // User channel for a different user - should be forbidden
    $otherUser = User::factory()->staff()->create([
        'classroom_id' => 2,
    ]);

    $response = $this->postJson('/api/broadcasting/auth', [
        'channel_name' => "private-user.{$otherUser->id}",
        'socket_id' => '123456.789',
    ], [
        'Authorization' => "Bearer {$token}",
    ]);

    $response->assertStatus(403);
});
