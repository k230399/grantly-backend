<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Brings the Laravel grant_rounds migration in line with the live Supabase schema.
// The columns below were added directly in Supabase over time; the Laravel migrations
// never captured them. Wrapped in hasColumn checks so this is a safe no-op when run
// against a database that already has them (production), and adds them in test setups
// (SQLite in-memory) where the migrations are the only source of truth.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('grant_rounds', function (Blueprint $table) {
            if (! Schema::hasColumn('grant_rounds', 'short_description')) {
                $table->string('short_description', 200)->nullable();
            }
            if (! Schema::hasColumn('grant_rounds', 'cover_image_url')) {
                $table->text('cover_image_url')->nullable();
            }
            if (! Schema::hasColumn('grant_rounds', 'eligible_organisation_types')) {
                $table->text('eligible_organisation_types')->nullable();
            }
            if (! Schema::hasColumn('grant_rounds', 'geographic_restrictions')) {
                $table->text('geographic_restrictions')->nullable();
            }
            if (! Schema::hasColumn('grant_rounds', 'required_documents')) {
                $table->json('required_documents')->nullable();
            }
            if (! Schema::hasColumn('grant_rounds', 'assessment_criteria')) {
                $table->text('assessment_criteria')->nullable();
            }
            if (! Schema::hasColumn('grant_rounds', 'key_focus_areas')) {
                $table->json('key_focus_areas')->nullable();
            }
            if (! Schema::hasColumn('grant_rounds', 'min_funding_amount')) {
                $table->decimal('min_funding_amount', 12, 2)->nullable();
            }
            if (! Schema::hasColumn('grant_rounds', 'total_funding_pool')) {
                $table->decimal('total_funding_pool', 12, 2)->nullable();
            }
            if (! Schema::hasColumn('grant_rounds', 'assessment_period_start')) {
                $table->timestamp('assessment_period_start')->nullable();
            }
            if (! Schema::hasColumn('grant_rounds', 'notification_date')) {
                $table->timestamp('notification_date')->nullable();
            }
            if (! Schema::hasColumn('grant_rounds', 'funding_release_date')) {
                $table->timestamp('funding_release_date')->nullable();
            }
            if (! Schema::hasColumn('grant_rounds', 'is_published')) {
                $table->boolean('is_published')->default(false);
            }
            if (! Schema::hasColumn('grant_rounds', 'is_featured')) {
                $table->boolean('is_featured')->default(false);
            }
            if (! Schema::hasColumn('grant_rounds', 'allow_multiple_applications')) {
                $table->boolean('allow_multiple_applications')->default(false);
            }
            if (! Schema::hasColumn('grant_rounds', 'max_applications_per_user')) {
                $table->integer('max_applications_per_user')->nullable();
            }
            if (! Schema::hasColumn('grant_rounds', 'application_form_schema')) {
                $table->json('application_form_schema')->nullable();
            }
            if (! Schema::hasColumn('grant_rounds', 'updated_by')) {
                $table->uuid('updated_by')->nullable();
            }
            if (! Schema::hasColumn('grant_rounds', 'published_at')) {
                $table->timestamp('published_at')->nullable();
            }
            if (! Schema::hasColumn('grant_rounds', 'closed_at')) {
                $table->timestamp('closed_at')->nullable();
            }
            if (! Schema::hasColumn('grant_rounds', 'contact_email')) {
                $table->string('contact_email', 255)->nullable();
            }
            if (! Schema::hasColumn('grant_rounds', 'contact_phone')) {
                $table->string('contact_phone', 20)->nullable();
            }
        });
    }

    public function down(): void
    {
        // No-op: dropping columns would risk data loss against production, and the
        // hasColumn guards in up() already make rollback ambiguous.
    }
};
