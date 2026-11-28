<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** docs/11-database-design.md §2.3 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organizations', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('user_id')->unique()->constrained();

            $table->string('registration_authority', 4);
            $table->string('main_state', 30)->nullable();
            $table->string('name', 200);
            $table->string('registration_no', 60);
            $table->date('registration_date');
            $table->date('expiry_date');
            $table->string('institution_category', 30);
            $table->string('ros_ssm_no', 60)->nullable();
            $table->text('address');
            $table->char('postcode', 5);
            $table->string('district', 60);
            $table->string('state', 30);
            $table->string('phone', 20);
            $table->string('email', 190);
            $table->unsignedInteger('beneficiaries_count');

            $table->string('bank_name', 60);
            $table->text('bank_account_no');          // encrypted cast (T7)
            $table->string('bank_account_holder', 200);
            $table->boolean('bank_holder_matches')->default(false); // FR-UM-07

            $table->string('pic_name', 120);
            $table->text('pic_ic_no');                // encrypted cast (T7)
            $table->string('pic_position', 80);
            $table->string('pic_phone', 20);
            $table->string('pic_email', 190);

            $table->string('status', 12)->default('pending');
            $table->text('rejection_reason')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users');
            $table->timestamp('expiry_reminder_sent_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['registration_authority', 'registration_no']);
            $table->index('status');
            $table->index('expiry_date');
        });

        DB::statement("ALTER TABLE organizations ADD CONSTRAINT organizations_status_check CHECK (status IN ('pending','verified','rejected','expired'))");
        DB::statement("ALTER TABLE organizations ADD CONSTRAINT organizations_authority_check CHECK (registration_authority IN ('jkm','main'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('organizations');
    }
};
