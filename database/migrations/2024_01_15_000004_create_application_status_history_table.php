<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create the application_status_history table.
     *
     * This is an append-only audit log. Every time an application's status changes,
     * a new row is inserted here. Rows are never updated or deleted.
     * This gives a full timeline of who changed what, and when.
     */
    public function up(): void
    {
        Schema::create('application_status_history', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // The application whose status changed
            $table->uuid('application_id');
            $table->foreign('application_id')
                  ->references('id')
                  ->on('applications')
                  ->onDelete('cascade'); // If application is deleted, remove its history too

            // The user (admin or applicant) who made the change
            $table->uuid('changed_by');
            $table->foreign('changed_by')
                  ->references('id')
                  ->on('profiles')
                  ->onDelete('restrict'); // Don't allow deleting a user who has audit records

            // What the status was before the change (null for the very first status entry)
            $table->string('previous_status', 20)->nullable();

            // What the status changed to
            $table->string('new_status', 20);

            // Optional comment from the admin, e.g. "Application approved at funding committee meeting"
            $table->text('notes')->nullable();

            // When the status change occurred — this is the meaningful business timestamp
            $table->timestamp('changed_at')->useCurrent();

            $table->timestamps(); // created_at and updated_at for Laravel record metadata
        });
    }

    /**
     * Reverse the migration — drop the application_status_history table.
     */
    public function down(): void
    {
        Schema::dropIfExists('application_status_history');
    }
};
