<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** docs/11-database-design.md §2.2 — single-use, 5-minute nonces (NFR-SEC-10). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wallet_nonces', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->char('nonce', 32)->unique();
            $table->text('message');
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallet_nonces');
    }
};
