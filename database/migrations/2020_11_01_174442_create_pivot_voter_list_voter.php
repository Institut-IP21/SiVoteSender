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
    public function up(): void
    {
        Schema::create('voterlist_voter', function (Blueprint $table): void {
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
    public function down(): void
    {
        Schema::dropIfExists('voterlist_voter');
    }
}
