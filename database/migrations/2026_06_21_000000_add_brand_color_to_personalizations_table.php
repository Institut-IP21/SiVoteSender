<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The org accent colour, mirrored from web_app (engine is the source of truth)
     * so the invite/result emails can render the brand colour on the action button.
     * Nullable — orgs without a chosen colour fall back to the default theme.
     */
    public function up(): void
    {
        Schema::table('personalizations', function (Blueprint $table): void {
            $table->string('brand_color', 7)->nullable()->after('photo_url');
        });
    }

    public function down(): void
    {
        Schema::table('personalizations', function (Blueprint $table): void {
            $table->dropColumn('brand_color');
        });
    }
};
