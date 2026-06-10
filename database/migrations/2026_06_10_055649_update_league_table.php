<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leagues', function (Blueprint $table) {
            $table->dropColumn(['short_name', 'country', 'match_type']);

            // Rename logo_url to logo_path for local storage
            $table->renameColumn('logo_url', 'logo_path');
        });
    }

    public function down(): void
    {
        Schema::table('leagues', function (Blueprint $table) {
            $table->string('short_name', 20)->nullable();
            $table->string('country')->nullable();
            $table->enum('match_type', ['T20', 'ODI', 'Test', 'T10'])->default('T20');

            $table->renameColumn('logo_path', 'logo_url');
        });
    }
};