<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Permanently-failed outbound e-mails — the alert aggregation buffer and an
     * audit trail. No foreign keys: a record must outlive a deleted voter row.
     */
    public function up(): void
    {
        Schema::create('email_send_failures', function (Blueprint $table): void {
            $table->id();
            $table->string('recipient')->nullable();
            $table->string('mailable')->nullable();
            $table->unsignedBigInteger('voter_id')->nullable();
            $table->unsignedBigInteger('sent_message_id')->nullable();
            $table->text('error')->nullable();
            $table->unsignedInteger('attempts')->default(0);
            // NULL until included in a dispatched alert.
            $table->timestamp('alerted_at')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_send_failures');
    }
};
