<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Drop is_internal from review_notes.
     * Review notes are now strictly admin-only — applicant-facing communication
     * lives on status-change notes (application_status_history.notes), so the
     * public/internal split adds no value.
     */
    public function up(): void
    {
        Schema::table('review_notes', function (Blueprint $table) {
            $table->dropColumn('is_internal');
        });
    }

    /**
     * Restore the column with its original default for clean rollbacks.
     */
    public function down(): void
    {
        Schema::table('review_notes', function (Blueprint $table) {
            $table->boolean('is_internal')->default(true);
        });
    }
};
