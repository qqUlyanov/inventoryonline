<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->string('model')->nullable()->after('brand');
            $table->string('cpu')->nullable()->after('model');
            $table->string('ram')->nullable()->after('cpu');
            $table->string('storage')->nullable()->after('ram');
            $table->string('os')->nullable()->after('storage');
            $table->string('diagonal')->nullable()->after('os');
            $table->string('resolution')->nullable()->after('diagonal');
            $table->string('printer_type')->nullable()->after('resolution');
        });
    }

    public function down()
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->dropColumn([
                'model',
                'cpu',
                'ram',
                'storage',
                'os',
                'diagonal',
                'resolution',
                'printer_type',
            ]);
        });
    }
};
