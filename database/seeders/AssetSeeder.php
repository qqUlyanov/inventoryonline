<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Asset;
use App\Models\AssetLog;

class AssetSeeder extends Seeder
{
    public function run()
    {
        Asset::factory(50)->create()->each(function ($asset) {
            // Добавляем операции для каждого актива
            AssetLog::factory()->create([
                'asset_id' => $asset->id,
                'user_id' => $asset->user_id, // используем владельца актива
                'action' => 'created',
                'description' => 'Создан новый ТМЦ',
            ]);

            // Добавляем несколько операций для теста
            AssetLog::factory()->create([
                'asset_id' => $asset->id,
                'user_id' => $asset->user_id, // исправлено
                'action' => 'updated',
                'description' => 'Обновлены характеристики ТМЦ',
            ]);

            AssetLog::factory()->create([
                'asset_id' => $asset->id,
                'user_id' => $asset->user_id, // исправлено
                'action' => 'transferred',
                'description' => 'Передан другому пользователю',
            ]);
        });
    }
}
