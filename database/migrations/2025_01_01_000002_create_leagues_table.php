<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leagues', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('short_name', 20)->nullable();
            $table->string('season')->nullable();         // e.g. "2025", "2024-25"
            $table->string('country')->nullable();
            $table->enum('match_type', ['T20', 'ODI', 'Test', 'T10'])->default('T20');
            $table->string('logo_url')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('is_active');
            $table->index(['name', 'season']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leagues');
    }
};
