<?php

namespace Tests\Feature\English;

use App\Actions\English\Levels\DeleteEnglishLevel;
use App\Actions\English\Levels\SaveEnglishLevel;
use App\Exceptions\BusinessRuleViolation;
use App\Models\English\EnglishCourse;
use App\Models\English\EnglishLevel;
use App\Models\Student;
use App\Services\Backup\KeptFiles;
use App\Services\English\EnglishCardBackgrounds;
use App\Services\Files\KeptPictures;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\Support\Pictures;
use Tests\TestCase;

/**
 * Each level has the design of its students' cards: a picture the admin
 * uploads, kept on the private disk and carried by every backup.
 */
class EnglishLevelsTest extends TestCase
{
    use RefreshDatabase;

    private function disk(): Filesystem
    {
        return Storage::disk(KeptPictures::DISK);
    }

    public function test_a_card_design_is_kept_replaced_and_removed(): void
    {
        $level = EnglishLevel::query()->where('name', 'A1')->sole();

        app(SaveEnglishLevel::class)->handle(['name' => 'A1', 'card_background' => Pictures::png('a1.png')], $level);
        $first = $level->fresh()->card_background_path;

        $this->assertMatchesRegularExpression('#^english-cards/'.$level->getKey().'-[a-z0-9]{10}\.png$#', (string) $first);
        $this->assertSame(Pictures::pngBytes(), $this->disk()->get($first));

        app(SaveEnglishLevel::class)->handle(['name' => 'A1', 'card_background' => Pictures::png('new.png')], $level->fresh());
        $second = $level->fresh()->card_background_path;

        $this->assertNotSame($first, $second);
        $this->assertSame([$second], $this->disk()->files(EnglishCardBackgrounds::FOLDER));

        app(SaveEnglishLevel::class)->handle(['name' => 'A1', 'remove_card_background' => true], $level->fresh());

        $this->assertNull($level->fresh()->card_background_path);
        $this->assertSame([], $this->disk()->files(EnglishCardBackgrounds::FOLDER));
    }

    public function test_only_a_real_picture_is_accepted_as_a_design(): void
    {
        $level = EnglishLevel::query()->where('name', 'B1')->sole();

        $this->assertThrows(fn () => app(SaveEnglishLevel::class)->handle(['name' => 'B1', 'card_background' => Pictures::pretender()], $level), ValidationException::class);

        $this->assertNull($level->fresh()->card_background_path);
    }

    public function test_the_designs_travel_with_the_backups(): void
    {
        $level = app(SaveEnglishLevel::class)->handle(['name' => 'Kids 1', 'sort_order' => '7', 'card_background' => Pictures::png()]);

        $this->assertArrayHasKey($level->card_background_path, app(KeptFiles::class)->all());
    }

    public function test_names_are_unique_and_a_level_in_use_is_kept(): void
    {
        $this->assertThrows(fn () => app(SaveEnglishLevel::class)->handle(['name' => 'A1']), ValidationException::class);

        $ofCourse = EnglishLevel::query()->where('name', 'A2')->sole();
        EnglishCourse::factory()->create(['english_level_id' => $ofCourse->getKey()]);
        $ofStudent = EnglishLevel::query()->where('name', 'B2')->sole();
        Student::factory()->english()->create(['english_level_id' => $ofStudent->getKey()]);
        $unused = app(SaveEnglishLevel::class)->handle(['name' => 'Kids', 'card_background' => Pictures::png()]);

        $this->assertKeptInUse($ofCourse);
        $this->assertKeptInUse($ofStudent);

        app(DeleteEnglishLevel::class)->handle($unused);
        $this->assertNull($unused->fresh());
        $this->assertSame([], $this->disk()->files(EnglishCardBackgrounds::FOLDER));
    }

    private function assertKeptInUse(EnglishLevel $level): void
    {
        try {
            app(DeleteEnglishLevel::class)->handle($level);
            $this->fail('A level in use was deleted.');
        } catch (BusinessRuleViolation $violation) {
            $this->assertSame('english_level.in_use', $violation->rule);
        }

        $this->assertNotNull($level->fresh());
    }
}
