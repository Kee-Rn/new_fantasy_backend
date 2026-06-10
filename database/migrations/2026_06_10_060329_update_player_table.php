<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('players', function (Blueprint $table) {
            $table->dropColumn(['batting_style', 'bowling_style', 'nationality']);

            // Rename photo_url to photo_path for local storage
            $table->renameColumn('photo_url', 'photo_path');
        });
    }

    public function down(): void
    {
        Schema::table('players', function (Blueprint $table) {
            $table->string('batting_style')->nullable();
            $table->string('bowling_style')->nullable();
            $table->string('nationality')->nullable();

            $table->renameColumn('photo_path', 'photo_url');
        });
    }
};