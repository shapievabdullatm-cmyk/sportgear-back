<?php
namespace App\Models;

use App\Enums\Role\RoleEnum;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, Notifiable;

    protected $fillable = [
        'phone', 'email', 'password',
        'first_name', 'last_name', 'gender', 'birth_date',
        'pending_email', 'email_verify_token', 'email_verify_token_expires_at',
    ];

    protected $hidden = [
        'password', 'email_verify_token',
    ];

    protected $casts = [
        'birth_date'                    => 'date',
        'email_verify_token_expires_at' => 'datetime',
    ];

    // ── Связи ─────────────────────────────────────────────────────
    public function roles()
    {
        return $this->belongsToMany(Role::class, 'role_user');
    }

    public function cartItems() { return $this->hasMany(CartItem::class); }
    public function orders()    { return $this->hasMany(Order::class); }
    public function wishlist()  { return $this->hasMany(Wishlist::class); }
    public function addresses() { return $this->hasMany(Address::class); }

    // ── Хелперы ───────────────────────────────────────────────────
    public function hasRole(RoleEnum $role): bool
    {
        return $this->roles->contains(function ($r) use ($role) {
            $value = $r->title instanceof RoleEnum ? $r->title->value : $r->title;
            return $value === $role->value;
        });
    }

    public function isAdmin(): bool
    {
        return $this->hasRole(RoleEnum::ADMIN);
    }

    public function isClient(): bool
    {
        return $this->hasRole(RoleEnum::CLIENT);
    }

    public function assignRole(RoleEnum $role): void
    {
        $roleModel = Role::where('title', $role->value)->firstOrFail();
        $this->roles()->syncWithoutDetaching($roleModel->id);
    }

    public function removeRole(RoleEnum $role): void
    {
        $roleModel = Role::where('title', $role->value)->first();
        if ($roleModel) {
            $this->roles()->detach($roleModel->id);
        }
    }
}
