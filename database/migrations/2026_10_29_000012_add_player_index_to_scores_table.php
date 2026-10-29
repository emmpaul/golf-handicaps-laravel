<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The scores of one player (the STATS block and the history of the Handicaps page, design
     * D13 and DO41 of visual-redesign): the unique (round_id, player_id, hole) starts with
     * round_id and cannot serve a filter on the player alone.
     */
    public function up(): void
    {
        Schema::table('scores', function (Blueprint $table) {
            $table->index('player_id');
        });
    }

    public function down(): void
    {
        Schema::table('scores', function (Blueprint $table) {
            $table->dropIndex(['player_id']);
        });
    }
};
