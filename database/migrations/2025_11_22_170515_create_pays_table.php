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
    Schema::create('pays', function (Blueprint $table) {
        $table->id();
        $table->integer('price')->nullable();
        $table->integer('id_admin')->nullable();
        $table->integer('id_client')->nullable();
        $table->integer('cuts')->nullable();
        $table->date('date')->nullable();
        $table->integer('discount')->nullable();
        $table->integer('discount_price')->nullable();
        $table->string('discount_code', 100)->nullable();
        $table->integer('id_frame')->nullable();
        $table->string('id_qr', 50)->nullable();
        $table->string('email', 200)->nullable();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pays');
    }
};
