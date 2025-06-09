<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithMapping;

class AssetsExport implements FromCollection, WithHeadings, ShouldAutoSize, WithMapping
{
    protected Collection $assets;

    public function __construct(Collection $assets)
    {
        $this->assets = $assets;
    }

    public function collection()
    {
        return $this->assets;
    }

    public function map($asset): array
    {
        return [
            $asset->id,
            $asset->name,
            $asset->type,
            $asset->brand,
            $asset->status,
            $asset->inv_number,
            $asset->price,
            $asset->room,
            optional($asset->user)->name,
            $asset->updated_at ? $asset->updated_at->format('d.m.Y H:i') : '',
        ];
    }

    public function headings(): array
    {
        return [
            'ID',
            'Название',
            'Тип',
            'Бренд',
            'Статус',
            'Инвентарный номер',
            'Цена',
            'Кабинет',
            'Пользователь',
            'Дата обновления',
        ];
    }
}
