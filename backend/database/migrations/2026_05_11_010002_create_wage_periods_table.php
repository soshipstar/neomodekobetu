<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 月次工賃台帳ヘッダー (事業所 × 年月)。
 *
 * 差分カテゴリ: schema
 * 背景: 就労 A/B では月単位で工賃を集計・確定・支払いする。
 *       事業所ごとに月次の状態 (下書き/確定/支払い済) を持たせる。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wage_periods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('classroom_id')->constrained('classrooms')->cascadeOnDelete();
            $table->string('year_month', 7)->comment('YYYY-MM');
            $table->string('status', 20)->default('draft')
                ->comment('draft | finalized | paid');
            $table->date('settlement_date')->nullable()->comment('締め日');
            $table->date('payment_date')->nullable()->comment('支払日');
            $table->timestamp('finalized_at')->nullable();
            $table->foreignId('finalized_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('paid_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestampsTz();

            $table->unique(['classroom_id', 'year_month']);
            $table->index('year_month');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wage_periods');
    }
};
