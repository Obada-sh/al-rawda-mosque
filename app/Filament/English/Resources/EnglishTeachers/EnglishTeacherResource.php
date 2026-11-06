<?php

namespace App\Filament\English\Resources\EnglishTeachers;

use App\Actions\English\Teachers\DeleteEnglishTeacher;
use App\Actions\English\Teachers\SaveEnglishTeacher;
use App\Filament\English\EnglishPanelGroup;
use App\Filament\English\Resources\EnglishTeachers\Pages\ManageEnglishTeachers;
use App\Filament\Support\Attempt;
use App\Models\English\EnglishTeacher;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use UnitEnum;

/**
 * The teachers of the English section: a list of its own, apart from the
 * mosque's. The teacher and the assistant of each hall are chosen from it.
 */
class EnglishTeacherResource extends Resource
{
    protected static ?string $model = EnglishTeacher::class;

    protected static ?string $slug = 'teachers';

    protected static string|UnitEnum|null $navigationGroup = EnglishPanelGroup::Courses;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static ?int $navigationSort = 30;

    protected static ?string $modelLabel = 'أستاذ';

    protected static ?string $pluralModelLabel = 'الأساتذة';

    protected static ?string $recordTitleAttribute = 'full_name';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('full_name')
                    ->label('اسم الأستاذ')
                    ->markAsRequired()
                    ->autofocus(),
                TextInput::make('phone')
                    ->label('رقم الأستاذ')
                    ->inputMode('tel')
                    ->extraInputAttributes(['dir' => 'ltr']),
                Toggle::make('is_active')
                    ->label('نشط')
                    ->helperText('الأستاذ غير النشط لا يُختار لقاعة جديدة.')
                    ->default(true)
                    ->columnSpanFull(),
                Textarea::make('notes')
                    ->label('ملاحظات')
                    ->rows(3)
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('full_name')
                    ->label('الاسم')
                    ->searchable()
                    ->sortable()
                    ->weight('medium'),
                TextColumn::make('phone')
                    ->label('الرقم')
                    ->placeholder('—'),
                TextColumn::make('halls_count')
                    ->label('أستاذ في')
                    ->counts('halls')
                    ->suffix(' قاعة')
                    ->alignCenter(),
                TextColumn::make('assisted_halls_count')
                    ->label('مساعد في')
                    ->counts('assistedHalls')
                    ->suffix(' قاعة')
                    ->alignCenter(),
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
                EditAction::make()
                    ->using(fn (EnglishTeacher $record, array $data, Action $action, SaveEnglishTeacher $saveTeacher): EnglishTeacher => Attempt::action(
                        $action,
                        fn (): EnglishTeacher => $saveTeacher->handle($data, $record),
                    )),
                DeleteAction::make()
                    ->modalDescription('يُحذف فقط الأستاذ الذي لم يُربط بأي قاعة. من درّس يُعطَّل بدلاً من الحذف.')
                    ->using(fn (EnglishTeacher $record, DeleteEnglishTeacher $deleteTeacher): bool => Attempt::confirmed(function () use ($record, $deleteTeacher): bool {
                        $deleteTeacher->handle($record);

                        return true;
                    })),
            ])
            ->defaultSort('full_name');
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageEnglishTeachers::route('/'),
        ];
    }
}
