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
        Schema::table('telegram_groups', function (Blueprint $table) {
            $table->unsignedBigInteger('user_adder')->after('id')->nullable();
            $table->foreign('user_adder')->references('chat_id')->on('telegram_bots')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('telegram_groups', function (Blueprint $table) {
            $table->dropColumn('user_adder');
        });
    }
};
