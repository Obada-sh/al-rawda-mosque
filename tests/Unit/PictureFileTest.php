<?php

namespace Tests\Unit;

use App\Support\PictureFile;
use PHPUnit\Framework\TestCase;
use Tests\Support\Pictures;

class PictureFileTest extends TestCase
{
    private string $folder;

    protected function setUp(): void
    {
        parent::setUp();

        $this->folder = sys_get_temp_dir().'/rawda-pictures-'.bin2hex(random_bytes(4));
        mkdir($this->folder);
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->folder.'/*') ?: []);
        rmdir($this->folder);

        parent::tearDown();
    }

    private function file(string $name, string $contents): string
    {
        file_put_contents($path = $this->folder.'/'.$name, $contents);

        return $path;
    }

    public function test_a_picture_is_known_by_what_is_in_it_not_by_its_name(): void
    {
        $this->assertSame('png', PictureFile::kind($this->file('photo.png', Pictures::pngBytes())));
        $this->assertSame([1, 1], PictureFile::dimensions($this->file('one-pixel.png', Pictures::pngBytes())));

        // A picture under a name that says otherwise is still that picture...
        $this->assertSame('png', PictureFile::kind($this->file('notes.txt', Pictures::pngBytes())));

        // ...and nothing else becomes a picture by being called one.
        $this->assertNull(PictureFile::kind($this->file('text.png', 'this is not a picture')));
        $this->assertNull(PictureFile::kind($this->file('empty.jpg', '')));
        $this->assertNull(PictureFile::dimensions($this->file('text.jpg', 'this is not a picture')));
    }

    public function test_drawings_and_animations_are_not_kept(): void
    {
        // A drawing can carry scripts, an animation is not a photo.
        $this->assertNull(PictureFile::kind($this->file('drawing.svg', '<svg xmlns="http://www.w3.org/2000/svg" width="10" height="10"/>')));
        $this->assertNull(PictureFile::kind($this->file('moving.gif', (string) base64_decode('R0lGODlhAQABAIAAAAAAAP///ywAAAAAAQABAAACAUwAOw==', true))));
    }

    public function test_a_file_that_is_not_there_is_not_a_picture(): void
    {
        $this->assertNull(PictureFile::kind($this->folder.'/missing.png'));
        $this->assertNull(PictureFile::kind(''));
    }
}
