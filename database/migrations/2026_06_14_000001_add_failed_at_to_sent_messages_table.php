<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Marks a permanently-failed send, distinguishing it from one still in
     * flight (status='sent', failed_at IS NULL) without touching the status enum.
     */
    public function up(): void
    {
        Schema::table('sent_messages', function (Blueprint $table): void {
            $table->timestamp('failed_at')->nullable()->after('status_msg')->index();
        });
    }

    public function down(): void
    {
        Schema::table('sent_messages', function (Blueprint $table): void {
            $table->dropColumn('failed_at');
        });
    }
};
