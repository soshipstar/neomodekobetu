<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * timestamp (without time zone) で残っていた業務記録系カラムを timestamptz に変換する。
 *
 * 放デイ業務リスク監査 SCHEMA-02/03/08 (P1):
 *  過去に複数回 (2026_03_18 / 04_16) のタイムゾーン修復 migration を打ったが、
 *  以降に追加されたテーブルや一部カラムが timestamp (tz なし) のまま残り、
 *  JST/UTC 混在で日時がズレるリスクがあった。特に監査証跡 (master_admin_audit_logs)
 *  や工賃・代理店精算など、日付境界が金額・法定記録に影響する箇所が対象。
 *
 * 変換方針 (PostgreSQL):
 *  既存の timestamp 値は「JST のローカル時刻」として保存されてきた前提で、
 *  `USING col AT TIME ZONE 'Asia/Tokyo'` により timestamptz (UTC 基準) に変換する。
 *  これにより以降の表示・集計が正しい JST 境界で行われる。
 *
 * 注: Stripe 連携系 (invoices/subscriptions 等) は外部の UTC タイムスタンプを
 *  そのまま受け取るため変換ルールが異なる。誤変換を避けるため本 migration の
 *  対象からは除外し、別途 Stripe webhook 側で timestamptz 保存に統一する
 *  (今後の課題として docs/data-retention.md 系に記録)。
 *
 * PostgreSQL 以外 (sqlite テスト等) では timestamp 型に時差概念がないため no-op。
 */
return new class extends Migration
{
    /** 変換対象: テーブル => [カラム, ...] */
    private array $targets = [
        'master_admin_audit_logs' => ['created_at'],
        'wage_periods'            => ['created_at', 'updated_at'],
        'wage_records'            => ['created_at', 'updated_at'],
        'agent_payouts'           => ['created_at', 'updated_at'],
    ];

    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return; // sqlite/mysql テスト環境では no-op
        }

        foreach ($this->targets as $table => $columns) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            foreach ($columns as $column) {
                if (! Schema::hasColumn($table, $column)) {
                    continue;
                }
                // 既に timestamptz なら skip
                $type = DB::selectOne(
                    'SELECT data_type FROM information_schema.columns WHERE table_name = ? AND column_name = ?',
                    [$table, $column]
                );
                if ($type && str_contains((string) $type->data_type, 'with time zone')) {
                    continue;
                }

                DB::statement(sprintf(
                    'ALTER TABLE %s ALTER COLUMN %s TYPE timestamptz USING %s AT TIME ZONE %s',
                    $table,
                    $column,
                    $column,
                    "'Asia/Tokyo'"
                ));
            }
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        foreach ($this->targets as $table => $columns) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            foreach ($columns as $column) {
                if (! Schema::hasColumn($table, $column)) {
                    continue;
                }
                // timestamptz → timestamp (JST ローカル時刻として戻す)
                DB::statement(sprintf(
                    'ALTER TABLE %s ALTER COLUMN %s TYPE timestamp USING %s AT TIME ZONE %s',
                    $table,
                    $column,
                    $column,
                    "'Asia/Tokyo'"
                ));
            }
        }
    }
};
