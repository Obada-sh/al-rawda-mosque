<?php

namespace App\Actions\Export;

use App\Enums\RankingCriterion;
use App\Exceptions\BusinessRuleViolation;
use App\Http\Requests\Export\LeadersSlidesRequest;
use App\Models\Circle;
use App\Models\Course;
use App\Services\Export\ExportedFile;
use App\Services\Export\LeadersDeck;
use App\Services\Export\LeadersSlides;
use App\Services\Export\Pptx\InvalidSlideTemplate;
use App\Services\Export\Pptx\SlideShowOptions;
use App\Services\Statistics\CircleLeaders;
use App\Services\Statistics\ReportFilter;
use App\Support\CircleTitle;
use App\Support\Dates;
use Illuminate\Validation\ValidationException;

/**
 * Makes the PowerPoint file of the students of a course, circle by circle
 * (or of one circle), best first by new pages, points, attendance or juz
 * exams. The slides follow each other by themselves and start again after
 * the last one, for a screen that is left running.
 */
final class ExportLeadersSlides
{
    public const NO_LEADERS = 'leaders_slides.no_leaders';

    public const CIRCLE_OF_ANOTHER_COURSE = 'leaders_slides.circle_of_another_course';

    public const UNUSABLE_TEMPLATE = 'slide_template.unusable';

    public function __construct(
        private readonly CircleLeaders $leaders,
        private readonly LeadersSlides $slides,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     *
     * @throws ValidationException
     * @throws BusinessRuleViolation leaders_slides.no_leaders | leaders_slides.circle_of_another_course | slide_template.unusable
     */
    public function handle(array $input): ExportedFile
    {
        $data = LeadersSlidesRequest::validateData($input);

        $course = Course::query()->findOrFail($data['course_id']);
        $circle = isset($data['circle_id']) ? Circle::query()->findOrFail($data['circle_id']) : null;
        $criterion = RankingCriterion::from($data['criterion']);

        if ($circle !== null && $circle->course_id !== $course->getKey()) {
            throw BusinessRuleViolation::because(self::CIRCLE_OF_ANOTHER_COURSE, 'الحلقة المختارة ليست من حلقات هذه الدورة.');
        }

        $filter = new ReportFilter(
            courseId: $course->getKey(),
            circleId: $circle?->getKey(),
            from: isset($data['from']) ? Dates::day($data['from']) : null,
            until: isset($data['until']) ? Dates::day($data['until']) : null,
        );

        $circles = $this->leaders->of($filter, $criterion, isset($data['top']) ? (int) $data['top'] : null);

        if ($circles->isEmpty()) {
            throw BusinessRuleViolation::because(self::NO_LEADERS, self::nothingToShow($criterion, $circle !== null));
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
                    circles: $circles->all(),
                ),
                $options,
            );
        } catch (InvalidSlideTemplate) {
            // The default design always opens; only the admin's own file can fail here.
            throw BusinessRuleViolation::because(
                self::UNUSABLE_TEMPLATE,
                'تعذّر استعمال قالبك الخاص للشرائح. ارفعه من جديد من صفحة الإعدادات العامة، أو عد فيها إلى التصميم الافتراضي.',
            );
        }

        $about = $circle === null ? 'أوائل الحلقات' : 'أوائل '.CircleTitle::of($circle->name);

        return new ExportedFile(
            name: "{$about} - {$course->name}.{$options->extension()}",
            contents: $contents,
            mimeType: $options->mimeType(),
        );
    }

    private static function nothingToShow(RankingCriterion $criterion, bool $oneCircle): string
    {
        $where = $oneCircle ? 'في هذه الحلقة' : 'في هذه الدورة';

        return 'لا يوجد ما يُعرض: '.match ($criterion) {
            RankingCriterion::NewPages => "لم يسمّع أي طالب {$where} صفحات جديدة في الفترة المختارة.",
            RankingCriterion::Points => "لم يكسب أي طالب {$where} نقاطاً في الفترة المختارة.",
            RankingCriterion::Attendance => "لم يحضر أي طالب {$where} جلسة في الفترة المختارة.",
            RankingCriterion::Exams => "لم ينجح أي طالب {$where} في سبر جزء في الفترة المختارة.",
        };
    }
}
