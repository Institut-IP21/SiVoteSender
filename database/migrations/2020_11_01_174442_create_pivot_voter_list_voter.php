<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePivotVoterListVoter extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('voterlist_voter', function (Blueprint $table) {
            $table->foreignId('voterlist_id');
            $table->foreignId('voter_id');
            $table->timestamps();
            $table->index('voterlist_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('voterlist_voter');
    }
}
