<?php

namespace App\Http\Requests\Auth;

use App\Http\Requests\BaseRequest;
use Closure;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * The signed-in admin changing their own name, username or password.
 *
 * The password is optional: left empty, it stays as it is. Changing it
 * requires the current password and typing the new one twice.
 *
 * Context: ignore_id => the user being updated,
 *          password_hash => that user's current password hash.
 */
class AdminProfileRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:255'],
            'username' => [
                'required', 'string', 'min:3', 'max:100', 'regex:/^[A-Za-z0-9._-]+$/',
                Rule::unique('users', 'username')->ignore($this->context('ignore_id')),
            ],
            'current_password' => ['nullable', 'required_with:password', 'string', $this->matchesCurrentPassword()],
            'password' => ['nullable', 'string', Password::min(8), 'max:255', 'confirmed'],
        ];
    }

    public function messages(): array
    {
        return [
            'username.regex' => 'اسم المستخدم يقبل الأحرف اللاتينية والأرقام والنقطة والشرطة فقط.',
            'current_password.required_with' => 'أدخل كلمة المرور الحالية لتغيير كلمة المرور.',
            'password.confirmed' => 'كلمتا المرور غير متطابقتين.',
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'الاسم',
            'username' => 'اسم المستخدم',
            'current_password' => 'كلمة المرور الحالية',
            'password' => 'كلمة المرور الجديدة',
        ];
    }

    private function matchesCurrentPassword(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            $hash = (string) $this->context('password_hash', '');

            if ($hash === '' || ! Hash::check((string) $value, $hash)) {
                $fail('كلمة المرور الحالية غير صحيحة.');
            }
        };
    }
}
