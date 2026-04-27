<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create the application_documents table.
     *
     * Tracks files uploaded as part of an application (budgets, supporting material, etc.).
     * Files are stored in Supabase Storage — this table stores the metadata and path,
     * not the file itself. Laravel issues signed URLs to let the browser download files securely.
     */
    public function up(): void
    {
        Schema::create('application_documents', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // Which application this document belongs to
            $table->uuid('application_id');
            $table->foreign('application_id')
                  ->references('id')
                  ->on('applications')
                  ->onDelete('cascade'); // If the application is deleted, remove its documents too

            $table->string('file_name');         // Original filename as uploaded, e.g. "project-budget.pdf"
            $table->string('file_type', 10);     // File extension: pdf, docx, xlsx, jpg, png
            $table->string('storage_path');      // Path inside the Supabase Storage bucket, e.g. "documents/abc123/budget.pdf"
            $table->string('document_type', 50); // Category of document, e.g. "budget", "supporting_material", "other"
            $table->unsignedInteger('file_size_bytes'); // File size — validated server-side (max 10 MB = 10_485_760 bytes)

            // The exact moment this file was uploaded — separate from created_at so the meaning is explicit
            $table->timestamp('uploaded_at')->useCurrent();

            $table->timestamps(); // created_at and updated_at managed by Laravel
        });
    }

    /**
     * Reverse the migration — drop the application_documents table.
     */
    public function down(): void
    {
        Schema::dropIfExists('application_documents');
    }
};
