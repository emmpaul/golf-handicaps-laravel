<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scores', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('round_id')->constrained()->cascadeOnDelete();
            $table->uuid('player_id');
            $table->smallInteger('hole');
            $table->smallInteger('strokes');

            // A score belongs to a line of its round: no score for a player outside the round.
            $table->foreign(['round_id', 'player_id'])
                ->references(['round_id', 'player_id'])->on('round_players')
                ->cascadeOnDelete();
            $table->unique(['round_id', 'player_id', 'hole']);
        });

        DB::statement('ALTER TABLE scores ADD CONSTRAINT scores_hole_check CHECK (hole BETWEEN 1 AND 18)');
        DB::statement('ALTER TABLE scores ADD CONSTRAINT scores_strokes_check CHECK (strokes BETWEEN 1 AND 15)');
    }

    public function down(): void
    {
        Schema::dropIfExists('scores');
    }
};
