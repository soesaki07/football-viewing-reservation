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
        Schema::create('teams', function (Blueprint $table) {
            $table->id()->comment('チームID');
            $table->unsignedBigInteger('external_team_id')->unique()->comment('外部API側のチームID');
            $table->string('name')->comment('正式名称');
            $table->string('short_name', 100)->nullable()->comment('短縮名');
            $table->string('tla', 10)->nullable()->index()->comment('チーム略称');
            $table->string('crest_url', 2048)->nullable()->comment('チームロゴURL');
            $table->string('country_name', 100)->nullable()->comment('所属国');
            $table->timestamps();
        });

        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement("ALTER TABLE teams COMMENT = 'チーム情報'");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('teams');
    }
};
