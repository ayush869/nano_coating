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
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('table_name', 50)->index();
            $table->unsignedBigInteger('record_id');
            $table->string('action', 10); // created | updated
            $table->json('old_json')->nullable();
            $table->json('new_json')->nullable();
            $table->timestamp('at')->useCurrent();
        });

        // Running counters for document numbers, one row per prefix + financial year.
        Schema::create('sequences', function (Blueprint $table) {
            $table->string('key', 40)->primary(); // e.g. QT/2026-27
            $table->unsignedInteger('last')->default(0);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sequences');
        Schema::dropIfExists('audit_logs');
    }
};
