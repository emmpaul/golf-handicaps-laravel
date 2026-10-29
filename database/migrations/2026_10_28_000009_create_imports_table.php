<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('imports', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('status', 10);
            // Relative to the photos disk; the round saved from the import keeps the same path.
            $table->string('photo_path');
            // The card read by the AI (App\Domain\Imports\ScorecardDraft::toArray()).
            $table->jsonb('draft')->nullable();
            // The editor state saved by the autosave: { version: 1, card }.
            $table->jsonb('correction')->nullable();
            // Why the reading failed, shown on the import screen.
            $table->string('failure', 200)->nullable();
            // Deleting the round deletes its import; its photo is deleted by DeleteRound.
            $table->foreignUuid('round_id')->nullable()->unique()->constrained()->cascadeOnDelete();
            $table->timestamps();

            // The list of imports: most recent first.
            $table->index('created_at');
        });

        // Blueprint has no CHECK.
        DB::statement("ALTER TABLE imports ADD CONSTRAINT imports_status_check CHECK (status IN ('queued', 'reading', 'ready', 'failed'))");
        DB::statement("ALTER TABLE imports ADD CONSTRAINT imports_ready_draft_check CHECK (status <> 'ready' OR draft IS NOT NULL)");
    }

    public function down(): void
    {
        Schema::dropIfExists('imports');
    }
};
