<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 国保連 (WAM-NET) 請求に必要な事業所属性を classrooms に追加。
 *
 * 差分カテゴリ: schema
 * 背景: 障害福祉サービス費等請求情報インターフェース仕様で必須となる
 *       事業所番号 (10桁) / 都道府県コード (2桁) / 当該事業所の主使用
 *       サービスコード (6桁) を持たせる。これがないと WAM-NET CSV を
 *       生成しても国保連へ提出できない。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('classrooms', function (Blueprint $table) {
            $table->string('wam_office_code', 10)->nullable()
                ->comment('事業所番号 (国保連登録 10桁)');
            $table->string('prefecture_code', 2)->nullable()
                ->comment('都道府県コード (2桁)');
            $table->string('wam_service_code_default', 6)->nullable()
                ->comment('主使用サービスコード (6桁) — 加算なしの基本単位');
            $table->integer('wam_unit_price_yen')->default(10)
                ->comment('1単位の単価 (円、地域区分により 10〜12 程度)');
        });
    }

    public function down(): void
    {
        Schema::table('classrooms', function (Blueprint $table) {
            $table->dropColumn(['wam_office_code', 'prefecture_code', 'wam_service_code_default', 'wam_unit_price_yen']);
        });
    }
};
