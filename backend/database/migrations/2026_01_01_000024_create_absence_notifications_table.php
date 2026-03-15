<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('absence_notifications', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('message_id')->nullable();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->date('absence_date');
            $table->text('reason')->nullable();
            $table->date('makeup_request_date')->nullable();
            $table->string('makeup_status', 20)->default('none')->comment('none, pending, approved, rejected');
            $table->foreignId('makeup_approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('makeup_approved_at')->nullable();
            $table->text('makeup_note')->nullable();
            $table->timestampTz('created_at')->useCurrent();

            $table->index('student_id');
            $table->index('absence_date');
        });

        DB::statement("ALTER TABLE absence_notifications ADD CONSTRAINT absence_notifications_makeup_status_check CHECK (makeup_status IN ('none', 'pending', 'approved', 'rejected'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('absence_notifications');
    }
};
