<?php

namespace App\Actions\Auth;

use App\Http\Requests\Auth\AdminUserRequest;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Creates the admin account, or updates one. The password is hashed by
 * the User model.
 */
final class SaveAdminUser
{
    /**
     * @param  array<string, mixed>  $input
     *
     * @throws ValidationException
     */
    public function handle(array $input, ?User $user = null): User
    {
        $data = AdminUserRequest::validateData($input, ['ignore_id' => $user?->getKey()]);

        $user ??= new User;
        $user->fill($data)->save();

        return $user;
    }
}
