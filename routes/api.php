<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AssetController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\AnalyticsController;
use App\Http\Controllers\Api\AssetLogController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;
use App\Http\Controllers\Api\AssetRequestController;

// --- Аутентификация и пользователь ---
Route::middleware(['auth:sanctum'])->get('/user', function (Request $request) {
    return response()->json($request->user());
});
Route::middleware('auth:sanctum')->get('/user', [UserController::class, 'show']);
Route::middleware('auth:sanctum')->get('/users-with-stats', [UserController::class, 'withStats']);
Route::middleware('auth:sanctum')->post('/logout', [AuthenticatedSessionController::class, 'destroy']);

// --- Работа с активами (ТМЦ) ---
Route::middleware('auth:sanctum')->get('/assets', [AssetController::class, 'index']);
Route::middleware('auth:sanctum')->post('/assets', [AssetController::class, 'store']);
Route::middleware('auth:sanctum')->get('/assets/download', [AssetController::class, 'download']);

// --- Сводка по стоимости активов (этот маршрут должен быть ДО /assets/{asset}) ---
Route::middleware('auth:sanctum')->get('/assets/cost-summary', [AssetController::class, 'costSummary']);

Route::middleware('auth:sanctum')->get('/assets/{asset}', [AssetController::class, 'show']);
Route::middleware('auth:sanctum')->match(['put', 'patch'], '/assets/{asset}', [AssetController::class, 'update']);
Route::middleware('auth:sanctum')->delete('/assets/{asset}', [AssetController::class, 'destroy']);

// --- Для поддержки stateful авторизации (например, SPA) ---
Route::middleware(['auth:sanctum', EnsureFrontendRequestsAreStateful::class])->group(function () {
    Route::post('/assets', [AssetController::class, 'store']);
});

// --- Импорт/экспорт и аналитика ---
Route::middleware('auth:sanctum')->post('/assets/import', [AssetController::class, 'import']);
Route::middleware('auth:sanctum')->get('/analytics/summary', [AnalyticsController::class, 'summary']);

// --- Пользователи и их активы ---
Route::middleware('auth:sanctum')->get('/users/{id}/details', [UserController::class, 'getUserDetails']);
Route::middleware('auth:sanctum')->get('/users/{user}/assets', [UserController::class, 'userAssets']);
Route::middleware('auth:sanctum')->get('/users/{user}/assets-all', [UserController::class, 'userAssetsAll']);

// --- Логи активов ---
Route::middleware('auth:sanctum')->get('/asset-logs', [AssetLogController::class, 'index']);

// --- Управление пользователями ---
Route::middleware('auth:sanctum')->post('/users', [UserController::class, 'store']);
Route::middleware('auth:sanctum')->delete('/users/{id}', [UserController::class, 'destroy']);
Route::middleware('auth:sanctum')->put('/users/{id}', [UserController::class, 'update']);

// --- Передача ТМЦ ---
Route::middleware('auth:sanctum')->post('/assets/{asset}/transfer', [AssetController::class, 'transfer']);

// --- Заявки на ТМЦ ---
Route::middleware(['auth:sanctum'])->post('/asset-requests', [AssetRequestController::class, 'store']);
Route::middleware(['auth:sanctum'])->get('/asset-requests', [AssetController::class, 'assetRequests']);
Route::middleware(['auth:sanctum'])->get('/asset-requests/{id}', [AssetController::class, 'showAssetRequest']);

// --- Одобрение/отклонение заявок ---
Route::middleware(['auth:sanctum'])->post('/asset-requests/{id}/approve', [AssetRequestController::class, 'approve']);
Route::middleware(['auth:sanctum'])->post('/asset-requests/{id}/reject', [AssetRequestController::class, 'reject']);

// --- Уведомления пользователя ---
Route::middleware(['auth:sanctum'])->get('/notifications', [\App\Http\Controllers\Api\NotificationController::class, 'index']);
Route::middleware(['auth:sanctum'])->post('/notifications/read', [\App\Http\Controllers\Api\NotificationController::class, 'markAllRead']);

// --- История заявок (approved/rejected) ---
Route::middleware(['auth:sanctum'])->get('/asset-requests-history', [AssetController::class, 'assetRequestsHistory']);

// --- Заявки на ремонт (техника в ремонте) ---
Route::middleware(['auth:sanctum'])->get('/repair-requests', [AssetController::class, 'repairRequests']);

// --- Установить статус заявки "В процессе" (in_progress) ---
Route::middleware(['auth:sanctum'])->post('/asset-requests/{id}/set-in-progress', [\App\Http\Controllers\Api\AssetRequestController::class, 'setInProgress']);

// --- Завершить заявку (complete) ---
Route::middleware(['auth:sanctum'])->post('/asset-requests/{id}/complete', [\App\Http\Controllers\Api\AssetRequestController::class, 'complete']);