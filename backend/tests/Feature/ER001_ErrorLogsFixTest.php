<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\Company;
use App\Models\ErrorLog;
use App\Models\Student;
use App\Models\User;
use App\Services\WebPushService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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

    // =========================================================================
    // D. error_logs クリーンアップが updated_at 不在で落ちる
    //    routes/console.php の毎日 04:00 タスクが SQLSTATE[42703]
    //    "Undefined column: updated_at" で繰り返し失敗していた。
    //    ErrorLog は $timestamps = false で created_at しか持たないため、
    //    クリーンアップ判定は created_at で行う。
    // =========================================================================

    public function test_error_logs_cleanup_query_uses_existing_column(): void
    {
        // 4日前の解決済みエラー
        $old = ErrorLog::create([
            'level'       => 'error',
            'message'     => '古い解決済み',
            'is_resolved' => true,
            'created_at'  => now()->subDays(4),
        ]);
        // 1日前の解決済みエラー (削除されてはならない)
        $recent = ErrorLog::create([
            'level'       => 'error',
            'message'     => '新しい解決済み',
            'is_resolved' => true,
            'created_at'  => now()->subDay(),
        ]);
        // 4日前の未解決エラー (削除されてはならない)
        $unresolved = ErrorLog::create([
            'level'       => 'error',
            'message'     => '古い未解決',
            'is_resolved' => false,
            'created_at'  => now()->subDays(4),
        ]);

        // routes/console.php のクリーンアップと同一クエリ
        $deleted = ErrorLog::where('is_resolved', true)
            ->where('created_at', '<', now()->subDays(3))
            ->delete();

        $this->assertSame(1, $deleted);
        $this->assertNull(ErrorLog::find($old->id));
        $this->assertNotNull(ErrorLog::find($recent->id));
        $this->assertNotNull(ErrorLog::find($unresolved->id));
    }

    // =========================================================================
    // E. DashboardController.summary() で $students 未定義
    //    /api/staff/dashboard/summary 呼び出し時に
    //    "Undefined variable $students" で例外 → catch で 0 件扱い。
    //    accessibleIds 経由のサブクエリで実際の件数が返るようにする。
    // =========================================================================

    public function test_dashboard_summary_counts_unsubmitted_documents_per_accessible_classroom(): void
    {
        $s = $this->setupStaffContext();
        $other = Classroom::create([
            'classroom_name' => '別校',
            'company_id' => $s['company']->id,
            'is_active' => true,
        ]);

        $studentMine = Student::create([
            'classroom_id' => $s['classroom']->id,
            'student_name' => '担当生徒',
            'is_active'    => true,
        ]);
        $studentOther = Student::create([
            'classroom_id' => $other->id,
            'student_name' => '他校生徒',
            'is_active'    => true,
        ]);

        // 担当教室の未提出 1件
        DB::table('submission_requests')->insert([
            'student_id'   => $studentMine->id,
            'created_by'   => $s['staff']->id,
            'title'        => '未提出書類',
            'is_completed' => false,
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);
        // 担当教室の提出済 1件 (カウントされない)
        DB::table('submission_requests')->insert([
            'student_id'   => $studentMine->id,
            'created_by'   => $s['staff']->id,
            'title'        => '提出済書類',
            'is_completed' => true,
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);
        // 別教室の未提出 1件 (アクセス権限なしでカウントされない)
        DB::table('submission_requests')->insert([
            'student_id'   => $studentOther->id,
            'created_by'   => $s['staff']->id,
            'title'        => '他校未提出',
            'is_completed' => false,
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);

        $response = $this->actingAs($s['staff'], 'sanctum')
            ->getJson('/api/staff/dashboard/summary');

        $response->assertStatus(200);
        $response->assertJsonPath('data.unsubmitted_documents', 1);
    }
}
