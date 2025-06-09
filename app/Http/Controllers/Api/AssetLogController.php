<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AssetLog;
use Illuminate\Http\Request;

class AssetLogController extends Controller
{
    public function index(Request $request)
    {
        $logs = AssetLog::with(['asset', 'user'])->orderByDesc('created_at')->paginate(20);
        return response()->json($logs);
    }
}
