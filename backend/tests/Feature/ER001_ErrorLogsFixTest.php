<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\Company;
use App\Models\User;
use App\Services\WebPushService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * ER001: /admin/error-logs に蓄積されていた本番エラーの再発防止テスト
 *
 * 差分カテゴリ: api / schema / logic
 * 対象:
 *  A. newsletter_settings.default_requests / default_others カラム追加
 *  B. events.target_audience NULL で落ちる（'all' デフォルト化）
 *  C. WebPushService::$publicKey null 代入エラー
 */
class ER001_ErrorLogsFixTest extends TestCase
{
    use RefreshDatabase;

    private function setupStaffContext(): array
    {
        $company = Company::create(['name' => '企業A']);
        $classroom = Classroom::create([
            'classroom_name' => '本校',
            'company_id' => $company->id,
            'is_active' => true,
        ]);
        $staff = User::create([
            'username' => 'staff_er001',
            'password' => bcrypt('pass'),
            'full_name' => 'スタッフ',
            'user_type' => 'staff',
            'classroom_id' => $classroom->id,
            'is_active' => true,
        ]);
        return compact('company', 'classroom', 'staff');
    }

    // =========================================================================
    // A. newsletter_settings.default_requests / default_others
    // =========================================================================

    public function test_newsletter_settings_accepts_default_requests_and_others(): void
    {
        $s = $this->setupStaffContext();

        $response = $this->actingAs($s['staff'], 'sanctum')
            ->putJson('/api/staff/newsletter-settings', [
                'display_settings' => ['show_greeting' => true],
                'calendar_format' => 'list',
                'default_requests' => 'お願いの既定文言',
                'default_others' => 'その他の既定文言',
            ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.default_requests', 'お願いの既定文言');
        $response->assertJsonPath('data.default_others', 'その他の既定文言');
    }

    // =========================================================================
    // B. events.target_audience のデフォルト
    // =========================================================================

    public function test_event_creation_defaults_target_audience_and_color(): void
    {
        $s = $this->setupStaffContext();

        $response = $this->actingAs($s['staff'], 'sanctum')
            ->postJson('/api/staff/events', [
                'event_name' => 'テストイベント',
                'event_date' => '2026-05-01',
            ]);

        $response->assertStatus(201);
        $this->assertEquals('all', $response->json('data.target_audience'));
        $this->assertEquals('#28a745', $response->json('data.event_color'));
    }

    public function test_event_creation_preserves_explicit_target_audience(): void
    {
        $s = $this->setupStaffContext();

        $response = $this->actingAs($s['staff'], 'sanctum')
            ->postJson('/api/staff/events', [
                'event_name' => 'テストイベント2',
                'event_date' => '2026-05-02',
                'target_audience' => 'elementary',
            ]);

        $response->assertStatus(201);
        $this->assertEquals('elementary', $response->json('data.target_audience'));
    }

    // =========================================================================
    // C. WebPushService null publicKey
    // =========================================================================

    public function test_web_push_service_instantiates_without_vapid_keys(): void
    {
        // VAPID 未設定の環境でもインスタンス化で落ちないこと
        config([
            'services.webpush.public_key' => null,
            'services.webpush.private_key' => null,
        ]);

        $service = new WebPushService();
        $this->assertInstanceOf(WebPushService::class, $service);
    }

    public function test_web_push_send_returns_zero_when_not_configured(): void
    {
        config([
            'services.webpush.public_key' => null,
            'services.webpush.private_key' => null,
        ]);

        $service = new WebPushService();
        $sent = $service->sendToUser(1, 'タイトル', '本文');
        $this->assertEquals(0, $sent);
    }
}
