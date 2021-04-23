<?php

use App\Models\SentMessage;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSentMessagesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('sent_messages', function (Blueprint $table) {
            $table->id();
            $table->enum('type', ['sms', 'email']);
            $table->foreignId('voter_id');
            $table->foreignId('voterlist_id');
            $table->uuid('batch_uuid')->nullable();
            $table->foreignId('verification_id')->nullable();
            $table->boolean('successful')->default(false);

            $table->enum(
                'status',
                [
                    'sent',
                    'delivered',
                    'soft-bounce',
                    'bounce',
                    'complaint',
                    'blocked'
                ]
            )->default('sent');

            $table->string('status_msg')
                ->nullable()
                ->comment('Used for any error messages returned by sender');

            $table->softDeletes('deleted_at');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('sent_messages');
    }
}
