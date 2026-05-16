<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('review_notes', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->uuid('application_id');
            $table->foreign('application_id')
                  ->references('id')
                  ->on('applications')
                  ->onDelete('cascade');

            // Restrict on reviewer_id so we never lose attribution.
            $table->uuid('reviewer_id');
            $table->foreign('reviewer_id')
                  ->references('id')
                  ->on('profiles')
                  ->onDelete('restrict');

            $table->text('note_content');

            // Dropped later (see 2026_05_08_121657_drop_is_internal_from_review_notes_table).
            $table->boolean('is_internal')->default(true);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('review_notes');
    }
};
