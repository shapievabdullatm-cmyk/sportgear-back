<?php
namespace App\Models;

use App\Enums\Role\RoleEnum;
use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    protected $table    = 'roles';
    protected $fillable = ['title', 'label'];

    // Убрали cast — title остаётся строкой
    public function users()
    {
        return $this->belongsToMany(User::class, 'role_user');
    }
}
