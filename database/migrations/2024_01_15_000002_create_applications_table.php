<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('applications', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->uuid('applicant_id');
            $table->foreign('applicant_id')
                  ->references('id')
                  ->on('profiles')
                  ->onDelete('cascade');

            // Restrict on grant_round_id so a round with applications cannot be deleted; close it instead.
            $table->uuid('grant_round_id');
            $table->foreign('grant_round_id')
                  ->references('id')
                  ->on('grant_rounds')
                  ->onDelete('restrict');

            $table->string('project_name');
            $table->text('project_description');
            $table->decimal('funding_requested', 10, 2);
            $table->decimal('total_project_budget', 10, 2);

            $table->boolean('declaration_accepted')->default(false);

            $table->string('status', 20)->default('draft');

            // Set when the applicant submits. Null otherwise.
            $table->timestamp('submitted_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('applications');
    }
};
