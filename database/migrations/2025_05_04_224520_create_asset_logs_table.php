<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('asset_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->nullable()->constrained()->onDelete('cascade'); // <-- nullable()
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null');
            $table->string('action'); // например: 'created', 'updated', 'transferred', 'status_changed'
            $table->text('description')->nullable(); // Человеческое описание, например: "Изменён статус на 'В ремонте'"
            $table->timestamps();
        });
    }


    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('asset_logs');
    }
};
