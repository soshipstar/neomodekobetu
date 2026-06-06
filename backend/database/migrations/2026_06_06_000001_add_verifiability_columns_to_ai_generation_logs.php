<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * AIセーフティ ガイドライン 観点10「検証可能性」対応。
 * 事後検証・再現に必要な情報(推論パラメータ・systemプロンプト・参照情報)を
 * ai_generation_logs に記録できるようにする。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_generation_logs', function (Blueprint $table) {
            if (! Schema::hasColumn('ai_generation_logs', 'temperature')) {
                $table->decimal('temperature', 4, 2)->nullable()->after('model')->comment('推論温度');
            }
            if (! Schema::hasColumn('ai_generation_logs', 'max_tokens')) {
                $table->integer('max_tokens')->nullable()->after('temperature')->comment('最大出力トークン');
            }
            if (! Schema::hasColumn('ai_generation_logs', 'system_prompt')) {
                $table->text('system_prompt')->nullable()->after('max_tokens')->comment('使用したシステムプロンプト(=版)');
            }
            if (! Schema::hasColumn('ai_generation_logs', 'parameters')) {
                // response_format / embedding_model / 参照情報(referenced) / finish_reason 等
                $table->jsonb('parameters')->nullable()->after('system_prompt')->comment('推論パラメータ・参照情報');
            }
        });
    }

    public function down(): void
    {
        Schema::table('ai_generation_logs', function (Blueprint $table) {
            foreach (['temperature', 'max_tokens', 'system_prompt', 'parameters'] as $col) {
                if (Schema::hasColumn('ai_generation_logs', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
