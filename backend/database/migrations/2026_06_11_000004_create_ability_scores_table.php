<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 能力評価システム P3: 評価スコア(T_評価スコア)。
 *
 * 観察記録(ability_observations)を(児童×項目)で直近3か月集計し、評価表の判定フロー
 * (支援量→成功率→般化)で決定的に採点した結果を時系列で追記する。上書きせず履歴を残す。
 * 個人内評価(過去の自分との比較)であり他児比較はしない。
 *
 * 分類: schema
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ability_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->string('item_id', 20)->comment('評価項目(M_項目)');
            $table->string('axis_id', 8)->comment('評価時の成長段階軸');
            $table->unsignedTinyInteger('score')->comment('0〜10');
            $table->unsignedTinyInteger('prev_score')->nullable()->comment('前回点数');
            $table->smallInteger('change')->nullable()->comment('前回からの変化');
            $table->boolean('needs_review')->default(false)->comment('±3点以上の変動など要人間確認');
            $table->string('method', 20)->default('rule_engine')->comment('採点方法');
            $table->foreignId('evaluated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->json('evidence_record_ids')->nullable()->comment('根拠となった観察記録ID');
            $table->text('notes')->nullable()->comment('根拠の自動転記(日付・行動)');
            $table->date('evaluated_on');
            $table->timestamps();

            $table->foreign('item_id')->references('item_id')->on('ability_eval_items')->cascadeOnDelete();
            $table->foreign('axis_id')->references('axis_id')->on('ability_eval_axes');
            $table->index(['student_id', 'item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ability_scores');
    }
};
