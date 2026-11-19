<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public function hasPermission(string $permission): bool
    {
        $permissions = once(fn (): array => DB::table('permission_user')
            ->join('permissions', 'permissions.id', '=', 'permission_user.permission_id')
            ->where('user_id', $this->id)->pluck('permissions.name')->all());

        return in_array($permission, $permissions, true);
    }

    public function isAdministrator(): bool
    {
        return once(fn (): bool => DB::table('role_user')
            ->join('roles', 'roles.id', '=', 'role_user.role_id')
            ->where('user_id', $this->id)->where('roles.name', 'administrator')->exists());
    }

    public function canAccessService(int $serviceId): bool
    {
        $assignments = once(fn (): array => DB::table('service_assignments')
            ->where('user_id', $this->id)->pluck('service_id')->map(fn ($id): int => (int) $id)->all());
        $sensitiveServices = once(fn (): array => Service::where('is_sensitive', true)->pluck('id')->all());

        return in_array($serviceId, $assignments, true)
            || ($this->isAdministrator() && ! in_array($serviceId, $sensitiveServices, true));
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
