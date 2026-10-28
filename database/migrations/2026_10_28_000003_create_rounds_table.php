<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rounds', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('course_name', 100);
            $table->string('tees', 100);
            $table->date('played_on');
            // Relative to the photos disk; null for a round typed by hand.
            $table->string('photo_path')->nullable();
            $table->timestamps();

            // The list of rounds: most recent first, by date of play then by date of saving.
            $table->index(['played_on', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rounds');
    }
};
