<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Spatie\Permission\Models\Role;

/**
 * Save as app/Console/Commands/CreateLocalAdmin.php.
 * Run: php artisan app:create-local-admin
 * Bootstrap a new local administrator without exposing public registration.
 */
class CreateLocalAdmin extends Command
{
    protected $signature = 'app:create-local-admin';

    protected $description = 'Create a new administrator account for local development';

    public function handle(): int
    {
        if (! app()->environment('local')) {
            $this->error('This setup command runs only in the local environment.');

            return self::FAILURE;
        }

        if (! $this->input->isInteractive()) {
            $this->error('Run this command interactively to enter your password privately.');

            return self::FAILURE;
        }

        $roleModel = config('permission.models.role', Role::class);
        $userModel = new User();
        $roleInstance = new $roleModel();

        foreach ([$userModel->getTable(), $roleInstance->getTable(), config('permission.table_names.model_has_roles', 'model_has_roles')] as $table) {
            if (! Schema::hasTable($table)) {
                $this->error("Required table [{$table}] is missing. Run php artisan migrate, then retry.");

                return self::FAILURE;
            }
        }

        $name = trim((string) $this->ask('Your name', 'David Koh'));
        $email = strtolower(trim((string) $this->ask('Login email')));

        $validator = Validator::make(
            compact('name', 'email'),
            ['name' => ['required', 'string', 'max:255'], 'email' => ['required', 'email', 'max:255']]
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->error($message);
            }

            return self::FAILURE;
        }

        // Include soft-deleted records and compare case-insensitively.
        if (User::withTrashed()->whereRaw('LOWER(email) = ?', [$email])->exists()) {
            $this->error('An account with this email already exists. No account or password was changed.');
            $this->line('Use another email for this new administrator, or inspect the existing account before updating it.');

            return self::FAILURE;
        }

        // Disable visible-input fallback; never put a password in command arguments.
        $password = $this->secret('Choose a new password (at least 12 characters)', false);
        $confirmation = $this->secret('Enter the password again', false);
        $validator = Validator::make(
            ['password' => $password, 'password_confirmation' => $confirmation],
            ['password' => ['required', 'string', 'min:12', 'max:72', 'confirmed']]
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->error($message);
            }

            return self::FAILURE;
        }

        // Roll back the user creation if role assignment fails.
        DB::transaction(function () use ($name, $email, $password, $roleModel): void {
            $role = $roleModel::findOrCreate('administrator', 'web');

            $user = new User();
            $user->name = $name;
            $user->email = $email;
            $user->password = Hash::make($password);
            $user->is_active = true;
            $user->save();
            $user->assignRole($role);
        });

        unset($password, $confirmation);
        $this->info('Administrator account created successfully.');
        $this->line('Log in at http://localhost:8000/login using the email and password you entered.');
        $this->line('Then open http://localhost:8000/admin/users to manage accounts.');

        return self::SUCCESS;
    }
}
