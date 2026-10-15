<?php

namespace App\Filament\Resources\JuzExams\Pages;

use App\Actions\Exams\OpenJuzExam;
use App\Enums\ExamStatus;
use App\Filament\Resources\JuzExams\JuzExamResource;
use App\Filament\Support\Attempt;
use App\Filament\Support\Fields\StudentSelect;
use App\Models\Enrollment;
use App\Models\JuzExam;
use App\Support\QuranPages;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Resources\Pages\ManageRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;

class ManageJuzExams extends ManageRecords
{
    protected static string $resource = JuzExamResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->openExamAction(),
        ];
    }

    /**
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        return [
            'pending' => Tab::make('بانتظار السبر')
                ->badge(fn (): ?int => JuzExam::query()->where('status', ExamStatus::Pending->value)->count() ?: null)
                ->badgeColor('warning')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', ExamStatus::Pending->value)),
            'done' => Tab::make('تم السبر')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', ExamStatus::Done->value)),
            'all' => Tab::make('الكل'),
        ];
    }

    /**
     * Opens an exam by hand: for a juz the student memorized before joining
     * the system, or to repeat an exam.
     */
    private function openExamAction(): Action
    {
        return Action::make('open')
            ->label('فتح سبر يدوياً')
            ->icon(Heroicon::OutlinedPlus)
            ->modalHeading('فتح سبر يدوياً')
            ->modalDescription('السبر يُفتح تلقائياً عندما يُتم الطالب جزءاً. استعمل هذا لجزء حفظه الطالب قبل دخول النظام، أو لإعادة سبر.')
            ->modalSubmitActionLabel('فتح السبر')
            ->schema([
                StudentSelect::make()
                    ->live()
                    ->afterStateUpdated(fn (Set $set) => $set('enrollment_id', null)),
                Select::make('juz_no')
                    ->label('الجزء')
                    ->options(fn (): array => collect(range(1, QuranPages::JUZ_COUNT))
                        ->mapWithKeys(fn (int $juz): array => [$juz => "الجزء {$juz}"])
                        ->all())
                    ->searchable()
                    ->markAsRequired(),
                Select::make('enrollment_id')
                    ->label('الدورة')
                    ->helperText('اختياري: الدورة التي يُنسب إليها هذا السبر.')
                    ->options(fn (Get $get): array => self::coursesOfStudent($get('student_id'))),
            ])
            ->action(fn (array $data, Action $action, OpenJuzExam $openJuzExam): JuzExam => Attempt::action(
                $action,
                fn (): JuzExam => $openJuzExam->handle($data),
            ))
            ->successNotificationTitle('تم فتح السبر');
    }

    /**
     * @return array<int, string> enrollment id => course name
     */
    private static function coursesOfStudent(mixed $studentId): array
    {
        if (blank($studentId)) {
            return [];
        }

        return Enrollment::query()
            ->with('course')
            ->where('student_id', $studentId)
            ->latest('enrolled_on')
            ->get()
            ->mapWithKeys(fn (Enrollment $enrollment): array => [$enrollment->getKey() => $enrollment->course->name])
            ->all();
    }
}
