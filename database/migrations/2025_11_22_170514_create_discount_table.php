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
    Schema::create('discount', function (Blueprint $table) {
        $table->id();
        $table->integer('id_admin')->nullable();
        $table->string('code', 50)->nullable();
        $table->date('startdate')->nullable();   // PostgreSQL không phân biệt hoa thường
        $table->date('enddate')->nullable();
        $table->integer('value')->nullable();
        $table->integer('quantity')->nullable();
        $table->integer('count_quantity')->nullable();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('discount');
    }
};
