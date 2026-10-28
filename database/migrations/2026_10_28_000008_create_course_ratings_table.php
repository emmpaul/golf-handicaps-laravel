<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The course rating and slope typed for a course and tees. A row exists only once a rating
     * is typed: without a row, the computation falls back on the par and 113.
     */
    public function up(): void
    {
        Schema::create('course_ratings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('course_name', 100);
            $table->string('tees', 100);
            // In tenths: 714 = 71,4.
            $table->smallInteger('rating_t');
            $table->smallInteger('slope');
            $table->timestamps();
        });

        // Blueprint cannot express an index on an expression: one rating per course and tees,
        // whatever the case and the surrounding spaces, the key of KnownCourses.
        DB::statement('CREATE UNIQUE INDEX course_ratings_course_tees_unique ON course_ratings ((lower(trim(course_name))), (lower(trim(tees))))');
        DB::statement('ALTER TABLE course_ratings ADD CONSTRAINT course_ratings_rating_t_check CHECK (rating_t BETWEEN 400 AND 900)');
        DB::statement('ALTER TABLE course_ratings ADD CONSTRAINT course_ratings_slope_check CHECK (slope BETWEEN 55 AND 155)');
    }

    public function down(): void
    {
        Schema::dropIfExists('course_ratings');
    }
};
