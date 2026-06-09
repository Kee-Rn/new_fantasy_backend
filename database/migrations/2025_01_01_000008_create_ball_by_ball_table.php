<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Stores every delivery in a match entered ball-by-ball via CMS.
        // The BallByBallStatsService aggregates these into player_performances.
        Schema::create('ball_by_ball', function (Blueprint $table) {
            $table->id();

            // ── Match context ──────────────────────────────────────────
            $table->foreignId('match_id')
                  ->constrained('matches')
                  ->onDelete('cascade');

            $table->unsignedTinyInteger('innings');          // 1 or 2
            $table->unsignedTinyInteger('over_number');      // 0-based: over 1 = 0, over 2 = 1 …
            $table->unsignedTinyInteger('ball_number');      // Legal delivery: 1-6. Extras: 7+

            // ── Players involved ───────────────────────────────────────
            $table->foreignId('batsman_id')
                  ->constrained('players')
                  ->onDelete('cascade');

            $table->foreignId('bowler_id')
                  ->constrained('players')
                  ->onDelete('cascade');

            // ── Runs off the bat ───────────────────────────────────────
            $table->unsignedTinyInteger('runs_off_bat')->default(0);  // 0, 1, 2, 3, 4, 6
            $table->boolean('is_four')->default(false);
            $table->boolean('is_six')->default(false);

            // ── Extras ─────────────────────────────────────────────────
            $table->enum('extra_type', [
                'wide',
                'no_ball',
                'bye',
                'leg_bye',
                'penalty',
            ])->nullable();                                  // null = no extra
            $table->unsignedTinyInteger('extra_runs')->default(0);

            // ── Wicket ─────────────────────────────────────────────────
            $table->boolean('is_wicket')->default(false);
            $table->enum('wicket_type', [
                'bowled',
                'lbw',
                'caught',
                'caught_and_bowled',
                'run_out',
                'stumped',
                'hit_wicket',
                'obstructing_field',
                'handled_ball',
                'retired_hurt',
                'timed_out',
            ])->nullable();

            // Player who got out — may differ from batsman_id on a run-out
            $table->foreignId('dismissed_player_id')
                  ->nullable()
                  ->constrained('players')
                  ->onDelete('set null');

            // Catcher / stumper / fielder on a run-out
            $table->foreignId('fielder_id')
                  ->nullable()
                  ->constrained('players')
                  ->onDelete('set null');

            // ── Running totals ─────────────────────────────────────────
            // Stored after each ball for cheap live score reads
            $table->unsignedSmallInteger('total_runs_after')->default(0);
            $table->unsignedTinyInteger('total_wickets_after')->default(0);

            // ── Notes ──────────────────────────────────────────────────
            $table->string('notes')->nullable();             // e.g. "DRS review", "Power play ends"

            $table->timestamps();

            // ── Indexes ────────────────────────────────────────────────
            $table->index('match_id');
            $table->index(['match_id', 'innings']);
            $table->index('batsman_id');
            $table->index('bowler_id');

            // Prevent duplicate delivery entry from CMS
            $table->unique(
                ['match_id', 'innings', 'over_number', 'ball_number'],
                'uq_delivery'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ball_by_ball');
    }
};
