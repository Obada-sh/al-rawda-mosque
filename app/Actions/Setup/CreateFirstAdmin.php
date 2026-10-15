<?php

namespace App\Actions\Setup;

use App\Actions\Auth\SaveAdminUser;
use App\Exceptions\BusinessRuleViolation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Creates the admin account the first time the system is opened.
 * Once an admin exists this can never run again.
 */
final class CreateFirstAdmin
{
    public function __construct(private readonly SaveAdminUser $saveAdminUser) {}

    public function isPending(): bool
    {
        return User::query()->doesntExist();
    }

    /**
     * @param  array<string, mixed>  $input
     *
     * @throws ValidationException
     * @throws BusinessRuleViolation setup.already_done
     */
    public function handle(array $input): User
    {
        return DB::transaction(function () use ($input): User {
            if (User::query()->lockForUpdate()->exists()) {
                throw BusinessRuleViolation::because('setup.already_done', 'تم إنشاء حساب المدير مسبقاً.');
            }

            return $this->saveAdminUser->handle($input);
        });
    }
}
