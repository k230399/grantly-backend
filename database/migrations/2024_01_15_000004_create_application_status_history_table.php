<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Append-only audit log. Rows are never updated or deleted.
    public function up(): void
    {
        Schema::create('application_status_history', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->uuid('application_id');
            $table->foreign('application_id')
                  ->references('id')
                  ->on('applications')
                  ->onDelete('cascade');

            // Restrict on changed_by so we never lose attribution for an audit record.
            $table->uuid('changed_by');
            $table->foreign('changed_by')
                  ->references('id')
                  ->on('profiles')
                  ->onDelete('restrict');

            // Null for the very first status entry on a row.
            $table->string('previous_status', 20)->nullable();
            $table->string('new_status', 20);

            $table->text('notes')->nullable();

            // changed_at is the meaningful business timestamp; created_at is row metadata.
            $table->timestamp('changed_at')->useCurrent();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('application_status_history');
    }
};
