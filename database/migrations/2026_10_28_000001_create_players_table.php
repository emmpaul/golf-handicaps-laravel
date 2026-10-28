<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('players', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name', 50);
            $table->timestamps();
        });

        // Blueprint cannot express an index on an expression: one name per player, whatever the case.
        DB::statement('CREATE UNIQUE INDEX players_name_lower_unique ON players (lower(name))');
    }

    public function down(): void
    {
        Schema::dropIfExists('players');
    }
};
