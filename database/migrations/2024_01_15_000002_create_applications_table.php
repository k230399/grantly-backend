<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create the applications table.
     *
     * An application is an applicant's request for funding from a specific grant round.
     * Status lifecycle: draft → submitted → under_review → approved | rejected
     */
    public function up(): void
    {
        Schema::create('applications', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // The applicant who owns this application — links to profiles table
            $table->uuid('applicant_id');
            $table->foreign('applicant_id')
                  ->references('id')
                  ->on('profiles')
                  ->onDelete('cascade'); // If the user is deleted, remove their applications too

            // The grant round being applied to — cannot delete a round that has applications
            $table->uuid('grant_round_id');
            $table->foreign('grant_round_id')
                  ->references('id')
                  ->on('grant_rounds')
                  ->onDelete('restrict');

            $table->string('project_name');           // Short title of the project being funded
            $table->text('project_description');      // Full description of what the applicant plans to do
            $table->decimal('funding_requested', 10, 2);    // How much the applicant is asking for
            $table->decimal('total_project_budget', 10, 2); // The total cost of the project (funding_requested + other sources)

            // The applicant must tick this box to confirm their details are accurate before submitting
            $table->boolean('declaration_accepted')->default(false);

            // Tracks where the application is in the lifecycle
            // draft = in progress, submitted = sent to admin, under_review = being assessed,
            // approved / rejected = final decision made
            $table->string('status', 20)->default('draft');

            // Null until the applicant clicks "Submit" — at that point it's locked and timestamped
            $table->timestamp('submitted_at')->nullable();

            $table->timestamps(); // created_at and updated_at managed by Laravel
        });
    }

    /**
     * Reverse the migration — drop the applications table.
     */
    public function down(): void
    {
        Schema::dropIfExists('applications');
    }
};
