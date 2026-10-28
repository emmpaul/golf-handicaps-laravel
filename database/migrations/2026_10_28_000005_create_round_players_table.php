<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('round_players', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('round_id')->constrained()->cascadeOnDelete();
            // A player who played a round cannot be deleted.
            $table->foreignUuid('player_id')->index()->constrained()->restrictOnDelete();
            // The order of the lines on the card, from 0.
            $table->smallInteger('position');

            // Also the target of the composite foreign key of scores.
            $table->unique(['round_id', 'player_id']);
            $table->unique(['round_id', 'position']);
        });

        DB::statement('ALTER TABLE round_players ADD CONSTRAINT round_players_position_check CHECK (position BETWEEN 0 AND 3)');
    }

    public function down(): void
    {
        Schema::dropIfExists('round_players');
    }
};
