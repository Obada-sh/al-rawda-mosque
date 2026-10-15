<?php

namespace Tests\Feature\Panel;

use App\Actions\Cards\PrepareAttendanceCards;
use App\Actions\Circles\SaveCircle;
use App\Actions\Enrollments\UpdateEnrollmentDates;
use App\Actions\Students\SaveStudent;
use App\Contracts\QrCodeRenderer;
use App\Enums\CardPaper;
use App\Filament\Support\Presenters\CirclePresenter;
use App\Filament\Support\Presenters\StudentPresenter;
use App\Models\Circle;
use App\Models\Course;
use App\Models\Student;
use App\Models\User;
use App\Services\Cards\CardDesign;
use App\Support\CardFont;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\Concerns\BuildsDomain;
use Tests\Support\Pictures;
use Tests\TestCase;

/**
 * The students' attendance cards, drawn after the designer's Photoshop
 * file: photo, name, circle, QR code and the circle's logo on the fixed
 * design, 54 × 86 mm.
 */
class AttendanceCardsTest extends TestCase
{
    use BuildsDomain;
    use RefreshDatabase;

    private function url(array $query): string
    {
        return route('filament.admin.attendance-cards.print', $query);
    }

    public function test_a_qr_code_is_drawn_as_an_inline_svg(): void
    {
        $svg = app(QrCodeRenderer::class)->svg('1234567890');

        $this->assertStringStartsWith('<svg', $svg);
        $this->assertStringContainsString('viewBox', $svg);
        $this->assertStringNotContainsString('<?xml', $svg);
    }

    public function test_the_qr_code_of_the_card_is_in_the_green_of_the_design_without_a_border(): void
    {
        $qr = app(QrCodeRenderer::class);

        $card = $qr->svg('1234567890', CardDesign::INK, quietZone: false);
        $framed = $qr->svg('1234567890', CardDesign::INK);

        $this->assertStringContainsString(CardDesign::INK, $card);
        // Without its border the code takes less room around the same modules.
        $this->assertNotSame($card, $framed);

        $this->expectException(InvalidArgumentException::class);
        $qr->svg('1234567890', 'green');
    }

    public function test_the_cards_of_a_course_are_printed_for_its_current_students(): void
    {
        $this->actingAs(User::factory()->create());

        $course = Course::factory()->create(['name' => 'دورة الصيف']);
        $circle = Circle::factory()->create(['course_id' => $course->getKey(), 'name' => 'حلقة النور']);
        $present = $this->enroll(Student::factory()->create(['full_name' => 'أحمد الحاضر']), $course, $circle);
        $left = $this->enroll(Student::factory()->create(['full_name' => 'خالد المغادر']), $course, $circle);
        $otherCourse = $this->enroll(Student::factory()->create(['full_name' => 'طالب دورة أخرى']));

        app(UpdateEnrollmentDates::class)->handle($left, [
            'enrolled_on' => $left->enrolled_on->toDateString(),
            'left_on' => now()->toDateString(),
        ]);

        $this->get($this->url(['course_id' => $course->getKey()]))
            ->assertOk()
            ->assertSee('بطاقات دورة الصيف')
            ->assertSee('أحمد الحاضر')
            ->assertSee('حلقة النور')
            ->assertSee('data-code="'.$present->card_code.'"', escape: false)
            // The QR code is a picture inside the card's drawing.
            ->assertSee('class="rawda-badge__qr" href="data:image/svg+xml;base64,', escape: false)
            ->assertDontSee('خالد المغادر')
            ->assertDontSee($otherCourse->card_code);
    }

    public function test_cards_can_be_limited_to_a_circle_or_to_chosen_students(): void
    {
        $course = Course::factory()->create();
        $firstCircle = Circle::factory()->create(['course_id' => $course->getKey()]);
        $secondCircle = Circle::factory()->create(['course_id' => $course->getKey()]);
        $inFirst = $this->enroll(course: $course, circle: $firstCircle);
        $inSecond = $this->enroll(course: $course, circle: $secondCircle);
        $alsoInSecond = $this->enroll(course: $course, circle: $secondCircle);

        $prepare = app(PrepareAttendanceCards::class);

        $this->assertCount(3, $prepare->handle(['course_id' => $course->getKey()]));

        $byCircle = $prepare->handle(['course_id' => $course->getKey(), 'circle_id' => $secondCircle->getKey()]);
        $this->assertEqualsCanonicalizing(
            [$inSecond->card_code, $alsoInSecond->card_code],
            $byCircle->pluck('code')->all(),
        );

        $chosen = $prepare->handle(['course_id' => $course->getKey(), 'enrollment_ids' => [$inFirst->getKey()]]);
        $this->assertSame([$inFirst->card_code], $chosen->pluck('code')->all());
    }

    public function test_the_print_page_needs_a_signed_in_admin_and_a_valid_course(): void
    {
        User::factory()->create();
        $course = Course::factory()->create();

        $this->get($this->url(['course_id' => $course->getKey()]))->assertRedirect('/admin/login');

        $this->actingAs(User::query()->sole());

        $this->get($this->url(['course_id' => 999999]))->assertSessionHasErrors(['course_id']);
        $this->get($this->url([]))->assertSessionHasErrors(['course_id']);
        $this->get($this->url(['course_id' => $course->getKey()]))->assertOk()->assertSee('لا يوجد طلاب');
    }

    public function test_cards_are_laid_out_nine_to_an_a4_sheet_or_one_to_a_page(): void
    {
        $this->actingAs(User::factory()->create());

        $course = Course::factory()->create();
        $circle = Circle::factory()->create(['course_id' => $course->getKey()]);

        foreach (range(1, 10) as $ignored) {
            $this->enroll(course: $course, circle: $circle);
        }

        // 3 × 3 cards of 54 × 86 mm on an A4 sheet: ten cards are two sheets.
        $this->assertSame(9, CardDesign::cardsPerSheet());

        $sheets = $this->get($this->url(['course_id' => $course->getKey()]))
            ->assertOk()
            ->assertSee('size: A4;', escape: false)
            ->assertDontSee('<section class="single">', escape: false)
            ->getContent();

        $this->assertSame(2, substr_count($sheets, '<section class="sheet">'));
        $this->assertSame(10, substr_count($sheets, '<svg class="rawda-badge"'));
        $this->assertSame(10, substr_count($sheets, 'width="54mm" height="86mm"'));

        // For a card printer: every card is a page of its own size, upright.
        $singles = $this->get($this->url(['course_id' => $course->getKey(), 'paper' => CardPaper::Single->value]))
            ->assertOk()
            ->assertSee('size: 54mm 86mm;', escape: false)
            ->assertDontSee('<section class="sheet">', escape: false)
            ->getContent();

        $this->assertSame(10, substr_count($singles, '<section class="single">'));

        // Anything else than a known kind of paper is the sheet.
        $this->get($this->url(['course_id' => $course->getKey(), 'paper' => 'papyrus']))
            ->assertOk()
            ->assertSee('size: A4;', escape: false);
    }

    public function test_the_card_carries_the_photo_the_name_the_circle_and_the_circle_logo(): void
    {
        $this->actingAs(User::factory()->create());

        $course = Course::factory()->create();
        $withLogo = app(SaveCircle::class)->handle($course, ['name' => 'تاج الوقار', 'logo' => Pictures::png('logo.png')]);
        $withoutLogo = Circle::factory()->create(['course_id' => $course->getKey(), 'name' => 'حلقة الفجر']);

        $withPhoto = app(SaveStudent::class)->handle(['full_name' => 'ضياء الدين يزبك', 'photo' => Pictures::png()]);
        $this->enroll($withPhoto, $course, $withLogo);
        $this->enroll(Student::factory()->create(['full_name' => 'خالد بلا صورة']), $course, $withoutLogo);

        $page = $this->get($this->url(['course_id' => $course->getKey()]))
            ->assertOk()
            // The fixed design (with the mosque's logo) lies under every card.
            ->assertSee(asset('img/card/background.png'), escape: false)
            ->assertSee('ضياء الدين يزبك')
            ->assertSee('تاج الوقار')
            ->assertSee('خالد بلا صورة')
            ->assertSee('حلقة الفجر')
            // The texts are in the card's font, the panel's font standing in for it.
            ->assertSee("font-family: '".CardFont::FAMILY."'", escape: false)
            ->getContent();

        // The photo is cut to the frame of the design; without one, a grey figure stands in its place.
        $this->assertSame(1, substr_count($page, 'class="rawda-badge__photo"'));
        $this->assertStringContainsString((string) StudentPresenter::photoUrl($withPhoto), $page);
        $this->assertSame(1, substr_count($page, 'class="rawda-badge__nobody"'));

        // The circle's logo, on the cards of its students only.
        $this->assertSame(1, substr_count($page, 'class="rawda-badge__logo"'));
        $this->assertStringContainsString(e((string) CirclePresenter::logoUrl($withLogo)), $page);
    }
}
