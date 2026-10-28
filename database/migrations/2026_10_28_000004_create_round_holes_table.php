<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('round_holes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('round_id')->constrained()->cascadeOnDelete();
            $table->smallInteger('hole');
            $table->smallInteger('par');
            $table->smallInteger('stroke_index');
            $table->smallInteger('distance')->nullable();

            $table->unique(['round_id', 'hole']);
            $table->unique(['round_id', 'stroke_index']);
        });

        // Blueprint has no CHECK: a second line behind RoundCardChecks, which refuses first.
        DB::statement('ALTER TABLE round_holes ADD CONSTRAINT round_holes_hole_check CHECK (hole BETWEEN 1 AND 18)');
        DB::statement('ALTER TABLE round_holes ADD CONSTRAINT round_holes_par_check CHECK (par BETWEEN 3 AND 5)');
        DB::statement('ALTER TABLE round_holes ADD CONSTRAINT round_holes_stroke_index_check CHECK (stroke_index BETWEEN 1 AND 18)');
        DB::statement('ALTER TABLE round_holes ADD CONSTRAINT round_holes_distance_check CHECK (distance BETWEEN 1 AND 999)');
    }

    public function down(): void
    {
        Schema::dropIfExists('round_holes');
    }
};
