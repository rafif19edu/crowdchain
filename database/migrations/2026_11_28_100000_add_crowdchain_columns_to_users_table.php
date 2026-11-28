<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** docs/11-database-design.md §2.1 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->ulid('ulid')->unique()->after('id');
            $table->string('phone', 20)->nullable()->after('email');
            $table->string('role', 12)->after('password');
            $table->string('status', 12)->default('active')->after('role');
            $table->text('suspended_reason')->nullable()->after('status');
            $table->string('wallet_address', 42)->nullable()->unique()->after('suspended_reason');
            $table->timestamp('wallet_linked_at')->nullable()->after('wallet_address');
            $table->softDeletes();
            $table->index(['role', 'status']);
        });

        DB::statement("ALTER TABLE users ADD CONSTRAINT users_role_check CHECK (role IN ('admin','donor','fundraiser'))");
        DB::statement("ALTER TABLE users ADD CONSTRAINT users_status_check CHECK (status IN ('active','suspended'))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_role_check');
        DB::statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_status_check');

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['role', 'status']);
            $table->dropSoftDeletes();
            $table->dropColumn(['ulid', 'phone', 'role', 'status', 'suspended_reason', 'wallet_address', 'wallet_linked_at']);
        });
    }
};
