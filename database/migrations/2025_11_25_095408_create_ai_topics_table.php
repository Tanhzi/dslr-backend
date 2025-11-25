<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('ai_topics', function (Blueprint $table) {
            $table->id(); // id (INT, auto-increment, PK)
            $table->integer('id_admin')->nullable();
            $table->string('name'); // VARCHAR
            $table->string('type'); // VARCHAR
            $table->text('illustration'); // TEXT
            $table->smallInteger('is_prompt')->default(0); // tinyint → smallint (0/1)
            
            // ENUM trong PostgreSQL → dùng VARCHAR + ràng buộc (hoặc chỉ dùng VARCHAR)
            // Laravel không hỗ trợ enum trực tiếp trên mọi DB, nên dùng string
            $table->string('status')->default('active'); // ví dụ: 'active', 'inactive', 'pending'
            
            $table->timestamps(); // created_at + updated_at (TIMESTAMP)
        });
    }

    public function down()
    {
        Schema::dropIfExists('ai_topics');
    }
};