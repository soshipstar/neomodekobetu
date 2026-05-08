<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 欠席連絡 (absence_notifications) に体調・対応関連カラムを追加する。
 *
 * 背景: 淡田由貴さん (てらこやプラス) からの機能要望。
 * 「欠席時対応加算の欄に体温記入、通院の有無、ほかの症状（腹痛、頭痛、咽頭痛、咳、
 *  くしゃみ、鼻水）のチェック欄、その他困っていること、アドバイスを記入できると
 *  よいなと思いました」
 *
 * 入力者の責任分担:
 *  - 保護者: body_temperature / hospital_visit / symptom_* / other_concerns
 *  - スタッフ: advice (advice_by, advice_at は自動記録)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('absence_notifications', function (Blueprint $table) {
            // 保護者入力
            $table->decimal('body_temperature', 4, 1)->nullable()->after('reason')->comment('体温 (摂氏)');
            $table->boolean('hospital_visit')->default(false)->after('body_temperature')->comment('通院の有無');
            $table->boolean('symptom_abdominal_pain')->default(false)->after('hospital_visit')->comment('症状: 腹痛');
            $table->boolean('symptom_headache')->default(false)->after('symptom_abdominal_pain')->comment('症状: 頭痛');
            $table->boolean('symptom_sore_throat')->default(false)->after('symptom_headache')->comment('症状: 咽頭痛');
            $table->boolean('symptom_cough')->default(false)->after('symptom_sore_throat')->comment('症状: 咳');
            $table->boolean('symptom_sneeze')->default(false)->after('symptom_cough')->comment('症状: くしゃみ');
            $table->boolean('symptom_runny_nose')->default(false)->after('symptom_sneeze')->comment('症状: 鼻水');
            $table->text('other_concerns')->nullable()->after('symptom_runny_nose')->comment('その他困っていること (保護者入力)');

            // スタッフ入力
            $table->text('advice')->nullable()->after('other_concerns')->comment('スタッフからのアドバイス');
            $table->foreignId('advice_by')->nullable()->after('advice')->constrained('users')->nullOnDelete()->comment('アドバイス入力スタッフID');
            $table->timestampTz('advice_at')->nullable()->after('advice_by')->comment('アドバイス入力日時');
        });
    }

    public function down(): void
    {
        Schema::table('absence_notifications', function (Blueprint $table) {
            $table->dropConstrainedForeignId('advice_by');
            $table->dropColumn([
                'body_temperature',
                'hospital_visit',
                'symptom_abdominal_pain',
                'symptom_headache',
                'symptom_sore_throat',
                'symptom_cough',
                'symptom_sneeze',
                'symptom_runny_nose',
                'other_concerns',
                'advice',
                'advice_at',
            ]);
        });
    }
};
