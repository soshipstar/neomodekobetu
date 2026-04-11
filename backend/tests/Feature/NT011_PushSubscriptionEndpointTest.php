<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\Company;
use App\Models\PushSubscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * NT011: Push Subscription エンドポイントのテスト
 *
 * 差分カテゴリ: api
 * 対象:
 *  - GET  /api/push/vapid-key    VAPID public key 取得
 *  - POST /api/push/subscribe    購読登録 (upsert)
 *  - POST /api/push/unsubscribe  購読解除
 */
class NT011_PushSubscriptionEndpointTest extends TestCase
{
    use RefreshDatabase;

    private function user(): User
    {
        $company = Company::create(['name' => 'A']);
        $classroom = Classroom::create([
            'classroom_name' => 'A1',
            'company_id' => $company->id,
            'is_active' => true,
        ]);
        return User::create([
            'username' => 'user_nt011',
            'password' => bcrypt('pass'),
            'full_name' => 'テスト',
            'user_type' => 'staff',
            'classroom_id' => $classroom->id,
            'is_active' => true,
        ]);
    }

    public function test_vapid_key_returns_public_key_when_configured(): void
    {
        config(['services.webpush.public_key' => 'BFtU_dummyPublicKey_64bytes_base64url_encoded_00000000000000000000000']);
        $user = $this->user();

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/push/vapid-key');

        $response->assertStatus(200);
        $this->assertNotEmpty($response->json('publicKey'));
    }

    public function test_vapid_key_returns_error_when_not_configured(): void
    {
        config(['services.webpush.public_key' => null]);
        $user = $this->user();

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/push/vapid-key');

        $response->assertStatus(500);
    }

    public function test_subscribe_creates_subscription(): void
    {
        $user = $this->user();

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/push/subscribe', [
            'endpoint' => 'https://fcm.googleapis.com/fcm/send/abc123',
            'keys' => [
                'p256dh' => 'BOgw_somePublicKey_base64url',
                'auth' => 'auth_secret_base64url',
            ],
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('push_subscriptions', [
            'user_id' => $user->id,
            'endpoint' => 'https://fcm.googleapis.com/fcm/send/abc123',
        ]);
    }

    public function test_subscribe_upserts_existing_endpoint(): void
    {
        $user = $this->user();
        PushSubscription::create([
            'user_id' => $user->id,
            'endpoint' => 'https://fcm.googleapis.com/fcm/send/abc',
            'p256dh' => 'old_p256dh',
            'auth' => 'old_auth',
        ]);

        $this->actingAs($user, 'sanctum')->postJson('/api/push/subscribe', [
            'endpoint' => 'https://fcm.googleapis.com/fcm/send/abc',
            'keys' => [
                'p256dh' => 'new_p256dh',
                'auth' => 'new_auth',
            ],
        ])->assertStatus(200);

        $this->assertDatabaseCount('push_subscriptions', 1);
        $this->assertDatabaseHas('push_subscriptions', [
            'endpoint' => 'https://fcm.googleapis.com/fcm/send/abc',
            'p256dh' => 'new_p256dh',
            'auth' => 'new_auth',
        ]);
    }

    public function test_subscribe_validation_rejects_missing_keys(): void
    {
        $user = $this->user();

        $this->actingAs($user, 'sanctum')->postJson('/api/push/subscribe', [
            'endpoint' => 'https://fcm.googleapis.com/fcm/send/xyz',
        ])->assertStatus(422);
    }

    public function test_unsubscribe_deletes_subscription(): void
    {
        $user = $this->user();
        PushSubscription::create([
            'user_id' => $user->id,
            'endpoint' => 'https://fcm.googleapis.com/fcm/send/to-delete',
            'p256dh' => 'p256dh',
            'auth' => 'auth',
        ]);

        $this->actingAs($user, 'sanctum')->postJson('/api/push/unsubscribe', [
            'endpoint' => 'https://fcm.googleapis.com/fcm/send/to-delete',
        ])->assertStatus(200);

        $this->assertDatabaseMissing('push_subscriptions', [
            'endpoint' => 'https://fcm.googleapis.com/fcm/send/to-delete',
        ]);
    }

    public function test_unsubscribe_only_affects_own_user(): void
    {
        $user1 = $this->user();
        $classroom2 = Classroom::create(['classroom_name' => 'B1', 'is_active' => true]);
        $user2 = User::create([
            'username' => 'user2_nt011',
            'password' => bcrypt('pass'),
            'full_name' => 'ユーザー2',
            'user_type' => 'staff',
            'classroom_id' => $classroom2->id,
            'is_active' => true,
        ]);

        PushSubscription::create([
            'user_id' => $user2->id,
            'endpoint' => 'https://fcm.googleapis.com/fcm/send/user2',
            'p256dh' => 'p',
            'auth' => 'a',
        ]);

        // user1 で user2 の endpoint 削除を試みても影響なし
        $this->actingAs($user1, 'sanctum')->postJson('/api/push/unsubscribe', [
            'endpoint' => 'https://fcm.googleapis.com/fcm/send/user2',
        ])->assertStatus(200);

        // user2 のレコードは残っている
        $this->assertDatabaseHas('push_subscriptions', [
            'user_id' => $user2->id,
            'endpoint' => 'https://fcm.googleapis.com/fcm/send/user2',
        ]);
    }
}
