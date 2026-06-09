<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // match_players is the playing squad for each team in a match.
        // Admin selects these via CMS before the match starts.
        // Fantasy users pick from this pool when building their fantasy team.
        Schema::create('match_players', function (Blueprint $table) {
            $table->id();
            $table->foreignId('match_id')
                  ->constrained('matches')
                  ->onDelete('cascade');
            $table->foreignId('player_id')
                  ->constrained('players')
                  ->onDelete('cascade');
            $table->foreignId('team_id')
                  ->constrained('teams')
                  ->onDelete('cascade');

            // is_playing_xi = confirmed in the starting 11
            // is_bench      = in the squad but not playing
            // Both false    = announced in squad, xi not yet confirmed
            $table->boolean('is_playing_xi')->default(false);
            $table->boolean('is_bench')->default(false);

            // Batting order (1-11), set once xi is confirmed
            $table->unsignedTinyInteger('batting_order')->nullable();

            $table->timestamps();

            // One player can only appear once per match
            $table->unique(['match_id', 'player_id']);

            $table->index('match_id');
            $table->index('player_id');
            $table->index('team_id');
            $table->index(['match_id', 'team_id']);
            $table->index(['match_id', 'is_playing_xi']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('match_players');
    }
};
