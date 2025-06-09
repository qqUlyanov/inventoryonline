<?php

namespace Database\Factories;

use App\Models\Asset;
use Illuminate\Database\Eloquent\Factories\Factory;

class AssetFactory extends Factory
{
    protected $model = Asset::class;

    public function definition()
    {
        static $i = 0;
        $data = [
            [
                'name' => 'qwe',
                'type' => 'Ноутбук',
                'brand' => 'Lenovo',
                'inv_number' => 'INV-2025-0001',
                'price' => 42000,
            ],
            [
                'name' => 'Монитор Samsung S24F350',
                'type' => 'Монитор',
                'brand' => 'Samsung',
                'inv_number' => 'INV-2025-0002',
                'price' => 15000,
            ],
            [
                'name' => 'Принтер HP LaserJet Pro M404dn',
                'type' => 'Принтер',
                'brand' => 'HP',
                'inv_number' => 'INV-2025-0003',
                'price' => 25000,
            ],
            [
                'name' => 'Системный блок DEXP Atlas H330',
                'type' => 'Системный блок',
                'brand' => 'DEXP',
                'inv_number' => 'INV-2025-0004',
                'price' => 35000,
            ],
            [
                'name' => 'Проектор Epson EB-X41',
                'type' => 'Проектор',
                'brand' => 'Epson',
                'inv_number' => 'INV-2025-0005',
                'price' => 20000,
            ],
            [
                'name' => 'Сканер Canon CanoScan LiDE 300',
                'type' => 'Сканер',
                'brand' => 'Canon',
                'inv_number' => 'INV-2025-0006',
                'price' => 12000,
            ],
            [
                'name' => 'Клавиатура Logitech K380',
                'type' => 'Клавиатура',
                'brand' => 'Logitech',
                'inv_number' => 'INV-2025-0007',
                'price' => 3000,
            ],
            [
                'name' => 'Мышь Logitech M185',
                'type' => 'Мышь',
                'brand' => 'Logitech',
                'inv_number' => 'INV-2025-0008',
                'price' => 1500,
            ],
            [
                'name' => 'ИБП APC Back-UPS 650VA',
                'type' => 'ИБП',
                'brand' => 'APC',
                'inv_number' => 'INV-2025-0009',
                'price' => 8000,
            ],
            [
                'name' => 'Маршрутизатор MikroTik hAP ac2',
                'type' => 'Маршрутизатор',
                'brand' => 'MikroTik',
                'inv_number' => 'INV-2025-0010',
                'price' => 6000,
            ],
        ];
        $item = $data[$i % count($data)];
        $i++;
        $passport = [];
        switch ($item['type']) {
            case 'Ноутбук':
                $passport = [
                    'model' => 'ThinkPad X1 Carbon Gen 9',
                    'cpu' => 'Intel i7-1165G7',
                    'ram' => '16GB',
                    'storage' => '512GB SSD',
                    'os' => 'Windows 11 Pro',
                ]; break;
            case 'Монитор':
                $passport = [
                    'model' => 'S24F350',
                    'diagonal' => '23.8"',
                    'resolution' => '1920x1080',
                ]; break;
            case 'Принтер':
                $passport = [
                    'model' => 'LaserJet Pro M404dn',
                    'printer_type' => 'Лазерный',
                ]; break;
            case 'Системный блок':
                $passport = [
                    'model' => 'Atlas H330',
                    'cpu' => 'Intel i5-10400',
                    'ram' => '8GB',
                    'storage' => '256GB SSD',
                    'os' => 'Windows 10 Pro',
                ]; break;
            case 'Проектор':
                $passport = [
                    'model' => 'EB-X41',
                ]; break;
            case 'Сканер':
                $passport = [
                    'model' => 'CanoScan LiDE 300',
                ]; break;
            case 'Клавиатура':
                $passport = [
                    'model' => 'K380',
                ]; break;
            case 'Мышь':
                $passport = [
                    'model' => 'M185',
                ]; break;
            case 'ИБП':
                $passport = [
                    'model' => 'Back-UPS 650VA',
                ]; break;
            case 'Маршрутизатор':
                $passport = [
                    'model' => 'hAP ac2',
                ]; break;
        }
        // Формируем name из type + brand + model
        $name = $item['type'] . ' ' . $item['brand'];
        if (!empty($passport['model'])) {
            $name .= ' ' . $passport['model'];
        }
        return array_merge([
            'name' => $name,
            'type' => $item['type'],
            'brand' => $item['brand'],
            'status' => 'Используется',
            'room' => 101,
            'user_id' => \App\Models\User::factory(),
            'inv_number' => $item['inv_number'],
            'price' => $item['price'],
        ], $passport);
    }
}
