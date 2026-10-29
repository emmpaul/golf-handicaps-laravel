<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row per billed AI call, the shape of the Next.js base so that its import copies it.
        Schema::create('ai_usage', function (Blueprint $table) {
            $table->uuid('id')->primary();
            // YYYY-MM in the budget time zone, fixed when the call is recorded.
            $table->char('month', 7)->index();
            $table->string('model', 100);
            $table->integer('input_tokens');
            $table->integer('output_tokens');
            $table->bigInteger('cost_micro_usd');
            $table->timestamp('created_at')->nullable();
        });

        DB::statement('ALTER TABLE ai_usage ADD CONSTRAINT ai_usage_input_tokens_check CHECK (input_tokens >= 0)');
        DB::statement('ALTER TABLE ai_usage ADD CONSTRAINT ai_usage_output_tokens_check CHECK (output_tokens >= 0)');
        DB::statement('ALTER TABLE ai_usage ADD CONSTRAINT ai_usage_cost_micro_usd_check CHECK (cost_micro_usd >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_usage');
    }
};
