<?php

namespace App\Filament\Resources\TeacherAttendances\Tables;

use App\Actions\Attendance\DeleteTeacherAttendance;
use App\Enums\AttendanceMethod;
use App\Filament\Support\Filters\CourseFilter;
use App\Filament\Support\Filters\DateRangeFilter;
use App\Filament\Support\Options;
use App\Models\Circle;
use App\Models\TeacherAttendance;
use App\Services\Teachers\TeacherCircles;
use Filament\Actions\DeleteAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class TeacherAttendancesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('session.session_date')
                    ->label('التاريخ')
                    ->date()
                    ->sortable(),
                TextColumn::make('teacher.full_name')
                    ->label('الأستاذ')
                    ->searchable()
                    ->weight('medium'),
                TextColumn::make('roles')
                    ->label('عمله في الدورة')
                    ->state(fn (TeacherAttendance $record): string => self::roles($record))
                    ->placeholder('—')
                    ->wrap(),
                TextColumn::make('session.course.name')
                    ->label('الدورة'),
                TextColumn::make('method')
                    ->label('الطريقة')
                    ->badge()
                    ->formatStateUsing(fn (AttendanceMethod $state): string => $state->label())
                    ->color(fn (AttendanceMethod $state): string => $state === AttendanceMethod::Qr ? 'success' : 'gray'),
                TextColumn::make('scanned_at')
                    ->label('وقت المسح')
                    ->time('H:i')
                    ->placeholder('—')
                    ->alignCenter(),
            ])
            ->filters([
                CourseFilter::make('session'),
                SelectFilter::make('teacher_id')
                    ->label('الأستاذ')
                    ->options(fn (): array => Options::teachers())
                    ->searchable(),
                DateRangeFilter::make('session_date', 'التاريخ', relationship: 'session'),
                SelectFilter::make('method')
                    ->label('الطريقة')
                    ->options(AttendanceMethod::options()),
            ])
            ->recordActions([
                DeleteAction::make()
                    ->modalHeading(fn (TeacherAttendance $record): string => "حذف حضور {$record->teacher->full_name}")
                    ->using(function (TeacherAttendance $record, DeleteTeacherAttendance $deleteTeacherAttendance): bool {
                        $deleteTeacherAttendance->handle($record);

                        return true;
                    }),
            ])
            ->defaultSort('id', 'desc')
            ->emptyStateHeading('لا يوجد حضور أساتذة مسجَّل')
            ->emptyStateDescription('يُسجَّل حضور الأستاذ بمسح بطاقته في شاشة مسح الحضور، أو يدوياً من الزر أعلاه.');
    }

    /** "مشرف حلقة النور، مساعد حلقة الفجر": what the teacher does in the course of that day. */
    private static function roles(TeacherAttendance $attendance): string
    {
        $teacherId = (int) $attendance->teacher_id;
        $circles = $attendance->session->course->circles
            ->filter(fn (Circle $circle): bool => (int) $circle->supervisor_teacher_id === $teacherId
                || (int) $circle->assistant_teacher_id === $teacherId)
            ->sortBy('name');

        return TeacherCircles::roles($attendance->teacher, $circles);
    }
}
