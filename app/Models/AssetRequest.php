<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AssetRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'asset_ids',
        'operation',
        'user_id',
        'comment',
        'created_by',
        'status',
        'reject_comment',
    ];

    protected $casts = [
        'asset_ids' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by');
    }
}
