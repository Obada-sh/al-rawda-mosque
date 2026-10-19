<?php

namespace Tests\Unit;

use App\Services\Export\Pptx\InvalidSlideTemplate;
use App\Services\Export\Pptx\SlideContent;
use App\Services\Export\Pptx\SlideDeckTemplate;
use App\Services\Export\Pptx\SlideShowOptions;
use App\Services\Export\Pptx\TextFitter;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Tests\Support\Deck;
use ZipArchive;

/**
 * The engine that turns a slide template into a presentation: the board
 * of the default design the system ships with (one cell repeated for
 * every student), and designs with numbered places (tests/Fixtures).
 */
class SlideDeckTemplateTest extends TestCase
{
    /** @var list<string> files made by a test, removed after it */
    private array $temporaryFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->temporaryFiles as $file) {
            @unlink($file);
        }

        parent::tearDown();
    }

    /** The placeholders of a student in the leaders' slides (LeadersTemplates::STUDENT_PLACEHOLDERS). */
    private const CELL = ['place', 'name', 'score', 'value', 'unit'];

    /** A design with that many numbered places ({{name1}}...), with cards and medals for the first places. */
    private static function numbered(int $places): string
    {
        return dirname(__DIR__, 2)."/tests/Fixtures/slides/numbered-{$places}.pptx";
    }

    /** The default design: a board. */
    private static function board(): string
    {
        return dirname(__DIR__, 2).'/resources/pptx/leaders.pptx';
    }

    /**
     * @return list<array<string, string>> the cells of so many students, the first one first
     */
    private static function students(int $count): array
    {
        return array_map(
            fn (int $place): array => ['place' => (string) $place, 'name' => "طالب {$place}", 'value' => (string) (100 - $place)],
            range(1, $count),
        );
    }

    /**
     * @param  array<int, int>|null  $places
     */
    private static function circle(string $name, ?array $places): SlideContent
    {
        $values = ['circle' => $name, 'criterion' => 'الأوائل في الحفظ'];

        foreach ($places ?? [] as $slot => $place) {
            $values["place{$slot}"] = (string) $place;
            $values["name{$slot}"] = "طالب {$slot}";
            $values["score{$slot}"] = "{$slot}0 صفحة";
        }

        return new SlideContent($values, $places);
    }

    /**
     * A copy of a default template whose slide went through an edit.
     *
     * @param  callable(string): string  $edit  receives and returns the XML of the slide
     */
    private function editedTemplate(string $template, callable $edit): string
    {
        $path = $this->temporaryFile();
        copy($template, $path);

        $zip = new ZipArchive;
        $zip->open($path);
        $zip->addFromString('ppt/slides/slide1.xml', $edit((string) $zip->getFromName('ppt/slides/slide1.xml')));
        $zip->close();

        return $path;
    }

    private function temporaryFile(string $contents = ''): string
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'tpl');
        file_put_contents($path, $contents);

        return $this->temporaryFiles[] = $path;
    }

    public function test_a_design_with_numbered_places_has_as_many_slots(): void
    {
        foreach ([1, 3, 4] as $places) {
            $template = SlideDeckTemplate::open(self::numbered($places), self::CELL);

            $this->assertFalse($template->isBoard());
            $this->assertSame($places, $template->slots());
            $this->assertSame($places, $template->itemsPerSlide());

            foreach (['mosque', 'criterion', 'circle', 'supervisor_line', 'course', 'period', "place{$places}", "name{$places}", "score{$places}"] as $placeholder) {
                $this->assertContains($placeholder, $template->placeholders(), "numbered-{$places}.pptx");
            }
        }
    }

    public function test_one_filled_slide_is_made_for_each_item_in_the_given_order(): void
    {
        $bytes = SlideDeckTemplate::open(self::numbered(3))->render([
            new SlideContent([
                'mosque' => 'جامع الروضة',
                'circle' => 'حلقة أ',
                'supervisor_line' => 'المشرف: الشيخ ياسر',
                'course' => 'دورة الخريف',
                'period' => 'من 01/10/2026 إلى 07/10/2026',
                'place1' => '1', 'name1' => 'أحمد', 'score1' => '12 صفحة',
                'place2' => '2', 'name2' => 'بلال', 'score2' => '4 صفحات',
                'place3' => '3', 'name3' => 'خالد', 'score3' => 'صفحة واحدة',
            ], [1 => 1, 2 => 2, 3 => 3]),
            self::circle('حلقة ب', [1 => 1]),
        ], new SlideShowOptions);

        $deck = new Deck($bytes);

        $this->assertCount(2, $deck->slides());

        $first = $deck->paragraphs(0);
        $this->assertContains('حلقة أ', $first);
        $this->assertContains('جامع الروضة', $first);
        $this->assertContains('المشرف: الشيخ ياسر', $first);
        // Two placeholders of one paragraph, with the text between them kept.
        $this->assertContains('دورة الخريف  من 01/10/2026 إلى 07/10/2026', $first);
        $this->assertContains('أحمد', $first);
        $this->assertContains('12 صفحة', $first);
        $this->assertContains('صفحة واحدة', $first);

        $this->assertContains('حلقة ب', $deck->paragraphs(1));
        $this->assertNotContains('حلقة أ', $deck->paragraphs(1));

        foreach ([0, 1] as $slide) {
            $this->assertStringNotContainsString('{{', implode(' ', $deck->paragraphs($slide)));
        }
    }

    public function test_a_placeholder_without_a_value_leaves_nothing_behind(): void
    {
        $deck = new Deck(SlideDeckTemplate::open(self::numbered(1))->render(
            [new SlideContent(['circle' => 'حلقة أ', 'name1' => 'أحمد'], [1 => 1])],
            new SlideShowOptions,
        ));

        $this->assertSame(['حلقة أ', 'أحمد'], array_values(array_filter($deck->paragraphs(0), fn (string $text): bool => trim($text) !== '')));
    }

    public function test_a_value_is_written_as_text_whatever_it_contains(): void
    {
        $deck = new Deck(SlideDeckTemplate::open(self::numbered(1))->render(
            [new SlideContent(['circle' => 'حلقة <أ> & "ب" {{name1}}', 'name1' => "O'Neil </a:t>"], [1 => 1])],
            new SlideShowOptions,
        ));

        // The slide is still well-formed XML, and a value is never read as a placeholder.
        $this->assertContains('حلقة <أ> & "ب" {{name1}}', $deck->paragraphs(0));
        $this->assertContains("O'Neil </a:t>", $deck->paragraphs(0));
    }

    public function test_a_placeholder_cut_into_pieces_by_powerpoint_is_still_found(): void
    {
        $template = SlideDeckTemplate::open($this->editedTemplate(self::numbered(1), fn (string $xml): string => str_replace(
            '<a:t>{{circle}}</a:t>',
            '<a:t>{{cir</a:t></a:r><a:r><a:t>cle</a:t></a:r><a:r><a:t>}} المباركة</a:t>',
            $xml,
        )));

        $this->assertContains('circle', $template->placeholders());

        $deck = new Deck($template->render([new SlideContent(['circle' => 'حلقة أ'], [])], new SlideShowOptions));

        $this->assertContains('حلقة أ المباركة', $deck->paragraphs(0));
    }

    public function test_placeholders_are_found_whatever_their_case_and_spacing(): void
    {
        $template = SlideDeckTemplate::open($this->editedTemplate(self::numbered(1), fn (string $xml): string => str_replace(
            ['{{circle}}', '{{name1}}'],
            ['{{ Circle }}', '{{NAME1}}'],
            $xml,
        )));

        $deck = new Deck($template->render([new SlideContent(['circle' => 'حلقة أ', 'name1' => 'أحمد'], [1 => 1])], new SlideShowOptions));

        $this->assertContains('حلقة أ', $deck->paragraphs(0));
        $this->assertContains('أحمد', $deck->paragraphs(0));
        $this->assertSame(1, $template->slots());
    }

    public function test_the_shapes_of_an_empty_slot_are_removed(): void
    {
        $deck = new Deck(SlideDeckTemplate::open(self::numbered(3))->render(
            [self::circle('حلقة أ', [1 => 1, 2 => 2])],
            new SlideShowOptions,
        ));

        $shapes = $deck->shapes(0);

        $this->assertContains('slot1 name', $shapes);
        $this->assertContains('slot2 name', $shapes);
        $this->assertSame([], array_values(array_filter($shapes, fn (string $name): bool => str_starts_with($name, 'slot3'))));
        // What does not belong to a slot is never touched: the mosque's logo among them.
        $this->assertContains('circle', $shapes);
        $this->assertContains('logo', $shapes);
    }

    public function test_a_slot_takes_the_look_of_the_place_it_holds(): void
    {
        // Two students tied for the first place, then a third and a fourth place.
        $deck = new Deck(SlideDeckTemplate::open(self::numbered(4))->render(
            [self::circle('حلقة أ', [1 => 1, 2 => 1, 3 => 3, 4 => 4])],
            new SlideShowOptions,
        ));

        $slotShapes = array_values(array_filter($deck->shapes(0), fn (string $name): bool => str_starts_with($name, 'slot')));

        $this->assertSame([
            'slot1 card', 'slot1 medal', 'slot1 place', 'slot1 name', 'slot1 score',
            // The second row holds a first place: the card and the medal of a first place.
            'slot2@1 card', 'slot2@1 medal', 'slot2 place', 'slot2 name', 'slot2 score',
            // A third place has a medal of its own, but no card of its own.
            'slot3@0 card', 'slot3@3 medal', 'slot3 place', 'slot3 name', 'slot3 score',
            // A fourth place has neither.
            'slot4@0 card', 'slot4@0 medal', 'slot4 place', 'slot4 name', 'slot4 score',
        ], $slotShapes);
    }

    public function test_without_places_every_shape_is_kept(): void
    {
        $template = self::numbered(4);

        $deck = new Deck(SlideDeckTemplate::open($template)->render([self::circle('حلقة أ', null)], new SlideShowOptions));

        $this->assertSame(Deck::fromFile($template)->shapes(0), $deck->shapes(0));
    }

    public function test_the_template_slide_is_replaced_by_its_copies_in_the_file(): void
    {
        $deck = new Deck(SlideDeckTemplate::open(self::numbered(3))->render(
            [self::circle('حلقة أ', [1 => 1]), self::circle('حلقة ب', [1 => 1]), self::circle('حلقة ج', [1 => 1])],
            new SlideShowOptions,
        ));

        $this->assertSame(['ppt/slides/slide2.xml', 'ppt/slides/slide3.xml', 'ppt/slides/slide4.xml'], $deck->slides());
        $this->assertFalse($deck->has('ppt/slides/slide1.xml'));
        $this->assertFalse($deck->has('ppt/slides/_rels/slide1.xml.rels'));

        // Every part of the file is declared, and nothing that is gone still is.
        $contentTypes = $deck->part('[Content_Types].xml');
        $this->assertStringNotContainsString('/ppt/slides/slide1.xml', $contentTypes);

        foreach ($deck->slides() as $slide) {
            $this->assertStringContainsString('PartName="/'.$slide.'"', $contentTypes);
            // A copy keeps what the template slide pointed to (its layout).
            $this->assertTrue($deck->has('ppt/slides/_rels/'.basename($slide).'.rels'));
        }

        $this->assertStringContainsString('<Slides>3</Slides>', $deck->part('docProps/app.xml'));
        $this->assertStringContainsString('<Notes>0</Notes>', $deck->part('docProps/app.xml'));
        // The notes page of the template slide went with it.
        $this->assertSame([], array_values(array_filter($deck->parts(), fn (string $part): bool => str_starts_with($part, 'ppt/notesSlides/'))));
    }

    public function test_the_show_loops_and_moves_on_by_itself(): void
    {
        $deck = new Deck(SlideDeckTemplate::open(self::numbered(3))->render(
            [self::circle('حلقة أ', [1 => 1]), self::circle('حلقة ب', [1 => 1])],
            new SlideShowOptions(secondsPerSlide: 8, loop: true),
        ));

        $this->assertMatchesRegularExpression('/<p:showPr[^>]*\bloop="1"/', $deck->part('ppt/presProps.xml'));
        $this->assertMatchesRegularExpression('/<p:showPr[^>]*\buseTimings="1"/', $deck->part('ppt/presProps.xml'));

        foreach ([0, 1] as $slide) {
            $this->assertMatchesRegularExpression('/<p:transition[^>]*\badvTm="8000"/', $deck->slide($slide));
        }
    }

    public function test_the_timing_and_the_loop_can_be_left_to_the_template(): void
    {
        $deck = new Deck(SlideDeckTemplate::open(self::numbered(3))->render(
            [self::circle('حلقة أ', [1 => 1])],
            new SlideShowOptions(secondsPerSlide: null, loop: false),
        ));

        $this->assertStringNotContainsString('<p:transition', $deck->slide(0));
        $this->assertStringNotContainsString('loop="1"', $deck->part('ppt/presProps.xml'));
    }

    public function test_a_file_can_be_made_to_open_as_a_running_show(): void
    {
        $template = SlideDeckTemplate::open(self::numbered(3));
        $slides = [self::circle('حلقة أ', [1 => 1])];

        $editable = new SlideShowOptions(opensAsShow: false);
        $show = new SlideShowOptions(opensAsShow: true);

        $this->assertSame('pptx', $editable->extension());
        $this->assertSame('ppsx', $show->extension());
        $this->assertStringEndsWith('presentationml.presentation', $editable->mimeType());
        $this->assertStringEndsWith('presentationml.slideshow', $show->mimeType());

        $this->assertStringContainsString(
            'presentationml.presentation.main+xml',
            (new Deck($template->render($slides, $editable)))->part('[Content_Types].xml'),
        );

        $showTypes = (new Deck($template->render($slides, $show)))->part('[Content_Types].xml');
        $this->assertStringContainsString('presentationml.slideshow.main+xml', $showTypes);
        $this->assertStringNotContainsString('presentationml.presentation.main+xml', $showTypes);
    }

    public function test_a_template_gives_the_same_presentation_every_time(): void
    {
        $template = SlideDeckTemplate::open(self::numbered(4));
        $slides = [self::circle('حلقة أ', [1 => 1, 2 => 2]), self::circle('حلقة ب', [1 => 1])];

        $first = new Deck($template->render($slides, new SlideShowOptions(secondsPerSlide: 5)));
        $second = new Deck($template->render($slides, new SlideShowOptions(secondsPerSlide: 5)));

        $this->assertSame($first->slides(), $second->slides());
        $this->assertSame($first->slide(0), $second->slide(0));
        $this->assertSame($first->part('ppt/presentation.xml'), $second->part('ppt/presentation.xml'));
        $this->assertSame(4, $template->slots());
    }

    public function test_a_presentation_needs_at_least_one_slide(): void
    {
        $this->expectException(InvalidArgumentException::class);

        SlideDeckTemplate::open(self::numbered(3))->render([], new SlideShowOptions);
    }

    public function test_what_is_not_a_presentation_is_refused(): void
    {
        $archive = $this->temporaryFile();
        $zip = new ZipArchive;
        $zip->open($archive, ZipArchive::OVERWRITE);
        $zip->addFromString('hello.txt', 'hello');
        $zip->close();

        foreach ([$this->temporaryFile('just some text'), $archive, sys_get_temp_dir().'/no-such-file.pptx'] as $path) {
            try {
                SlideDeckTemplate::open($path);
                $this->fail('A file that is not a presentation was accepted.');
            } catch (InvalidSlideTemplate $exception) {
                $this->assertSame(InvalidSlideTemplate::NOT_A_PRESENTATION, $exception->reason);
            }
        }
    }

    public function test_a_presentation_without_placeholders_is_refused(): void
    {
        // A finished presentation has nothing left to fill.
        $finished = $this->temporaryFile(SlideDeckTemplate::open(self::numbered(1))->render(
            [self::circle('حلقة أ', [1 => 1])],
            new SlideShowOptions,
        ));

        try {
            SlideDeckTemplate::open($finished);
            $this->fail('A presentation without placeholders was accepted.');
        } catch (InvalidSlideTemplate $exception) {
            $this->assertSame(InvalidSlideTemplate::NO_PLACEHOLDERS, $exception->reason);
        }
    }

    public function test_a_broken_slide_is_refused(): void
    {
        $broken = $this->editedTemplate(self::numbered(1), fn (string $xml): string => substr($xml, 0, (int) (strlen($xml) / 2)));

        try {
            SlideDeckTemplate::open($broken);
            $this->fail('A presentation with a broken slide was accepted.');
        } catch (InvalidSlideTemplate $exception) {
            $this->assertSame(InvalidSlideTemplate::NOT_A_PRESENTATION, $exception->reason);
        }
    }

    public function test_the_default_design_is_a_board_that_holds_a_large_circle(): void
    {
        $template = SlideDeckTemplate::open(self::board(), self::CELL);

        $this->assertTrue($template->isBoard());
        $this->assertSame(0, $template->slots());
        $this->assertGreaterThanOrEqual(30, $template->itemsPerSlide());

        foreach (['title', 'name', 'value'] as $placeholder) {
            $this->assertContains($placeholder, $template->placeholders());
        }

        // Opened without the placeholders of a student, the same slide has no cell to repeat.
        $this->assertFalse(SlideDeckTemplate::open(self::board())->isBoard());
    }

    public function test_the_cell_is_repeated_for_every_student_from_the_top_right(): void
    {
        $deck = new Deck(SlideDeckTemplate::open(self::board(), self::CELL)->render(
            [new SlideContent(['title' => 'نقاط حلقة الفرقان'], cells: self::students(7))],
            new SlideShowOptions,
        ));

        $names = $deck->named(0, 'cell name');
        $scores = $deck->named(0, 'cell score');

        $this->assertSame(array_map(fn (int $place): string => "طالب {$place}", range(1, 7)), array_column($names, 'text'));
        $this->assertSame(['99', '98', '97', '96', '95', '94', '93'], array_column($scores, 'text'));
        $this->assertContains('نقاط حلقة الفرقان', $deck->paragraphs(0));
        $this->assertStringNotContainsString('{{', implode(' ', $deck->paragraphs(0)));
        // The area of the board is not drawn.
        $this->assertSame([], $deck->named(0, 'cells'));

        // Seven students: two columns, read from right to left, then down.
        [$first, $second, $third] = $names;
        $this->assertGreaterThan($second['x'], $first['x']);
        $this->assertSame($first['y'], $second['y']);
        $this->assertSame($first['x'], $third['x']);
        $this->assertGreaterThan($first['y'], $third['y']);
        // The last row is not full: its cell stands on the right, under the first.
        $this->assertSame($first['x'], $names[6]['x']);

        // Each score stands on the left of its name, on the same line.
        foreach ($names as $index => $name) {
            $this->assertLessThan($name['x'], $scores[$index]['x']);
            $this->assertSame($name['y'], $scores[$index]['y']);
        }
    }

    public function test_the_fewer_the_students_the_larger_their_cells(): void
    {
        $original = Deck::fromFile(self::board())->named(0, 'cell name')[0];

        $deck = new Deck(SlideDeckTemplate::open(self::board(), self::CELL)->render([
            new SlideContent([], cells: self::students(1)),
            new SlideContent([], cells: self::students(12)),
            new SlideContent([], cells: self::students(30)),
        ], new SlideShowOptions));

        $one = $deck->named(0, 'cell name')[0];
        $twelve = $deck->named(1, 'cell name')[0];
        $thirty = $deck->named(2, 'cell name')[0];

        $this->assertGreaterThan($original['width'], $one['width']);
        $this->assertGreaterThan($twelve['width'], $one['width']);
        $this->assertGreaterThan($thirty['width'], $twelve['width']);
        // The text grows and shrinks with its cell, and the cell keeps its proportions.
        $this->assertGreaterThan($original['sizes'][0], $one['sizes'][0]);
        $this->assertLessThan($original['sizes'][0], $thirty['sizes'][0]);
        $this->assertEqualsWithDelta($original['width'] / $original['height'], $thirty['width'] / $thirty['height'], 0.01);

        // Every cell stays on the slide, below the title.
        foreach ([0 => 1, 1 => 12, 2 => 30] as $slide => $count) {
            $cells = [...$deck->named($slide, 'cell name'), ...$deck->named($slide, 'cell score')];

            $this->assertCount(2 * $count, $cells);

            foreach ($cells as $cell) {
                $this->assertGreaterThanOrEqual(0, $cell['x']);
                $this->assertLessThanOrEqual(12192000, $cell['x'] + $cell['width']);
                $this->assertGreaterThanOrEqual($original['y'], $cell['y']);
                $this->assertLessThanOrEqual(6858000, $cell['y'] + $cell['height']);
            }
        }
    }

    public function test_a_long_name_is_drawn_smaller_to_fit_its_cell(): void
    {
        $cells = self::students(4);
        $cells[1]['name'] = 'عبد الرحمن بن محمد بن عبد الله الحسيني';

        $deck = new Deck(SlideDeckTemplate::open(self::board(), self::CELL)->render(
            [new SlideContent([], cells: $cells)],
            new SlideShowOptions,
        ));

        $names = $deck->named(0, 'cell name');

        $this->assertLessThan($names[0]['sizes'][0], $names[1]['sizes'][0]);
        $this->assertSame($names[0]['sizes'][0], $names[2]['sizes'][0]);
        // Never below six tenths of its size.
        $this->assertGreaterThanOrEqual((int) floor($names[0]['sizes'][0] * TextFitter::SMALLEST), $names[1]['sizes'][0]);
    }

    public function test_a_long_title_is_drawn_smaller_to_stay_on_one_line(): void
    {
        $template = SlideDeckTemplate::open(self::board(), self::CELL);
        $original = Deck::fromFile(self::board())->named(0, 'title')[0]['sizes'][0];

        $deck = new Deck($template->render([
            new SlideContent(['title' => 'نقاط حلقة أ'], cells: self::students(3)),
            new SlideContent(['title' => 'نقاط حلقة الإمام أبي حنيفة النعمان للحفظ المتقن (1/2)'], cells: self::students(3)),
        ], new SlideShowOptions));

        $this->assertSame($original, $deck->named(0, 'title')[0]['sizes'][0]);
        $this->assertLessThan($original, $deck->named(1, 'title')[0]['sizes'][0]);
        // The shrinking PowerPoint stored in the template is now part of the size.
        $this->assertStringNotContainsString('fontScale', $deck->slide(1));
    }

    public function test_every_shape_of_a_board_has_an_id_of_its_own(): void
    {
        $deck = new Deck(SlideDeckTemplate::open(self::board(), self::CELL)->render(
            [new SlideContent([], cells: self::students(20))],
            new SlideShowOptions,
        ));

        $ids = array_column($deck->drawn(0), 'id');

        $this->assertCount(2 * 20 + 2, $ids);
        $this->assertSame($ids, array_values(array_unique($ids)));
    }

    public function test_more_students_than_a_slide_holds_are_refused(): void
    {
        $template = SlideDeckTemplate::open(self::board(), self::CELL);

        $this->expectException(InvalidArgumentException::class);

        $template->render([new SlideContent([], cells: self::students($template->itemsPerSlide() + 1))], new SlideShowOptions);
    }

    public function test_without_an_area_the_board_takes_the_room_below_its_cell(): void
    {
        $original = Deck::fromFile(self::board())->named(0, 'cell name')[0];
        $template = SlideDeckTemplate::open($this->editedTemplate(self::board(), fn (string $xml): string => (string) preg_replace(
            '#<p:sp><p:nvSpPr><p:cNvPr id="\d+" name="cells".*?</p:sp>#s',
            '',
            $xml,
        )), self::CELL);

        $this->assertTrue($template->isBoard());

        $deck = new Deck($template->render([new SlideContent([], cells: self::students(12))], new SlideShowOptions));

        $this->assertCount(12, $deck->named(0, 'cell name'));

        foreach ([...$deck->named(0, 'cell name'), ...$deck->named(0, 'cell score')] as $cell) {
            $this->assertGreaterThanOrEqual($original['y'], $cell['y']);
            $this->assertLessThanOrEqual(12192000, $cell['x'] + $cell['width']);
            $this->assertLessThanOrEqual(6858000, $cell['y'] + $cell['height']);
        }
    }

    public function test_a_board_without_room_for_its_cell_shows_nobody(): void
    {
        // The area shrunk to a sliver: not even one cell fits in it.
        $sliver = $this->editedTemplate(self::board(), fn (string $xml): string => (string) preg_replace(
            '#(name="cells".*?<a:ext cx=")\d+(" cy=")\d+#s',
            '${1}200000${2}200000',
            $xml,
        ));

        $this->assertSame(0, SlideDeckTemplate::open($sliver, self::CELL)->itemsPerSlide());

        // A cell that takes its place from the layout instead of standing where it is drawn.
        $unplaced = $this->editedTemplate(self::board(), fn (string $xml): string => (string) preg_replace(
            '#(name="cell name".*?)<a:xfrm>.*?</a:xfrm>#s',
            '$1',
            $xml,
        ));

        try {
            SlideDeckTemplate::open($unplaced, self::CELL);
            $this->fail('A cell without a place of its own was accepted.');
        } catch (InvalidSlideTemplate $exception) {
            $this->assertSame(InvalidSlideTemplate::NO_ROOM, $exception->reason);
        }
    }
}
