<?php

namespace App\Filament\Resources\Activities;

use App\Filament\Enums\PanelGroup;
use App\Filament\Resources\Activities\Pages\CreateActivity;
use App\Filament\Resources\Activities\Pages\EditActivity;
use App\Filament\Resources\Activities\Pages\ListActivities;
use App\Filament\Resources\Activities\Schemas\ActivityForm;
use App\Filament\Resources\Activities\Tables\ActivitiesTable;
use App\Models\Activity;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * The activities outside the lessons: a trip, a competition, a meeting.
 * An activity is for one circle or for the whole mosque; the admin marks
 * who attended, and each of them gains the points of the activity.
 */
class ActivityResource extends Resource
{
    protected static ?string $model = Activity::class;

    protected static string|UnitEnum|null $navigationGroup = PanelGroup::Daily;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFlag;

    protected static ?int $navigationSort = 45;

    protected static ?string $modelLabel = 'نشاط';

    protected static ?string $pluralModelLabel = 'النشاطات';

    protected static ?string $recordTitleAttribute = 'title';

    public static function form(Schema $schema): Schema
    {
        return ActivityForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ActivitiesTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('circle.course')->withCount('attendances');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListActivities::route('/'),
            'create' => CreateActivity::route('/create'),
            'edit' => EditActivity::route('/{record}/edit'),
        ];
    }
}
