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
    Schema::create('template', function (Blueprint $table) {
        $table->id();
        $table->integer('id_admin')->nullable();
        $table->binary('frame')->nullable();
        $table->integer('id_topic')->nullable();
        $table->integer('cuts')->nullable();
        $table->string('type', 50)->nullable();
    });
}
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('template');
    }
};
