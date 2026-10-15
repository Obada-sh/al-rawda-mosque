<?php

namespace App\Services\Export\Pptx;

use RuntimeException;
use ZipArchive;

/**
 * A .pptx file held in memory: a set of named parts (XML files, pictures)
 * inside a ZIP archive.
 */
final class PptxPackage
{
    public const CONTENT_TYPES = '[Content_Types].xml';

    public const PRESENTATION = 'ppt/presentation.xml';

    public const PRESENTATION_RELATIONSHIPS = 'ppt/_rels/presentation.xml.rels';

    /** Templates are small; nothing bigger than this is unpacked. */
    private const MAX_UNPACKED_BYTES = 150 * 1024 * 1024;

    private const MAX_PARTS = 5000;

    /** @var array<string, string> part name => contents */
    private array $parts = [];

    private function __construct() {}

    /**
     * @throws InvalidSlideTemplate when the file is not a presentation
     */
    public static function open(string $path): self
    {
        $zip = new ZipArchive;

        if (! is_file($path) || $zip->open($path, ZipArchive::RDONLY) !== true) {
            throw InvalidSlideTemplate::notAPresentation();
        }

        $package = new self;

        try {
            if ($zip->numFiles > self::MAX_PARTS) {
                throw InvalidSlideTemplate::tooLarge();
            }

            $unpacked = 0;

            for ($index = 0; $index < $zip->numFiles; $index++) {
                $name = $zip->getNameIndex($index);

                // Folders are listed too; only files are parts.
                if ($name === false || str_ends_with($name, '/')) {
                    continue;
                }

                $unpacked += (int) ($zip->statIndex($index)['size'] ?? 0);

                if ($unpacked > self::MAX_UNPACKED_BYTES) {
                    throw InvalidSlideTemplate::tooLarge();
                }

                $contents = $zip->getFromIndex($index);

                if ($contents === false) {
                    throw InvalidSlideTemplate::notAPresentation();
                }

                $package->parts[$name] = $contents;
            }
        } finally {
            $zip->close();
        }

        foreach ([self::CONTENT_TYPES, self::PRESENTATION, self::PRESENTATION_RELATIONSHIPS] as $required) {
            if (! $package->has($required)) {
                throw InvalidSlideTemplate::notAPresentation();
            }
        }

        return $package;
    }

    public function has(string $name): bool
    {
        return isset($this->parts[$name]);
    }

    public function get(string $name): string
    {
        return $this->parts[$name] ?? throw new RuntimeException("The presentation has no part [{$name}].");
    }

    public function put(string $name, string $contents): void
    {
        $this->parts[$name] = $contents;
    }

    public function remove(string $name): void
    {
        unset($this->parts[$name]);
    }

    /**
     * @return list<string>
     */
    public function names(): array
    {
        return array_keys($this->parts);
    }

    /**
     * The package as the bytes of a .pptx file.
     */
    public function toBinary(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'pptx');

        if ($path === false) {
            throw new RuntimeException('Could not create a temporary file for the presentation.');
        }

        try {
            $zip = new ZipArchive;

            if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                throw new RuntimeException('Could not write the presentation.');
            }

            foreach ($this->namesInWritingOrder() as $name) {
                $zip->addFromString($name, $this->parts[$name]);
            }

            if (! $zip->close()) {
                throw new RuntimeException('Could not write the presentation.');
            }

            $binary = file_get_contents($path);

            if ($binary === false) {
                throw new RuntimeException('Could not read the presentation back.');
            }

            return $binary;
        } finally {
            @unlink($path);
        }
    }

    /**
     * Office programs write the list of content types first; so do we.
     *
     * @return list<string>
     */
    private function namesInWritingOrder(): array
    {
        $names = array_values(array_diff($this->names(), [self::CONTENT_TYPES]));

        return [self::CONTENT_TYPES, ...$names];
    }
}
