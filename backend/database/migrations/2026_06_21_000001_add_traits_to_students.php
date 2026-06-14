<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * AI学習基盤 S4e: 多次元分析の「特性」軸を児童マスタに追加する。
 *
 *  - 統制語彙(App\Support\StudentTrait)のコード配列を保持する。自由記述PIIは入れない。
 *  - 要配慮個人情報。集計のみに使用(同意済み・k匿名)。プロンプトには既定で入れない。
 *  - 記録時点の値は ai_revision_events.dim_meta['traits'] に凍結(学習同意がある場合のみ)。
 *
 * 分類: schema
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->jsonb('traits')->nullable()->after('gender')
                ->comment('S4e 多次元分析の特性軸(要配慮)。StudentTrait統制コードの配列。自由記述PII不可・集計のみ');
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropColumn('traits');
        });
    }
};
