<?php

namespace App\Filament\Support\Export;

use App\Actions\Export\ExportLeadersSlides;
use App\Enums\RankingCriterion;
use App\Filament\Support\Attempt;
use App\Filament\Support\Download;
use App\Filament\Support\Options;
use App\Filament\Support\Reports\ReportFilterFields;
use App\Filament\Widgets\Reports\StudentsReport;
use App\Http\Requests\Export\LeadersSlidesRequest;
use App\Services\Export\ExportedFile;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The button that exports the slides of the students of each circle as a
 * PowerPoint file. It is the same on every report page.
 */
final class LeadersSlidesAction
{
    public const NAME = 'exportLeadersSlides';

    /**
     * @param  Closure(): array<string, mixed>  $filters  the filter of the page the button stands on; the window opens on the same course, circle and period
     */
    public static function make(Closure $filters): Action
    {
        return Action::make(self::NAME)
            ->label('شرائح الأوائل')
            ->icon(Heroicon::OutlinedPresentationChartBar)
            ->color('gray')
            ->modalHeading('تصدير شرائح الحلقات')
            ->modalDescription('ملف PowerPoint فيه شريحة لكل حلقة، يُرتَّب فيها طلابها من الأول، بحجم يناسب عددهم (والحلقة الكبيرة تأخذ أكثر من شريحة). تتوالى الشرائح وحدها ويعود العرض إلى أوله بلا توقف.')
            ->modalSubmitActionLabel('تصدير الملف')
            ->fillForm(fn (): array => self::defaults($filters()))
            ->schema([
                Grid::make(['md' => 2])
                    ->schema([
                        ReportFilterFields::course()
                            ->placeholder(null)
                            ->live()
                            ->markAsRequired(),
                        ReportFilterFields::circle()
                            ->helperText('اختر حلقة لملف خاص بها، أو اتركها لكل حلقات الدورة.'),
                        Select::make('criterion')
                            ->label('الترتيب حسب')
                            ->options(self::criteria())
                            ->selectablePlaceholder(false)
                            ->helperText(fn (Get $get): ?string => self::criterionNote($get('criterion')))
                            ->live()
                            ->markAsRequired(),
                        TextInput::make('top')
                            ->label('عدد الطلاب في كل حلقة')
                            ->inputMode('numeric')
                            ->placeholder('كل الطلاب')
                            ->helperText('اتركه فارغاً لعرض كل طلاب الحلقة. وإن كتبت عدداً عُرض الأوائل فقط ممن لهم شيء في الفترة.'),
                        ReportFilterFields::from()
                            ->helperText('اتركه فارغاً ليبدأ العدّ من أول الدورة.'),
                        ReportFilterFields::until()
                            ->helperText('اتركه فارغاً ليستمر العدّ حتى اليوم.'),
                        TextInput::make('seconds_per_slide')
                            ->label('مدة عرض كل شريحة')
                            ->inputMode('numeric')
                            ->suffix('ثانية')
                            ->markAsRequired(),
                    ]),
                Toggle::make('opens_as_show')
                    ->label('يبدأ العرض فور فتح الملف')
                    ->helperText('يُحفظ الملف بصيغة ‎.ppsx فيبدأ العرض بملء الشاشة عند فتحه. اتركه مطفأً إن أردت تعديل الشرائح في PowerPoint قبل عرضها.'),
            ])
            ->action(fn (array $data, Action $action, ExportLeadersSlides $export): StreamedResponse => Download::of(
                Attempt::action($action, fn (): ExportedFile => $export->handle($data)),
            ));
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private static function defaults(array $filters): array
    {
        $criterion = $filters[StudentsReport::CRITERION_FILTER] ?? null;
        $courseId = $filters['course_id'] ?? Options::soleActiveCourseId();

        return [
            'course_id' => $courseId,
            'circle_id' => filled($courseId) ? ($filters['circle_id'] ?? null) : null,
            'from' => $filters['from'] ?? null,
            'until' => $filters['until'] ?? null,
            'criterion' => is_string($criterion) && isset(self::criteria()[$criterion]) ? $criterion : RankingCriterion::NewPages->value,
            'top' => null,
            'seconds_per_slide' => LeadersSlidesRequest::DEFAULT_SECONDS_PER_SLIDE,
            'opens_as_show' => false,
        ];
    }

    /**
     * @return array<string, string> value => label
     */
    private static function criteria(): array
    {
        return [
            RankingCriterion::NewPages->value => 'الحفظ (الصفحات الجديدة)',
            RankingCriterion::Points->value => 'النقاط',
            RankingCriterion::Attendance->value => 'الحضور',
            RankingCriterion::Exams->value => 'السبر',
        ];
    }

    /**
     * What the number next to each name will be.
     */
    private static function criterionNote(mixed $criterion): ?string
    {
        return match (is_string($criterion) ? RankingCriterion::tryFrom($criterion) : null) {
            RankingCriterion::NewPages => 'بجانب كل اسم: عدد الصفحات الجديدة التي سمّعها في الفترة.',
            RankingCriterion::Points => 'بجانب كل اسم: نقاطه في الفترة.',
            RankingCriterion::Attendance => 'بجانب كل اسم: عدد أيام حضوره. والترتيب بنسبة الحضور، فلا يتأخر من التحق بالدورة متأخراً.',
            RankingCriterion::Exams => 'بجانب كل اسم: عدد الأجزاء التي نجح في سبرها في الفترة.',
            default => null,
        };
    }
}
