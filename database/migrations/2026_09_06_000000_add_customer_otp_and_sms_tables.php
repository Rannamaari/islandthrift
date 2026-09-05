<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table): void {
            $table->timestamp('phone_verified_at')->nullable()->after('phone');
            $table->index(['company_id', 'phone']);
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->string('phone')->nullable()->after('email');
        });

        Schema::create('customer_otp_challenges', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('company_id');
            $table->string('phone', 10);
            $table->string('code_hash');
            $table->string('purpose')->default('registration');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('expires_at');
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('consumed_at')->nullable();
            $table->string('request_ip', 45)->nullable();
            $table->timestamps();

            $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
            $table->index(['company_id', 'phone', 'created_at']);
            $table->index('expires_at');
        });

        Schema::create('sms_delivery_logs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('company_id')->nullable();
            $table->uuid('user_id')->nullable();
            $table->string('purpose')->default('general');
            $table->string('source');
            $table->string('transaction_id')->nullable();
            $table->text('transaction_description')->nullable();
            $table->string('reference_number')->nullable();
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->unsignedInteger('recipient_count')->default(0);
            $table->unsignedInteger('sent_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->boolean('successful')->default(false);
            $table->boolean('dry_run')->default(false);
            $table->json('invalid_recipients')->nullable();
            $table->json('gateway_response')->nullable();
            $table->timestamp('sent_at');
            $table->timestamps();

            $table->foreign('company_id')->references('id')->on('companies')->nullOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            $table->index(['company_id', 'sent_at']);
            $table->index(['successful', 'sent_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sms_delivery_logs');
        Schema::dropIfExists('customer_otp_challenges');

        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('phone'));
        Schema::table('customers', function (Blueprint $table): void {
            $table->dropIndex(['company_id', 'phone']);
            $table->dropColumn('phone_verified_at');
        });
    }
};
