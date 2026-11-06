<?php

namespace Tests\Feature\English;

use App\Actions\English\Cards\PrepareEnglishCards;
use App\Actions\English\Enrollments\UpdateEnglishEnrollmentDates;
use App\Actions\English\Levels\SaveEnglishLevel;
use App\Filament\English\Support\EnglishPresenter;
use App\Models\English\EnglishCourse;
use App\Models\English\EnglishHall;
use App\Models\English\EnglishLevel;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsDomain;
use Tests\Concerns\BuildsEnglish;
use Tests\Support\Pictures;
use Tests\TestCase;

/**
 * The attendance cards of the English section: in the design of the
 * course's level, with the student's photo, name, hall and code.
 */
class EnglishCardsTest extends TestCase
{
    use BuildsDomain;
    use BuildsEnglish;
    use RefreshDatabase;

    public function test_the_cards_of_a_course_hall_by_hall_without_those_who_left(): void
    {
        $level = EnglishLevel::query()->where('name', 'A2')->sole();
        $course = EnglishCourse::factory()->create(['english_level_id' => $level->getKey(), 'name' => 'A2 مسائي']);
        $blue = EnglishHall::factory()->create(['english_course_id' => $course->getKey(), 'name' => 'ب']);
        $red = EnglishHall::factory()->create(['english_course_id' => $course->getKey(), 'name' => 'أ']);
        $zaid = $this->enrollInEnglish(Student::factory()->english()->create(['full_name' => 'زيد']), $course, $blue);
        $amer = $this->enrollInEnglish(Student::factory()->english()->create(['full_name' => 'عامر']), $course, $red);
        $left = $this->enrollInEnglish(course: $course, hall: $red);
        app(UpdateEnglishEnrollmentDates::class)->handle($left, ['enrolled_on' => $left->enrolled_on->toDateString(), 'left_on' => now()->toDateString()]);

        $cards = app(PrepareEnglishCards::class)->handle(['english_course_id' => $course->getKey()]);

        $this->assertSame([$amer->card_code, $zaid->card_code], $cards->pluck('code')->all());
        $this->assertSame(['أ', 'A2', 'A2 مسائي'], [$cards[0]->hallName, $cards[0]->levelName, $cards[0]->courseName]);
        $this->assertStringStartsWith('<svg', $cards[0]->qrSvg);

        $this->assertSame([$zaid->card_code], app(PrepareEnglishCards::class)->handle(['english_course_id' => $course->getKey(), 'english_hall_id' => $blue->getKey()])->pluck('code')->all());
        $this->assertSame([$amer->card_code], app(PrepareEnglishCards::class)->handle(['english_course_id' => $course->getKey(), 'english_enrollment_ids' => [$amer->getKey()]])->pluck('code')->all());
    }

    public function test_the_printed_card_wears_the_design_of_the_level(): void
    {
        $this->actingAs(User::factory()->create());
        $level = app(SaveEnglishLevel::class)->handle(['name' => 'A1', 'card_background' => Pictures::png()], EnglishLevel::query()->where('name', 'A1')->sole());
        $enrollment = $this->enrollInEnglish(course: EnglishCourse::factory()->create(['english_level_id' => $level->getKey()]));

        $designUrl = EnglishPresenter::cardBackgroundUrl($level);

        $this->get(EnglishPresenter::cardsUrl($enrollment->english_course_id))
            ->assertOk()
            ->assertSee('class="rawda-badge rawda-badge--english"', escape: false)
            ->assertSee('data-code="'.$enrollment->card_code.'"', escape: false)
            ->assertSee(e($designUrl), escape: false)
            ->assertSee($enrollment->student->full_name)
            ->assertSee('<clipPath id="rawda-english-photo"', escape: false);

        $this->get($designUrl)->assertOk()->assertHeader('Cache-Control');
    }

    public function test_a_level_without_a_design_prints_the_plain_card(): void
    {
        $this->actingAs(User::factory()->create());
        $enrollment = $this->enrollInEnglish();

        $this->get(EnglishPresenter::cardsUrl($enrollment->english_course_id, enrollmentIds: [$enrollment->getKey()]))
            ->assertOk()
            ->assertSee('English Course')
            ->assertDontSee('rawda-badge__design', escape: false);
    }

    public function test_the_mosque_cards_are_unchanged(): void
    {
        $this->actingAs(User::factory()->create());
        $enrollment = $this->enroll();

        $this->get(route('filament.admin.attendance-cards.print', ['course_id' => $enrollment->course_id]))
            ->assertOk()
            ->assertSee('img/card/background.png', escape: false)
            ->assertDontSee('rawda-badge--english', escape: false);
    }
}
