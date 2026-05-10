<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 月次工賃台帳明細 (利用者ごとの工賃明細)。
 *
 * 差分カテゴリ: schema
 * 背景: WagePeriod に紐づく利用者別の工賃計算結果を保持する。
 *       student_records (連絡帳の出退勤・作業内容) から WageCalculationService が集計して書き込む。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wage_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wage_period_id')->constrained('wage_periods')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();

            // 集計ベース
            $table->integer('attendance_days')->default(0)->comment('出勤日数');
            $table->integer('total_work_minutes')->default(0)->comment('総作業時間 (分)');
            $table->decimal('wage_eligible_hours', 8, 2)->default(0)
                ->comment('工賃対象時間 (時間)');

            // 計算方式と単価のスナップショット
            $table->string('calculation_type', 20)->nullable()
                ->comment('hourly | piece_rate | fixed');
            $table->decimal('hourly_rate', 8, 2)->nullable()->comment('適用時給 (円)');
            $table->decimal('piece_rate_amount', 10, 2)->default(0)
                ->comment('出来高合計 (円)');

            // 計算結果
            $table->decimal('base_wage', 10, 2)->default(0)->comment('基本工賃 (円)');
            $table->integer('overtime_minutes')->default(0)->comment('時間外労働 (分) — A型のみ');
            $table->decimal('overtime_wage', 10, 2)->default(0)->comment('時間外手当 (円)');
            $table->decimal('bonus', 10, 2)->default(0)->comment('賞与・手当 (円)');
            $table->decimal('deductions', 10, 2)->default(0)->comment('控除 (円)');
            $table->decimal('net_wage', 10, 2)->default(0)->comment('支給額 (円)');

            $table->text('notes')->nullable();
            $table->timestamp('calculated_at')->nullable();
            $table->timestampsTz();

            $table->unique(['wage_period_id', 'student_id']);
            $table->index('student_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wage_records');
    }
};
