<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 事業所 (classrooms) に正式な service_type カラムを追加する。
 *
 * 既存設計では settings JSONB に「放課後等デイサービス」「児童発達支援」等の
 * 自由記述で持っていたが、これを ENUM 風 varchar カラムに正規化する。
 *
 * 値:
 *   after_school   : 放課後等デイサービス（児童発達支援も含む）
 *   employment_a   : 就労継続支援A型
 *   employment_b   : 就労継続支援B型
 *   transition     : 就労移行支援
 *
 * 既存データはすべて after_school にマイグレーションする。
 * (settings.service_type が「放課後等デイサービス」「児童発達支援」のみのため)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('classrooms', function (Blueprint $table) {
            $table->string('service_type', 30)->default('after_school')->after('classroom_name')
                ->comment('after_school / employment_a / employment_b / transition');
        });

        // 値ドメインを CHECK 制約で固定
        DB::statement("ALTER TABLE classrooms ADD CONSTRAINT classrooms_service_type_check
            CHECK (service_type IN ('after_school', 'employment_a', 'employment_b', 'transition'))");

        // 既存事業所の settings.service_type を見て値を決定する。
        // 「放課後等デイサービス」「児童発達支援」「未設定」はすべて after_school。
        // 将来的に手動で書き換えていた就労系がある場合のみ拾う。
        DB::table('classrooms')->orderBy('id')->each(function ($room) {
            $settings = json_decode($room->settings ?? '{}', true) ?: [];
            $legacy = (string) ($settings['service_type'] ?? '');
            $service = match (true) {
                str_contains($legacy, '就労継続支援A') || str_contains($legacy, '就A') => 'employment_a',
                str_contains($legacy, '就労継続支援B') || str_contains($legacy, '就B') => 'employment_b',
                str_contains($legacy, '就労移行')                                         => 'transition',
                default                                                                  => 'after_school',
            };
            DB::table('classrooms')->where('id', $room->id)->update(['service_type' => $service]);
        });
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE classrooms DROP CONSTRAINT IF EXISTS classrooms_service_type_check');

        Schema::table('classrooms', function (Blueprint $table) {
            $table->dropColumn('service_type');
        });
    }
};
