<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
public function up()
{
    Schema::create('camera', function (Blueprint $table) {
        $table->id();
        $table->integer('id_admin')->nullable();
        $table->integer('time1')->nullable();
        $table->integer('time2')->nullable();
        $table->smallInteger('video')->nullable();      // thay tinyint
        $table->smallInteger('mirror')->nullable();     // thay tinyint
        $table->string('time_run', 5)->default('11:00');
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('camera');
    }
};
