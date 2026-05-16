<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Metadata only. The files themselves live in Supabase Storage.
    public function up(): void
    {
        Schema::create('application_documents', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->uuid('application_id');
            $table->foreign('application_id')
                  ->references('id')
                  ->on('applications')
                  ->onDelete('cascade');

            $table->string('file_name');
            $table->string('file_type', 10);
            $table->string('storage_path');
            $table->string('document_type', 50);
            // Validated server-side against the 10 MB cap (NFR12).
            $table->unsignedInteger('file_size_bytes');

            // uploaded_at is the meaningful business timestamp; created_at is row metadata.
            $table->timestamp('uploaded_at')->useCurrent();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('application_documents');
    }
};
