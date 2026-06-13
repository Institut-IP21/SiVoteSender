<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateVotersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('voters', function (Blueprint $table): void {
            $table->id();
            $table->string('title')->nullable();
            $table->string('email')->nullable();
            $table->dateTime('email_verified')->nullable();
            $table->boolean('email_blocked')->default(false);
            $table->string('phone')->nullable();
            $table->dateTime('phone_verified')->nullable();
            $table->boolean('phone_blocked')->default(false);
            $table->softDeletes('deleted_at');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('voters');
    }
}
