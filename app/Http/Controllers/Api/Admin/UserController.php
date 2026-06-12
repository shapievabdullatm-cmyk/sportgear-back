<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\Role\RoleEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Admin\User\StoreRequest;
use App\Http\Requests\Api\Admin\User\UpdateRequest;
use App\Http\Resources\User\AdminUserListResource;
use App\Http\Resources\User\AdminUserResource;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $query = User::query()
            ->whereHas('roles', fn($q) => $q->where('title', RoleEnum::CLIENT->value))
            ->withCount('addresses');

        if ($request->filled('search')) {
            $search = $request->string('search')->toString();
            $upper  = 'АБВГДЕЁЖЗИЙКЛМНОПРСТУФХЦЧШЩЪЫЬЭЮЯABCDEFGHIJKLMNOPQRSTUVWXYZ';
            $lower  = 'абвгдеёжзийклмнопрстуфхцчшщъыьэюяabcdefghijklmnopqrstuvwxyz';

            $query->where(function ($q) use ($search, $upper, $lower) {
                $q->whereRaw("translate(coalesce(first_name, ''), '{$upper}', '{$lower}') LIKE translate(?, '{$upper}', '{$lower}')", ["%{$search}%"])
                  ->orWhereRaw("translate(coalesce(last_name, ''), '{$upper}', '{$lower}') LIKE translate(?, '{$upper}', '{$lower}')", ["%{$search}%"])
                  ->orWhere('phone', 'LIKE', "%{$search}%")
                  ->orWhereRaw("translate(coalesce(email, ''), '{$upper}', '{$lower}') LIKE translate(?, '{$upper}', '{$lower}')", ["%{$search}%"]);

                if (is_numeric($search)) {
                    $q->orWhere('id', $search);
                }
            });
        }

        $query->orderByDesc('created_at')->orderByDesc('id');

        $perPage = $request->integer('per_page', 20);
        $users   = $query->paginate($perPage);

        return AdminUserListResource::collection($users);
    }

    public function show(User $user)
    {
        return AdminUserResource::make($user->load(['addresses' => fn($q) => $q->orderByDesc('is_default')->orderBy('id'), 'roles']));
    }

    public function edit(User $user)
    {
        return $this->show($user);
    }

    public function store(StoreRequest $request)
    {
        $user = UserService::store($request->validated());

        return AdminUserResource::make($user);
    }

    public function update(UpdateRequest $request, User $user)
    {
        $user = UserService::update($user, $request->validated());

        return AdminUserResource::make($user);
    }

    public function destroy(User $user)
    {
        UserService::destroy($user);

        return response()->json(['message' => 'Пользователь удалён']);
    }
}