<?php

namespace App\Services\Files;

use App\Support\PictureFile;
use Illuminate\Contracts\Filesystem\Factory as Storage;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

/**
 * A folder of the private disk that holds one kind of picture (the photos
 * of the students, the logos of the circles...): one file per picture,
 * named after the record it belongs to. Pictures are never in the public
 * folder: only the signed-in admin is handed them.
 *
 * Every picture gets a name of its own, so a browser never shows an old
 * picture in place of a new one. Only names of that form are ever read
 * from the disk.
 *
 * Each folder is listed in Services\Backup\KeptFiles, so backups carry it.
 */
abstract class KeptPictures
{
    public const DISK = 'local';

    /** The folder of the private disk; each kind of picture names its own. */
    public const FOLDER = '';

    public function __construct(private readonly Storage $storage) {}

    /**
     * Keeps a copy of the uploaded picture and says where. The file is
     * expected to have been checked already (a Request class, Rules\Picture).
     *
     * @return string the name to store with the record
     */
    public function keep(Model $owner, UploadedFile $file): string
    {
        $extension = PictureFile::kind((string) $file->getRealPath()) ?? 'jpg';
        $name = static::FOLDER.'/'.$owner->getKey().'-'.Str::lower(Str::random(10)).'.'.$extension;

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
     * The file of a picture on this computer, or null when it is not there
     * (the folder was emptied by hand, or an old backup was restored).
     */
    public function path(?string $name): ?string
    {
        return $this->isOurs($name) && $this->disk()->exists($name)
            ? $this->disk()->path($name)
            : null;
    }

    private function isOurs(?string $name): bool
    {
        $pattern = '#^'.preg_quote(static::FOLDER, '#').'/[0-9]+-[a-z0-9]{10}\.(jpg|png|webp)$#';

        return filled($name) && preg_match($pattern, (string) $name) === 1;
    }

    private function disk(): Filesystem
    {
        return $this->storage->disk(self::DISK);
    }
}
