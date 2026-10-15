<?php

namespace App\Services\Export;

use App\Support\FileName;

/**
 * A file made for the admin to download.
 */
final readonly class ExportedFile
{
    public string $name;

    /**
     * @param  string  $name  the name the file is saved under; made safe for Windows
     * @param  string  $contents  the bytes of the file
     */
    public function __construct(
        string $name,
        public string $contents,
        public string $mimeType,
    ) {
        $this->name = FileName::safe($name);
    }
}
