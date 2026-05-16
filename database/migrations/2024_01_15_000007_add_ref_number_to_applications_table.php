<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Human-readable reference number for applications. The Application model formats
    // the raw integer as "APP-YYYY-000042". A Postgres sequence gives us atomic, collision-free
    // numbers under concurrent inserts.
    public function up(): void
    {
        // IF NOT EXISTS keeps the migration safe to re-run after a fresh seed.
        DB::statement('CREATE SEQUENCE IF NOT EXISTS applications_ref_number_seq START 1 INCREMENT 1');

        Schema::table('applications', function (Blueprint $table) {
            // nullable() so existing rows can be back-filled before the NOT NULL tightening below.
            $table->unsignedInteger('ref_number')->nullable()->unique()->after('id');
        });

        // DEFAULT pulls from the sequence so future inserts get a value automatically.
        DB::statement(
            "ALTER TABLE applications ALTER COLUMN ref_number SET DEFAULT nextval('applications_ref_number_seq')"
        );

        // Back-fill existing rows.
        DB::statement(
            "UPDATE applications SET ref_number = nextval('applications_ref_number_seq') WHERE ref_number IS NULL"
        );

        DB::statement('ALTER TABLE applications ALTER COLUMN ref_number SET NOT NULL');
    }

    // Column must be dropped before the sequence, since the DEFAULT still depends on it.
    public function down(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->dropColumn('ref_number');
        });

        DB::statement('DROP SEQUENCE IF EXISTS applications_ref_number_seq');
    }
};
