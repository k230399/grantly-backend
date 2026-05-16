<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Review notes are now strictly admin-only. Applicant-facing comms goes through
    // status-change notes on application_status_history, so the public/internal split adds no value.
    public function up(): void
    {
        Schema::table('review_notes', function (Blueprint $table) {
            $table->dropColumn('is_internal');
        });
    }

    public function down(): void
    {
        Schema::table('review_notes', function (Blueprint $table) {
            $table->boolean('is_internal')->default(true);
        });
    }
};
