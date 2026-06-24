<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 能力評価: 段階別の具体設問(項目×学年帯ごとの「指導員が答えられる問い」)。
 *
 * 到達目安(M_到達目安)を根拠に、各 (項目, 学年帯) に対して具体的で観察可能な問いと
 * 観察のヒントを保持する。AIで一括生成し、編集・再生成も可能(is_active で出題可否)。
 * 日々の出題(AbilityQuestionService::buildQuestion)はこの問いを優先して表示する。
 *
 * 分類: schema(段階別具体設問機能 P-C の一部)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ability_stage_questions', function (Blueprint $table) {
            $table->id();
            $table->string('item_id', 20)->comment('評価項目(M_項目)');
            $table->string('axis_id', 8)->comment('学年帯/段階(評価軸)');
            $table->text('question')->comment('指導員が「どれくらいできているか」で答えられる具体的な問い');
            $table->text('hint')->nullable()->comment('観察の手がかり(具体例)');
            $table->string('model', 40)->nullable()->comment('生成に用いたモデル');
            $table->boolean('is_active')->default(true)->comment('日々の出題で使うか');
            $table->timestampTz('generated_at')->nullable()->comment('AI生成日時');
            $table->timestampTz('reviewed_at')->nullable()->comment('人によるレビュー日時');
            $table->timestamps();

            $table->unique(['item_id', 'axis_id']);
            $table->foreign('item_id')->references('item_id')->on('ability_eval_items')->cascadeOnDelete();
            $table->foreign('axis_id')->references('axis_id')->on('ability_eval_axes');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ability_stage_questions');
    }
};
