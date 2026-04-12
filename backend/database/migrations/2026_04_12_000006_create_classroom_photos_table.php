<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 事業所ごとの共有写真ライブラリ。
     *
     * - 写真は事業所単位で管理し、容量制限 100MB までとする (file_size の SUM)
     * - 圧縮後 ≤100KB の JPEG を保存
     * - アップロード時に活動内容・日時・写っている児童をメタデータとして記録
     * - 写真自体は物理的に 1 ファイル。チャットや施設通信からは
     *   file_path を参照するだけで、ファイル本体は複製しない
     * - storage: storage/app/public/classroom_photos/{classroom_id}/{uuid}.jpg
     *
     * 児童タグ用の中間テーブルを別途 classroom_photo_student に作る。
     */
    public function up(): void
    {
        Schema::create('classroom_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('classroom_id')->constrained('classrooms')->cascadeOnDelete();
            $table->foreignId('uploader_id')->constrained('users');
            $table->string('file_path', 500);       // storage 相対パス
            $table->integer('file_size');           // bytes
            $table->string('mime', 64);
            $table->integer('width')->nullable();
            $table->integer('height')->nullable();
            $table->text('activity_description')->nullable();
            $table->date('activity_date')->nullable();
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->useCurrent();

            $table->index(['classroom_id', 'activity_date']);
            $table->index('uploader_id');
        });

        Schema::create('classroom_photo_student', function (Blueprint $table) {
            $table->id();
            $table->foreignId('classroom_photo_id')->constrained('classroom_photos')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->unique(['classroom_photo_id', 'student_id'], 'classroom_photo_student_unique');
            $table->index('student_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('classroom_photo_student');
        Schema::dropIfExists('classroom_photos');
    }
};
