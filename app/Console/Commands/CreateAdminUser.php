<?php

namespace App\Console\Commands;

use App\Actions\Auth\SaveAdminUser;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;

/**
 * Creates the admin account, or resets its password.
 *
 * The password is typed here by the person who will use it; it is never
 * stored in a seeder or a config file.
 */
class CreateAdminUser extends Command
{
    protected $signature = 'rawda:admin';

    protected $description = 'Create the admin account, or reset the password of an existing one';

    public function handle(SaveAdminUser $saveAdminUser): int
    {
        $username = (string) $this->ask('Username', 'admin');
        $existing = User::query()->where('username', $username)->first();

        if ($existing !== null && ! $this->confirm("User [{$username}] exists. Reset its password?", true)) {
            return self::SUCCESS;
        }

        $name = (string) $this->ask('Display name', $existing?->name ?? 'المدير');
        $password = (string) $this->secret('Password (at least 8 characters)');

        if ($password !== (string) $this->secret('Repeat the password')) {
            $this->error('The two passwords do not match.');

            return self::FAILURE;
        }

        try {
            $saveAdminUser->handle(
                ['name' => $name, 'username' => $username, 'password' => $password],
                $existing,
            );
        } catch (ValidationException $exception) {
            foreach ($exception->validator->errors()->all() as $message) {
                $this->error($message);
            }

            return self::FAILURE;
        }

        $this->info("Admin [{$username}] is ready.");

        return self::SUCCESS;
    }
}
