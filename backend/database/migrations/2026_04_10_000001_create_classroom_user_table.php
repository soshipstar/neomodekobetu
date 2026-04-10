<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * ユーザーと教室の多対多リレーション用中間テーブル
     * users.classroom_id は「現在アクティブな教室」として残し、
     * classroom_user は「所属している教室の一覧」を表す
     */
    public function up(): void
    {
        Schema::create('classroom_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('classroom_id')->constrained('classrooms')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'classroom_id']);
            $table->index('classroom_id');
        });

        // 既存のusers.classroom_idを中間テーブルに複製
        DB::statement('
            INSERT INTO classroom_user (user_id, classroom_id, created_at, updated_at)
            SELECT id, classroom_id, NOW(), NOW()
            FROM users
            WHERE classroom_id IS NOT NULL
            ON CONFLICT (user_id, classroom_id) DO NOTHING
        ');
    }

    public function down(): void
    {
        Schema::dropIfExists('classroom_user');
    }
};
