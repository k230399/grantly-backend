<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add a JSONB form_data column to the applications table.
     *
     * The applications table has a fixed set of columns (project_name,
     * funding_requested, etc.) that every application uses. But each grant
     * round can also define its own custom questions via the
     * `application_form_schema` JSON field on grant_rounds. The applicant's
     * answers to those custom questions need somewhere to live — that's
     * what form_data stores.
     *
     * We use jsonb (rather than plain json) so Postgres can index and query
     * inside the structure efficiently if we ever need to filter on a
     * specific custom answer.
     *
     * Nullable because the applicant fills this in over time — a freshly
     * created draft application won't have any answers yet.
     */
    public function up(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            // jsonb is Postgres' binary JSON type — faster to query than plain json.
            // after('declaration_accepted') is cosmetic ordering for MySQL; Postgres ignores it.
            $table->jsonb('form_data')->nullable()->after('declaration_accepted');
        });
    }

    /**
     * Reverse the migration — drop the form_data column.
     */
    public function down(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->dropColumn('form_data');
        });
    }
};
