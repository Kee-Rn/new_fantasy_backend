<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Aggregated scorecard stats per player per match.
        // Auto-calculated from ball_by_ball entries OR entered manually via CMS.
        // fantasy_points is computed by PointsCalculator service after stats are finalised.
        Schema::create('player_performances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('match_player_id')
                  ->unique()                           // 1-to-1: one performance per match_player
                  ->constrained('match_players')
                  ->onDelete('cascade');

            // ── Batting ────────────────────────────────────────────────
            $table->unsignedSmallInteger('runs')->default(0);
            $table->unsignedSmallInteger('balls_faced')->default(0);
            $table->unsignedTinyInteger('fours')->default(0);
            $table->unsignedTinyInteger('sixes')->default(0);
            $table->enum('out_status', ['out', 'not_out', 'dnb'])
                  ->default('dnb');                    // dnb = did not bat

            // ── Bowling ────────────────────────────────────────────────
            $table->decimal('overs', 4, 1)->default(0.0); // e.g. 3.4 = 3 overs 4 balls
            $table->unsignedSmallInteger('bowling_runs')->default(0);  // runs conceded
            $table->unsignedTinyInteger('wickets')->default(0);
            $table->unsignedTinyInteger('maidens')->default(0);
            $table->boolean('lbw_or_bowled')->default(false); // any wicket was LBW or bowled
            $table->unsignedTinyInteger('no_balls')->default(0);
            $table->unsignedTinyInteger('wides')->default(0);

            // ── Fielding ───────────────────────────────────────────────
            $table->unsignedTinyInteger('catches')->default(0);
            $table->unsignedTinyInteger('stumpings')->default(0);
            $table->unsignedTinyInteger('run_outs')->default(0);

            // ── Wicketkeeper extras ────────────────────────────────────
            $table->unsignedTinyInteger('byes')->default(0);
            $table->unsignedTinyInteger('leg_byes')->default(0);

            // ── Computed fantasy score ─────────────────────────────────
            // Populated by PointsCalculator after match_players.is_playing_xi is confirmed
            $table->integer('fantasy_points')->default(0);

            $table->timestamps();

            $table->index('match_player_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('player_performances');
    }
};
