<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\AssetLog;
use Illuminate\Support\Facades\Auth;

class UserController extends Controller
{
    public function show(Request $request)
    {
        $user = $request->user();
        return response()->json([
            'id' => $user->id,
            'fullName' => $user->name,
            'position' => $user->position,
            'room' => $user->room,
            'email' => $user->email,
            'createdAt' => $user->created_at,
            'avatar_path' => $user->avatar_path,
            'role' => $user->role,
        ]);
    }

    public function withStats(Request $request)
    {
        // Получаем всех пользователей с активами, без пагинации
        $users = User::with('assets')
            ->when($request->search, function($q, $search) {
                $q->where('name', 'like', "%$search%");
            })
            ->get();

        $data = $users->map(function ($user) {
            return [
                'id' => $user->id,
                'fullName' => $user->name,
                'room' => $user->room,
                'position' => $user->position,
                'avatar_path' => $user->avatar_path,
                'stats' => [
                    'total' => $user->assets->count(),
                    'pending' => $user->assets->where('status', 'Об. статуса')->count(),
                    'repair' => $user->assets->where('status', 'В ремонте')->count(),
                ],
                'assets' => $user->assets,
            ];
        });

        return response()->json($data);
    }

    public function getUserDetails($id)
    {
        $user = User::with(['assets', 'userLogs'])->find($id); // Используем userLogs вместо assetLog
        if (!$user) {
            return response()->json(['error' => 'Пользователь не найден'], 404);
        }

        $userData = [
            'id' => $user->id,
            'fullName' => $user->name,
            'room' => $user->room,
            'position' => $user->position,
            'email' => $user->email,
            'createdAt' => $user->created_at,
            'assets' => $user->assets,
            'assetLog' => $user->userLogs, // Только логи изменений профиля
        ];

        return response()->json($userData);
    }

    public function userAssets(Request $request, User $user)
    {
        $perPage = min((int)$request->input('per_page', 15), 15);
        $assets = $user->assets()->orderBy('id', 'desc')->paginate($perPage);

        return response()->json($assets);
    }

    public function userAssetsAll(User $user)
    {
        return response()->json($user->assets()->orderBy('id', 'desc')->get());
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'position' => 'nullable|string|max:255',
            'room' => 'nullable|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:6',
            'avatar' => 'nullable|image|max:2048',
        ]);

        $user = new User();
        $user->name = $validated['name'];
        $user->position = $validated['position'] ?? null;
        $user->room = $validated['room'] ?? null;
        $user->email = $validated['email'];
        $user->password = bcrypt($validated['password']);
        $user->role = 'user';

        if ($request->hasFile('avatar')) {
            $user->avatar_path = $request->file('avatar')->store('avatars', 'public');
        }

        $user->save();

        return response()->json($user, 201);
    }

    public function update(Request $request, $id)
    {
        $user = Auth::user();
        $targetUser = \App\Models\User::findOrFail($id);

        // Только админ или сам пользователь может менять room
        if ($user->id !== $targetUser->id && $user->role !== 'admin') {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'position' => 'sometimes|string|max:255',
            'room' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|unique:users,email,' . $targetUser->id,
            'avatar' => 'nullable|image|max:2048',
        ]);

        $roomChanged = isset($validated['room']) && $validated['room'] !== $targetUser->room;

        // Обработка аватара
        if ($request->hasFile('avatar')) {
            $avatarPath = $request->file('avatar')->store('avatars', 'public');
            $targetUser->avatar_path = $avatarPath;
        }

        $targetUser->fill($validated);
        $targetUser->save();

        // Если изменился кабинет — обновить room у всей техники пользователя
        if ($roomChanged) {
            \App\Models\Asset::where('user_id', $targetUser->id)->update(['room' => $targetUser->room]);
        }

        return response()->json($targetUser);
    }

    public function destroy(Request $request, $id)
    {
        $currentUser = $request->user();
        if ($currentUser->id == $id) {
            return response()->json(['error' => 'Нельзя удалить самого себя'], 403);
        }

        $user = User::find($id);
        if (!$user) {
            return response()->json(['error' => 'Пользователь не найден'], 404);
        }

        $user->delete();

        return response()->json(['message' => 'Пользователь удалён']);
    }
}
