<?php

namespace App\Filament\Resources\Activities\Schemas;

use App\Enums\ActivityAudience;
use App\Filament\Support\Options;
use App\Models\Activity;
use App\Support\Dates;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class ActivityForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('النشاط')
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('title')
                            ->label('اسم النشاط')
                            ->placeholder('رحلة، مسابقة، لقاء...')
                            ->markAsRequired()
                            ->autofocus(),
                        DatePicker::make('activity_date')
                            ->label('تاريخ النشاط')
                            ->default(fn (): string => Dates::today()->toDateString())
                            ->maxDate(fn () => Dates::today())
                            ->markAsRequired(),
                        ToggleButtons::make('audience')
                            ->label('النشاط لـ')
                            ->options(ActivityAudience::options())
                            ->default(ActivityAudience::Circle->value)
                            ->inline()
                            ->live()
                            // Another audience has other students: the list starts again.
                            ->afterStateUpdated(function (Set $set): void {
                                $set('circle_id', null);
                                $set('enrollment_ids', []);
                            })
                            ->markAsRequired(),
                        TextInput::make('points')
                            ->label('نقاط الحضور')
                            ->helperText('ينالها كل طالب حضر النشاط. اكتب 0 إن كان النشاط بلا نقاط.')
                            ->inputMode('numeric')
                            ->markAsRequired(),
                        // The course is only the way to the circle: it is not stored with the activity.
                        Select::make('course_id')
                            ->label('الدورة')
                            ->options(fn (?Activity $record): array => self::courses($record))
                            ->default(fn (): ?int => Options::soleActiveCourseId())
                            ->visible(fn (Get $get): bool => self::isForCircle($get))
                            ->live()
                            ->afterStateUpdated(function (Set $set): void {
                                $set('circle_id', null);
                                $set('enrollment_ids', []);
                            })
                            ->markAsRequired(),
                        Select::make('circle_id')
                            ->label('الحلقة')
                            ->options(fn (Get $get, ?Activity $record): array => Options::circles($get('course_id'), $record?->circle_id))
                            ->visible(fn (Get $get): bool => self::isForCircle($get))
                            ->live()
                            ->afterStateUpdated(fn (Set $set) => $set('enrollment_ids', []))
                            ->markAsRequired(),
                        Textarea::make('notes')
                            ->label('ملاحظات')
                            ->rows(2)
                            ->columnSpanFull(),
                    ]),
                Section::make('الحاضرون')
                    ->description(fn (Get $get): string => self::isForCircle($get)
                        ? 'ضع علامة عند كل طالب من الحلقة حضر النشاط.'
                        : 'ضع علامة عند كل طالب حضر النشاط. القائمة مرتبة حلقةً حلقة، والبحث بالاسم أو باسم الحلقة. تذهب النقاط إلى دورة الطالب.')
                    ->columnSpanFull()
                    ->schema([
                        CheckboxList::make('enrollment_ids')
                            ->label('الطلاب الذين حضروا')
                            ->hiddenLabel()
                            ->options(fn (Get $get, ?Activity $record): array => Options::activityAttendees(
                                ActivityAudience::tryFrom((string) $get('audience')) ?? ActivityAudience::Circle,
                                $get('circle_id'),
                                $record,
                            ))
                            ->columns(fn (Get $get): int => self::isForCircle($get) ? 3 : 2)
                            ->searchable()
                            ->bulkToggleable()
                            ->searchPrompt('ابحث عن طالب')
                            ->noSearchResultsMessage('لا يوجد طالب بهذا الاسم.'),
                    ]),
            ]);
    }

    private static function isForCircle(Get $get): bool
    {
        return $get('audience') === ActivityAudience::Circle->value;
    }

    /**
     * The running courses, and the course of the circle the activity
     * already belongs to even if that course is over.
     *
     * @return array<int, string>
     */
    private static function courses(?Activity $record): array
    {
        $courses = Options::activeCourses();
        $course = $record?->circle?->course;

        if ($course !== null) {
            $courses[$course->getKey()] ??= $course->name;
        }

        return $courses;
    }
}
