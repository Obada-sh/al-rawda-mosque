<?php

namespace App\Filament\Resources\Courses\RelationManagers;

use App\Filament\Support\Tables\TransfersLogTable;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;

/**
 * The transfers log of the course: every student moved between its circles.
 */
class TransfersRelationManager extends RelationManager
{
    protected static string $relationship = 'transfers';

    protected static ?string $title = 'سجل التنقلات';

    public function table(Table $table): Table
    {
        return TransfersLogTable::configure($table);
    }
}
