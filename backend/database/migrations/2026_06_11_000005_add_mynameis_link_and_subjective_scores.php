<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 能力評価システム P5: mynameis(本人の主観的自己評価, https://fesvol.xyz)連携。
 *
 * 連携方式(ユーザー合意): kiduri 受信(共有シークレット)方式。kiduri が児童↔mynameis user_id の
 * マッピングを保持し(契約上 kiduri 側が保持)、mynameis から push される主観プロフィール
 * (項目ごとの1〜5 Likert)を受信して貯める。客観評価(ability_scores)と並べて統合表示する。
 * 項目コード(DEV-1-1 等)は両アプリで同一マスタ由来。
 *
 * 分類: schema
 */
return new class extends Migration
{
    public function up(): void
    {
        // 児童 ↔ mynameis ユーザーの紐づけ(結合キー = mynameis users.id)
        Schema::table('students', function (Blueprint $table) {
            $table->unsignedBigInteger('mynameis_user_id')->nullable()
                ->comment('紐づく mynameis(自己評価アプリ)の users.id');
            $table->timestamp('mynameis_linked_at')->nullable();
            $table->index('mynameis_user_id');
        });

        // mynameis から受信した主観自己評価(項目ごとの最新値。上書き=最新が現在値)
        Schema::create('ability_subjective_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->string('item_id', 20)->comment('評価項目(両アプリ共通コード)');
            $table->string('axis_id', 8)->nullable()->comment('mynameis 側で回答した軸(任意)');
            $table->unsignedTinyInteger('response_value')->comment('主観 1〜5 Likert');
            $table->timestamp('responded_at')->nullable()->comment('mynameis 側の回答日時');
            $table->string('source', 20)->default('mynameis');
            $table->timestamps();

            $table->foreign('item_id')->references('item_id')->on('ability_eval_items')->cascadeOnDelete();
            $table->unique(['student_id', 'item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ability_subjective_scores');
        Schema::table('students', function (Blueprint $table) {
            $table->dropColumn(['mynameis_user_id', 'mynameis_linked_at']);
        });
    }
};
