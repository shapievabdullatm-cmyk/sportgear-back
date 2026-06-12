<?php

namespace App\Services;

use App\Enums\Role\RoleEnum;
use App\Models\Address;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class UserService
{
    public static function store(array $data): User
    {
        return DB::transaction(function () use ($data) {
            $addresses = $data['addresses'] ?? [];
            unset($data['addresses']);

            $user = User::create($data);

            $clientRole = Role::firstOrCreate(['title' => RoleEnum::CLIENT->value]);
            $user->roles()->syncWithoutDetaching($clientRole->id);

            self::syncAddresses($user, $addresses);

            return $user->load('addresses', 'roles');
        });
    }

    public static function update(User $user, array $data): User
    {
        return DB::transaction(function () use ($user, $data) {
            $addresses = $data['addresses'] ?? null;
            unset($data['addresses']);

            $user->update($data);

            if ($addresses !== null) {
                self::syncAddresses($user, $addresses);
            }

            return $user->fresh(['addresses', 'roles']);
        });
    }

    public static function destroy(User $user): void
    {
        DB::transaction(function () use ($user) {
            $user->addresses()->delete();
            $user->roles()->detach();
            $user->delete();
        });
    }

    /**
     * Синхронизирует адреса юзера: создаёт новые, обновляет существующие,
     * удаляет отсутствующие. Гарантирует ровно один is_default = true.
     */
    private static function syncAddresses(User $user, array $payload): void
    {
        $existingIds = $user->addresses()->pluck('id')->all();
        $keepIds     = [];
        $defaultId   = null;

        foreach ($payload as $idx => $data) {
            $isDefault = !empty($data['is_default']);
            $data['is_default'] = $isDefault;

            $providedId = $data['id'] ?? null;
            unset($data['id'], $data['user_id']);

            if ($providedId && in_array($providedId, $existingIds, true)) {
                $address = Address::find($providedId);
                if ($address && $address->user_id === $user->id) {
                    $address->update($data);
                    $keepIds[] = $address->id;
                    if ($isDefault) $defaultId = $address->id;
                }
            } else {
                $address = $user->addresses()->create($data);
                $keepIds[] = $address->id;
                if ($isDefault) $defaultId = $address->id;
            }
        }

        // Удаляем те, что не попали в payload
        $toDelete = array_diff($existingIds, $keepIds);
        if (!empty($toDelete)) {
            Address::whereIn('id', $toDelete)->delete();
        }

        // Гарантируем единственный is_default
        if ($defaultId) {
            $user->addresses()->where('id', '!=', $defaultId)->update(['is_default' => false]);
            $user->addresses()->where('id', $defaultId)->update(['is_default' => true]);
        } elseif (!empty($keepIds)) {
            // Если ни один не помечен дефолтным — делаем первый
            $first = $user->addresses()->orderBy('id')->first();
            if ($first) {
                $user->addresses()->where('id', '!=', $first->id)->update(['is_default' => false]);
                $first->update(['is_default' => true]);
            }
        }
    }
}