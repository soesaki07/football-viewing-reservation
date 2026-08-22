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
        Schema::create('shops', function (Blueprint $table) {
            $table->id()->comment('店舗ID');
            $table->foreignId('user_id')->constrained()->cascadeOnUpdate()->restrictOnDelete()->comment('店舗管理者のユーザーID');
            $table->string('name')->index()->comment('店舗名');
            $table->string('postal_code', 10)->nullable()->comment('郵便番号');
            $table->string('prefecture', 50)->nullable()->index()->comment('都道府県');
            $table->string('city', 100)->nullable()->comment('市区町村');
            $table->string('address_line')->comment('番地・建物名');
            $table->decimal('latitude', 10, 7)->nullable()->index()->comment('緯度');
            $table->decimal('longitude', 10, 7)->nullable()->index()->comment('経度');
            $table->string('google_place_id')->nullable()->unique()->comment('Google Place ID');
            $table->string('phone_number', 30)->nullable()->comment('電話番号');
            $table->text('description')->nullable()->comment('店舗説明');
            $table->time('opening_time')->nullable()->comment('通常営業開始時刻');
            $table->time('closing_time')->nullable()->comment('通常営業終了時刻');
            $table->string('website_url', 2048)->nullable()->comment('店舗webサイトURL');
            $table->string('status', 30)->default('draft')->index()->comment('店舗の状態');
            $table->timestamps();
            $table->softDeletes();
        });

        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement("ALTER TABLE shops COMMENT = 'スポーツバー情報'");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shops');
    }
};
