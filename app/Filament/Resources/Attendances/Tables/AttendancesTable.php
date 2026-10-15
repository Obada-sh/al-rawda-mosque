<?php

namespace App\Filament\Resources\Attendances\Tables;

use App\Actions\Attendance\DeleteAttendance;
use App\Enums\AttendanceMethod;
use App\Filament\Support\Filters\CourseFilter;
use App\Filament\Support\Filters\DateRangeFilter;
use App\Models\Attendance;
use Filament\Actions\DeleteAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class AttendancesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('session.session_date')
                    ->label('التاريخ')
                    ->date()
                    ->sortable(),
                TextColumn::make('enrollment.student.full_name')
                    ->label('الطالب')
                    ->searchable()
                    ->weight('medium'),
                TextColumn::make('enrollment.circle.name')
                    ->label('الحلقة'),
                TextColumn::make('enrollment.course.name')
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
                CourseFilter::make(),
                DateRangeFilter::make('session_date', 'التاريخ', relationship: 'session'),
                SelectFilter::make('method')
                    ->label('الطريقة')
                    ->options(AttendanceMethod::options()),
            ])
            ->recordActions([
                DeleteAction::make()
                    ->modalHeading(fn (Attendance $record): string => "حذف حضور {$record->enrollment->student->full_name}")
                    ->modalDescription('تُحذف مع الحضور النقاط التي مُنحت عليه.')
                    ->using(function (Attendance $record, DeleteAttendance $deleteAttendance): bool {
                        $deleteAttendance->handle($record);

                        return true;
                    }),
            ])
            ->defaultSort('id', 'desc')
            ->emptyStateHeading('لا يوجد حضور مسجَّل');
    }
}
