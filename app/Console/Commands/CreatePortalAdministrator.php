<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CreatePortalAdministrator extends Command
{
    protected $signature = 'portal:admin {email} {--name=Administrator}';

    protected $description = 'Create an internal portal administrator with a generated one-time password';

    public function handle(): int
    {
        $email = (string) $this->argument('email');
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('Invalid email.');

            return self::FAILURE;
        }
        if (User::where('email', $email)->exists()) {
            $this->error('Account already exists.');

            return self::FAILURE;
        }
        $password = bin2hex(random_bytes(12));
        DB::transaction(function () use ($email, $password): void {
            $user = User::create(['name' => $this->option('name'), 'email' => $email, 'password' => $password]);
            $role = DB::table('roles')->where('name', 'administrator')->value('id');
            if (! $role) {
                throw new \RuntimeException('Run database seeding first.');
            }
            DB::table('role_user')->insert(['user_id' => $user->id, 'role_id' => $role]);
            foreach (DB::table('permissions')->pluck('id') as $permission) {
                DB::table('permission_user')->insert(['user_id' => $user->id, 'permission_id' => $permission]);
            }
        });
        $this->info('Administrator created. No counseling assignment is granted automatically.');
        $this->line('Temporary password: '.$password);

        return self::SUCCESS;
    }
}
