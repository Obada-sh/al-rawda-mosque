<?php

namespace App\Filament\Resources\Departments\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class DepartmentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('اسم القسم')
                    ->markAsRequired()
                    ->autofocus()
                    ->columnSpanFull(),
                Toggle::make('is_active')
                    ->label('نشط')
                    ->helperText('القسم غير النشط لا يظهر عند تسجيل مصروف جديد، وتبقى مصاريفه السابقة كما هي.')
                    ->default(true)
                    ->columnSpanFull(),
            ]);
    }
}
