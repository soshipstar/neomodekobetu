<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 能力評価システム P2: 観察記録(T_観察記録)。
 *
 * 日々の活動記録入力時に、トグルON教室で児童ごとに成長段階に合った設問を1問提示し、
 * 支援者が「支援コード(SUP0〜6)+結果+新規場面+見られた行動(文言)」で回答した記録を貯める。
 * 直近3か月の観察を(児童×項目)で集計してスコアを更新するのは後続フェーズ(P3)。
 *
 * 分類: schema
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ability_observations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('classroom_id')->constrained('classrooms')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignId('daily_record_id')->nullable()->constrained('daily_records')->nullOnDelete()
                ->comment('入力元の活動記録(日付・場面の出所)');
            $table->string('item_id', 20)->comment('設問の評価項目(M_項目)');
            $table->string('axis_id', 8)->comment('出題時の児童の成長段階軸(S1〜S6 等)');
            $table->string('support_code', 8)->nullable()->comment('提供した支援(SUP0〜SUP6)');
            $table->string('result', 16)->nullable()->comment('completed/partial/refused');
            $table->boolean('is_new_scene')->default(false)->comment('新規場面か(般化判定用)');
            $table->text('behavior')->nullable()->comment('見られた行動(事実)');
            $table->date('observed_date');
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->foreign('item_id')->references('item_id')->on('ability_eval_items')->cascadeOnDelete();
            $table->foreign('axis_id')->references('axis_id')->on('ability_eval_axes');
            $table->foreign('support_code')->references('code')->on('ability_support_codes');

            $table->index(['student_id', 'item_id']);
            $table->index(['classroom_id', 'observed_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ability_observations');
    }
};
