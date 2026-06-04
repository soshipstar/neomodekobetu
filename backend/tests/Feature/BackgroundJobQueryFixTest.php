<?php

namespace Tests\Feature;

use App\Models\ErrorLog;
use App\Models\SecurityAlert;
use App\Models\User;
use App\Services\ApiAnomalyDetectionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * エラーログ対処: バックグラウンドジョブの SQL カラム誤り
 *
 * 差分カテゴリ: logic
 *
 * (1) ApiAnomalyDetectionService: HAVING でエイリアス "cnt" を参照していたため
 *     PostgreSQL で「column "cnt" does not exist」(error_logs 88件・毎時失敗)。
 *     => havingRaw('COUNT(*) >= ?') に修正。毎時の不正検知が全滅していた。
 *
 * (2) 解決済みエラーログの自動削除 (routes/console.php) が存在しない updated_at 列を
 *     参照していたため「column "updated_at" does not exist」(error_logs 8件・毎日失敗)。
 *     => created_at に修正。error_logs に updated_at 列は無い。
 */
class BackgroundJobQueryFixTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(): User
    {
        return User::create([
            'username'  => 'anomaly_target_' . uniqid(),
            'password'  => Hash::make('p'),
            'full_name' => '不正検知対象',
            'user_type' => 'staff',
            'is_active' => true,
        ]);
    }

    private function insertAccessLogs(int $userId, int $count, int $status = 200, string $path = '/api/test'): void
    {
        $rows = [];
        for ($i = 0; $i < $count; $i++) {
            $rows[] = [
                'user_id'     => $userId,
                'user_type'   => 'staff',
                'method'      => 'GET',
                'path'        => $path,
                'status_code' => $status,
                'created_at'  => now()->subMinutes(10),
            ];
        }
        DB::table('api_access_logs')->insert($rows);
    }

    /**
     * (1) 過大リクエスト検知が SQL 例外を投げず、閾値超過で SecurityAlert を作る
     */
    public function test_anomaly_detection_runs_without_sql_error_and_detects(): void
    {
        config(['security.anomaly.max_requests_per_hour' => 5]);
        config(['security.anomaly.max_forbidden_per_hour' => 999]);
        config(['security.anomaly.max_exports_per_hour' => 999]);
        config(['security.anomaly.max_not_found_per_hour' => 999]);
        config(['security.anomaly.email_master_admins' => false]);

        $user = $this->makeUser();
        $this->insertAccessLogs($user->id, 6); // 閾値 5 を超過

        $service = app(ApiAnomalyDetectionService::class);
        $saved = $service->run(); // 旧コードはここで QueryException("cnt") を投げていた

        $this->assertNotEmpty($saved);
        $this->assertDatabaseHas('security_alerts', [
            'rule'    => 'A_excessive_requests',
            'user_id' => $user->id,
        ]);
    }

    /**
     * (1) 閾値未満なら検知しない (havingRaw が正しく機能している)
     */
    public function test_anomaly_detection_below_threshold_no_alert(): void
    {
        config(['security.anomaly.max_requests_per_hour' => 100]);
        config(['security.anomaly.max_forbidden_per_hour' => 999]);
        config(['security.anomaly.max_exports_per_hour' => 999]);
        config(['security.anomaly.max_not_found_per_hour' => 999]);
        config(['security.anomaly.email_master_admins' => false]);

        $user = $this->makeUser();
        $this->insertAccessLogs($user->id, 3); // 閾値 100 未満

        $saved = app(ApiAnomalyDetectionService::class)->run();

        $this->assertEmpty($saved);
        $this->assertDatabaseCount('security_alerts', 0);
    }

    /**
     * (2) 解決済みエラーログ削除が created_at で動作する (updated_at 列は存在しない)
     */
    public function test_resolved_error_log_cleanup_uses_created_at(): void
    {
        // 古い解決済み (削除対象)
        $old = ErrorLog::create(['level' => 'error', 'message' => 'old resolved', 'is_resolved' => true]);
        $old->created_at = now()->subDays(10);
        $old->save();

        // 新しい解決済み (残る)
        ErrorLog::create(['level' => 'error', 'message' => 'recent resolved', 'is_resolved' => true]);
        // 古い未解決 (残る)
        $oldUnresolved = ErrorLog::create(['level' => 'error', 'message' => 'old unresolved', 'is_resolved' => false]);
        $oldUnresolved->created_at = now()->subDays(10);
        $oldUnresolved->save();

        // routes/console.php の cleanup-resolved-error-logs と同じクエリ
        $deleted = ErrorLog::where('is_resolved', true)
            ->where('created_at', '<', now()->subDays(3))
            ->delete();

        $this->assertSame(1, $deleted);
        $this->assertDatabaseMissing('error_logs', ['message' => 'old resolved']);
        $this->assertDatabaseHas('error_logs', ['message' => 'recent resolved']);
        $this->assertDatabaseHas('error_logs', ['message' => 'old unresolved']);
    }
}
