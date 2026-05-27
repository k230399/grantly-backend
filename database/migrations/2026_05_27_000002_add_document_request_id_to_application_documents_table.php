<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Links a document to the document_requests row it fulfills. Nullable so the existing
// project-level documents (document_type) and custom-question documents (form_field_id) keep working.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('application_documents', function (Blueprint $table) {
            $table->uuid('document_request_id')->nullable()->after('form_field_id');
            $table->foreign('document_request_id')
                  ->references('id')
                  ->on('document_requests')
                  ->nullOnDelete();
            $table->index('document_request_id');
        });
    }

    public function down(): void
    {
        Schema::table('application_documents', function (Blueprint $table) {
            $table->dropForeign(['document_request_id']);
            $table->dropIndex(['document_request_id']);
            $table->dropColumn('document_request_id');
        });
    }
};
