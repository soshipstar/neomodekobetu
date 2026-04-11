<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\Company;
use App\Models\User;
use App\Services\NotificationService;
use App\Services\WebPushService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

/**
 * NT010: 通知カテゴリ別 ON/OFF 設定のテスト
 *
 * 差分カテゴリ: api / logic
 * 背景: ユーザーが通知カテゴリごとに ON/OFF を切り替えられるようにした。
 *       in-app 通知は履歴として常に作成するが、Web Push は該当カテゴリが
 *       有効な場合のみ送信する。
 */
class NT010_NotificationPreferencesTest extends TestCase
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
            'username' => 'user_nt010',
            'password' => bcrypt('pass'),
            'full_name' => 'テスト',
            'user_type' => 'staff',
            'classroom_id' => $classroom->id,
            'is_active' => true,
        ]);
    }

    public function test_show_returns_all_categories_enabled_by_default(): void
    {
        $user = $this->user();

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/notification-preferences');

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertArrayHasKey('chat', $data);
        $this->assertArrayHasKey('announcement', $data);
        $this->assertArrayHasKey('meeting', $data);
        $this->assertArrayHasKey('kakehashi', $data);
        $this->assertArrayHasKey('monitoring', $data);
        $this->assertArrayHasKey('support_plan', $data);
        $this->assertArrayHasKey('submission', $data);
        $this->assertArrayHasKey('absence', $data);
        // 未設定はデフォルトで enabled=true
        foreach ($data as $item) {
            $this->assertTrue($item['enabled']);
        }
    }

    public function test_update_changes_preferences(): void
    {
        $user = $this->user();

        $response = $this->actingAs($user, 'sanctum')
            ->putJson('/api/notification-preferences', [
                'preferences' => [
                    'chat' => false,
                    'announcement' => true,
                ],
            ]);

        $response->assertStatus(200);
        $this->assertFalse($response->json('data.chat.enabled'));
        $this->assertTrue($response->json('data.announcement.enabled'));

        // 永続化されている
        $user->refresh();
        $this->assertFalse($user->acceptsNotification('chat'));
        $this->assertTrue($user->acceptsNotification('announcement'));
    }

    public function test_accepts_notification_default_true_for_missing_key(): void
    {
        $user = $this->user();
        // preferences 未設定
        $this->assertTrue($user->acceptsNotification('chat'));
        $this->assertTrue($user->acceptsNotification('meeting'));

        // 一部だけ設定しても未設定キーは有効
        $user->notification_preferences = ['chat' => false];
        $user->save();
        $user->refresh();

        $this->assertFalse($user->acceptsNotification('chat'));
        $this->assertTrue($user->acceptsNotification('meeting'));
        $this->assertTrue($user->acceptsNotification('announcement'));
    }

    public function test_notify_skips_push_when_category_disabled(): void
    {
        $user = $this->user();
        $user->notification_preferences = ['chat' => false];
        $user->save();

        // WebPushService をモックして sendToUser が呼ばれないことを確認
        $mock = Mockery::mock(WebPushService::class);
        $mock->shouldNotReceive('sendToUser');
        $this->app->instance(WebPushService::class, $mock);

        $service = new NotificationService();
        $notification = $service->notify($user->fresh(), 'chat_message', 'タイトル', '本文');

        // in-app 通知は作成されている
        $this->assertDatabaseHas('notifications', [
            'user_id' => $user->id,
            'type' => 'chat_message',
            'title' => 'タイトル',
        ]);
        $this->assertEquals($user->id, $notification->user_id);
    }

    public function test_notify_sends_push_when_category_enabled(): void
    {
        $user = $this->user();
        // デフォルトで全カテゴリ有効

        $mock = Mockery::mock(WebPushService::class);
        $mock->shouldReceive('sendToUser')->once()->andReturn(1);
        $this->app->instance(WebPushService::class, $mock);

        $service = new NotificationService();
        $service->notify($user, 'chat_message', 'タイトル', '本文');

        $this->assertDatabaseHas('notifications', [
            'user_id' => $user->id,
            'type' => 'chat_message',
        ]);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
