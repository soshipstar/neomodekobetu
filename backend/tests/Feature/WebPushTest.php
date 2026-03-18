<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\PushSubscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebPushTest extends TestCase
{
    use RefreshDatabase;

    private Classroom $classroom;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->classroom = Classroom::create([
            'classroom_name' => 'Test Classroom',
            'is_active' => true,
        ]);

        $this->user = User::create([
            'username' => 'push_test_user',
            'password' => bcrypt('password'),
            'full_name' => 'Push Test User',
            'email' => 'push@example.com',
            'user_type' => 'staff',
            'classroom_id' => $this->classroom->id,
            'is_active' => true,
        ]);
    }

    /**
     * Test: vapid-key endpoint returns the configured public key.
     */
    public function test_vapid_key_returns_public_key(): void
    {
        config(['services.webpush.public_key' => 'test-vapid-public-key-base64url']);

        $response = $this->actingAs($this->user)
            ->getJson('/api/push/vapid-key');

        $response->assertOk()
            ->assertJson([
                'publicKey' => 'test-vapid-public-key-base64url',
            ]);
    }

    /**
     * Test: vapid-key endpoint returns error when key is not configured.
     */
    public function test_vapid_key_returns_error_when_not_configured(): void
    {
        config(['services.webpush.public_key' => '']);

        $response = $this->actingAs($this->user)
            ->getJson('/api/push/vapid-key');

        $response->assertStatus(500)
            ->assertJsonStructure(['error']);
    }

    /**
     * Test: subscribe endpoint saves subscription data.
     */
    public function test_subscribe_saves_subscription(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/push/subscribe', [
                'endpoint' => 'https://fcm.googleapis.com/fcm/send/test-endpoint-123',
                'keys' => [
                    'p256dh' => 'BNcRdreALRFXTkOOUHK1EtK2wtaz5Ry4YfYCA_0QTpQtUbVlUls0VJXg7A8u-Ts1XbjhazAkj7I99e8p8byo4Us',
                    'auth' => 'tBHItJI5svbpC7',
                ],
            ]);

        $response->assertOk()
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('push_subscriptions', [
            'user_id' => $this->user->id,
            'endpoint' => 'https://fcm.googleapis.com/fcm/send/test-endpoint-123',
            'p256dh' => 'BNcRdreALRFXTkOOUHK1EtK2wtaz5Ry4YfYCA_0QTpQtUbVlUls0VJXg7A8u-Ts1XbjhazAkj7I99e8p8byo4Us',
            'auth' => 'tBHItJI5svbpC7',
        ]);
    }

    /**
     * Test: subscribe endpoint validates required fields.
     */
    public function test_subscribe_validates_required_fields(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/push/subscribe', []);

        $response->assertStatus(422);
    }

    /**
     * Test: duplicate subscription updates existing record instead of creating new one.
     */
    public function test_duplicate_subscription_updates_existing(): void
    {
        $endpoint = 'https://fcm.googleapis.com/fcm/send/test-endpoint-456';

        // First subscription
        $this->actingAs($this->user)
            ->postJson('/api/push/subscribe', [
                'endpoint' => $endpoint,
                'keys' => [
                    'p256dh' => 'old-p256dh-key',
                    'auth' => 'old-auth',
                ],
            ]);

        // Second subscription with same endpoint but different keys
        $this->actingAs($this->user)
            ->postJson('/api/push/subscribe', [
                'endpoint' => $endpoint,
                'keys' => [
                    'p256dh' => 'new-p256dh-key',
                    'auth' => 'new-auth',
                ],
            ]);

        // Should have only one record
        $count = PushSubscription::where('endpoint', $endpoint)->count();
        $this->assertEquals(1, $count);

        // Should have updated keys
        $sub = PushSubscription::where('endpoint', $endpoint)->first();
        $this->assertEquals('new-p256dh-key', $sub->p256dh);
        $this->assertEquals('new-auth', $sub->auth);
    }

    /**
     * Test: unsubscribe endpoint removes subscription.
     */
    public function test_unsubscribe_removes_subscription(): void
    {
        $endpoint = 'https://fcm.googleapis.com/fcm/send/test-endpoint-789';

        PushSubscription::create([
            'user_id' => $this->user->id,
            'endpoint' => $endpoint,
            'p256dh' => 'test-p256dh',
            'auth' => 'test-auth',
        ]);

        $response = $this->actingAs($this->user)
            ->postJson('/api/push/unsubscribe', [
                'endpoint' => $endpoint,
            ]);

        $response->assertOk()
            ->assertJson(['success' => true]);

        $this->assertDatabaseMissing('push_subscriptions', [
            'endpoint' => $endpoint,
        ]);
    }

    /**
     * Test: unsubscribe with non-existent endpoint returns success false.
     */
    public function test_unsubscribe_nonexistent_returns_false(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/push/unsubscribe', [
                'endpoint' => 'https://example.com/nonexistent',
            ]);

        $response->assertOk()
            ->assertJson(['success' => false]);
    }

    /**
     * Test: unauthenticated requests are rejected.
     */
    public function test_unauthenticated_requests_rejected(): void
    {
        $this->getJson('/api/push/vapid-key')->assertUnauthorized();
        $this->postJson('/api/push/subscribe', [])->assertUnauthorized();
        $this->postJson('/api/push/unsubscribe', [])->assertUnauthorized();
    }

    /**
     * Test: user can only unsubscribe their own subscriptions.
     */
    public function test_user_cannot_unsubscribe_others(): void
    {
        $otherUser = User::create([
            'username' => 'other_user',
            'password' => bcrypt('password'),
            'full_name' => 'Other User',
            'email' => 'other@example.com',
            'user_type' => 'staff',
            'classroom_id' => $this->classroom->id,
            'is_active' => true,
        ]);

        $endpoint = 'https://fcm.googleapis.com/fcm/send/other-endpoint';

        PushSubscription::create([
            'user_id' => $otherUser->id,
            'endpoint' => $endpoint,
            'p256dh' => 'test-p256dh',
            'auth' => 'test-auth',
        ]);

        // Try to unsubscribe as different user
        $response = $this->actingAs($this->user)
            ->postJson('/api/push/unsubscribe', [
                'endpoint' => $endpoint,
            ]);

        $response->assertOk()
            ->assertJson(['success' => false]);

        // Subscription should still exist
        $this->assertDatabaseHas('push_subscriptions', [
            'endpoint' => $endpoint,
            'user_id' => $otherUser->id,
        ]);
    }
}
