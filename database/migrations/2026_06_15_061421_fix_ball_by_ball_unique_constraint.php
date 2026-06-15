<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
{
    Schema::table('ball_by_ball', function (Blueprint $table) {
        // Drop old constraint — ball_number is now delivery sequence not legal ball
        $table->dropUnique('uq_delivery');
        // New unique: match + innings + over + delivery sequence (always unique)
        $table->unique(['match_id', 'innings', 'over_number', 'ball_number'], 'uq_delivery');
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
