<?php

namespace App\Http\Requests\English;

use App\Http\Requests\BaseRequest;
use App\Services\English\EnglishPapers;

/**
 * A paper of an English course: a sheet or a picture the admin adds, with
 * a title. A new paper needs its file; a paper being edited may keep it.
 *
 * Context: editing => true when an existing paper is edited.
 */
class EnglishPaperRequest extends BaseRequest
{
    public const MAX_KILOBYTES = 10240;

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'min:2', 'max:255'],
            'file' => [
                $this->context('editing') ? 'nullable' : 'required',
                'file',
                'extensions:'.implode(',', EnglishPapers::EXTENSIONS),
                'max:'.self::MAX_KILOBYTES,
            ],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    public function messages(): array
    {
        return [
            'file.extensions' => 'اختر ملف PDF أو صورة (JPG أو PNG أو WebP) أو ملف Word أو Excel أو PowerPoint.',
            'file.max' => 'حجم الملف لا يتجاوز 10 ميغابايت.',
        ];
    }

    public function attributes(): array
    {
        return [
            'title' => 'العنوان',
            'file' => 'الملف',
            'notes' => 'ملاحظات',
        ];
    }
}
