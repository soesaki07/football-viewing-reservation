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
        Schema::create('broadcast_seat_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('broadcast_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('seat_type_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->unsignedSmallInteger('capacity')->default(0);
            $table->unsignedInteger('price')->default(0);
            $table->unsignedSmallInteger('max_people_per_reservation')->default(4);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->unique(['broadcast_id', 'seat_type_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('broadcast_seat_types');
    }
};
