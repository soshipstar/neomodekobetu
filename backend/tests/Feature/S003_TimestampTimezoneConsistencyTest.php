<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * S003: タイムスタンプのタイムゾーン一貫性テスト (SCHEMA-02/03/08)
 *
 * 差分カテゴリ: schema
 *
 * 放デイ業務リスク監査で検出。過去に複数回のタイムゾーン修復 migration を
 * 打ったが、以降に追加された業務記録系テーブルが timestamp (tz なし) のまま
 * 残り、JST/UTC 混在で日時がズレるリスクがあった。
 *
 * 本テストは、業務記録・監査系テーブルの日時カラムが timestamptz (with time
 * zone) で統一されていることを保証し、再発を防ぐ CI ガードとして機能する。
 *
 * PostgreSQL 以外 (sqlite テスト) では型概念が異なるため skip する。
 */
class S003_TimestampTimezoneConsistencyTest extends TestCase
{
    use RefreshDatabase;

    /**
     * timestamptz であるべき業務記録・監査系テーブルのカラム。
     * 監査 SCHEMA-02/03/08 で指摘された箇所を中心に列挙。
     *
     * @var array<string, string[]>
     */
    private array $mustBeTimestamptz = [
        'master_admin_audit_logs'  => ['created_at'],
        'wage_periods'             => ['created_at', 'updated_at'],
        'wage_records'             => ['created_at', 'updated_at'],
        'agent_payouts'            => ['created_at', 'updated_at'],
        'individual_support_plans' => ['guardian_confirmed_at', 'basis_generated_at'],
        'audit_logs'               => ['created_at'],
        'ai_generation_logs'       => ['created_at'],
    ];

    public function test_business_record_timestamps_are_timezone_aware(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            $this->markTestSkipped('PostgreSQL 以外では timestamptz 型の検証をスキップします。');
        }

        $violations = [];

        foreach ($this->mustBeTimestamptz as $table => $columns) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            foreach ($columns as $column) {
                if (! Schema::hasColumn($table, $column)) {
                    continue;
                }
                $row = DB::selectOne(
                    'SELECT data_type FROM information_schema.columns WHERE table_name = ? AND column_name = ?',
                    [$table, $column]
                );
                if ($row && ! str_contains((string) $row->data_type, 'with time zone')) {
                    $violations[] = "{$table}.{$column} = {$row->data_type}";
                }
            }
        }

        $this->assertEmpty(
            $violations,
            "以下のカラムが timestamptz でなく timestamp のままです (SCHEMA-02/03/08):\n" . implode("\n", $violations)
        );
    }
}
