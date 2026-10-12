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
    Schema::create('event', function (Blueprint $table) {
        $table->id();
        $table->integer('id_admin')->nullable();
        $table->string('name', 200)->nullable();
        $table->date('date')->nullable();
        $table->string('background')->nullable();      // path/URL Supabase (trước đây là BYTEA)
        $table->string('logo')->nullable();            // path/URL Supabase (trước đây là BYTEA)
        $table->json('apply')->nullable();           // JSON hoặc jsonb()
        $table->string('note1', 500)->nullable();
        $table->string('note2', 500)->nullable();
        $table->string('note3', 500)->nullable();
        $table->smallInteger('ev_back')->nullable();
        $table->smallInteger('ev_logo')->nullable();
        $table->smallInteger('ev_note')->nullable();
    });
}
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('event');
    }
};
