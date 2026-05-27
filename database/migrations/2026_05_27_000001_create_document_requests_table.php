<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Admin-driven requests for additional supporting documents on a submitted/under-review
// application. The applicant uploads the file through the existing application_documents
// pipeline, linked back by document_request_id.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->uuid('application_id');
            $table->foreign('application_id')
                  ->references('id')
                  ->on('applications')
                  ->onDelete('cascade');

            // Restrict on requested_by so attribution stays intact even if an admin profile is deleted.
            $table->uuid('requested_by');
            $table->foreign('requested_by')
                  ->references('id')
                  ->on('profiles')
                  ->onDelete('restrict');

            $table->string('label', 200);
            $table->text('description')->nullable();

            // pending | fulfilled | cancelled. Enforced in the controller, not the DB, to stay flexible.
            $table->string('status', 20)->default('pending');

            $table->timestamp('requested_at')->useCurrent();
            $table->timestamp('fulfilled_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();

            $table->timestamps();

            $table->index(['application_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_requests');
    }
};
