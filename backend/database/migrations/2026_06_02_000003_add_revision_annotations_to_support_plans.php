<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 個別支援計画に「原案からの変更点(注釈)」を保存する列を追加。
 *
 * 要望: 保護者コメントと個別支援会議の議事録を反映して本案の下書きを AI 生成する際、
 *       原案を全面書き換えせず一部の削除・追加にとどめ、原案からの変更点を
 *       注釈として確認できるようにする。その変更点(項目ごとの追記/削除と理由)を保存する。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('individual_support_plans', function (Blueprint $table) {
            $table->jsonb('revision_annotations')->nullable()
                ->comment('原案→本案下書きの変更点(項目ごとの追記/削除と理由)の注釈');
            $table->timestampTz('revision_generated_at')->nullable()
                ->comment('本案下書きを AI 生成した日時');
        });
    }

    public function down(): void
    {
        Schema::table('individual_support_plans', function (Blueprint $table) {
            $table->dropColumn(['revision_annotations', 'revision_generated_at']);
        });
    }
};
