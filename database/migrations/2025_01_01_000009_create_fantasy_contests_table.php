<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A fantasy contest is the game users join for a specific match.
        // One match can have multiple contests (e.g. Grand League, Head-to-Head, Free).
        Schema::create('fantasy_contests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('match_id')
                  ->constrained('matches')
                  ->onDelete('cascade');

            $table->string('name');                         // e.g. "Grand League", "Head-to-Head"
            $table->unsignedInteger('entry_fee')->default(0);       // in smallest currency unit (paise/cents)
            $table->unsignedInteger('prize_pool')->default(0);
            $table->unsignedSmallInteger('max_teams')->default(0);  // 0 = unlimited
            $table->unsignedSmallInteger('total_teams')->default(0);// filled as users join

            $table->enum('status', [
                'upcoming',
                'active',
                'completed',
                'cancelled',
            ])->default('upcoming');

            // Deadline for fantasy team submissions (usually match start_time)
            $table->dateTime('deadline_at')->nullable();

            // Points pipeline status
            $table->enum('points_status', [
                'pending',
                'calculating',
                'calculated',
                'failed',
            ])->default('pending');
            $table->dateTime('points_calculated_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('match_id');
            $table->index('status');
            $table->index('deadline_at');
            $table->index('points_status');
            $table->index(['match_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fantasy_contests');
    }
};
