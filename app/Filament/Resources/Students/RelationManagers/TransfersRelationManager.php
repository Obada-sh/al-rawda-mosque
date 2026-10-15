<?php

namespace App\Filament\Resources\Students\RelationManagers;

use App\Filament\Support\Tables\TransfersLogTable;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;

/**
 * The transfers log of the student: every move between circles, in every
 * course.
 */
class TransfersRelationManager extends RelationManager
{
    protected static string $relationship = 'transfers';

    protected static ?string $title = 'سجل التنقلات';

    public function table(Table $table): Table
    {
        return TransfersLogTable::configure($table, ofOneStudent: true);
    }
}
