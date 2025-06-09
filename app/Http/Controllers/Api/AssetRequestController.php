<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AssetRequest;
use App\Models\User;
use App\Models\Asset;
use App\Models\AssetLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Notifications\SimpleNotification;

class AssetRequestController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'asset_ids' => 'required|array|min:1',
            'operation' => 'required|string',
            'user_id' => 'nullable|integer|exists:users,id',
            'comment' => 'nullable|string',
        ]);

        // Проверка на активные заявки по выбранным asset_ids
        $assetIds = $validated['asset_ids'];
        $activeRequests = AssetRequest::where(function ($query) use ($assetIds) {
            foreach ($assetIds as $id) {
                $query->orWhereJsonContains('asset_ids', $id);
            }
        })
        ->whereIn('status', ['pending', 'in_progress'])
        ->exists();

        if ($activeRequests) {
            return response()->json([
                'message' => 'Для выбранной техники уже существует активная заявка.'
            ], 409);
        }

        $assetRequest = AssetRequest::create([
            'asset_ids' => $validated['asset_ids'],
            'operation' => $validated['operation'],
            'user_id' => $validated['user_id'] ?? null,
            'comment' => $validated['comment'] ?? null,
            'created_by' => \Illuminate\Support\Facades\Auth::id() ?? 1,
        ]);

        // --- Смена статуса техники на "Об. статуса" при создании заявки ---
        foreach ($assetIds as $assetId) {
            $asset = Asset::find($assetId);
            if ($asset && $asset->status !== 'Об. статуса') {
                $oldStatus = $asset->status;
                $asset->status = 'Об. статуса';
                $asset->save();
                AssetLog::create([
                    'asset_id' => $asset->id,
                    'user_id' => \Illuminate\Support\Facades\Auth::id(),
                    'action' => 'updated',
                    'description' => "Статус изменён: $oldStatus → Об. статуса (по созданию заявки)",
                ]);
            }
        }
        // --- конец смены статуса ---

        // --- Логируем создание заявки ---
        \App\Models\AssetLog::create([
            'asset_id' => null,
            'user_id' => \Illuminate\Support\Facades\Auth::id(),
            'action' => 'request_created',
            'description' => 'Создана заявка на операцию: ' . $assetRequest->operation . ', id: ' . $assetRequest->id,
        ]);
        // --- конец лога ---

        // --- Уведомление администраторам ---
        $admins = \App\Models\User::where('role', 'admin')->get();
        $userName = Auth::user()?->name ?? 'Пользователь';
        $operation = match ($assetRequest->operation) {
            'repair' => 'Ремонт',
            'change' => 'Сменить',
            'transfer' => 'Передача',
            'error' => 'Сообщить об ошибке',
            default => $assetRequest->operation,
        };
        $text = "Новая заявка: $operation от $userName";
        foreach ($admins as $admin) {
            $admin->notify(new SimpleNotification($text, $assetRequest->id));
        }
        // --- конец уведомления ---

        return response()->json(['success' => true, 'id' => $assetRequest->id]);
    }

    /**
     * Одобрить заявку на операцию с ТМЦ.
     */
    public function approve(Request $request, $id)
    {
        $user = $request->user();
        $assetRequest = AssetRequest::findOrFail($id);

        if ($assetRequest->status !== 'pending') {
            return response()->json(['message' => 'Заявка уже обработана'], 400);
        }

        // Только админ может одобрять
        if ($user->role !== 'admin') {
            return response()->json(['message' => 'Нет прав'], 403);
        }

        DB::beginTransaction();
        try {
            // --- Передача ТМЦ ---
            if ($assetRequest->operation === 'transfer' && $assetRequest->user_id) {
                $targetUser = User::find($assetRequest->user_id);
                if ($targetUser) {
                    foreach ($assetRequest->asset_ids as $assetId) {
                        $asset = Asset::find($assetId);
                        if ($asset) {
                            $oldUserId = $asset->user_id;
                            $asset->user_id = $targetUser->id;
                            $asset->room = $targetUser->room;
                            $asset->status = 'Используется'; // <-- статус всегда "Используется" после передачи
                            $asset->save();

                            AssetLog::create([
                                'asset_id' => $asset->id,
                                'user_id' => $user->id,
                                'action' => 'transferred',
                                'description' => "ТМЦ передан пользователю: {$targetUser->name} (id: {$targetUser->id})",
                            ]);
                        }
                    }
                }
            }
            // --- Ремонт ТМЦ ---
            elseif ($assetRequest->operation === 'repair') {
                // Здесь больше не меняем статус техники на 'В ремонте', это делает setInProgress
                // Можно добавить другую логику, если нужно
            }
            // --- Смена статуса ТМЦ ---
            elseif ($assetRequest->operation === 'change') {
                foreach ($assetRequest->asset_ids as $assetId) {
                    $asset = Asset::find($assetId);
                    if ($asset) {
                        $oldStatus = $asset->status;
                        $asset->status = 'Свободен';
                        $asset->user_id = null;
                        $asset->room = null;
                        $asset->save();

                        AssetLog::create([
                            'asset_id' => $asset->id,
                            'user_id' => $user->id,
                            'action' => 'updated',
                            'description' => "Статус изменён: $oldStatus → Свободен (по заявке), пользователь и кабинет сброшены",
                        ]);
                    }
                }
            }
            // Можно добавить обработку других операций (например, error) при необходимости

            $assetRequest->status = 'approved';
            $assetRequest->reject_comment = null;
            $assetRequest->save();

            // --- Логируем одобрение заявки ---
            \App\Models\AssetLog::create([
                'asset_id' => null,
                'user_id' => $user->id,
                'action' => 'request_approved',
                'description' => 'Заявка одобрена: id ' . $assetRequest->id,
            ]);
            // --- конец лога ---

            // --- Уведомление пользователю о смене статуса ---
            $requestUser = \App\Models\User::find($assetRequest->created_by);
            if ($requestUser) {
                $requestUser->notify(new \App\Notifications\SimpleNotification(
                    'Ваша заявка №' . $assetRequest->id . ' одобрена',
                    $assetRequest->id
                ));
            }
            // --- конец уведомления ---

            // --- Удаляем уведомления, связанные с этой заявкой ---
            \Illuminate\Notifications\DatabaseNotification::where('data->asset_request_id', $assetRequest->id)->delete();

            DB::commit();
            return response()->json(['success' => true]);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json(['message' => 'Ошибка при одобрении: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Отклонить заявку на операцию с ТМЦ.
     */
    public function reject(Request $request, $id)
    {
        $user = $request->user();
        $assetRequest = AssetRequest::findOrFail($id);

        if ($assetRequest->status !== 'pending') {
            return response()->json(['message' => 'Заявка уже обработана'], 400);
        }

        // Только админ может отклонять
        if ($user->role !== 'admin') {
            return response()->json(['message' => 'Нет прав'], 403);
        }

        $validated = $request->validate([
            'comment' => 'required|string|max:1000',
        ]);

        $assetRequest->status = 'rejected';
        $assetRequest->reject_comment = $validated['comment'];
        $assetRequest->save();

        // --- Логируем отклонение заявки ---
        \App\Models\AssetLog::create([
            'asset_id' => null,
            'user_id' => $user->id,
            'action' => 'request_rejected',
            'description' => 'Заявка отклонена: id ' . $assetRequest->id . '. Причина: ' . $validated['comment'],
        ]);
        // --- конец лога ---

        // --- Уведомление пользователю о смене статуса ---
        $requestUser = \App\Models\User::find($assetRequest->created_by);
        if ($requestUser) {
            $requestUser->notify(new \App\Notifications\SimpleNotification(
                'Ваша заявка №' . $assetRequest->id . ' отклонена. Причина: ' . $validated['comment'],
                $assetRequest->id
            ));
        }
        // --- конец уведомления ---

        // --- Удаляем уведомления, связанные с этой заявкой ---
        \Illuminate\Notifications\DatabaseNotification::where('data->asset_request_id', $assetRequest->id)->delete();

        return response()->json(['success' => true]);
    }

    /**
     * Установить статус заявки "in_progress" (например, для ремонта).
     */
    public function setInProgress(Request $request, $id)
    {
        $user = $request->user();
        $assetRequest = \App\Models\AssetRequest::findOrFail($id);

        // Только админ может менять статус
        if ($user->role !== 'admin') {
            return response()->json(['message' => 'Нет прав'], 403);
        }

        if ($assetRequest->status !== 'pending') {
            return response()->json(['message' => 'Заявка уже обработана'], 400);
        }

        $assetRequest->status = 'in_progress';
        $assetRequest->save();

        // Если заявка на ремонт — меняем статус техники на "В ремонте"
        if ($assetRequest->operation === 'repair') {
            foreach ($assetRequest->asset_ids as $assetId) {
                $asset = \App\Models\Asset::find($assetId);
                if ($asset) {
                    $oldStatus = $asset->status;
                    $asset->status = 'В ремонте';
                    $asset->save();
                    \App\Models\AssetLog::create([
                        'asset_id' => $asset->id,
                        'user_id' => $user->id,
                        'action' => 'updated',
                        'description' => "Статус изменён: $oldStatus → В ремонте (по заявке)",
                    ]);
                }
            }
        }

        // Логируем смену статуса
        \App\Models\AssetLog::create([
            'asset_id' => null,
            'user_id' => $user->id,
            'action' => 'request_in_progress',
            'description' => 'Заявка переведена в статус "В процессе": id ' . $assetRequest->id,
        ]);

        // --- Уведомление пользователю о смене статуса ---
        $requestUser = \App\Models\User::find($assetRequest->created_by);
        if ($requestUser) {
            $requestUser->notify(new \App\Notifications\SimpleNotification(
                'Ваша заявка №' . $assetRequest->id . ' переведена в статус "В процессе"',
                $assetRequest->id
            ));
        }
        // --- конец уведомления ---

        return response()->json(['success' => true]);
    }

    /**
     * Завершить заявку (complete).
     */
    public function complete(Request $request, $id)
    {
        $user = $request->user();
        $assetRequest = \App\Models\AssetRequest::findOrFail($id);

        // Только админ может завершать заявку
        if ($user->role !== 'admin') {
            return response()->json(['message' => 'Нет прав'], 403);
        }

        // Только заявки в статусе in_progress можно завершить
        if ($assetRequest->status !== 'in_progress') {
            return response()->json(['message' => 'Заявка не в процессе'], 400);
        }

        $assetRequest->status = 'approved';
        $assetRequest->save();

        // Если заявка на ремонт — меняем статус техники на "Используется"
        if ($assetRequest->operation === 'repair') {
            foreach ($assetRequest->asset_ids as $assetId) {
                $asset = \App\Models\Asset::find($assetId);
                if ($asset) {
                    $oldStatus = $asset->status;
                    $asset->status = 'Используется';
                    $asset->save();
                    \App\Models\AssetLog::create([
                        'asset_id' => $asset->id,
                        'user_id' => $user->id,
                        'action' => 'updated',
                        'description' => "Статус изменён: $oldStatus → Используется (по завершению заявки)",
                    ]);
                }
            }
        }

        // Логируем завершение заявки
        \App\Models\AssetLog::create([
            'asset_id' => null,
            'user_id' => $user->id,
            'action' => 'request_completed',
            'description' => 'Заявка завершена: id ' . $assetRequest->id,
        ]);

        // --- Уведомление пользователю о смене статуса ---
        $requestUser = \App\Models\User::find($assetRequest->created_by);
        if ($requestUser) {
            $requestUser->notify(new \App\Notifications\SimpleNotification(
                'Ваша заявка №' . $assetRequest->id . ' завершена',
                $assetRequest->id
            ));
        }
        // --- конец уведомления ---

        return response()->json(['success' => true]);
    }
}
