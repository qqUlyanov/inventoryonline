<?php

namespace Database\Seeders;

use App\Models\Asset;
use App\Models\User;
use App\Models\AssetLog;
use Illuminate\Database\Seeder;

class AssetLogSeeder extends Seeder
{
    public function run(): void
    {
        AssetLog::factory()->count(50)->create();

    }
}
