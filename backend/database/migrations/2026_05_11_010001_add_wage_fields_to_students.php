<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 就労利用者の工賃計算に必要な項目を students に追加。
 *
 * 差分カテゴリ: schema
 * 背景: 工賃管理 (A型は時給、B型は出来高 or 時給) と有給休暇 (A型のみ義務) を
 *       利用者ごとに保持する。放デイ利用者では使わない。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            // 工賃計算方式: hourly (時給) / piece_rate (出来高) / fixed (固定)
            $table->string('wage_calculation_type', 20)->nullable()
                ->comment('就労系: hourly | piece_rate | fixed');
            // 時給 (A型は最低賃金以上必須)
            $table->decimal('hourly_rate', 8, 2)->nullable()
                ->comment('就労系の時給 (円)');
            // 出来高単価の単位 (B型用)
            $table->string('piece_rate_unit', 50)->nullable()
                ->comment('B型: 出来高単位 (例: 1袋, 1個, 1作業)');
            $table->decimal('piece_rate_amount', 8, 2)->nullable()
                ->comment('B型: 出来高単価');
            // 有給休暇残日数 (A型のみ)
            $table->decimal('paid_leave_days', 5, 1)->default(0)
                ->comment('A型: 有給休暇残日数');
            // 雇用形態 (A型のみ)
            $table->string('employment_status', 30)->nullable()
                ->comment('A型: full_time | part_time | trainee');
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropColumn([
                'wage_calculation_type',
                'hourly_rate',
                'piece_rate_unit',
                'piece_rate_amount',
                'paid_leave_days',
                'employment_status',
            ]);
        });
    }
};
