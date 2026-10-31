<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The key drawn on the device for each photo it keeps (offline-pwa, design D8): a replayed
     * upload of the same key finds its import instead of creating a second one. Nullable: the
     * imports sent before this change, and the previous code after a rollback, have none.
     */
    public function up(): void
    {
        Schema::table('imports', function (Blueprint $table) {
            $table->uuid('upload_key')->nullable()->unique();
        });
    }

    public function down(): void
    {
        Schema::table('imports', function (Blueprint $table) {
            $table->dropUnique(['upload_key']);
            $table->dropColumn('upload_key');
        });
    }
};
