<?php

namespace App\Actions\Export;

use App\Enums\RankingCriterion;
use App\Exceptions\BusinessRuleViolation;
use App\Http\Requests\Export\LeadersSlidesRequest;
use App\Models\Course;
use App\Services\Export\ExportedFile;
use App\Services\Export\LeadersDeck;
use App\Services\Export\LeadersSlides;
use App\Services\Export\Pptx\InvalidSlideTemplate;
use App\Services\Export\Pptx\SlideShowOptions;
use App\Services\Statistics\CircleLeaders;
use App\Services\Statistics\ReportFilter;
use App\Support\Dates;
use Illuminate\Validation\ValidationException;

/**
 * Makes the PowerPoint file of the leaders of a course: one slide per
 * circle with its best students of the period, by new pages or by points.
 * The slides follow each other by themselves and start again after the
 * last one, for a screen that is left running.
 */
final class ExportLeadersSlides
{
    public const NO_LEADERS = 'leaders_slides.no_leaders';

    public const UNUSABLE_TEMPLATE = 'slide_template.unusable';

    public function __construct(
        private readonly CircleLeaders $leaders,
        private readonly LeadersSlides $slides,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     *
     * @throws ValidationException
     * @throws BusinessRuleViolation leaders_slides.no_leaders | slide_template.unusable
     */
    public function handle(array $input): ExportedFile
    {
        $data = LeadersSlidesRequest::validateData($input);

        $course = Course::query()->findOrFail($data['course_id']);
        $criterion = RankingCriterion::from($data['criterion']);
        $top = (int) $data['top'];

        $filter = new ReportFilter(
            courseId: $course->getKey(),
            from: isset($data['from']) ? Dates::day($data['from']) : null,
            until: isset($data['until']) ? Dates::day($data['until']) : null,
        );

        $circles = $this->leaders->of($filter, $criterion, $top);

        if ($circles->isEmpty()) {
            throw BusinessRuleViolation::because(self::NO_LEADERS, $criterion === RankingCriterion::NewPages
                ? 'لا يوجد ما يُعرض: لم يسمّع أي طالب في هذه الدورة صفحات جديدة في الفترة المختارة.'
                : 'لا يوجد ما يُعرض: لم يكسب أي طالب في هذه الدورة نقاطاً في الفترة المختارة.');
        }

        $options = new SlideShowOptions(
            secondsPerSlide: (int) $data['seconds_per_slide'],
            loop: true,
            opensAsShow: (bool) ($data['opens_as_show'] ?? false),
        );

        try {
            $contents = $this->slides->render(
                new LeadersDeck(
                    courseName: $course->name,
                    from: $filter->from,
                    until: $filter->until,
                    criterion: $criterion,
                    top: $top,
                    circles: $circles->all(),
                ),
                $options,
            );
        } catch (InvalidSlideTemplate) {
            // The default designs always open; only the admin's own file can fail here.
            throw BusinessRuleViolation::because(
                self::UNUSABLE_TEMPLATE,
                'تعذّر استعمال قالبك الخاص للشرائح. ارفعه من جديد من صفحة الإعدادات العامة، أو عد فيها إلى التصميم الافتراضي.',
            );
        }

        return new ExportedFile(
            name: "أوائل الحلقات - {$course->name}.{$options->extension()}",
            contents: $contents,
            mimeType: $options->mimeType(),
        );
    }
}
