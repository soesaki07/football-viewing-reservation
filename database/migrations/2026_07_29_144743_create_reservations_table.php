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
        Schema::create('reservations', function (Blueprint $table) {
            $table->id()->comment('予約ID');
            $table->string('reservation_code', 30)->unique()->comment('ユーザー向け予約番号');
            $table->foreignId('user_id')->constrained()->cascadeOnUpdate()->restrictOnDelete()->comment('ユーザーID');
            $table->foreignId('broadcast_seat_type_id')->constrained()->cascadeOnUpdate()->restrictOnDelete()->comment('放映席種ID');
            $table->unsignedSmallInteger('number_of_people')->default(1)->comment('予約人数');
            $table->unsignedInteger('unit_price')->default(0)->comment('予約時点の1人あたり料金');
            $table->unsignedInteger('total_price')->default(0)->comment('予約時点の合計料金');
            $table->string('status', 30)->default('confirmed')->index()->comment('ステータス');
            $table->dateTime('reserved_at')->index()->comment('予約日時');
            $table->dateTime('cancelled_at')->nullable()->comment('キャンセル日時');
            $table->dateTime('visited_at')->nullable()->comment('来店確認日時');
            $table->text('customer_note')->nullable()->comment('ユーザー備考');
            $table->text('shop_note')->nullable()->comment('店舗側メモ');
            $table->timestamps();
        });

        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement("ALTER TABLE reservations COMMENT = '予約情報'");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reservations');
    }
};
