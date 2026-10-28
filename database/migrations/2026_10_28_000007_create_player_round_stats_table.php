<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The handicap history of each player of each round, derived from the rounds and the
     * ratings: RecomputePlayerStats rewrites it inside every write. Differentials and indexes
     * are integer tenths (174 = 17,4), as in the Next.js application.
     */
    public function up(): void
    {
        Schema::create('player_round_stats', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('round_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('player_id')->index()->constrained()->restrictOnDelete();
            $table->smallInteger('gross');
            $table->smallInteger('adjusted');
            $table->integer('differential_t');
            // Null for the first round of the player.
            $table->integer('index_before_t')->nullable();
            $table->integer('index_after_t');
            // The differential is retained in the current index of the player.
            $table->boolean('counts_in_current')->default(false);

            // A row belongs to a line of its round: removing the line removes the row, even if
            // a recompute were forgotten.
            $table->foreign(['round_id', 'player_id'])
                ->references(['round_id', 'player_id'])->on('round_players')
                ->cascadeOnDelete();
            $table->unique(['round_id', 'player_id']);
        });

        // The index is capped at 54,0.
        DB::statement('ALTER TABLE player_round_stats ADD CONSTRAINT player_round_stats_index_before_t_check CHECK (index_before_t <= 540)');
        DB::statement('ALTER TABLE player_round_stats ADD CONSTRAINT player_round_stats_index_after_t_check CHECK (index_after_t <= 540)');
    }

    public function down(): void
    {
        Schema::dropIfExists('player_round_stats');
    }
};
