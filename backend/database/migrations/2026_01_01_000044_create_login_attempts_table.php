<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('login_attempts', function (Blueprint $table) {
            $table->id();
            $table->string('username', 100);
            $table->string('ip_address', 45);
            $table->string('user_agent', 500)->nullable();
            $table->foreignId('user_id')->nullable();
            $table->boolean('success')->default(false);
            $table->timestampTz('attempted_at')->useCurrent();

            $table->index('username');
            $table->index('ip_address');
            $table->index('attempted_at');
            $table->index(['username', 'ip_address']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('login_attempts');
    }
};
