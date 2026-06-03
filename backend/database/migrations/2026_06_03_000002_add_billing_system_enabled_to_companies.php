<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 国保連請求システム(kiduriacount)連携を「企業単位」で使う/使わないを管理する。
 *
 * 要望: 連携の利用可否は教室(事業所)単位ではなく企業単位で設定したい。
 *       企業管理から切り替え、その企業に紐づくすべての事業所(classrooms)に一括適用する。
 *
 * 既存の classrooms.billing_system_enabled は引き続き「実際の判定(ヘッダ表示/
 * SSOチケット発行)」に使う。本フラグ(企業)を切り替えると配下の全 classroom へ反映する。
 * 既定は false(無効)。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->boolean('billing_system_enabled')->default(false)
                ->comment('国保連請求システム(kiduriacount)連携を企業単位で使うか(配下の全事業所へ適用)');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn('billing_system_enabled');
        });
    }
};
