<?php

namespace App\Http\Requests\Auth;

use App\Http\Requests\BaseRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * Creating the admin account, or changing its name / password.
 *
 * Context: ignore_id => the user being updated (null when creating).
 */
class AdminUserRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:255'],
            'username' => [
                'required', 'string', 'min:3', 'max:100', 'regex:/^[A-Za-z0-9._-]+$/',
                Rule::unique('users', 'username')->ignore($this->context('ignore_id')),
            ],
            'password' => ['required', 'string', Password::min(8), 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'username.regex' => 'اسم المستخدم يقبل الأحرف اللاتينية والأرقام والنقطة والشرطة فقط.',
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'الاسم',
            'username' => 'اسم المستخدم',
            'password' => 'كلمة المرور',
        ];
    }
}
