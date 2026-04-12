<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 連絡帳 (IntegratedNote) に添付される写真の pivot。
     *
     * 実ファイルは classroom_photos.file_path に 1 箇所だけ保存され、
     * 複数の連絡帳や施設通信・チャットから参照される設計。
     * この pivot はどの連絡帳にどの写真を添付するかを表す。
     */
    public function up(): void
    {
        Schema::create('integrated_note_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('integrated_note_id')->constrained('integrated_notes')->cascadeOnDelete();
            $table->foreignId('classroom_photo_id')->constrained('classroom_photos')->cascadeOnDelete();
            $table->integer('sort_order')->default(0);
            $table->unique(['integrated_note_id', 'classroom_photo_id'], 'integrated_note_photo_unique');
            $table->index('classroom_photo_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integrated_note_photos');
    }
};
