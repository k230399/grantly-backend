<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('grant_rounds', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->string('title');
            $table->text('description');
            $table->decimal('max_funding_amount', 10, 2);
            $table->text('eligibility_criteria');

            $table->string('status', 10)->default('draft');

            $table->timestamp('opens_at')->nullable();
            $table->timestamp('closes_at')->nullable();

            // Prevent deleting an admin who has created rounds.
            $table->uuid('created_by');
            $table->foreign('created_by')
                  ->references('id')
                  ->on('profiles')
                  ->onDelete('restrict');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('grant_rounds');
    }
};
