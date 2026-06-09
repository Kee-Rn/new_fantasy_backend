<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The 11 players a user selected for their fantasy team.
        // Captain gets 2× points, Vice-Captain gets 1.5× points.
        // `points` is the final multiplied score for this slot (set after calculation).
        Schema::create('fantasy_team_players', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fantasy_team_id')
                  ->constrained('fantasy_teams')
                  ->onDelete('cascade');
            $table->foreignId('player_id')
                  ->constrained('players')
                  ->onDelete('cascade');

            $table->boolean('is_captain')->default(false);
            $table->boolean('is_vice_captain')->default(false);

            // Raw fantasy points from player_performances (before multiplier)
            $table->integer('base_points')->default(0);

            // Final points after applying captain/vice-captain multiplier
            $table->integer('points')->default(0);

            $table->timestamps();

            // One player per team (no duplicates)
            $table->unique(['fantasy_team_id', 'player_id']);

            $table->index('fantasy_team_id');
            $table->index('player_id');
            $table->index('is_captain');
            $table->index('is_vice_captain');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fantasy_team_players');
    }
};
