<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Asset extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'type', 'brand', 'status', 'image_path', 'room', 'user_id', 'inv_number', 'price', 'location',
        // паспортные поля
        'model', 'cpu', 'ram', 'storage', 'os', 'diagonal', 'resolution', 'printer_type'
    ];

    // Реляция: Один Asset принадлежит одному пользователю
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Логи перемещений этого объекта
    public function assetLogs()
    {
        return $this->hasMany(AssetLog::class);
    }

    public function latestOperations($limit = 5)
    {
        return $this->assetLogs()->latest()->take($limit)->get();
    }

    // Метод для получения комментариев (если комментарии хранятся в AssetLog)
    public function comments()
    {
        return $this->assetLogs()->whereNotNull('description')->get();
    }
}
