<?php

namespace App\Filament\English\Resources\EnglishCourses\RelationManagers;

use App\Actions\English\Halls\DeleteEnglishHall;
use App\Actions\English\Halls\SaveEnglishHall;
use App\Filament\English\Support\EnglishOptions;
use App\Filament\English\Support\EnglishPresenter;
use App\Filament\Support\Attempt;
use App\Models\English\EnglishCourse;
use App\Models\English\EnglishHall;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * The halls (قاعات) of the course, each with its teacher and assistant
 * from the English section's teachers.
 */
class HallsRelationManager extends RelationManager
{
    protected static string $relationship = 'halls';

    protected static ?string $title = 'القاعات';

    protected static ?string $modelLabel = 'قاعة';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('اسم القاعة')
                    ->markAsRequired()
                    ->autofocus()
                    ->columnSpanFull(),
                Select::make('teacher_id')
                    ->label('الأستاذ')
                    ->options(fn (?EnglishHall $record): array => EnglishOptions::teachers($record?->teacher_id))
                    ->searchable(),
                Select::make('assistant_id')
                    ->label('المساعد')
                    ->options(fn (?EnglishHall $record): array => EnglishOptions::teachers($record?->assistant_id))
                    ->searchable(),
                Toggle::make('is_active')
                    ->label('نشطة')
                    ->helperText('القاعة غير النشطة لا يُسجَّل فيها طلاب جدد ولا يُنقل إليها أحد.')
                    ->default(true)
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['teacher', 'assistant']))
            ->columns([
                TextColumn::make('name')
                    ->label('القاعة')
                    ->searchable()
                    ->weight('medium'),
                TextColumn::make('teacher.full_name')
                    ->label('الأستاذ')
                    ->placeholder('—'),
                TextColumn::make('assistant.full_name')
                    ->label('المساعد')
                    ->placeholder('—'),
                TextColumn::make('enrollments_count')
                    ->label('الطلاب')
                    ->counts(['enrollments' => fn (Builder $query) => $query->whereNull('left_on')])
                    ->alignCenter(),
                IconColumn::make('is_active')
                    ->label('نشطة')
                    ->boolean()
                    ->alignCenter(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->using(fn (array $data, Action $action, SaveEnglishHall $saveHall): EnglishHall => Attempt::action(
                        $action,
                        fn (): EnglishHall => $saveHall->handle($this->course(), $data),
                    )),
            ])
            ->recordActions([
                Action::make('cards')
                    ->label('بطاقات القاعة')
                    ->icon(Heroicon::OutlinedPrinter)
                    ->color('gray')
                    ->url(fn (EnglishHall $record): string => EnglishPresenter::cardsUrl($record->english_course_id, $record->getKey()))
                    ->openUrlInNewTab(),
                EditAction::make()
                    ->using(fn (EnglishHall $record, array $data, Action $action, SaveEnglishHall $saveHall): EnglishHall => Attempt::action(
                        $action,
                        fn (): EnglishHall => $saveHall->handle($this->course(), $data, $record),
                    )),
                DeleteAction::make()
                    ->modalDescription('تُحذف فقط القاعة التي لم يدخلها أي طالب. القاعة التي استُعملت يمكن تعطيلها.')
                    ->using(fn (EnglishHall $record, DeleteEnglishHall $deleteHall): bool => Attempt::confirmed(function () use ($record, $deleteHall): bool {
                        $deleteHall->handle($record);

                        return true;
                    })),
            ])
            ->defaultSort('name')
            ->emptyStateHeading('لا توجد قاعات بعد')
            ->emptyStateDescription('أضف قاعات الدورة أولاً، ثم سجّل الطلاب فيها.')
            ->paginated(false);
    }

    private function course(): EnglishCourse
    {
        /** @var EnglishCourse */
        return $this->getOwnerRecord();
    }
}
