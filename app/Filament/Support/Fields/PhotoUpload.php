<?php

namespace App\Filament\Support\Fields;

use Filament\Forms\Components\FileUpload;

/**
 * The photo of a student or of a teacher, as their attendance card shows
 * it: in a circle. Once a picture is chosen, an editor opens with a
 * circle of the card's proportions over it; the admin moves and zooms the
 * picture until the face sits in the circle, and what is inside it is
 * what the card shows. The editor opens again from the pencil of the
 * picture.
 *
 * The picture is kept as the browser cut it (a square, at most 500 px),
 * then checked and stored by the action (SaveStudent, SaveTeacher).
 */
final class PhotoUpload
{
    public static function make(string $name = 'photo'): FileUpload
    {
        return FileUpload::make($name)
            ->avatar()
            ->imageEditor()
            ->circleCropper()
            // The admin chooses what is in the circle, rather than the middle of the picture being kept.
            ->automaticallyCropImagesToAspectRatio(false)
            ->automaticallyOpenImageEditorForAspectRatio()
            ->storeFiles(false)
            ->helperText('JPG أو PNG أو WebP. بعد اختيار الصورة تظهر دائرة بقدر دائرة البطاقة: حرّك الصورة وكبّرها حتى يكون الوجه داخلها ثم احفظ، فما في الدائرة هو ما يظهر على البطاقة. ولتعديلها لاحقاً اضغط رمز القلم فوقها.');
    }
}
