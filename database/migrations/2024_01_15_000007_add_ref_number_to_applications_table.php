<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add a human-readable reference number to the applications table.
     *
     * The UUID primary key is great for the database but impossible for a person
     * to remember or quote in an email. This migration adds a second identifier —
     * a plain integer stored in ref_number — which Laravel formats as APP-2026-000042.
     *
     * We use a Postgres sequence rather than PHP-side logic because a sequence is
     * atomic: two concurrent inserts each get their own unique integer with no risk
     * of collision, even under heavy load.
     */
    public function up(): void
    {
        // A "sequence" is a Postgres-native counter that increments by 1 each time
        // it's called, atomically. Think of it like a ticket dispenser — each caller
        // gets their own number, no two ever get the same one.
        // IF NOT EXISTS makes this safe to re-run (e.g. in CI after a fresh seed).
        DB::statement('CREATE SEQUENCE IF NOT EXISTS applications_ref_number_seq START 1 INCREMENT 1');

        Schema::table('applications', function (Blueprint $table) {
            // ref_number stores the raw integer from the sequence (e.g. 42).
            // The formatted display string (e.g. APP-2026-000042) is computed by
            // the Application model accessor — we don't store it here.
            //
            // nullable() for now — we need the column to exist before we can back-fill
            // existing rows. We'll flip it to NOT NULL at the end of this migration.
            //
            // unique() ensures no two applications can ever share a reference number,
            // even if someone manually tampers with the sequence.
            //
            // after('id') is cosmetic — puts it next to the primary key in MySQL.
            // Postgres ignores column ordering, but it makes schema dumps more readable.
            $table->unsignedInteger('ref_number')->nullable()->unique()->after('id');
        });

        // Wire the column's DEFAULT to pull the next value from the sequence automatically.
        // This means every INSERT that doesn't explicitly set ref_number gets a unique
        // integer from Postgres — no PHP code needed at insert time.
        DB::statement(
            "ALTER TABLE applications ALTER COLUMN ref_number SET DEFAULT nextval('applications_ref_number_seq')"
        );

        // Back-fill any rows that already exist in the table (e.g. dev seed data).
        // Each NULL ref_number gets the next value from the sequence.
        // New inserts going forward are handled by the DEFAULT above.
        DB::statement(
            "UPDATE applications SET ref_number = nextval('applications_ref_number_seq') WHERE ref_number IS NULL"
        );

        // Now that every row has a value, tighten the column to NOT NULL.
        // This prevents any future insert from omitting the value.
        // (In practice, the DEFAULT handles this — but NOT NULL is belt-and-suspenders.)
        DB::statement('ALTER TABLE applications ALTER COLUMN ref_number SET NOT NULL');
    }

    /**
     * Reverse the migration — remove ref_number and drop the sequence.
     *
     * Column must be dropped first because dropping the column removes the DEFAULT
     * that references the sequence. If you tried to drop the sequence first, Postgres
     * would refuse because the column's DEFAULT still depends on it.
     */
    public function down(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->dropColumn('ref_number');
        });

        DB::statement('DROP SEQUENCE IF EXISTS applications_ref_number_seq');
    }
};
