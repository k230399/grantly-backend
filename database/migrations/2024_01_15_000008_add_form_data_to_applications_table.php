<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // form_data stores the applicant's answers to a round's custom questions
    // (grant_rounds.application_form_schema). jsonb so we can index/query inside the structure.
    public function up(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->jsonb('form_data')->nullable()->after('declaration_accepted');
        });
    }

    public function down(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->dropColumn('form_data');
        });
    }
};
