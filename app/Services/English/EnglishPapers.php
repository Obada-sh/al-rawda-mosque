<?php

namespace App\Services\English;

use App\Models\English\EnglishCourse;
use Illuminate\Contracts\Filesystem\Factory as Storage;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

/**
 * Where the papers of the English courses are kept (sheets, pictures):
 * one file each, on the private disk, named after the course and a random
 * part, with the ending of what the file really is. Only names of that
 * form are ever read from the disk, and the admin is handed them by
 * EnglishPaperController.
 *
 * The folder is listed in Services\Backup\KeptFiles, so backups carry it.
 */
final class EnglishPapers
{
    public const DISK = 'local';

    public const FOLDER = 'english-papers';

    /** The endings a paper may have. */
    public const EXTENSIONS = ['pdf', 'jpg', 'jpeg', 'png', 'webp', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx'];

    public function __construct(private readonly Storage $storage) {}

    /**
     * Keeps a copy of the uploaded file and says where. The file is
     * expected to have been checked already (EnglishPaperRequest).
     *
     * @return string the name to store with the paper
     */
    public function keep(EnglishCourse $course, UploadedFile $file): string
    {
        // The ending was checked by the Request class against the list.
        $extension = Str::lower($file->getClientOriginalExtension());
        $extension = in_array($extension, self::EXTENSIONS, true) ? $extension : 'bin';
        $name = self::FOLDER.'/'.$course->getKey().'-'.Str::lower(Str::random(10)).'.'.$extension;

        $this->disk()->put($name, (string) file_get_contents($file->getRealPath()));

        return $name;
    }

    public function remove(?string $name): void
    {
        if ($this->isOurs($name)) {
            $this->disk()->delete($name);
        }
    }

    /**
     * The file of a paper on this computer, or null when it is not there
     * (the folder was emptied by hand, or an old backup was restored).
     */
    public function path(?string $name): ?string
    {
        return $this->isOurs($name) && $this->disk()->exists($name)
            ? $this->disk()->path($name)
            : null;
    }

    /**
     * Whether a browser shows such a file by itself (a PDF, a picture)
     * rather than saving it.
     */
    public static function opensInBrowser(string $name): bool
    {
        return in_array(Str::lower(pathinfo($name, PATHINFO_EXTENSION)), ['pdf', 'jpg', 'jpeg', 'png', 'webp'], true);
    }

    private function isOurs(?string $name): bool
    {
        $pattern = '#^'.preg_quote(self::FOLDER, '#').'/[0-9]+-[a-z0-9]{10}\.(?:'.implode('|', [...self::EXTENSIONS, 'bin']).')$#';

        return filled($name) && preg_match($pattern, (string) $name) === 1;
    }

    private function disk(): Filesystem
    {
        return $this->storage->disk(self::DISK);
    }
}
