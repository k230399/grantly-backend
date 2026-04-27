<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create the notifications table.
     *
     * Stores in-app notifications for users — e.g. "Your application was approved."
     * Transactional emails (Resend) are separate; this table powers the notification inbox in the UI.
     */
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // The user who should receive this notification
            $table->uuid('user_id');
            $table->foreign('user_id')
                  ->references('id')
                  ->on('profiles')
                  ->onDelete('cascade'); // If the user is deleted, remove their notifications

            // The application this notification relates to (optional — some notifications may be general)
            $table->uuid('application_id')->nullable();
            $table->foreign('application_id')
                  ->references('id')
                  ->on('applications')
                  ->onDelete('cascade'); // If the application is deleted, remove related notifications

            // Machine-readable type, e.g. "application_submitted", "status_changed", "round_closing_soon"
            // Lets the frontend render the right icon or action button
            $table->string('type', 50);

            $table->text('message'); // Human-readable notification text, e.g. "Your application has been approved."

            // False when first created; set to true when the user opens or dismisses the notification
            $table->boolean('is_read')->default(false);

            $table->timestamps(); // created_at (when sent) and updated_at (when read) managed by Laravel
        });
    }

    /**
     * Reverse the migration — drop the notifications table.
     */
    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
