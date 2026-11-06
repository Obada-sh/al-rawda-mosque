<?php

namespace App\Actions\English\Papers;

use App\Http\Requests\English\EnglishPaperRequest;
use App\Models\English\EnglishCourse;
use App\Models\English\EnglishPaper;
use App\Services\English\EnglishPapers;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Adds a paper to an English course (a sheet, a picture), or changes one:
 * its title and notes, and, when a new file is chosen, its file.
 */
final class SaveEnglishPaper
{
    public function __construct(private readonly EnglishPapers $papers) {}

    /**
     * @param  array<string, mixed>  $input
     *
     * @throws ValidationException
     */
    public function handle(EnglishCourse $course, array $input, ?EnglishPaper $paper = null): EnglishPaper
    {
        $data = EnglishPaperRequest::validateData($input, ['editing' => $paper !== null]);

        /** @var UploadedFile|null $file */
        $file = $data['file'] ?? null;

        $paper ??= new EnglishPaper(['english_course_id' => $course->getKey()]);
        $paper->fill(['title' => $data['title'], 'notes' => $data['notes'] ?? null]);

        if ($file === null) {
            $paper->save();

            return $paper;
        }

        $previous = $paper->exists ? $paper->file_path : null;

        // The new file is kept first and the old one removed last: the paper
        // never points at a missing file.
        $paper->forceFill([
            'file_path' => $this->papers->keep($course, $file),
            'original_name' => Str::limit($file->getClientOriginalName(), 250, ''),
            'mime_type' => Str::limit((string) ($file->getMimeType() ?: 'application/octet-stream'), 150, ''),
            'size' => (int) $file->getSize(),
        ])->save();

        if ($previous !== null && $previous !== $paper->file_path) {
            $this->papers->remove($previous);
        }

        return $paper;
    }
}
