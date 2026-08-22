<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('broadcast_seat_types', function (Blueprint $table) {
            $table->id()->comment('放映席種ID');
            $table->foreignId('broadcast_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete()->comment('放映情報ID');
            $table->foreignId('seat_type_id')->constrained()->cascadeOnUpdate()->restrictOnDelete()->comment('席種ID');
            $table->unsignedSmallInteger('capacity')->default(0)->comment('試合あたりで予約可能な人数');
            $table->unsignedInteger('price')->default(0)->comment('1人あたりの料金（円）');
            $table->unsignedSmallInteger('max_people_per_reservation')->default(4)->comment('1予約当たり最大人数');
            $table->boolean('is_active')->default(true)->index()->comment('予約受付対象フラグ');
            $table->timestamps();
            $table->unique(['broadcast_id', 'seat_type_id']);
        });

        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement("ALTER TABLE broadcast_seat_types COMMENT = '放映席情報'");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('broadcast_seat_types');
    }
};
