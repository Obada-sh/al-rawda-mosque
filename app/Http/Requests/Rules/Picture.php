<?php

namespace App\Http\Requests\Rules;

use App\Support\PictureFile;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;

/**
 * The uploaded file really is a JPG, PNG or WebP picture: the file itself
 * is looked at, not its name or what the browser said about it.
 */
final class Picture implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $value instanceof UploadedFile || PictureFile::kind((string) $value->getRealPath()) === null) {
            $fail('اختر صورة بصيغة JPG أو PNG أو WebP.');
        }
    }
}
