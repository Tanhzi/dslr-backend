<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Bảng content_chat đã tồn tại trên production (tạo tay/cũ hơn)
        // nhưng thiếu migration — thêm lại để migrate sạch (test sqlite, máy mới).
        if (Schema::hasTable('content_chat')) {
            return;
        }

        Schema::create('content_chat', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('id_admin');
            $table->string('title', 255);
            $table->text('content');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_chat');
    }
};
