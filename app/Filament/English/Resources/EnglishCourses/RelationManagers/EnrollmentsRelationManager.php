<?php

namespace App\Filament\English\Resources\EnglishCourses\RelationManagers;

use App\Actions\English\Enrollments\EnrollEnglishStudent;
use App\Actions\English\Enrollments\RegisterEnglishStudent;
use App\Enums\Section;
use App\Filament\English\Resources\EnglishStudents\Schemas\EnglishStudentForm;
use App\Filament\English\Support\EnglishEnrollmentActions;
use App\Filament\English\Support\EnglishMoneyColumns;
use App\Filament\English\Support\EnglishOptions;
use App\Filament\English\Support\EnglishPresenter;
use App\Filament\Support\Attempt;
use App\Filament\Support\Fields\StudentSelect;
use App\Filament\Support\Presenters\StudentPresenter;
use App\Models\English\EnglishCourse;
use App\Models\English\EnglishEnrollment;
use App\Support\Dates;
use App\Support\Money;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Fieldset;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Js;

/**
 * The students of the course: who is in which hall, and where each one
 * stands with the price of the course.
 */
class EnrollmentsRelationManager extends RelationManager
{
    protected static string $relationship = 'enrollments';

    protected static ?string $title = 'الطلاب';

    protected static ?string $modelLabel = 'تسجيل';

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => EnglishMoneyColumns::withMoney($query)
                ->with(['student', 'hall'])
                ->withCount('attendances'))
            ->columns([
                TextColumn::make('student.full_name')
                    ->label('الطالب')
                    ->description(fn (EnglishEnrollment $record): ?string => StudentPresenter::father($record->student))
                    ->searchable()
                    ->sortable()
                    ->weight('medium'),
                TextColumn::make('hall.name')
                    ->label('القاعة')
                    ->sortable(),
                TextColumn::make('enrolled_on')
                    ->label('تاريخ التسجيل')
                    ->date()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('left_on')
                    ->label('تاريخ الخروج')
                    ->date()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('attendances_count')
                    ->label('أيام الحضور')
                    ->alignCenter(),
                ...EnglishMoneyColumns::make(),
                TextColumn::make('card_code')
                    ->label('رقم البطاقة')
                    ->fontFamily('mono')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('english_hall_id')
                    ->label('القاعة')
                    ->options(fn (): array => EnglishOptions::hallsOfCourse($this->course()->getKey())),
                TernaryFilter::make('left_on')
                    ->label('الوجود في الدورة')
                    ->placeholder('الكل')
                    ->trueLabel('خرجوا من الدورة')
                    ->falseLabel('ما زالوا في الدورة')
                    ->nullable(),
            ])
            ->headerActions([
                $this->enrollAction(),
                $this->registerAction(),
                Action::make('cards')
                    ->label('طباعة بطاقات الدورة')
                    ->icon(Heroicon::OutlinedPrinter)
                    ->color('gray')
                    ->url(fn (): string => EnglishPresenter::cardsUrl((int) $this->course()->getKey()))
                    ->openUrlInNewTab(),
            ])
            ->recordActions([
                EnglishEnrollmentActions::pay(),
                EnglishEnrollmentActions::changeHall(),
                EnglishEnrollmentActions::printCard(),
                ActionGroup::make([
                    EnglishEnrollmentActions::changePrice(),
                    EnglishEnrollmentActions::editDates(),
                    EnglishEnrollmentActions::delete(),
                ]),
            ])
            ->toolbarActions([
                BulkAction::make('cards')
                    ->label('طباعة بطاقات المحدَّدين')
                    ->icon(Heroicon::OutlinedPrinter)
                    ->action(function (Collection $records): void {
                        $url = EnglishPresenter::cardsUrl((int) $this->course()->getKey(), enrollmentIds: $records->modelKeys());
                        $this->js('window.open('.Js::from($url).", '_blank')");
                    })
                    ->deselectRecordsAfterCompletion(),
            ])
            ->defaultSort('enrolled_on', 'desc')
            ->emptyStateHeading('لا يوجد طلاب في الدورة بعد');
    }

    /**
     * Enrolls a student who is already in the system: one of the English
     * section, or one of the mosque (who is then on both lists).
     */
    private function enrollAction(): Action
    {
        return Action::make('enroll')
            ->label('تسجيل طالب موجود')
            ->icon(Heroicon::OutlinedUserPlus)
            ->visible(fn (): bool => $this->course()->isActive())
            ->modalHeading(fn (): string => "تسجيل طالب في {$this->course()->name}")
            ->modalDescription(fn (): string => 'ابحث باسم الطالب: يظهر طلاب الانجليزي وطلاب دورات المسجد. طالب المسجد يُضاف إلى قائمة الانجليزي ويبقى في المسجد. '
                .'سعر الدورة: '.Money::pounds($this->course()->price).'.')
            ->modalSubmitActionLabel('تسجيل')
            ->schema([
                StudentSelect::toEnroll(Section::English, scope: fn (Builder $query) => $query->whereDoesntHave(
                    'englishEnrollments',
                    fn (Builder $query) => $query->where('english_course_id', $this->course()->getKey()),
                )),
                ...$this->enrollmentFields(),
            ])
            ->action(fn (array $data, Action $action, EnrollEnglishStudent $enroll): EnglishEnrollment => Attempt::action(
                $action,
                fn (): EnglishEnrollment => $enroll->handle([...$data, 'english_course_id' => $this->course()->getKey()]),
            ))
            ->successNotificationTitle('تم تسجيل الطالب');
    }

    /**
     * Registers a student who is not in the system yet, straight into the course.
     */
    private function registerAction(): Action
    {
        return Action::make('register')
            ->label('طالب جديد')
            ->icon(Heroicon::OutlinedPlus)
            ->color('gray')
            ->visible(fn (): bool => $this->course()->isActive())
            ->modalHeading(fn (): string => "طالب جديد في {$this->course()->name}")
            ->modalDescription('لطالب ليس في النظام أبداً. إن كان من طلاب المسجد فاستعمل «تسجيل طالب موجود» حتى لا يتكرر.')
            ->modalSubmitActionLabel('إضافة وتسجيل')
            ->fillForm(fn (): array => [
                'english_level_id' => $this->course()->english_level_id,
                'enrolled_on' => Dates::today()->toDateString(),
            ])
            ->schema([
                Fieldset::make('بيانات الطالب')
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema(EnglishStudentForm::details()),
                Fieldset::make('التسجيل في الدورة')
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema($this->enrollmentFields()),
            ])
            ->action(fn (array $data, Action $action, RegisterEnglishStudent $register): EnglishEnrollment => Attempt::action(
                $action,
                fn (): EnglishEnrollment => $register->handle([...$data, 'english_course_id' => $this->course()->getKey()]),
            ))
            ->successNotificationTitle('تمت إضافة الطالب وتسجيله');
    }

    /**
     * @return list<mixed>
     */
    private function enrollmentFields(): array
    {
        return [
            Select::make('english_hall_id')
                ->label('القاعة')
                ->options(fn (): array => EnglishOptions::halls($this->course()->getKey()))
                ->markAsRequired(),
            DatePicker::make('enrolled_on')
                ->label('تاريخ التسجيل')
                ->default(fn (): string => Dates::today()->toDateString())
                ->maxDate(fn () => Dates::today())
                ->markAsRequired(),
            ...EnglishEnrollmentActions::priceFields(),
        ];
    }

    private function course(): EnglishCourse
    {
        /** @var EnglishCourse */
        return $this->getOwnerRecord();
    }
}
