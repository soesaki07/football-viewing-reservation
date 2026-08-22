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
        Schema::create('seat_types', function (Blueprint $table) {
            $table->id()->comment('席種ID');
            $table->foreignId('shop_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete()->comment('店舗ID');
            $table->string('name', 100)->comment('席種名');
            $table->string('description', 500)->nullable()->comment('席の説明');
            $table->unsignedSmallInteger('default_capacity')->default(0)->comment('通常時の定員');
            $table->unsignedInteger('default_price')->default(0)->comment('通常時の1人当たりの料金（円）');
            $table->boolean('is_active')->default(true)->index()->comment('使用フラグ');
            $table->timestamps();
            $table->unique(['shop_id', 'name']);
        });

        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement("ALTER TABLE seat_types COMMENT = '座席種別'");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('seat_types');
    }
};
