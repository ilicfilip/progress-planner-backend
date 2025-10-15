<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('registered_sites', function (Blueprint $table) {
            $table->id();
            $table->string('site_url')->unique();
            $table->string('license_key')->nullable();
            $table->string('last_emailed_at')->nullable();
            $table->date('last_emailed_date')->nullable();
            $table->json('raw_data')->nullable(); // Store complete JSON response
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('registered_sites');
    }
};
