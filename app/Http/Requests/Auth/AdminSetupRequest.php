<?php

namespace App\Http\Requests\Auth;

/**
 * The first-run form that creates the admin account: the same rules as
 * any admin account, plus typing the password twice.
 */
class AdminSetupRequest extends AdminUserRequest
{
    public function rules(): array
    {
        $rules = parent::rules();
        $rules['password'][] = 'confirmed';

        return $rules;
    }

    public function messages(): array
    {
        return [
            ...parent::messages(),
            'password.confirmed' => 'كلمتا المرور غير متطابقتين.',
        ];
    }
}
