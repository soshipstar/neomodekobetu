<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Laravel のキュー失敗ジョブ記録テーブル。
 *
 * 本テーブルが存在しないと、queue:work の retry 上限を超えたジョブを
 * Illuminate\Queue\Failed\DatabaseUuidFailedJobProvider が失敗記録しようとして
 *   SQLSTATE[42P01]: Undefined table: relation "failed_jobs" does not exist
 * を投げ、error_logs に蓄積される。
 *
 * 構造は Laravel 標準 (`php artisan queue:failed-table`) のテンプレートに従う。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('failed_jobs', function (Blueprint $table) {
            $table->id();
            $table->string('uuid')->unique();
            $table->text('connection');
            $table->text('queue');
            $table->longText('payload');
            $table->longText('exception');
            $table->timestampTz('failed_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('failed_jobs');
    }
};
