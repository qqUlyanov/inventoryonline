<?php

namespace App\Http\Controllers\Api;

use App\Models\Asset;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use App\Models\AssetLog;
use Illuminate\Support\Facades\Log;

class AnalyticsController extends Controller
{

    public function summary(Request $request)
    {
        $user = $request->user();

        // Для админа — запрос ко всем Asset, иначе — только к своим
        $query = $user->role === 'admin'
            ? Asset::query()
            : Asset::where('user_id', $user->id);

        // Пагинируем активы
        $assetsPaginated = $query->orderBy('id', 'desc')->get();

        // Получаем только ID активов для фильтрации логов
        $assetIds = $query->pluck('id');

        // --- Количество заявок ---
        // Для админа
        $requests_count = \App\Models\AssetRequest::where('status', 'pending')->count();
        $requests_total = \App\Models\AssetRequest::count();
        $requests_history_count = \App\Models\AssetRequest::whereIn('status', ['approved', 'rejected'])->count();

        // Подсчёт заявок на ремонт в процессе
        $repairRequestsInProgress = \App\Models\AssetRequest::where('operation', 'repair')
            ->where('status', 'in_progress')
            ->count();

        // Для обычного пользователя
        $user_requests_count = \App\Models\AssetRequest::where('status', 'pending')->where('created_by', $user->id)->count();
        $user_requests_history_count = \App\Models\AssetRequest::whereIn('status', ['approved', 'rejected'])->where('created_by', $user->id)->count();

        $freeAssetsCount = Asset::whereIn('status', ['Свободен', 'free'])
            ->whereNull('user_id')
            ->whereNull('room')
            ->count();

        $stats = [
            'total' => $user->role === 'admin'
                ? Asset::count()
                : Asset::where('user_id', $user->id)->count(),

            'in_repair' => $user->role === 'admin'
                ? Asset::where('status', 'В ремонте')->count()
                : Asset::where('user_id', $user->id)->where('status', 'В ремонте')->count(),

            'logs_count' => $user->role === 'admin'
                ? \App\Models\AssetLog::count()
                : \App\Models\AssetLog::whereIn('asset_id', $assetIds)->count(),

            // Для админа
            'requests_count' => $requests_count,
            'requests_total' => $requests_total,
            'requests_history_count' => $requests_history_count,
            // Для обычного пользователя
            'user_requests_count' => $user_requests_count,
            'user_requests_history_count' => $user_requests_history_count,

            'repair_requests_in_progress' => $repairRequestsInProgress,
            'free_assets_count' => $freeAssetsCount,
        ];

        // Возвращаем статистику и пагинированный список активов
        return response()->json([
            'stats' => $stats,
            'assets' => $assetsPaginated,
        ]);
    }

}
