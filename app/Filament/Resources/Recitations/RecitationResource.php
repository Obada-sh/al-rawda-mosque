<?php

namespace App\Filament\Resources\Recitations;

use App\Filament\Enums\PanelGroup;
use App\Filament\Resources\Recitations\Pages\ManageRecitations;
use App\Filament\Resources\Recitations\Schemas\RecitationForm;
use App\Filament\Resources\Recitations\Tables\RecitationsTable;
use App\Models\Recitation;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * What students recite: a range of pages, heard by a teacher on a day.
 * Every recitation belongs to the student's enrollment in a course.
 */
class RecitationResource extends Resource
{
    protected static ?string $model = Recitation::class;

    protected static string|UnitEnum|null $navigationGroup = PanelGroup::Daily;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBookOpen;

    protected static ?int $navigationSort = 30;

    protected static ?string $modelLabel = 'تسميع';

    protected static ?string $pluralModelLabel = 'التسميع';

    public static function form(Schema $schema): Schema
    {
        return RecitationForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return RecitationsTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with([
            'teacher',
            'pointTransaction',
            'enrollment.student',
            'enrollment.course',
            'enrollment.circle',
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageRecitations::route('/'),
        ];
    }
}
