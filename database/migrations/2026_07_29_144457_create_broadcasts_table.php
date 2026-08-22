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
        Schema::create('broadcasts', function (Blueprint $table) {
            $table->id()->comment('放映情報ID');
            $table->foreignId('shop_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete()->comment('店舗ID');
            $table->foreignId('football_match_id')->constrained('football_matches')->cascadeOnUpdate()->restrictOnDelete()->comment('試合Id');
            $table->string('title')->nullable()->comment('店舗独自イベント名');
            $table->dateTime('reservation_start_at')->nullable()->comment('予約受付開始日時');
            $table->dateTime('reservation_end_at')->nullable()->comment('予約受付終了日時');
            $table->dateTime('doors_open_at')->nullable()->comment('入場開始日時');
            $table->string('status', 30)->default('draft')->index()->comment('ステータス');
            $table->text('notes')->nullable()->comment('注意事項');
            $table->timestamps();
            $table->unique(['shop_id', 'football_match_id']);
        });

        DB::statement("ALTER TABLE broadcasts COMMENT = '放映情報'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('broadcasts');
    }
};
