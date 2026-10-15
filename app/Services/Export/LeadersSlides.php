<?php

namespace App\Services\Export;

use App\Contracts\SettingsStore;
use App\Enums\RankingCriterion;
use App\Services\Export\Pptx\InvalidSlideTemplate;
use App\Services\Export\Pptx\SlideContent;
use App\Services\Export\Pptx\SlideShowOptions;
use App\Services\Statistics\CircleLeaderboard;
use App\Support\ArabicNoun;
use App\Support\Dates;
use App\Support\SettingKey;

/**
 * Writes the leaders of each circle on the slide template: one slide per
 * circle, in a PowerPoint file.
 *
 * What a template can show (it uses the placeholders it wants):
 *
 *   {{mosque}} {{course}} {{period}} {{from}} {{until}} {{criterion}}
 *   {{circle}} {{supervisor}} {{supervisor_line}} {{assistant}} {{assistant_line}}
 *
 * and for each leader, numbered from 1:
 *
 *   {{place1}} {{name1}} {{score1}} {{value1}} {{unit1}}
 *
 * {{score1}} is the number with its unit ("24 صفحة"); {{value1}} and
 * {{unit1}} are the same two, for a design that separates them.
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
        $template = $this->templates->for($deck->top);
        $slots = $template->slots();

        $shared = $this->sharedValues($deck);

        return $template->render(
            array_map(
                fn (CircleLeaderboard $circle): SlideContent => $this->slide($deck, $circle, $shared, $slots),
                $deck->circles,
            ),
            $options,
        );
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
            'criterion' => $deck->criterion === RankingCriterion::NewPages ? 'الأوائل في الحفظ' : 'الأوائل في النقاط',
        ];
    }

    /**
     * @param  array<string, string>  $shared
     * @param  int  $slots  how many leaders the template has room for
     */
    private function slide(LeadersDeck $deck, CircleLeaderboard $circle, array $shared, int $slots): SlideContent
    {
        $noun = $deck->criterion === RankingCriterion::NewPages ? ArabicNoun::page() : ArabicNoun::point();

        $values = $shared + [
            'circle' => $circle->circleName,
            'supervisor' => $circle->supervisorName ?? '',
            'supervisor_line' => $circle->supervisorName === null ? '' : "المشرف: {$circle->supervisorName}",
            'assistant' => $circle->assistantName ?? '',
            'assistant_line' => $circle->assistantName === null ? '' : "المساعد: {$circle->assistantName}",
        ];

        $places = [];

        foreach (array_slice($circle->leaders, 0, $slots) as $index => $leader) {
            $slot = $index + 1;

            $places[$slot] = $leader->place;

            $values["place{$slot}"] = (string) $leader->place;
            $values["name{$slot}"] = $leader->studentName;
            $values["score{$slot}"] = $noun->count($leader->score);
            $values["value{$slot}"] = ArabicNoun::digits($leader->score);
            $values["unit{$slot}"] = $noun->after($leader->score);
        }

        return new SlideContent($values, $places);
    }
}
