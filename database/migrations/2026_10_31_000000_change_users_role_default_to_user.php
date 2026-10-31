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
        // 初期値を仕様の値（user）にする。インデックスは既存のものを引き継ぐ
        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 30)->default('user')->change();
        });

        // 仕様にない値 customer が残っていれば user に直す
        DB::table('users')->where('role', 'customer')->update(['role' => 'user']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // 初期値だけ戻す（データの更新は戻さない）
        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 30)->default('customer')->change();
        });
    }
};
