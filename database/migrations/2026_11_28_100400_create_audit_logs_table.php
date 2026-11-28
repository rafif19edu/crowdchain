<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** docs/11-database-design.md §2.14 — append-only (NFR-SEC-14). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actor_id')->nullable()->constrained('users'); // null = system (scheduler)
            $table->string('action', 40);
            $table->nullableMorphs('auditable');
            $table->text('reason')->nullable();
            $table->char('tx_hash', 66)->nullable();
            $table->jsonb('metadata')->nullable();
            $table->ipAddress('ip_address')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['action', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
