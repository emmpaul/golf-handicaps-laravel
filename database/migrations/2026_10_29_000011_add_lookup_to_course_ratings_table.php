<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** The checks added by up(), dropped by down() before their columns. */
    private const array CHECKS = [
        'course_ratings_status_check',
        'course_ratings_source_check',
        'course_ratings_rating_slope_check',
        'course_ratings_rating_source_check',
        'course_ratings_confirmed_rating_check',
    ];

    /**
     * The lookup of the rating on Simulator Golf Tour (design D1 of course-rating-sgt): one row
     * per course and tees from the first round, its rating and slope set only once confirmed.
     * Every row written before is a rating typed by hand, hence confirmed.
     */
    public function up(): void
    {
        Schema::table('course_ratings', function (Blueprint $table) {
            $table->smallInteger('rating_t')->nullable()->change();
            $table->smallInteger('slope')->nullable()->change();
            $table->string('source', 10)->nullable();
            $table->string('status', 20)->default('confirmed');
            $table->integer('sgt_course_id')->nullable();
            $table->string('sgt_course_name', 200)->nullable();
            $table->string('sgt_course_location', 200)->nullable();
            // [{id, name, location, par}] of the SGT courses to choose from.
            $table->jsonb('candidates')->nullable();
            // [{name, ratingT, slope}] read on the SGT card, rating in tenths.
            $table->jsonb('tees_read')->nullable();
            $table->string('proposed_tee', 100)->nullable();
            $table->string('note', 200)->nullable();
        });

        DB::table('course_ratings')->update(['source' => 'manual']);
        DB::statement('ALTER TABLE course_ratings ALTER COLUMN status DROP DEFAULT');

        DB::statement("ALTER TABLE course_ratings ADD CONSTRAINT course_ratings_status_check CHECK (status IN ('pending', 'proposed', 'choose_course', 'choose_tee', 'manual', 'not_found', 'failed', 'confirmed'))");
        DB::statement("ALTER TABLE course_ratings ADD CONSTRAINT course_ratings_source_check CHECK (source IN ('sgt', 'manual'))");
        DB::statement('ALTER TABLE course_ratings ADD CONSTRAINT course_ratings_rating_slope_check CHECK ((rating_t IS NULL) = (slope IS NULL))');
        DB::statement('ALTER TABLE course_ratings ADD CONSTRAINT course_ratings_rating_source_check CHECK ((rating_t IS NULL) = (source IS NULL))');
        DB::statement("ALTER TABLE course_ratings ADD CONSTRAINT course_ratings_confirmed_rating_check CHECK (status <> 'confirmed' OR rating_t IS NOT NULL)");
    }

    /**
     * Back to the ratings typed by hand: the lookups without a confirmed value are lost.
     */
    public function down(): void
    {
        DB::table('course_ratings')->whereNull('rating_t')->delete();

        foreach (self::CHECKS as $check) {
            DB::statement("ALTER TABLE course_ratings DROP CONSTRAINT {$check}");
        }

        Schema::table('course_ratings', function (Blueprint $table) {
            $table->dropColumn(['source', 'status', 'sgt_course_id', 'sgt_course_name', 'sgt_course_location', 'candidates', 'tees_read', 'proposed_tee', 'note']);
            $table->smallInteger('rating_t')->nullable(false)->change();
            $table->smallInteger('slope')->nullable(false)->change();
        });
    }
};
