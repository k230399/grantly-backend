<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Links a document to a specific custom-question field on the grant round's application_form_schema.
// Nullable so existing project-level documents (matched by document_type) keep working.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('application_documents', function (Blueprint $table) {
            $table->uuid('form_field_id')->nullable()->after('document_type');
            $table->index('form_field_id');
        });
    }

    public function down(): void
    {
        Schema::table('application_documents', function (Blueprint $table) {
            $table->dropIndex(['form_field_id']);
            $table->dropColumn('form_field_id');
        });
    }
};
