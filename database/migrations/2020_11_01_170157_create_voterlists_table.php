<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateVoterListsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('voterlists', function (Blueprint $table): void {
            $table->id();
            $table->string('owner', 36)->comment('Should be UUID of owner');
            $table->string('title');
            $table->timestamps();
            $table->softDeletes('deleted_at');

            $table->index('owner');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('voterlists');
    }
}
