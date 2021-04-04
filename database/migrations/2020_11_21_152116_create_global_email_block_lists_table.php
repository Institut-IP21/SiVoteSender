<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateGlobalEmailBlockListsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('global_email_block_lists', function (Blueprint $table) {
            $table->string('email');
            $table->enum(
                'status',
                [
                    'bounce',
                    'complaint'
                ]
            );

            $table->string('status_msg')->nullable();
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
        Schema::dropIfExists('global_email_block_lists');
    }
}
