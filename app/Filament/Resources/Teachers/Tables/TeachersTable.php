<?php

namespace App\Filament\Resources\Teachers\Tables;

use App\Actions\Teachers\DeleteTeacher;
use App\Actions\Teachers\SaveTeacher;
use App\Filament\Support\Attempt;
use App\Filament\Support\Cards\CardPrintActions;
use App\Filament\Support\Presenters\TeacherPresenter;
use App\Models\Teacher;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class TeachersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('photo')
                    ->label('الصورة')
                    ->state(fn (Teacher $record): ?string => TeacherPresenter::photoUrl($record))
                    ->defaultImageUrl(fn (): string => asset('img/student.svg'))
                    ->circular()
                    ->imageSize(36)
                    ->toggleable(),
                TextColumn::make('full_name')
                    ->label('الاسم')
                    ->searchable()
                    ->sortable()
                    ->weight('medium'),
                TextColumn::make('phone')
                    ->label('الهاتف')
                    ->placeholder('—')
                    ->searchable(),
                TextColumn::make('supervised_circles_count')
                    ->label('حلقات يشرف عليها')
                    ->counts('supervisedCircles')
                    ->alignCenter(),
                TextColumn::make('attendances_count')
                    ->label('مرات الحضور')
                    ->counts('attendances')
                    ->alignCenter(),
                TextColumn::make('recitations_count')
                    ->label('مرات التسميع')
                    ->counts('recitations')
                    ->alignCenter()
                    ->toggleable(),
                IconColumn::make('is_active')
                    ->label('نشط')
                    ->boolean()
                    ->alignCenter(),
            ])
            ->filters([
                TernaryFilter::make('is_active')
                    ->label('الحالة')
                    ->placeholder('الكل')
                    ->trueLabel('النشطون')
                    ->falseLabel('غير النشطين'),
            ])
            ->recordActions([
                CardPrintActions::forTeacher(),
                EditAction::make()
                    ->using(fn (Teacher $record, array $data, Action $action, SaveTeacher $saveTeacher): Teacher => Attempt::action(
                        $action,
                        fn (): Teacher => $saveTeacher->handle($data, $record),
                    )),
                DeleteAction::make()
                    ->using(fn (Teacher $record, DeleteTeacher $deleteTeacher): bool => Attempt::confirmed(function () use ($record, $deleteTeacher): bool {
                        $deleteTeacher->handle($record);

                        return true;
                    })),
            ])
            ->toolbarActions([
                CardPrintActions::forSelectedTeachers(),
            ])
            ->defaultSort('full_name');
    }
}
