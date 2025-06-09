<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAssetsTable extends Migration
{
    public function up()
    {
        Schema::create('assets', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type');
            $table->string('brand');
            $table->string('status');
            $table->string('image_path')->nullable();
            $table->string('room')->nullable(); // теперь nullable
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('cascade');
            $table->string('inv_number')->nullable();
            $table->bigInteger('price')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('assets');
    }
}
