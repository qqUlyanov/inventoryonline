<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAssetRequestsTable extends Migration
{
    public function up()
    {
        Schema::create('asset_requests', function (Blueprint $table) {
            $table->id();
            $table->json('asset_ids');
            $table->string('operation');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->text('comment')->nullable();
            $table->unsignedBigInteger('created_by');
            $table->string('status')->default('pending');
            $table->text('reject_comment')->nullable(); // <--- добавлено
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('asset_requests');
    }
}
