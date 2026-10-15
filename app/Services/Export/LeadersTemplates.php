<?php

namespace App\Services\Export;

use App\Services\Export\Pptx\InvalidSlideTemplate;
use App\Services\Export\Pptx\SlideDeckTemplate;
use Illuminate\Contracts\Filesystem\Factory as Storage;
use Illuminate\Contracts\Filesystem\Filesystem;
use InvalidArgumentException;

/**
 * The slide the leaders of each circle are written on.
 *
 * The system comes with a default design for every number of leaders.
 * The admin can replace it by a design of their own: a PowerPoint file
 * with one slide that carries placeholders such as {{circle}} and
 * {{name1}}. Their file is kept outside the public folder.
 */
final class LeadersTemplates
{
    /** The default design exists for 1 up to this many leaders on a slide. */
    public const MAX_LEADERS = 10;

    /** The disk and the folder of the admin's own design; the backup takes this folder with it. */
    public const DISK = 'local';

    public const FOLDER = 'slide-templates';

    private const CUSTOM = self::FOLDER.'/leaders.pptx';

    public function __construct(private readonly Storage $storage) {}

    /**
     * The template in use: the admin's own design when there is one,
     * otherwise the default design for that many leaders.
     *
     * @throws InvalidSlideTemplate when the admin's file can no longer be read
     */
    public function for(int $leaders): SlideDeckTemplate
    {
        return SlideDeckTemplate::open($this->hasCustom() ? $this->customPath() : $this->defaultPath($leaders));
    }

    public function hasCustom(): bool
    {
        return $this->disk()->exists(self::CUSTOM);
    }

    /**
     * How many leaders the admin's own design has room for; null when the
     * default design is in use (or their file can no longer be read).
     */
    public function customSlots(): ?int
    {
        if (! $this->hasCustom()) {
            return null;
        }

        try {
            return SlideDeckTemplate::open($this->customPath())->slots();
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
     * The file of the default design for a number of leaders.
     */
    public function defaultPath(int $leaders): string
    {
        if ($leaders < 1 || $leaders > self::MAX_LEADERS) {
            throw new InvalidArgumentException('There is a default design for 1 to '.self::MAX_LEADERS.' leaders only.');
        }

        return resource_path("pptx/leaders-{$leaders}.pptx");
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
