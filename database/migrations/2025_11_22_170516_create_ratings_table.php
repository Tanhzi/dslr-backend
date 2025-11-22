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
    Schema::create('ratings', function (Blueprint $table) {
        $table->id();
        $table->string('name', 100)->nullable();
        $table->smallInteger('quality')->default(0);
        $table->smallInteger('smoothness')->default(0);
        $table->smallInteger('photo')->default(0);
        $table->smallInteger('service')->default(0);
        $table->text('comment')->nullable();
        $table->integer('id_admin')->nullable();
        $table->timestamps(); // created_at (có timezone)
    });
}
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ratings');
    }
};
