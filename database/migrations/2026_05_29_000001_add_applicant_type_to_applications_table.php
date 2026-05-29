<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            // Whether the applicant is applying as themselves or on behalf of an
            // organisation. Existing rows default to 'individual'.
            $table->string('applicant_type', 20)->default('individual')->after('grant_round_id');

            // ABN + organisation name are captured per application (a snapshot at apply
            // time) rather than read live from the profile, so later profile edits do not
            // change a submitted application. Both nullable: only organisations supply them.
            $table->string('abn', 11)->nullable()->after('applicant_type');
            $table->string('organisation_name')->nullable()->after('abn');
        });
    }

    public function down(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->dropColumn(['applicant_type', 'abn', 'organisation_name']);
        });
    }
};
