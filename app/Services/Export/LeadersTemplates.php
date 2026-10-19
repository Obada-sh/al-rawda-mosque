<?php

namespace App\Services\Export;

use App\Services\Export\Pptx\InvalidSlideTemplate;
use App\Services\Export\Pptx\SlideDeckTemplate;
use Illuminate\Contracts\Filesystem\Factory as Storage;
use Illuminate\Contracts\Filesystem\Filesystem;
use InvalidArgumentException;

/**
 * The slide the students of each circle are written on.
 *
 * The system comes with a default design: a board on which one cell (a
 * name and a number) is repeated for every student of the circle, as
 * large as the slide allows. The admin can replace it by a design of
 * their own: a PowerPoint file with one slide that carries placeholders
 * (see LeadersSlides), either such a cell or numbered places ({{name1}},
 * {{name2}}...). Their file is kept outside the public folder.
 */
final class LeadersTemplates
{
    /**
     * The placeholders of one student. Written once, without a number,
     * they make the cell of a board; with a number ({{name1}}) they are a
     * place of their own.
     */
    public const STUDENT_PLACEHOLDERS = ['place', 'name', 'score', 'value', 'unit'];

    /** The disk and the folder of the admin's own design; the backup takes this folder with it. */
    public const DISK = 'local';

    public const FOLDER = 'slide-templates';

    private const CUSTOM = self::FOLDER.'/leaders.pptx';

    public function __construct(private readonly Storage $storage) {}

    /**
     * Opens a file as a template of the leaders' slides.
     *
     * @throws InvalidSlideTemplate when the file cannot serve as one
     */
    public static function open(string $path): SlideDeckTemplate
    {
        return SlideDeckTemplate::open($path, self::STUDENT_PLACEHOLDERS);
    }

    /**
     * The template in use: the admin's own design when there is one,
     * otherwise the default design.
     *
     * @throws InvalidSlideTemplate when the admin's file can no longer be read
     */
    public function current(): SlideDeckTemplate
    {
        return self::open($this->hasCustom() ? $this->customPath() : $this->defaultPath());
    }

    public function hasCustom(): bool
    {
        return $this->disk()->exists(self::CUSTOM);
    }

    /**
     * The admin's own design; null when the default design is in use, or
     * their file can no longer be used.
     */
    public function custom(): ?SlideDeckTemplate
    {
        if (! $this->hasCustom()) {
            return null;
        }

        try {
            return self::open($this->customPath());
        } catch (InvalidSlideTemplate) {
            return null;
        }
    }

    /**
     * The bytes of the admin's own design; null when the default design is in use.
     */
    public function customContents(): ?string
    {
        return $this->hasCustom() ? $this->disk()->get(self::CUSTOM) : null;
    }

    /**
     * The file of the default design.
     */
    public function defaultPath(): string
    {
        return resource_path('pptx/leaders.pptx');
    }

    /**
     * Makes a copy of the given file the design in use. The file is
     * expected to have been checked already.
     */
    public function replaceWith(string $path): void
    {
        $contents = @file_get_contents($path);

        if ($contents === false) {
            throw new InvalidArgumentException("The template [{$path}] cannot be read.");
        }

        $this->disk()->put(self::CUSTOM, $contents);
    }

    /**
     * Goes back to the default design.
     */
    public function restoreDefault(): void
    {
        $this->disk()->delete(self::CUSTOM);
    }

    private function customPath(): string
    {
        return $this->disk()->path(self::CUSTOM);
    }

    private function disk(): Filesystem
    {
        return $this->storage->disk(self::DISK);
    }
}
