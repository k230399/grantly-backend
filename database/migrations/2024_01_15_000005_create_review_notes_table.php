<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create the review_notes table.
     *
     * Admins can attach notes to applications during review.
     * Notes can be internal (admin-only) or visible to the applicant.
     */
    public function up(): void
    {
        Schema::create('review_notes', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // The application this note is attached to
            $table->uuid('application_id');
            $table->foreign('application_id')
                  ->references('id')
                  ->on('applications')
                  ->onDelete('cascade'); // Remove notes if the application is deleted

            // The admin who wrote this note
            $table->uuid('reviewer_id');
            $table->foreign('reviewer_id')
                  ->references('id')
                  ->on('profiles')
                  ->onDelete('restrict'); // Don't allow deleting an admin who has written notes

            $table->text('note_content'); // The body of the note — plain text

            // When true, only admins can see this note.
            // When false, the note is also visible to the applicant.
            $table->boolean('is_internal')->default(true);

            $table->timestamps(); // created_at and updated_at managed by Laravel
        });
    }

    /**
     * Reverse the migration — drop the review_notes table.
     */
    public function down(): void
    {
        Schema::dropIfExists('review_notes');
    }
};
