<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('classroom_id')->nullable()->constrained('classrooms')->cascadeOnDelete();
            $table->string('username', 50)->unique();
            $table->string('password', 255);
            $table->string('full_name', 100);
            $table->string('user_type', 20)->default('staff')->comment('admin, staff, guardian, tablet_user');
            $table->boolean('is_master')->default(false);
            $table->string('email', 100)->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('password_plain', 255)->nullable()->comment('初期パスワード表示用');
            $table->timestampTz('email_verified_at')->nullable();
            $table->timestampTz('last_login_at')->nullable();
            $table->timestampsTz();
        });

        // CHECK constraint for user_type
        DB::statement("ALTER TABLE users ADD CONSTRAINT users_user_type_check CHECK (user_type IN ('admin', 'staff', 'guardian', 'tablet_user'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
