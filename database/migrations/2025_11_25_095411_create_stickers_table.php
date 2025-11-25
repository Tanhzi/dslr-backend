<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('stickers', function (Blueprint $table) {
            $table->id(); // id
            $table->integer('id_admin')->nullable();
            $table->integer('id_topic')->nullable(); // liên kết với ai_topics
            $table->binary('sticker')->nullable();   // MEDIUMBLOB → BYTEA (binary)
            $table->string('type')->nullable();      // VARCHAR
            $table->timestamps(); // created_at + updated_at
        });
    }

    public function down()
    {
        Schema::dropIfExists('stickers');
    }
};