<?php

namespace App\Imports;

use App\Models\Asset;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class AssetImport implements ToCollection, WithHeadingRow
{
    protected $user;

    public function __construct($user)
    {
        $this->user = $user;
    }

    public function collection(Collection $rows)
    {
        foreach ($rows as $row) {
            if (
                empty($row['name']) ||
                empty($row['type']) ||
                empty($row['brand']) ||
                empty($row['status'])
            ) {
                continue;
            }

            Asset::create([
                'name' => $row['name'],
                'type' => $row['type'],
                'brand' => $row['brand'],
                'status' => $row['status'],
                'image_path' => $row['image_path'] ?? null,
                'inv_number' => $row['inv_number'] ?? null,
                'price' => $row['price'] ?? null,
                'user_id' => $this->user->id,
                'room' => $this->user->room,
            ]);
        }
    }
}
