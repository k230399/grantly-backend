<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('profiles', function (Blueprint $table) {
            // When the account finished setup (first successful sign-in). Null = still pending an
            // invite. Drives the Team screen's Pending/Active status without calling Supabase's
            // admin list endpoint, which is unreliable.
            $table->timestamp('invite_accepted_at')->nullable()->after('role');
        });

        // Everyone who already exists has been using the app, so mark them accepted (Active).
        // New invited admins created after this migration start as null (Pending).
        DB::table('profiles')->whereNull('invite_accepted_at')->update([
            'invite_accepted_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::table('profiles', function (Blueprint $table) {
            $table->dropColumn('invite_accepted_at');
        });
    }
};
