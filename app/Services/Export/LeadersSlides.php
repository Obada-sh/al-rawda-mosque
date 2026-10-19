<?php

namespace App\Services\Export;

use App\Contracts\SettingsStore;
use App\Enums\RankingCriterion;
use App\Services\Export\Pptx\InvalidSlideTemplate;
use App\Services\Export\Pptx\SlideContent;
use App\Services\Export\Pptx\SlideShowOptions;
use App\Services\Statistics\CircleLeader;
use App\Services\Statistics\CircleLeaderboard;
use App\Support\ArabicNoun;
use App\Support\CircleTitle;
use App\Support\Dates;
use App\Support\SettingKey;

/**
 * Writes the students of each circle on the slide template, best first,
 * in a PowerPoint file: one slide per circle, or several for a circle
 * with more students than a slide can show (they are shared evenly
 * between its slides).
 *
 * What a template can show (it uses the placeholders it wants):
 *
 *   {{title}}  "نقاط حلقة تاج الوقار", with "(1/2)" when the circle takes two slides
 *   {{mosque}} {{course}} {{period}} {{from}} {{until}} {{criterion}} {{page}}
 *   {{circle}} {{supervisor}} {{supervisor_line}} {{assistant}} {{assistant_line}}
 *
 * and for each student (see LeadersTemplates::STUDENT_PLACEHOLDERS):
 *
 *   {{place}} {{name}} {{score}} {{value}} {{unit}}
 *
 * written once to make the cell of a board, or numbered from 1 for a
 * design with a fixed number of places ({{name1}}, {{name2}}...).
 * {{score}} is the number with its unit ("24 صفحة"); {{value}} and
 * {{unit}} are the same two, for a design that separates them.
 */
final class LeadersSlides
{
    public function __construct(
        private readonly LeadersTemplates $templates,
        private readonly SettingsStore $settings,
    ) {}

    /**
     * @return string the bytes of the .pptx (or .ppsx) file
     *
     * @throws InvalidSlideTemplate when the admin's own template can no longer be used
     */
    public function render(LeadersDeck $deck, SlideShowOptions $options): string
    {
        $template = $this->templates->current();
        $perSlide = $template->itemsPerSlide();

        if ($perSlide < 1) {
            throw InvalidSlideTemplate::noRoom();
        }

        $shared = $this->sharedValues($deck);
        $slides = [];

        foreach ($deck->circles as $circle) {
            $pages = self::pages($circle->leaders, $perSlide);

            foreach ($pages as $index => $students) {
                $values = $shared + $this->circleValues($deck, $circle, $index + 1, count($pages));

                $slides[] = $template->isBoard()
                    ? $this->board($deck, $values, $students)
                    : $this->places($deck, $values, $students);
            }
        }

        return $template->render($slides, $options);
    }

    /**
     * The students of a circle shared evenly between as few slides as
     * hold them: 13 students on slides of 12 make 7 and 6.
     *
     * @param  list<CircleLeader>  $students
     * @return list<list<CircleLeader>>
     */
    private static function pages(array $students, int $perSlide): array
    {
        $count = count($students);
        $pages = max(1, intdiv($count + $perSlide - 1, $perSlide));

        return array_chunk($students, max(1, intdiv($count + $pages - 1, $pages)));
    }

    /**
     * What is the same on every slide.
     *
     * @return array<string, string>
     */
    private function sharedValues(LeadersDeck $deck): array
    {
        $from = $deck->from === null ? '' : Dates::show($deck->from);
        $until = $deck->until === null ? '' : Dates::show($deck->until);

        return [
            'mosque' => $this->settings->get(SettingKey::MOSQUE_NAME) ?? '',
            'course' => $deck->courseName,
            'from' => $from,
            'until' => $until,
            'period' => match (true) {
                $from !== '' && $until !== '' => "من {$from} إلى {$until}",
                $from !== '' => "منذ {$from}",
                $until !== '' => "حتى {$until}",
                default => '',
            },
            'criterion' => match ($deck->criterion) {
                RankingCriterion::NewPages => 'الأوائل في الحفظ',
                RankingCriterion::Points => 'الأوائل في النقاط',
                RankingCriterion::Attendance => 'الأوائل في الحضور',
                RankingCriterion::Exams => 'الأوائل في السبر',
            },
        ];
    }

    /**
     * What one slide of a circle says about the circle.
     *
     * @return array<string, string>
     */
    private function circleValues(LeadersDeck $deck, CircleLeaderboard $circle, int $page, int $pages): array
    {
        $measure = match ($deck->criterion) {
            RankingCriterion::NewPages => 'حفظ',
            RankingCriterion::Points => 'نقاط',
            RankingCriterion::Attendance => 'حضور',
            RankingCriterion::Exams => 'سبر',
        };

        $circleTitle = CircleTitle::of($circle->circleName);
        $pageText = $pages > 1 ? "{$page}/{$pages}" : '';

        return [
            'title' => trim("{$measure} {$circleTitle}".($pageText === '' ? '' : " ({$pageText})")),
            'page' => $pageText,
            'circle' => $circle->circleName,
            'supervisor' => $circle->supervisorName ?? '',
            'supervisor_line' => $circle->supervisorName === null ? '' : "المشرف: {$circle->supervisorName}",
            'assistant' => $circle->assistantName ?? '',
            'assistant_line' => $circle->assistantName === null ? '' : "المساعد: {$circle->assistantName}",
        ];
    }

    /**
     * A board: one copy of the cell for each student.
     *
     * @param  array<string, string>  $values
     * @param  list<CircleLeader>  $students
     */
    private function board(LeadersDeck $deck, array $values, array $students): SlideContent
    {
        return new SlideContent(
            $values,
            cells: array_map(fn (CircleLeader $student): array => $this->studentValues($deck, $student), $students),
        );
    }

    /**
     * A design with numbered places: the students fill them in order, and
     * the places left empty are taken away.
     *
     * @param  array<string, string>  $values
     * @param  list<CircleLeader>  $students
     */
    private function places(LeadersDeck $deck, array $values, array $students): SlideContent
    {
        $places = [];

        foreach ($students as $index => $student) {
            $slot = $index + 1;
            $places[$slot] = $student->place;

            foreach ($this->studentValues($deck, $student) as $name => $value) {
                $values["{$name}{$slot}"] = $value;
            }
        }

        return new SlideContent($values, $places);
    }

    /**
     * @return array<string, string> one value for each of LeadersTemplates::STUDENT_PLACEHOLDERS
     */
    private function studentValues(LeadersDeck $deck, CircleLeader $student): array
    {
        $noun = match ($deck->criterion) {
            RankingCriterion::NewPages => ArabicNoun::page(),
            RankingCriterion::Points => ArabicNoun::point(),
            RankingCriterion::Attendance => ArabicNoun::day(),
            RankingCriterion::Exams => ArabicNoun::juz(),
        };

        // Points can fall below zero; the noun follows the number without its sign.
        $value = ArabicNoun::digits($student->score);
        $unit = $noun->after(abs($student->score));

        return [
            'place' => (string) $student->place,
            'name' => $student->studentName,
            'score' => $student->score < 0 ? "{$value} {$unit}" : $noun->count($student->score),
            'value' => $value,
            'unit' => $unit,
        ];
    }
}
