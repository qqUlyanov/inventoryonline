<?php

namespace Database\Factories;

use App\Models\Asset;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class AssetLogFactory extends Factory
{
    public function definition(): array
    {
        $action = $this->faker->randomElement([
            'created', 'updated', 'transferred', 'deleted', 'status_changed'
        ]);

        return [
            'asset_id' => Asset::inRandomOrder()->first()?->id ?? Asset::factory(),
            // user_id теперь может быть переопределён через state в сидере
            'user_id' => User::inRandomOrder()->first()?->id ?? User::factory(),
            'action' => $action,
            'description' => match ($action) {
                'created' => "ТМЦ добавлен в систему",
                'updated' => "Изменены параметры ТМЦ",
                'transferred' => "ТМЦ передан другому сотруднику",
                'deleted' => "ТМЦ удалён из учёта",
                'status_changed' => "Изменён статус ТМЦ",
            },
            'created_at' => $this->faker->dateTimeBetween('-6 months', 'now'),
            'updated_at' => now(),
        ];
    }
}
