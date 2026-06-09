<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('matches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('league_id')
                  ->constrained('leagues')
                  ->onDelete('cascade');
            $table->foreignId('home_team_id')
                  ->constrained('teams')
                  ->onDelete('cascade');
            $table->foreignId('away_team_id')
                  ->constrained('teams')
                  ->onDelete('cascade');

            // Set by admin after the toss; drives ball-by-ball innings assignment
            $table->foreignId('batting_first_team_id')
                  ->nullable()
                  ->constrained('teams')
                  ->onDelete('set null');

            $table->unsignedSmallInteger('match_number')->nullable(); // match 1, 2 … in the series
            $table->enum('match_type', ['T20', 'ODI', 'Test', 'T10'])->default('T20');
            $table->enum('status', ['upcoming', 'live', 'completed', 'cancelled'])->default('upcoming');

            $table->dateTime('start_time')->nullable();
            $table->string('venue')->nullable();
            $table->string('city')->nullable();

            // Toss info (filled after toss)
            $table->string('toss_winner')->nullable();          // team name or FK — keep flexible for CMS
            $table->enum('toss_decision', ['bat', 'bowl'])->nullable();

            // Final result (filled after match)
            $table->string('result')->nullable();               // e.g. "Mumbai Indians won by 6 wickets"
            $table->enum('result_type', [
                'runs',
                'wickets',
                'super_over',
                'dls',
                'tie',
                'no_result',
                'abandoned',
            ])->nullable();

            $table->boolean('is_featured')->default(false);     // highlight on home screen

            $table->timestamps();

            $table->index('league_id');
            $table->index('status');
            $table->index('start_time');
            $table->index('is_featured');
            $table->index(['home_team_id', 'away_team_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('matches');
    }
};
