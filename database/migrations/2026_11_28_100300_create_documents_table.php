<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** docs/11-database-design.md §2.4 — every uploaded file. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->morphs('documentable');
            $table->string('type', 30);
            $table->string('disk', 20);
            $table->string('path', 255);
            $table->string('original_name', 255);
            $table->string('mime', 60);
            $table->unsignedInteger('size_bytes');
            $table->char('sha256', 64);
            $table->string('visibility', 8);
            $table->foreignId('uploaded_by')->constrained('users');
            $table->timestamps();

            $table->index(['documentable_type', 'documentable_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};
