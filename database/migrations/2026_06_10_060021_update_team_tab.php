<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->dropColumn(['short_name', 'country', 'city']);

            // Rename logo_url to logo_path for local storage
            $table->renameColumn('logo_url', 'logo_path');
        });
    }

    public function down(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->string('short_name', 10)->nullable();
            $table->string('country')->nullable();
            $table->string('city')->nullable();

            $table->renameColumn('logo_path', 'logo_url');
        });
    }
};