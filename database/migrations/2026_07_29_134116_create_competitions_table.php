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
        Schema::create('competitions', function (Blueprint $table) {
            $table->id()->comment('大会ID');
            $table->unsignedBigInteger('external_competition_id')->nullable()->unique()->comment('外部API側の大会ID');
            $table->string('code', 20)->unique()->comment('大会コード');
            $table->string('name')->comment('大会名');
            $table->string('country_name', 100)->nullable()->comment('開催国・地域');
            $table->string('emblem_url', 2048)->nullable()->comment('大会ロゴURL');
            $table->boolean('is_active')->default(true)->index()->comment('アプリ上の表示対象フラグ');
            $table->timestamps();
        });

        DB::statement("ALTER TABLE competitions COMMENT = '大会情報'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('competitions');
    }
};
