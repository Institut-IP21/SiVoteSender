<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Free-text columns that were VARCHAR(255) with no max-length cap on the
     * way in. `status_msg` is the sharpest case: the SNS webhook stores the
     * raw SES `diagnosticCode`, and a long remote-MTA diagnostic overflowed
     * it, 500ing the webhook so the bounce was never recorded.
     * Widening only; nullability is kept as originally defined.
     */
    public function up(): void
    {
        Schema::table('voterlists', function (Blueprint $table): void {
            $table->text('title')->change();
        });

        Schema::table('verifications', function (Blueprint $table): void {
            $table->text('subject')->nullable()->change();
            $table->text('redirect_url')->nullable()->change();
        });

        Schema::table('sent_messages', function (Blueprint $table): void {
            $table->text('status_msg')->nullable()->comment('Used for any error messages returned by sender')->change();
        });

        Schema::table('global_email_block_lists', function (Blueprint $table): void {
            $table->text('status_msg')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('voterlists', function (Blueprint $table): void {
            $table->string('title')->change();
        });

        Schema::table('verifications', function (Blueprint $table): void {
            $table->string('subject')->nullable()->change();
            $table->string('redirect_url')->nullable()->change();
        });

        Schema::table('sent_messages', function (Blueprint $table): void {
            $table->string('status_msg')->nullable()->comment('Used for any error messages returned by sender')->change();
        });

        Schema::table('global_email_block_lists', function (Blueprint $table): void {
            $table->string('status_msg')->nullable()->change();
        });
    }
};
