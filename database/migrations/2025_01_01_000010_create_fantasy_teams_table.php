<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A user's fantasy team entered into a specific contest.
        // One user can create multiple teams in the same contest (if allowed).
        Schema::create('fantasy_teams', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')
                  ->constrained('users')
                  ->onDelete('cascade');
            $table->foreignId('contest_id')
                  ->constrained('fantasy_contests')
                  ->onDelete('cascade');

            $table->string('team_name', 100);
            $table->integer('total_points')->default(0);
            $table->unsignedSmallInteger('rank')->nullable(); // filled after points_calculated

            $table->timestamps();

            // By default one team per user per contest.
            // Remove this unique constraint if you want to allow multiple teams.
            $table->unique(['user_id', 'contest_id']);

            $table->index('contest_id');
            $table->index('user_id');
            $table->index(['contest_id', 'total_points']); // for leaderboard ordering
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fantasy_teams');
    }
};
