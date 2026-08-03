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
        Schema::create('broadcasts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('football_match_id')->constrained('football_matches')->cascadeOnUpdate()->restrictOnDelete();
            $table->string('title')->nullable();
            $table->dateTime('reservation_start_at')->nullable();
            $table->dateTime('reservation_end_at')->nullable();
            $table->dateTime('doors_open_at')->nullable();
            $table->string('status', 30)->default('draft')->index();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['shop_id', 'football_match_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('broadcasts');
    }
};
