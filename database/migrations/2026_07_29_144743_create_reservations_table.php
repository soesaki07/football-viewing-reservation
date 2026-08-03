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
        Schema::create('reservations', function (Blueprint $table) {
            $table->id();
            $table->string('reservation_code', 30)->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('broadcast_seat_type_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->unsignedSmallInteger('number_of_people')->default(1);
            $table->unsignedInteger('unit_price')->default(0);
            $table->unsignedInteger('total_price')->default(0);
            $table->string('status', 30)->default('confirmed')->index();
            $table->dateTime('reserved_at')->index();
            $table->dateTime('cancelled_at')->nullable();
            $table->dateTime('visited_at')->nullable();
            $table->text('customer_note')->nullable();
            $table->text('shop_note')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reservations');
    }
};
