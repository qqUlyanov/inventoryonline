<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $notifications = $user->notifications()
            ->orderBy('created_at', 'desc')
            ->take(30)
            ->get()
            ->map(function ($notif) {
                return [
                    'id' => $notif->id,
                    'text' => $notif->data['text'] ?? $notif->data['message'] ?? null,
                    'message' => $notif->data['message'] ?? null,
                    'asset_request_id' => $notif->data['asset_request_id'] ?? null,
                    'created_at' => $notif->created_at,
                    'read_at' => $notif->read_at,
                ];
            });

        return response()->json($notifications);
    }

    public function markAllRead(Request $request)
    {
        // Для корректной работы с Sanctum убедитесь, что запрос отправляется с заголовком X-XSRF-TOKEN и withCredentials: true на фронте!
        $user = $request->user();
        $user->unreadNotifications()->update(['read_at' => now()]);
        return response()->json(['status' => 'ok']);
    }
}
