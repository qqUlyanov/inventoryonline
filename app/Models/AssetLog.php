<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class AssetLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'asset_id',
        'user_id',
        'action',
        'description',
    ];

    // Явно указываем, что asset_id по умолчанию null
    protected $attributes = [
        'asset_id' => null,
    ];

    public function asset()
    {
        return $this->belongsTo(Asset::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
