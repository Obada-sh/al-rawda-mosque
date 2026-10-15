<?php

namespace App\Filament\Resources\Students\Pages;

use App\Actions\Students\ArchiveStudent;
use App\Actions\Students\RestoreStudent;
use App\Filament\Resources\Students\StudentResource;
use App\Filament\Support\Documents\DocumentActions;
use App\Models\Student;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;

/**
 * The student's file: their details, and below them everything they did
 * in every course (enrollments, recitations, points and exams).
 */
class ViewStudent extends ViewRecord
{
    protected static string $resource = StudentResource::class;

    public function getTitle(): string|Htmlable
    {
        /** @var Student $student */
        $student = $this->getRecord();

        return $student->isArchived() ? "{$student->full_name} (مؤرشف)" : $student->full_name;
    }

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
            DocumentActions::studentFile(),
            Action::make('archive')
                ->label('أرشفة')
                ->icon(Heroicon::OutlinedArchiveBox)
                ->color('gray')
                ->visible(fn (Student $record): bool => ! $record->isArchived())
                ->requiresConfirmation()
                ->modalDescription('الطالب المؤرشف يختفي من العمل اليومي ولا يُسجَّل له حضور أو تسميع، وتبقى كل سجلاته محفوظة. يمكن إعادة تفعيله في أي وقت.')
                ->action(fn (Student $record, ArchiveStudent $archiveStudent) => $archiveStudent->handle($record))
                ->successNotificationTitle('تمت أرشفة الطالب'),
            Action::make('restore')
                ->label('إعادة تفعيل')
                ->icon(Heroicon::OutlinedArrowUturnLeft)
                ->color('gray')
                ->visible(fn (Student $record): bool => $record->isArchived())
                ->action(fn (Student $record, RestoreStudent $restoreStudent) => $restoreStudent->handle($record))
                ->successNotificationTitle('تمت إعادة تفعيل الطالب'),
        ];
    }
}
