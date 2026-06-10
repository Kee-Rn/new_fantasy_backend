<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('matches', function (Blueprint $table) {
            $table->dropColumn(['match_number', 'match_type', 'venue', 'city']);
        });
    }

    public function down(): void
    {
        Schema::table('matches', function (Blueprint $table) {
            $table->unsignedSmallInteger('match_number')->nullable();
            $table->enum('match_type', ['T20', 'ODI', 'Test', 'T10'])->default('T20');
            $table->string('venue')->nullable();
            $table->string('city')->nullable();
        });
    }
};