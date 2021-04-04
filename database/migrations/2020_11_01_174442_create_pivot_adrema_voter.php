<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePivotAdremaVoter extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('adrema_voter', function (Blueprint $table) {
            $table->foreignId('adrema_id');
            $table->foreignId('voter_id');
            $table->timestamps();
            $table->index('adrema_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('adrema_voter');
    }
}
