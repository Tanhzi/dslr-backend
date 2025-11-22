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
    Schema::create('media', function (Blueprint $table) {
        $table->id();
        $table->string('file_path', 255);
        $table->string('file_type', 50)->nullable();
        $table->integer('id_admin')->nullable();
        $table->string('email', 200)->nullable();
        $table->string('session_id', 255)->nullable();
        $table->timestamps(); // tạo created_at + updated_at
        $table->binary('qr')->nullable();
        $table->string('link', 255)->nullable();
    });

    DB::statement('CREATE INDEX IF NOT EXISTS session_id_index ON media(session_id)');
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('media');
    }
};
