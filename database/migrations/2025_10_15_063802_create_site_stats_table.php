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
        Schema::create('site_stats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('registered_site_id')->constrained()->onDelete('cascade');
            $table->boolean('api_available')->default(false);
            $table->string('plugin_version')->nullable();
            $table->json('raw_response')->nullable(); // Store complete API response
            $table->text('error_message')->nullable(); // Store error if API fails
            $table->timestamp('last_fetched_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('site_stats');
    }
};
