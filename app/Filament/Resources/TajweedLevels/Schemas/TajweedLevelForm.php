<?php

namespace App\Filament\Resources\TajweedLevels\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class TajweedLevelForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('اسم المستوى')
                    ->markAsRequired()
                    ->autofocus(),
                TextInput::make('sort_order')
                    ->label('الترتيب')
                    ->helperText('المستويات تُعرض من الرقم الأصغر إلى الأكبر.')
                    ->inputMode('numeric')
                    ->default(0),
            ]);
    }
}
