<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_face_sheets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->jsonb('daily_life')->nullable()->comment('日常生活のこと（食事・排泄・入浴・清潔・着脱・日常生活・社会生活）');
            $table->jsonb('physical')->nullable()->comment('身体面（床上動作・移動）');
            $table->jsonb('profile')->nullable()->comment('性格・趣味・得意/苦手・コミュニケーション');
            $table->jsonb('considerations')->nullable()->comment('配慮事項（身体面/医療面・医療的ケア・行動面）');
            $table->text('memo')->nullable()->comment('MEMO欄');
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();

            $table->unique('student_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_face_sheets');
    }
};
