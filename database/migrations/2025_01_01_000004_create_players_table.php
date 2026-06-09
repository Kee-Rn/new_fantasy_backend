<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('players', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')
                  ->nullable()
                  ->constrained('teams')
                  ->onDelete('set null');  // null = unsold / no current team
            $table->string('name');
            $table->enum('role', ['WK', 'BAT', 'ALL', 'BOWL']);
            $table->enum('batting_style', [
                'Right-hand bat',
                'Left-hand bat',
            ])->nullable();
            $table->enum('bowling_style', [
                'Right-arm fast',
                'Right-arm medium',
                'Right-arm off-break',
                'Right-arm leg-break',
                'Left-arm fast',
                'Left-arm medium',
                'Left-arm orthodox',
                'Left-arm wrist-spin',
            ])->nullable();
            $table->string('nationality')->nullable();
            $table->string('photo_url')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('team_id');
            $table->index('role');
            $table->index('name');
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('players');
    }
};
