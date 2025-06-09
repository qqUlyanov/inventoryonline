<?php

namespace App\Http\Controllers\Api;

use App\Models\Asset;
use App\Models\User;
use App\Models\AssetLog;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use App\Imports\AssetImport;
use Symfony\Component\HttpFoundation\StreamedResponse;
use App\Exports\AssetsExport;
use App\Models\AssetRequest;
use Illuminate\Support\Facades\DB;

class AssetController extends Controller
{
    public function __construct()
    {
        // Все методы требуют аутентификацию через Sanctum
        $this->middleware('auth:sanctum');
    }

    /**
     * Получить список активов (ТМЦ) с фильтрацией и пагинацией.
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $perPage = min((int)$request->input('per_page', 50), 100);

        // Если явно передан user_id — возвращаем технику этого пользователя
        if ($request->has('user_id')) {
            $targetUser = User::findOrFail($request->user_id);
            $query = $targetUser->assets()->orderBy('id', 'desc');
        } elseif ($request->filled('status') && in_array($request->status, ['Свободен', 'free'])) {
            // Для свободной техники ищем по всей таблице и только с user_id=null, room=null
            $query = Asset::query()
                ->whereIn('status', ['Свободен', 'free'])
                ->whereNull('user_id')
                ->whereNull('room')
                ->orderBy('id', 'desc');
        } else {
            $query = $user->assets()->orderBy('id', 'desc');
        }

        // Остальные фильтры
        if ($request->filled('name')) {
            $query->where('name', 'like', '%' . $request->name . '%');
        }
        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }
        if ($request->filled('brand')) {
            $query->where('brand', $request->brand);
        }
        if ($request->filled('inv_number')) {
            $query->where('inv_number', 'like', '%' . $request->inv_number . '%');
        }
        if ($request->filled('room')) {
            $query->where('room', $request->room);
        }
        if ($request->filled('price')) {
            $query->where('price', $request->price);
        }

        $assets = $query->get();

        $result = $assets->map(function ($asset) {
            return [
                'id' => $asset->id,
                'inv_number' => $asset->inv_number,
                'name' => $asset->name,
                'type' => $asset->type,
                'brand' => $asset->brand,
                'status' => $asset->status,
                'room' => $asset->room,
                'price' => $asset->price,
                'photo' => $asset->image_path ? asset('storage/' . $asset->image_path) : null,
                // Добавляем поле updatedAt для фронта
                'updatedAt' => $asset->updated_at,
                'updated_at' => $asset->updated_at,
            ];
        })->values();

        // Поддержка разных форматов ответа (data/массив)
        if ($request->has('as_array') || $request->input('format') === 'array') {
            return response()->json($result);
        }
        return response()->json(['data' => $result]);
    }

    /**
     * Создать новый актив (ТМЦ).
     */
    public function store(Request $request)
    {
        $user = Auth::user();
        if ($user->role !== 'admin') {
            return response()->json(['message' => 'Forbidden'], 403);
        }
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|string|max:255',
            'brand' => 'required|string|max:255',
            'status' => 'sometimes|string|max:255',
            'photo' => 'nullable|image|max:2048', // изменено: теперь file
            'inv_number' => 'required|string|max:255|unique:assets,inv_number',
            'price' => 'required|numeric',
            'user_id' => 'nullable|exists:users,id',
            'room' => 'nullable|string|max:255',
        ]);

        $targetUserId = $request->input('user_id') ?? $user->id;
        $targetUser = User::findOrFail($targetUserId);

        $asset = new Asset($validated);

        // Обработка файла изображения
        if ($request->hasFile('photo')) {
            $file = $request->file('photo');
            $path = $file->store('assets', 'public');
            $asset->image_path = $path;
        }

        // Если статус не передан или явно 'Используется' — назначаем user_id и room
        if (!isset($validated['status']) || $validated['status'] === 'Используется') {
            $asset->user_id = $targetUser->id;
            $asset->room = $targetUser->room;
            $asset->status = 'Используется';
        } else {
            // Если статус 'Свободен' — user_id и room не назначаем
            $asset->user_id = null;
            $asset->room = null;
            $asset->status = 'Свободен';
        }

        $asset->save();
        AssetLog::create([
            'asset_id' => $asset->id,
            'user_id' => $asset->user_id,
            'action' => 'created',
            'description' => "Создан новый ТМЦ: {$asset->name}",
        ]);

        return response()->json($asset, 201);
    }

    /**
     * Получить паспорт техники (универсальный ответ для фронта, все поля на верхнем уровне)
     */
    public function show($id)
{
    $asset = Asset::with('user')->findOrFail($id);

    $passport = [
        'id' => $asset->id,
        'type' => $asset->type,
        'inv_number' => $asset->inv_number,
        'brand' => $asset->brand,
        'name' => $asset->name,
        'status' => $asset->status,
        'room' => $asset->room,
        'price' => $asset->price,
        'user' => $asset->user ? [
            'id' => $asset->user->id,
            'name' => $asset->user->name,
            'room' => $asset->user->room,
            'position' => $asset->user->position,
        ] : null,
        'created_at' => $asset->created_at,
        'updated_at' => $asset->updated_at,
        'model' => $asset->model,
        'cpu' => $asset->cpu,
        'ram' => $asset->ram,
        'storage' => $asset->storage,
        'os' => $asset->os,
        'diagonal' => $asset->diagonal,
        'resolution' => $asset->resolution,
        'printer_type' => $asset->printer_type,
        // Добавлено:
        'photo' => $asset->image_path ? asset('storage/' . $asset->image_path) : null,
    ];

    // Здесь можно получить реальные операции и комментарии, если нужно
    $operations = []; // Например: Operation::where('asset_id', $id)->get();
    $comments = [];   // Например: Comment::where('asset_id', $id)->get();

    return response()->json([
        'asset' => $passport,
        'operations' => $operations,
        'comments' => $comments,
    ]);
}
    /**
     * Обновить актив (ТМЦ).
     */
    public function update(Request $request, Asset $asset)
    {
        $user = Auth::user();
        $rules = [
            'name' => 'sometimes|string|max:255',
            'type' => 'sometimes|string|max:255',
            'brand' => 'sometimes|string|max:255',
            'status' => 'sometimes|string|max:255',
            'room' => 'sometimes|string|max:255',
            'price' => 'sometimes|numeric',
            'image' => 'nullable|image|max:2048',
            'user_id' => 'nullable|exists:users,id',
            'location' => 'nullable|string|max:255',
            'inv_number' => 'sometimes|string|max:255',
            // Паспортные поля:
            'cpu' => 'sometimes|string|max:255',
            'ram' => 'sometimes|string|max:255',
            'storage' => 'sometimes|string|max:255',
            'os' => 'sometimes|string|max:255',
            'diagonal' => 'sometimes|string|max:255',
            'model' => 'sometimes|string|max:255',
            'resolution' => 'sometimes|string|max:255',
            'printer_type' => 'sometimes|string|max:255',
        ];
        // Уникальность только если inv_number меняется
        if ($request->has('inv_number') && $request->inv_number !== $asset->inv_number) {
            $rules['inv_number'] = 'sometimes|string|max:255|unique:assets,inv_number';
        } else {
            $rules['inv_number'] = 'sometimes|string|max:255';
        }
        $validated = $request->validate($rules);

        // Универсальная логика назначения техники
        $targetUserId = $request->query('user_id') ?? $request->input('user_id') ?? $user->id;
        $targetUser = User::findOrFail($targetUserId);

        // Только админ может назначать другому пользователю
        if ($targetUser->id !== $user->id && $user->role !== 'admin') {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        // Если техника свободна — назначаем
        if ($asset->status === 'Свободен' && $asset->user_id === null) {
            $asset->user_id = $targetUser->id;
            $asset->room = $targetUser->room;
            $asset->status = 'Используется';
            $asset->save();
            AssetLog::create([
                'asset_id' => $asset->id,
                'user_id' => $user->id,
                'action' => 'assigned',
                'description' => "Техника назначена пользователю: {$targetUser->name} (id: {$targetUser->id}) (авто)",
            ]);
            return response()->json($asset);
        }
        // Если техника уже назначена этому пользователю — просто обновляем room
        if ($asset->user_id === $targetUser->id) {
            $asset->room = $targetUser->room;
            $asset->save();
            return response()->json($asset);
        }

        // --- Сброс user_id и room при смене на 'Свободен' ---
        if (isset($validated['status']) && in_array($validated['status'], ['Свободен', 'free'])) {
            $asset->user_id = null;
            $asset->room = null;
        }
        // fill/save только для остальных полей (user_id/room не трогаем)
        unset($validated['user_id'], $validated['room']);
        $asset->fill($validated);
        $asset->save();

        AssetLog::create([
            'asset_id' => $asset->id,
            'user_id' => $user->id,
            'action' => 'updated',
            'description' => 'Обновления: ' . json_encode($validated, JSON_UNESCAPED_UNICODE),
        ]);

        return response()->json($asset);
    }

    /**
     * Удалить актив (ТМЦ).
     */
    public function destroy(Asset $asset)
    {
        $user = Auth::user();

        // Только владелец или админ может удалить актив
        if ($user->id !== $asset->user_id && $user->role !== 'admin') {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        // Удаляем изображение, если есть
        if ($asset->image_path) {
            try {
                Storage::disk('public')->delete($asset->image_path);
            } catch (\Throwable $e) {
                // Не прерываем удаление, если файл не найден
            }
        }

        $assetName = $asset->name;
        $assetId = $asset->id;

        // --- СНАЧАЛА логируем удаление ---
        AssetLog::create([
            'asset_id' => $assetId,
            'user_id' => $user->id,
            'action' => 'deleted',
            'description' => "Удалён ТМЦ: $assetName",
        ]);

        // --- ПОТОМ удаляем asset ---
        $asset->delete();

        return response()->json(['message' => 'Asset deleted']);
    }

    /**
     * Получить сводку по стоимости активов.
     */
    public function costSummary(Request $request)
    {
        $user = $request->user();

        // Для админа — все активы, для обычного пользователя — только свои
        $assets = $user->role === 'admin'
            ? Asset::all()
            : $user->assets;

        // Группировка по типу
        $costByType = [];
        foreach ($assets as $asset) {
            $type = $asset->type ?: 'Без категории';
            $costByType[$type] = ($costByType[$type] ?? 0) + ($asset->price ?? 0);
        }

        // Группировка по статусу
        $costByStatus = [];
        foreach ($assets as $asset) {
            $status = $asset->status ?: 'Без статуса';
            $costByStatus[$status] = ($costByStatus[$status] ?? 0) + ($asset->price ?? 0);
        }

        // Общая стоимость
        $totalCost = $assets->sum('price');

        return response()->json([
            'total_cost' => $totalCost,
            'cost_by_type' => $costByType,
            'cost_by_status' => $costByStatus,
        ]);
    }

    /**
     * Импортировать активы из файла (Excel/CSV).
     */
    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv',
            'user_id' => 'sometimes|exists:users,id',
        ]);

        $authUser = $request->user();
        $targetUser = $authUser;

        // Если админ и передан user_id — импортируем для этого пользователя
        if ($authUser->role === 'admin' && $request->filled('user_id')) {
            $targetUser = \App\Models\User::findOrFail($request->input('user_id'));
        }

        \Maatwebsite\Excel\Facades\Excel::import(new \App\Imports\AssetImport($targetUser), $request->file('file'));

        return response()->json(['message' => 'Импорт завершён']);
    }

    /**
     * Скачать список активов с фильтрами (Excel/CSV).
     */
    public function download(Request $request)
    {
        $user = $request->user();

        // Для админа — можно скачать активы другого пользователя
        if ($user->role === 'admin' && $request->has('user_id')) {
            $targetUser = User::findOrFail($request->user_id);
            $query = $targetUser->assets()->orderBy('id', 'desc');
        } else {
            $query = $user->assets()->orderBy('id', 'desc');
        }

        // Применение фильтров
        if ($request->filled('name')) {
            $query->where('name', 'like', '%' . $request->name . '%');
        }
        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('brand')) {
            $query->where('brand', $request->brand);
        }
        if ($request->filled('inv_number')) {
            $query->where('inv_number', 'like', '%' . $request->inv_number . '%');
        }
        if ($request->filled('updated_at')) {
            $query->whereDate('updated_at', $request->updated_at);
        }
        if ($request->filled('price')) {
            $query->where('price', $request->price);
        }

        $assets = $query->get();

        $export = new AssetsExport($assets);

        $format = $request->input('format', 'xlsx');
        $fileName = 'tmc_export.' . $format;

        return Excel::download($export, $fileName, $format === 'csv' ? \Maatwebsite\Excel\Excel::CSV : \Maatwebsite\Excel\Excel::XLSX);
    }

    /**
     * Передать актив (ТМЦ) другому пользователю.
     */
    public function transfer(Request $request, Asset $asset)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
        ]);

        $targetUser = User::findOrFail($request->input('user_id'));

        // Обновляем владельца и кабинет
        $asset->user_id = $targetUser->id;
        $asset->room = $targetUser->room;
        $asset->save();

        // Логируем операцию передачи
        AssetLog::create([
            'asset_id' => $asset->id,
            'user_id' => $request->user()->id, // кто передал
            'action' => 'transferred',
            'description' => "ТМЦ передан пользователю: {$targetUser->name} (id: {$targetUser->id})",
        ]);

        return response()->json(['message' => 'ТМЦ передан другому пользователю']);
    }

    /**
     * Получить список всех заявок на операции с ТМЦ (для страницы "Заявки пользователей").
     * Поддержка поиска, пагинации, user_transfer, фильтрации по статусу.
     */
    public function assetRequests(Request $request)
    {
        $user = $request->user();
        $perPage = min((int)$request->input('per_page', 20), 50);

        $query = AssetRequest::query()
            ->with(['user:id,name,email,avatar_path,room,position'])
            ->orderByDesc('created_at');

        // Если не админ — только свои заявки
        if ($user->role !== 'admin') {
            $query->where('created_by', $user->id);
        }

        // Поиск по пользователю, ТМЦ, комментарию, операции, статусу
        if ($request->filled('search')) {
            $search = mb_strtolower($request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->whereHas('user', function ($qu) use ($search) {
                    $qu->whereRaw('LOWER(name) like ?', ["%$search%"])
                        ->orWhereRaw('LOWER(email) like ?', ["%$search%"])
                        ->orWhereRaw('LOWER(fullName) like ?', ["%$search%"]);
                })
                ->orWhereRaw('LOWER(operation) like ?', ["%$search%"])
                ->orWhereRaw('LOWER(comment) like ?', ["%$search%"])
                ->orWhereRaw('LOWER(status) like ?', ["%$search%"]);
            });
        }

        // Фильтр по статусу (если нужен, например, только pending)
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $requests = $query->paginate($perPage);

        // Собираем все asset_ids из заявок
        $assetIds = [];
        foreach ($requests as $req) {
            if (is_array($req->asset_ids)) {
                $assetIds = array_merge($assetIds, $req->asset_ids);
            }
        }
        $assets = \App\Models\Asset::whereIn('id', $assetIds)->get()->keyBy('id');

        // Собираем user_transfer для transfer-заявок
        $userTransferIds = [];
        foreach ($requests as $req) {
            if ($req->operation === 'transfer' && $req->user_id) {
                $userTransferIds[] = $req->user_id;
            }
        }
        $userTransfers = [];
        if ($userTransferIds) {
            $userTransfers = \App\Models\User::whereIn('id', $userTransferIds)->get()->keyBy('id');
        }

        // Формируем массив для фронта
        $data = [];
        foreach ($requests as $req) {
            $reqAssets = [];
            if (is_array($req->asset_ids)) {
                foreach ($req->asset_ids as $aid) {
                    if (isset($assets[$aid])) {
                        $asset = $assets[$aid];
                        $reqAssets[] = [
                            'id' => $asset->id,
                            'inv_number' => $asset->inv_number,
                            'name' => $asset->name,
                            'type' => $asset->type,
                            'brand' => $asset->brand,
                            'photo' => $asset->image_path ? asset('storage/' . $asset->image_path) : null,
                        ];
                    }
                }
            }
            // user_transfer для transfer-заявок
            $userTransfer = null;
            if ($req->operation === 'transfer' && $req->user_id && isset($userTransfers[$req->user_id])) {
                $userTransfer = $userTransfers[$req->user_id];
                $userTransfer->fullName = $userTransfer->name;
            }
            // Добавляем fullName для фронта (чтобы работал request.user.fullName)
            $userObj = $req->user;
            if ($userObj) {
                $userObj->fullName = $userObj->name;
            }

            // --- ДОПОЛНИТЕЛЬНЫЕ ПОЛЯ ДЛЯ UX ---
            $extra = [];
            if ($req->operation === 'change') {
                // Смена техники: ищем технику до и после (по логам или по user_id до/после заявки)
                // Для простоты: assets_before = техника пользователя до заявки, assets_after = после заявки
                $assetsBefore = \App\Models\Asset::whereIn('id', $req->asset_ids)
                    ->where('updated_at', '<', $req->created_at)
                    ->get();
                $assetsAfter = \App\Models\Asset::whereIn('id', $req->asset_ids)
                    ->where('updated_at', '>=', $req->created_at)
                    ->get();
                $extra['assets_before'] = $assetsBefore->map(fn($a) => [
                    'id' => $a->id,
                    'name' => $a->name,
                    'inv_number' => $a->inv_number,
                ]);
                $extra['assets_after'] = $assetsAfter->map(fn($a) => [
                    'id' => $a->id,
                    'name' => $a->name,
                    'inv_number' => $a->inv_number,
                ]);
            } elseif ($req->operation === 'repair') {
                // Ремонт: ищем даты постановки и завершения по логам
                $repairStarted = \App\Models\AssetLog::where('action', 'updated')
                    ->where('description', 'like', '%В ремонте%')
                    ->whereIn('asset_id', $req->asset_ids)
                    ->orderBy('created_at', 'asc')
                    ->first();
                $repairCompleted = \App\Models\AssetLog::where('action', 'request_completed')
                    ->whereIn('asset_id', $req->asset_ids)
                    ->orderBy('created_at', 'desc')
                    ->first();
                $extra['repair_started_at'] = $repairStarted?->created_at;
                $extra['repair_completed_at'] = $repairCompleted?->created_at;
            } elseif ($req->operation === 'transfer') {
                // Передача: user (от кого), user_transfer (кому), assets
                $extra['user'] = $userObj;
                $extra['user_transfer'] = $userTransfer;
                $extra['assets'] = $reqAssets;
            }

            $data[] = array_merge([
                'id' => $req->id,
                'created_at' => $req->created_at,
                'user' => $userObj,
                'operation' => $req->operation,
                'assets' => $reqAssets,
                'comment' => $req->comment,
                'status' => $req->status,
                'user_transfer' => $userTransfer,
                'reject_comment' => $req->reject_comment ?? null,
                'created_by' => $req->created_by,
            ], $extra);
        }

        return response()->json([
            'data' => $data,
            'current_page' => $requests->currentPage(),
            'last_page' => $requests->lastPage(),
            'per_page' => $requests->perPage(),
            'total' => $requests->total(),
        ]);
    }

    public function showAssetRequest($id)
    {
        $req = AssetRequest::with(['user:id,name,email,avatar_path,room,position'])->findOrFail($id);

        // Собираем ТМЦ
        $assets = [];
        if (is_array($req->asset_ids)) {
            $assetModels = \App\Models\Asset::whereIn('id', $req->asset_ids)->get()->keyBy('id');
            foreach ($req->asset_ids as $aid) {
                if (isset($assetModels[$aid])) {
                    $asset = $assetModels[$aid];
                    $assets[] = [
                        'id' => $asset->id,
                        'inv_number' => $asset->inv_number,
                        'name' => $asset->name,
                        'type' => $asset->type,
                        'brand' => $asset->brand,
                        'status' => $asset->status,
                        'photo' => $asset->image_path ? asset('storage/' . $asset->image_path) : null,
                    ];
                }
            }
        }

        // Добавляем fullName для фронта (чтобы работал request.user.fullName)
        $user = $req->user;
        if ($user) {
            $user->fullName = $user->name;
        }

        // Добавляем user_transfer для transfer-заявок
        $userTransfer = null;
        if ($req->operation === 'transfer' && $req->user_id) {
            $userTransferModel = \App\Models\User::find($req->user_id);
            if ($userTransferModel) {
                $userTransfer = $userTransferModel;
                $userTransfer->fullName = $userTransferModel->name;
            }
        }

        return response()->json([
            'id' => $req->id,
            'created_at' => $req->created_at,
            'user' => $user,
            'operation' => $req->operation,
            'assets' => $assets,
            'comment' => $req->comment,
            'status' => $req->status,
            'reject_comment' => $req->reject_comment ?? null, // причина отказа для истории и страницы заявки
            'user_transfer' => $userTransfer,
        ]);
    }

    /**
     * Получить историю заявок (approved/rejected) с поддержкой поиска и пагинации.
     */
    public function assetRequestsHistory(Request $request)
    {
        $user = $request->user();
        $perPage = min((int)$request->input('per_page', 20), 50);

        $query = AssetRequest::query()
            ->with(['user:id,name,email,avatar_path,room,position'])
            ->orderByDesc('created_at')
            ->whereIn('status', ['approved', 'rejected']);

        // Если не админ — только свои заявки
        if ($user->role !== 'admin') {
            $query->where('created_by', $user->id);
        }

        // Поиск по пользователю, ТМЦ, комментарию, операции, статусу
        if ($request->filled('search')) {
            $search = mb_strtolower($request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->whereHas('user', function ($qu) use ($search) {
                    $qu->whereRaw('LOWER(name) like ?', ["%$search%"])
                        ->orWhereRaw('LOWER(email) like ?', ["%$search%"])
                        ->orWhereRaw('LOWER(fullName) like ?', ["%$search%"]);
                })
                ->orWhereRaw('LOWER(operation) like ?', ["%$search%"])
                ->orWhereRaw('LOWER(comment) like ?', ["%$search%"])
                ->orWhereRaw('LOWER(status) like ?', ["%$search%"]);
            });
        }

        $requests = $query->paginate($perPage);

        // Собираем все asset_ids из заявок
        $assetIds = [];
        foreach ($requests as $req) {
            if (is_array($req->asset_ids)) {
                $assetIds = array_merge($assetIds, $req->asset_ids);
            }
        }
        $assets = \App\Models\Asset::whereIn('id', $assetIds)->get()->keyBy('id');

        // Собираем user_transfer для transfer-заявок
        $userTransferIds = [];
        foreach ($requests as $req) {
            if ($req->operation === 'transfer' && $req->user_id) {
                $userTransferIds[] = $req->user_id;
            }
        }
        $userTransfers = [];
        if ($userTransferIds) {
            $userTransfers = \App\Models\User::whereIn('id', $userTransferIds)->get()->keyBy('id');
        }

        // Формируем массив для фронта
        $data = [];
        foreach ($requests as $req) {
            $reqAssets = [];
            if (is_array($req->asset_ids)) {
                foreach ($req->asset_ids as $aid) {
                    if (isset($assets[$aid])) {
                        $asset = $assets[$aid];
                        $reqAssets[] = [
                            'id' => $asset->id,
                            'inv_number' => $asset->inv_number,
                            'name' => $asset->name,
                            'type' => $asset->type,
                            'brand' => $asset->brand,
                            'photo' => $asset->image_path ? asset('storage/' . $asset->image_path) : null,
                        ];
                    }
                }
            }
            // user_transfer для transfer-заявок
            $userTransfer = null;
            if ($req->operation === 'transfer' && $req->user_id && isset($userTransfers[$req->user_id])) {
                $userTransfer = $userTransfers[$req->user_id];
                $userTransfer->fullName = $userTransfer->name;
            }
            // Добавляем fullName для фронта (чтобы работал request.user.fullName)
            $userObj = $req->user;
            if ($userObj) {
                $userObj->fullName = $userObj->name;
            }
            $data[] = [
                'id' => $req->id,
                'created_at' => $req->created_at,
                'user' => $userObj,
                'operation' => $req->operation,
                'assets' => $reqAssets,
                'comment' => $req->comment,
                'status' => $req->status,
                'user_transfer' => $userTransfer,
                'reject_comment' => $req->reject_comment ?? null,
                'created_by' => $req->created_by,
            ];
        }

        return response()->json([
            'data' => $data,
            'current_page' => $requests->currentPage(),
            'last_page' => $requests->lastPage(),
            'per_page' => $requests->perPage(),
            'total' => $requests->total(),
        ]);
    }

    /**
     * Получить список заявок на ремонт со статусом "in_progress" (для страницы "Техника в ремонте").
     * Поддержка поиска по пользователю, технике, комментарию, id.
     */
    public function repairRequests(Request $request)
    {
        $user = $request->user();
        $perPage = min((int)$request->input('per_page', 20), 50);

        $query = AssetRequest::query()
            ->with(['user:id,name,email,avatar_path,room,position'])
            ->orderByDesc('created_at')
            ->where('operation', 'repair')
            ->where('status', 'in_progress');

        // Поиск по пользователю, технике, комментарию, id
        if ($request->filled('search')) {
            $search = mb_strtolower($request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->where('id', $search)
                  ->orWhereHas('user', function ($qu) use ($search) {
                      $qu->whereRaw('LOWER(name) like ?', ["%$search%"])
                          ->orWhereRaw('LOWER(email) like ?', ["%$search%"])
                          ->orWhereRaw('LOWER(fullName) like ?', ["%$search%"]);
                  })
                  ->orWhereRaw('LOWER(comment) like ?', ["%$search%"]);
            });
        }

        $requests = $query->paginate($perPage);

        // Собираем все asset_ids из заявок
        $assetIds = [];
        foreach ($requests as $req) {
            if (is_array($req->asset_ids)) {
                $assetIds = array_merge($assetIds, $req->asset_ids);
            }
        }
        $assets = \App\Models\Asset::whereIn('id', $assetIds)->get()->keyBy('id');

        // Формируем массив для фронта
        $data = [];
        foreach ($requests as $req) {
            $reqAssets = [];
            if (is_array($req->asset_ids)) {
                foreach ($req->asset_ids as $aid) {
                    if (isset($assets[$aid])) {
                        $asset = $assets[$aid];
                        $reqAssets[] = [
                            'id' => $asset->id,
                            'inv_number' => $asset->inv_number,
                            'name' => $asset->name,
                            'type' => $asset->type,
                            'brand' => $asset->brand,
                            'photo' => $asset->image_path ? asset('storage/' . $asset->image_path) : null,
                        ];
                    }
                }
            }
            // Добавляем fullName для фронта (чтобы работал request.user.fullName)
            $userObj = $req->user;
            if ($userObj) {
                $userObj->fullName = $userObj->name;
            }
            $data[] = [
                'id' => $req->id,
                'created_at' => $req->created_at,
                'user' => $userObj,
                'assets' => $reqAssets,
                'comment' => $req->comment,
                'status' => $req->status,
            ];
        }

        return response()->json([
            'data' => $data,
            'current_page' => $requests->currentPage(),
            'last_page' => $requests->lastPage(),
            'per_page' => $requests->perPage(),
            'total' => $requests->total(),
        ]);
    }
}
