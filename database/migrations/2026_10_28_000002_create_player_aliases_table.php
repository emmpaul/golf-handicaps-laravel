<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('player_aliases', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('player_id')->index()->constrained()->cascadeOnDelete();
            $table->string('alias', 50);
            $table->timestamps();
        });

        // One player per alias, whatever the case: assigning an alias moves it (AssignAlias).
        DB::statement('CREATE UNIQUE INDEX player_aliases_alias_lower_unique ON player_aliases (lower(alias))');
    }

    public function down(): void
    {
        Schema::dropIfExists('player_aliases');
    }
};
