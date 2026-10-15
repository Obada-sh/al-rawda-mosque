<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Base of every validation class in the system.
 *
 * All validation rules live in Request classes, one per use case. They are
 * ordinary Laravel form requests, so a controller can type-hint them, but
 * the admin panel has no controllers: there the Actions call
 * validateData(), which runs the very same rules(), messages() and
 * attributes(). Either way, no data reaches the business logic unvalidated.
 *
 * Rules that depend on what is being edited (for example "unique, except
 * this record") read it from the context given to validateData().
 */
abstract class BaseRequest extends FormRequest
{
    /**
     * Arabic-Indic and Persian digits, mapped to the digits MySQL stores.
     */
    private const DIGITS = [
        '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
        '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
        '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
        '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
    ];

    /** @var array<string, mixed> */
    protected array $context = [];

    /**
     * Fields whose Arabic digits are converted to Latin digits before validation.
     *
     * @var list<string>
     */
    protected array $digitFields = [];

    /**
     * Fields that are never trimmed: a password is used exactly as typed.
     *
     * @var list<string>
     */
    protected array $verbatimFields = ['password', 'password_confirmation', 'current_password'];

    /**
     * @return array<string, mixed>
     */
    abstract public function rules(): array;

    /**
     * There is a single admin role, and the panel only lets a signed-in
     * admin in, so every request is authorised.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Validates raw input outside the HTTP layer.
     *
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $context
     * @return array<string, mixed> only the validated fields
     *
     * @throws ValidationException
     */
    public static function validateData(array $data, array $context = []): array
    {
        $request = static::withContext($context);

        return Validator::make(
            $request->normalize($data),
            $request->rules(),
            $request->messages(),
            $request->attributes(),
        )->validate();
    }

    /**
     * The rules of one field, for forms that want to check a field early.
     *
     * @param  array<string, mixed>  $context
     * @return array<int, mixed>
     */
    public static function fieldRules(string $field, array $context = []): array
    {
        $rules = static::withContext($context)->rules()[$field] ?? [];

        return is_string($rules) ? explode('|', $rules) : $rules;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public static function withContext(array $context = []): static
    {
        $request = new static;
        $request->context = $context;

        return $request;
    }

    protected function context(string $key, mixed $default = null): mixed
    {
        return $this->context[$key] ?? $default;
    }

    /**
     * Runs the same clean-up when the class is used as a real form request.
     */
    protected function prepareForValidation(): void
    {
        $this->replace($this->normalize($this->all()));
    }

    /**
     * Trims strings (except passwords), turns empty strings into null and
     * converts Arabic digits in the fields listed in $digitFields.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function normalize(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = $this->normalize($value);

                continue;
            }

            if (! is_string($value)) {
                continue;
            }

            if (in_array($key, $this->verbatimFields, true)) {
                $data[$key] = $value === '' ? null : $value;

                continue;
            }

            $value = trim($value);

            if (in_array($key, $this->digitFields, true)) {
                $value = strtr($value, self::DIGITS);
            }

            $data[$key] = $value === '' ? null : $value;
        }

        return $data;
    }
}
