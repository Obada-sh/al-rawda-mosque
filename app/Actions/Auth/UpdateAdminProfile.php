<?php

namespace App\Actions\Auth;

use App\Http\Requests\Auth\AdminProfileRequest;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * The admin updates their own account. The password only changes when a
 * new one is given (and the current one was confirmed by the request).
 */
final class UpdateAdminProfile
{
    /**
     * @param  array<string, mixed>  $input
     *
     * @throws ValidationException
     */
    public function handle(User $user, array $input): User
    {
        $data = AdminProfileRequest::validateData($input, [
            'ignore_id' => $user->getKey(),
            'password_hash' => $user->password,
        ]);

        $user->fill([
            'name' => $data['name'],
            'username' => $data['username'],
        ]);

        if (isset($data['password'])) {
            $user->fill(['password' => $data['password']]);
        }

        $user->save();

        return $user;
    }
}
