<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('broadcasting auth returns 401 without token', function () {
    $response = $this->postJson('/api/broadcasting/auth', [
        'channel_name' => 'private-chat.1',
        'socket_id' => '123456.789',
    ]);

    $response->assertStatus(401);
    $response->assertJson([
        'message' => 'Unauthenticated.',
    ]);
});

test('broadcasting auth returns 401 with invalid token', function () {
    $response = $this->postJson('/api/broadcasting/auth', [
        'channel_name' => 'private-chat.1',
        'socket_id' => '123456.789',
    ], [
        'Authorization' => 'Bearer invalid-token-here',
    ]);

    $response->assertStatus(401);
    $response->assertJson([
        'message' => 'Unauthenticated.',
    ]);
});

test('broadcasting auth returns JSON error not HTML', function () {
    $response = $this->postJson('/api/broadcasting/auth', [
        'channel_name' => 'private-chat.1',
        'socket_id' => '123456.789',
    ]);

    $response->assertStatus(401);
    $response->assertHeader('Content-Type', 'application/json');
    $response->assertJsonStructure(['message']);
});
