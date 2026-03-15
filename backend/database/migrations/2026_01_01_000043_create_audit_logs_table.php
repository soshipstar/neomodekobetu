<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 50)->comment('create, update, delete, login, logout');
            $table->string('target_table', 100)->nullable()->comment('対象テーブル名');
            $table->unsignedBigInteger('target_id')->nullable()->comment('対象レコードID');
            $table->jsonb('old_values')->nullable()->comment('変更前の値');
            $table->jsonb('new_values')->nullable()->comment('変更後の値');
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->timestampTz('created_at')->useCurrent();

            $table->index('user_id');
            $table->index('action');
            $table->index(['target_table', 'target_id']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
