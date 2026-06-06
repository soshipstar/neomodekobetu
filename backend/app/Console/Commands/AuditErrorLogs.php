<?php

namespace App\Console\Commands;

use App\Models\ErrorLog;
use Illuminate\Console\Command;

/**
 * /admin/error-logs に蓄積された未対処エラーを監査し、既知の修正で解決済みになっている
 * ものを is_resolved=true に更新する。
 *
 * 使い方:
 *   php artisan error-logs:audit              # dry-run。何が解決済み対象になるかを表示
 *   php artisan error-logs:audit --apply      # 実際に is_resolved を更新する
 *   php artisan error-logs:audit --list       # 未対処エラーを exception_class ごとに集計表示
 *
 * 安全性:
 *  - 物理削除は一切行わない (resolved に flag を立てるだけ)
 *  - cleanup-resolved-error-logs (routes/console.php の毎日 04:00 タスク) が
 *    3日後に物理削除するので、誤って resolved にしても 3日以内なら手動で戻せる
 */
class AuditErrorLogs extends Command
{
    protected $signature = 'error-logs:audit
                            {--apply : 実際に is_resolved を更新する (省略時は dry-run)}
                            {--list : 未対処エラーを exception_class ごとに集計表示する}';

    protected $description = '蓄積された error_logs を監査し、既知の修正で解決済みのものを resolved にマークする';

    /**
     * パターン定義: 各エントリは
     *  - id: 識別子 (resolved_note に書く)
     *  - description: 人間向け説明
     *  - exception_class: マッチさせる例外クラス (部分一致 LIKE)。null なら無条件
     *  - message_like: マッチさせる message パターン (LIKE)
     *  - fixed_at: いつ修正されたか (resolved_note に書く)
     *  - fix_ref: 修正の参照 (file:line など)
     */
    private const PATTERNS = [
        [
            'id'              => 'EL-001-error-logs-updated-at',
            'description'     => 'error_logs クリーンアップが存在しない updated_at カラムを参照していた',
            'exception_class' => '%QueryException%',
            'message_like'    => '%column "updated_at" does not exist%error_logs%',
            'fixed_at'        => '2026-05-12',
            'fix_ref'         => 'routes/console.php:45 — updated_at → created_at',
        ],
        [
            'id'              => 'EL-001-error-logs-updated-at-alt',
            'description'     => 'error_logs クリーンアップ (PDOException 経由のパターン)',
            'exception_class' => '%PDOException%',
            'message_like'    => '%column "updated_at" does not exist%error_logs%',
            'fixed_at'        => '2026-05-12',
            'fix_ref'         => 'routes/console.php:45 — updated_at → created_at',
        ],
        [
            'id'              => 'EL-002-route-login-not-defined',
            'description'     => '未認証 API リクエストで Route [login] not defined が発生',
            'exception_class' => '%RouteNotFoundException%',
            'message_like'    => '%Route [login] not defined%',
            'fixed_at'        => '2026-05-12',
            'fix_ref'         => 'bootstrap/app.php — render hook で 401 JSON に変換 (既存実装)',
        ],
        [
            'id'              => 'EL-003-bug-report-flow',
            'description'     => 'バグ報告ステータス遷移時のエラー (通常管理者でも reporter 自己解決可に変更)',
            'exception_class' => null,
            'message_like'    => '%報告者は対応済み確認依頼中の報告を解決済み%',
            'fixed_at'        => '2026-05-12',
            'fix_ref'         => 'BugReportController::updateStatus — reporter 本人は自由遷移可',
        ],
        [
            'id'              => 'EL-004-cross-classroom-leak',
            'description'     => 'マスター管理者の workspace 切替時に他教室の生徒・活動が表示される問題',
            'exception_class' => null,
            'message_like'    => '%submission_requests table not available%Undefined variable%students%',
            'fixed_at'        => '2026-05-12',
            'fix_ref'         => 'DashboardController::summary — $students 定義の修正 + accessibleIds スコープ修正',
        ],
        [
            'id'              => 'EL-005-holiday-422',
            'description'     => '/admin/holidays で休日追加時に classroom_id 必須エラー (422)',
            'exception_class' => null,
            'message_like'    => '%classroom_id%The classroom id field is required%',
            'fixed_at'        => '2026-05-12',
            'fix_ref'         => 'HolidayController::store — 非マスターは自教室を auto-fill',
        ],
        [
            'id'              => 'EL-006-pusher-0000',
            'description'     => 'Broadcast (Reverb) クライアントが 0.0.0.0:8080 に接続して失敗',
            'exception_class' => '%BroadcastException%',
            'message_like'    => '%Pusher error%Failed to connect to 0.0.0.0 port 8080%',
            'fixed_at'        => '2026-05-12',
            'fix_ref'         => '.env REVERB_HOST=0.0.0.0 → reverb (Docker service name)',
        ],
        [
            'id'              => 'EL-007-failed-jobs-missing',
            'description'     => 'キュー失敗ジョブ記録時に failed_jobs テーブルが存在しない',
            'exception_class' => '%QueryException%',
            'message_like'    => '%relation "failed_jobs" does not exist%',
            'fixed_at'        => '2026-05-12',
            'fix_ref'         => 'migrations/2026_05_12_010001_create_failed_jobs_table.php 追加',
        ],
        [
            'id'              => 'EL-008-reverb-sigint',
            'description'     => 'Reverb サーバー起動時の SIGINT 定数未定義 (pcntl 拡張未ロード)',
            'exception_class' => '%Error%',
            'message_like'    => '%Undefined constant%Reverb%SIGINT%',
            'fixed_at'        => '2026-05-12',
            'fix_ref'         => 'docker/php/Dockerfile に pcntl 拡張を追加',
        ],
        [
            'id'              => 'EL-009-seeder-typo',
            'description'     => '存在しない Seeder クラスの手動指定ミス (操作ミス、コード変更不要)',
            'exception_class' => '%BindingResolutionException%',
            'message_like'    => '%DatabaseSeedersEmploymentDemoSeeder%',
            'fixed_at'        => '2026-05-12',
            'fix_ref'         => 'ユーザ操作ミス。正しくは Database\\Seeders\\EmploymentDemoSeeder',
        ],
        [
            'id'              => 'EL-010-cache-path',
            'description'     => 'ビュー/キャッシュ ディレクトリ未作成での一時エラー',
            'exception_class' => '%InvalidArgumentException%',
            'message_like'    => '%Please provide a valid cache path%',
            'fixed_at'        => '2026-05-12',
            'fix_ref'         => '初期化タイミングの一時的問題。現在は解消済み',
        ],
    ];

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $listOnly = (bool) $this->option('list');

        if ($listOnly) {
            return $this->listUnresolved();
        }

        $this->info($apply ? '=== APPLY モード (実際に更新します) ===' : '=== DRY-RUN モード (--apply で実行します) ===');
        $this->newLine();

        $totalMatched = 0;
        $totalResolved = 0;

        foreach (self::PATTERNS as $pattern) {
            $query = ErrorLog::where('is_resolved', false);

            if (!empty($pattern['exception_class'])) {
                $query->where('exception_class', 'like', $pattern['exception_class']);
            }
            if (!empty($pattern['message_like'])) {
                $query->where('message', 'like', $pattern['message_like']);
            }

            $count = $query->count();

            $this->line(sprintf(
                '[%s] %s',
                str_pad((string) $count, 4, ' ', STR_PAD_LEFT) . '件',
                $pattern['description']
            ));
            $this->line('  exception: ' . ($pattern['exception_class'] ?? '*'));
            $this->line('  message:   ' . ($pattern['message_like'] ?? '*'));
            $this->line('  fix:       ' . $pattern['fix_ref']);

            if ($count > 0) {
                $totalMatched += $count;

                if ($apply) {
                    $note = sprintf(
                        '[auto-resolved %s] %s (%s)',
                        now()->toDateString(),
                        $pattern['description'],
                        $pattern['fix_ref'],
                    );
                    $updated = $query->update([
                        'is_resolved'   => true,
                        'resolved_note' => $note,
                    ]);
                    $totalResolved += $updated;
                    $this->info("  → {$updated} 件を resolved にマークしました");
                } else {
                    $totalMatched += $count;
                }
            }
            $this->newLine();
        }

        $this->info("=== 集計 ===");
        $this->info("対象件数: {$totalMatched}");
        if ($apply) {
            $this->info("更新件数: {$totalResolved}");
        } else {
            $this->warn("dry-run のため更新していません。--apply で実行してください。");
        }

        // パターンマッチしなかった未対処エラーを表示
        $remaining = ErrorLog::where('is_resolved', false)->count();
        $this->line('');
        $this->info("未対処エラー総数 (更新前): " . ErrorLog::count());
        $this->info("解決済み合計 (更新前): " . ErrorLog::where('is_resolved', true)->count());
        $this->info("未対処 (更新前):      " . $remaining);

        return self::SUCCESS;
    }

    /**
     * 未対処エラーを exception_class + message プレフィックスで集計して表示する。
     */
    private function listUnresolved(): int
    {
        $this->info('=== 未対処エラー集計 ===');

        $logs = ErrorLog::where('is_resolved', false)
            ->orderByDesc('created_at')
            ->get(['id', 'level', 'exception_class', 'message', 'url', 'created_at']);

        if ($logs->isEmpty()) {
            $this->info('未対処のエラーはありません。');
            return self::SUCCESS;
        }

        // exception_class + message の先頭 100 文字でグルーピング
        $groups = $logs->groupBy(function ($log) {
            $msg = mb_substr($log->message ?? '', 0, 100);
            return ($log->exception_class ?? '?') . ' | ' . $msg;
        });

        $this->line(sprintf('%-6s | %-7s | %s', '件数', 'level', 'exception | message'));
        $this->line(str_repeat('-', 100));

        foreach ($groups->sortByDesc(fn ($g) => $g->count()) as $key => $items) {
            $this->line(sprintf(
                '%-6d | %-7s | %s',
                $items->count(),
                $items->first()->level,
                $key,
            ));
        }

        $this->newLine();
        $this->info('合計: ' . $logs->count() . ' 件');
        $this->info('全体件数: ' . ErrorLog::count() . ' 件 (resolved 含む)');

        return self::SUCCESS;
    }
}
