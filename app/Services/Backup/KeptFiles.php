<?php

namespace App\Services\Backup;

use App\Services\Circles\CircleLogos;
use App\Services\Export\LeadersTemplates;
use App\Services\Market\MarketItemPictures;
use App\Services\Students\StudentPhotos;
use App\Services\Teachers\TeacherPhotos;
use Illuminate\Contracts\Filesystem\Factory as Storage;
use Illuminate\Contracts\Filesystem\Filesystem;

/**
 * The files that are not in the database and therefore travel with every
 * backup: the admin's own slide template, the photos of the students and
 * of the teachers, the logos of the circles and the pictures of the
 * points markets' items.
 */
final class KeptFiles
{
    /** The folders of the private disk that are backed up, with everything directly in them. */
    private const FOLDERS = [
        LeadersTemplates::FOLDER,
        StudentPhotos::FOLDER,
        TeacherPhotos::FOLDER,
        CircleLogos::FOLDER,
        MarketItemPictures::FOLDER,
    ];

    /** A file of one of those folders: "slide-templates/leaders.pptx". */
    private const NAME = '#^[a-z0-9-]+/[A-Za-z0-9_.-]+$#';

    public function __construct(private readonly Storage $storage) {}

    /**
     * @return array<string, string> name ("folder/file") => contents
     */
    public function all(): array
    {
        $files = [];

        foreach (self::FOLDERS as $folder) {
            foreach ($this->disk()->files($folder) as $name) {
                if ($this->isKept($name)) {
                    $files[$name] = (string) $this->disk()->get($name);
                }
            }
        }

        ksort($files);

        return $files;
    }

    /**
     * Makes the kept folders hold exactly the given files.
     *
     * @param  array<string, string>  $files  name => contents; a name outside the kept folders is ignored
     */
    public function replaceWith(array $files): void
    {
        foreach (self::FOLDERS as $folder) {
            foreach ($this->disk()->files($folder) as $name) {
                if (! array_key_exists($name, $files)) {
                    $this->disk()->delete($name);
                }
            }
        }

        foreach ($files as $name => $contents) {
            if ($this->isKept((string) $name)) {
                $this->disk()->put((string) $name, $contents);
            }
        }
    }

    private function isKept(string $name): bool
    {
        return preg_match(self::NAME, $name) === 1
            && ! str_contains($name, '..')
            && in_array(dirname($name), self::FOLDERS, true);
    }

    private function disk(): Filesystem
    {
        return $this->storage->disk(LeadersTemplates::DISK);
    }
}
