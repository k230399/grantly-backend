<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create the grant_rounds table.
     *
     * A grant round is a funding opportunity created by an admin.
     * It moves through three statuses: draft → open → closed.
     * Only "open" rounds are visible to applicants.
     */
    public function up(): void
    {
        Schema::create('grant_rounds', function (Blueprint $table) {
            // UUID primary key — consistent with profiles and all other tables in this project
            $table->uuid('id')->primary();

            $table->string('title');                       // Short name displayed on the applicant portal
            $table->text('description');                   // Full description of what the round funds
            $table->decimal('max_funding_amount', 10, 2);  // Maximum dollars an applicant can request
            $table->text('eligibility_criteria');          // Who is allowed to apply

            // Status controls visibility: draft = admin only, open = public, closed = no new applications
            $table->string('status', 10)->default('draft');

            $table->timestamp('opens_at')->nullable();  // When the round opens to applicants (null = not scheduled)
            $table->timestamp('closes_at')->nullable(); // When the round closes (null = no hard deadline)

            // The admin who created this grant round — must be a row in profiles with role = admin
            $table->uuid('created_by');
            $table->foreign('created_by')
                  ->references('id')
                  ->on('profiles')
                  ->onDelete('restrict'); // Prevent deleting an admin who has created rounds

            $table->timestamps(); // created_at and updated_at managed by Laravel
        });
    }

    /**
     * Reverse the migration — drop the grant_rounds table.
     */
    public function down(): void
    {
        Schema::dropIfExists('grant_rounds');
    }
};
