<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Asset;

class DatabaseSeeder extends Seeder {
    public function run(): void {
        // Создаём админа
        User::factory()->create([
            'name' => 'Романенко Андрей Сережевич',
            'email' => 'plsfixmycar@gmail.com',
            'room' => '101',
            'position' => 'Директор',
            'role' => 'admin',
            'password' => bcrypt('password'),
        ]);

        User::factory()->create([
            'name' => 'Дмитрий Уколов Кто-тотамович',
            'email' => 'dimon78@gmail.com',
            'room' => '100',
            'position' => 'Учитель',
            'role' => 'user',
            'password' => bcrypt('password'),
        ]);

        // Создаём Assets для админа
        Asset::factory(rand(3, 5))->create([
            'user_id' => 1,
            'room' => '101',
        ]);

        // Создаём Assets для админа
        Asset::factory(rand(3, 5))->create([
            'user_id' => 2,
            'room' => '100',
        ]);

        // Создаём обычных пользователей
        $users = User::factory(5)->create();

        // Для каждого пользователя создаём по 3-5 Assets
        $users->each(function ($user) {
            Asset::factory(rand(3, 5))->create([
                'user_id' => $user->id,
                'room' => $user->room,
            ]);
        });
    }
}